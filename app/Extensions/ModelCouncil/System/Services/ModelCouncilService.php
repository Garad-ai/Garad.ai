<?php

namespace App\Extensions\ModelCouncil\System\Services;

use App\Domains\Engine\Enums\EngineEnum;
use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity as EntityFacade;
use App\Domains\Entity\Models\Entity;
use App\Extensions\ModelCouncil\System\Models\ModelCouncilResponse;
use App\Helpers\Classes\ApiHelper;
use App\Helpers\Classes\Helper;
use App\Helpers\Classes\MarketplaceHelper;
use App\Helpers\Classes\OpenAiParamHelper;
use App\Helpers\Classes\RateLimiter\RateLimiter;
use App\Models\Setting;
use App\Models\Usage;
use App\Models\User;
use App\Models\UserOpenaiChatMessage;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Promise\Utils as PromiseUtils;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ModelCouncilService
{
    public function streamCouncilResponse(Request $request, array $chatParams, array $history, ?User $user): StreamedResponse
    {
        $context = $this->prepareCouncilContext($request, $user);
        if (isset($context['error'])) {
            return $this->buildErrorStreamResponse(
                (string) $context['error'],
                (int) ($context['status'] ?? 422)
            );
        }

        /** @var Collection<int, string> $selectedModelSlugs */
        $selectedModelSlugs = $context['selected_model_slugs'];
        $allowedModelSlugs = (array) ($context['allowed_model_slugs'] ?? []);

        $normalizedHistory = $this->normalizeHistory($history);
        $synthesisModel = $this->resolveSynthesisModel((string) $request->input('synthesis_model_slug', ''), $allowedModelSlugs, $selectedModelSlugs);

        $message = UserOpenaiChatMessage::create([
            'user_id'             => auth()->id(),
            'user_openai_chat_id' => $chatParams['chat_id'],
            'input'               => $chatParams['prompt'],
            'response'            => '',
            'output'              => '',
            'hash'                => Str::random(256),
            'credits'             => 0,
            'words'               => 0,
            'images'              => is_array($chatParams['images'] ?? null)
                ? implode(',', $chatParams['images'])
                : ($chatParams['images'] ?? null),
            'pdfName'          => $chatParams['pdfname'] ?? null,
            'pdfPath'          => $chatParams['pdfpath'] ?? null,
            'is_council'       => true,
            'model_slug'       => $synthesisModel?->slug(),
            'council_response' => [],
        ]);

        return response()->stream(function () use ($selectedModelSlugs, $normalizedHistory, $chatParams, $synthesisModel, $message) {
            $this->prepareStreamEnvironment();
            $this->emitSse('message', (int) $message->id);
            $this->emitSseJson('data', ['type' => 'council_phase', 'phase' => 'collecting_models']);
            $this->emitSseJson('data', [
                'type'    => 'summary_section_title',
                'section' => 'model_replies',
                'title'   => __('Model Replies'),
            ]);

            $modelResults = $this->streamParallelModelResponses($selectedModelSlugs, $normalizedHistory);

            $successfulResults = collect($modelResults)->filter(fn ($item) => ! ($item['failed'] ?? true))->values();
            if ($successfulResults->isEmpty()) {
                $message->delete();
                $this->emitSseJson('data', [
                    'type'    => 'error',
                    'message' => __('All selected models failed to respond. Please try again.'),
                ]);
                $this->emitSseJson('data', ['type' => 'council_phase', 'phase' => 'done']);
                $this->emitSse('stop', '[DONE]');

                return;
            }

            if ($synthesisModel && ! $this->hasCreditForModel($synthesisModel->slug())) {
                $this->emitSseJson('data', [
                    'type'    => 'error',
                    'message' => __('You have no credits left. Please buy more credits to continue.'),
                ]);
                $this->emitSseJson('data', ['type' => 'council_phase', 'phase' => 'done']);
                $this->emitSse('stop', '[DONE]');

                return;
            }

            $this->emitSseJson('data', ['type' => 'council_phase', 'phase' => 'synthesizing']);
            $synthesisPayload = [];

            $synthesisPayload = $this->synthesize(
                prompt: (string) ($chatParams['prompt'] ?? ''),
                modelResults: $modelResults,
                synthesisModel: $synthesisModel,
            );

            $normalizedSynthesis = $this->normalizeSynthesisPayload(
                prompt: (string) ($chatParams['prompt'] ?? ''),
                modelResults: $modelResults,
                synthesisPayload: $synthesisPayload,
            );

            $finalAnswer = (string) ($normalizedSynthesis['final_answer'] ?? '');
            if ($finalAnswer === '') {
                $finalAnswer = trim((string) ($successfulResults->first()['response_text'] ?? __('Unable to synthesize a final answer.')));
            }

            $respondedModelsCount = $successfulResults->count();
            $failedModelsCount = collect($modelResults)->filter(fn ($row) => (bool) ($row['failed'] ?? false))->count();
            $confidencePercent = (int) ($normalizedSynthesis['confidence_percent'] ?? $this->estimateConfidence($modelResults));
            $overlapPercent = (int) ($normalizedSynthesis['overlap_percent'] ?? 0);
            $councilLabels = $this->mergeCouncilLabels($synthesisPayload);
            $titles = $this->buildCouncilTitles($councilLabels);
            $agreementTableRows = $this->resolveAgreementTableRows($synthesisPayload, $respondedModelsCount, $failedModelsCount, $confidencePercent, $overlapPercent, $councilLabels);
            $agreementAnalysis = array_values((array) ($normalizedSynthesis['agreement_analysis'] ?? []));
            $disagreements = array_values((array) ($normalizedSynthesis['disagreements'] ?? []));
            $uniqueDiscoveries = array_values((array) ($normalizedSynthesis['unique_discoveries'] ?? []));

            $councilMeta = [
                'responded_models_count' => $respondedModelsCount,
                'failed_models_count'    => $failedModelsCount,
                'confidence_percent'     => $confidencePercent,
                'overlap_percent'        => $overlapPercent,
                'discovery_style'        => 'comparative_insights_v1',
                'labels'                 => [
                    'responded_models' => $councilLabels['responded_models'],
                    'confidence'       => $councilLabels['confidence'],
                ],
            ];

            $councilResponse = [
                'meta'                   => $councilMeta,
                'titles'                 => $titles,
                'agreement_table_rows'   => $agreementTableRows,
                'agreement_analysis'     => $agreementAnalysis,
                'disagreements'          => $disagreements,
                'unique_discoveries'     => $uniqueDiscoveries,
                'confidence_percent'     => $confidencePercent,
                'overlap_percent'        => $overlapPercent,
                'responded_models_count' => $respondedModelsCount,
                'failed_models_count'    => $failedModelsCount,
            ];

            $this->emitSseJson('data', [
                'type'  => 'council_phase',
                'phase' => 'final_streaming',
            ]);

            $this->emitSseJson('data', [
                'type' => 'summary_meta',
                'meta' => $councilMeta,
            ]);

            foreach ($titles as $section => $title) {
                $this->emitSseJson('data', [
                    'type'    => 'summary_section_title',
                    'section' => (string) $section,
                    'title'   => (string) $title,
                ]);
            }

            foreach ($this->splitIntoChunks($finalAnswer, 60) as $chunk) {
                $this->emitSseJson('data', [
                    'type'  => 'summary_final_answer_delta',
                    'chunk' => $chunk,
                ]);
            }

            foreach ($agreementTableRows as $row) {
                $this->emitSseJson('data', [
                    'type'   => 'summary_table_row',
                    'level'  => (string) ($row['level'] ?? ''),
                    'impact' => (string) ($row['impact'] ?? ''),
                ]);
            }

            foreach ($agreementAnalysis as $item) {
                $this->emitSseJson('data', [
                    'type'    => 'summary_list_item',
                    'section' => 'agreement_analysis',
                    'item'    => (string) $item,
                ]);
            }

            foreach ($disagreements as $item) {
                $this->emitSseJson('data', [
                    'type'    => 'summary_list_item',
                    'section' => 'disagreements',
                    'item'    => (string) $item,
                ]);
            }

            foreach ($uniqueDiscoveries as $item) {
                $this->emitSseJson('data', [
                    'type'    => 'summary_list_item',
                    'section' => 'discoveries',
                    'item'    => (string) $item,
                ]);
            }

            $persisted = $this->persistCouncilResult(
                message: $message,
                chatParams: $chatParams,
                modelResults: $modelResults,
                finalAnswer: $finalAnswer,
                councilResponse: $councilResponse,
                synthesisModel: $synthesisModel,
                successfulResults: $successfulResults
            );

            if (! $persisted) {
                $this->emitSseJson('data', [
                    'type'    => 'error',
                    'message' => __('Unable to save Model Council response.'),
                ]);
            }

            $this->emitSseJson('data', [
                'type'             => 'summary_done',
                'final_answer'     => $finalAnswer,
                'council_response' => $councilResponse,
            ]);
            $this->emitSseJson('data', ['type' => 'council_phase', 'phase' => 'done']);

            $this->emitSse('stop', '[DONE]');
        }, 200, [
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
            'Content-Type'      => 'text/event-stream',
        ]);
    }

    public function streamCouncilSummaryFromShared(Request $request, array $chatParams, ?User $user): StreamedResponse
    {
        $context = $this->prepareCouncilContext($request, $user);
        if (isset($context['error'])) {
            return $this->buildErrorStreamResponse(
                (string) $context['error'],
                (int) ($context['status'] ?? 422)
            );
        }

        $sharedMessageUuid = trim((string) $request->input('shared_message_uuid', ''));
        if ($sharedMessageUuid === '') {
            return $this->buildErrorStreamResponse(
                (string) __('Shared message UUID is required for council summary synthesis.'),
                422
            );
        }

        /** @var Collection<int, string> $selectedModelSlugs */
        $selectedModelSlugs = $context['selected_model_slugs'];
        $allowedModelSlugs = (array) ($context['allowed_model_slugs'] ?? []);
        $synthesisModel = $this->resolveSynthesisModel((string) $request->input('synthesis_model_slug', ''), $allowedModelSlugs, $selectedModelSlugs);
        $skipModelReplay = (bool) $request->boolean('skip_model_replay', false);

        $message = UserOpenaiChatMessage::create([
            'user_id'             => auth()->id(),
            'user_openai_chat_id' => $chatParams['chat_id'],
            'input'               => $chatParams['prompt'],
            'response'            => '',
            'output'              => '',
            'hash'                => Str::random(256),
            'credits'             => 0,
            'words'               => 0,
            'images'              => is_array($chatParams['images'] ?? null)
                ? implode(',', $chatParams['images'])
                : ($chatParams['images'] ?? null),
            'pdfName'          => $chatParams['pdfname'] ?? null,
            'pdfPath'          => $chatParams['pdfpath'] ?? null,
            'is_council'       => true,
            'model_slug'       => $synthesisModel?->slug(),
            'council_response' => [],
        ]);

        return response()->stream(function () use ($sharedMessageUuid, $selectedModelSlugs, $chatParams, $synthesisModel, $message, $skipModelReplay) {
            $this->prepareStreamEnvironment();
            $this->emitSse('message', (int) $message->id);
            $this->emitSseJson('data', ['type' => 'council_phase', 'phase' => 'collecting_models']);
            $this->emitSseJson('data', [
                'type'    => 'summary_section_title',
                'section' => 'model_replies',
                'title'   => __('Model Replies'),
            ]);

            $sourceMessagesBySlug = $this->waitForSharedModelMessages(
                sharedMessageUuid: $sharedMessageUuid,
                chatId: (int) $chatParams['chat_id'],
                userId: (int) auth()->id(),
                selectedModelSlugs: $selectedModelSlugs
            );

            $modelResults = $this->buildModelResultsFromSharedMessages($selectedModelSlugs, $sourceMessagesBySlug);
            if (! $skipModelReplay) {
                foreach ($modelResults as $result) {
                    $modelSlug = (string) ($result['model_slug'] ?? '');
                    $modelLabel = (string) ($result['model_label'] ?? $modelSlug);
                    $responseText = trim((string) ($result['response_text'] ?? ''));
                    $failed = (bool) ($result['failed'] ?? true);

                    $this->emitSseJson('data', [
                        'type'       => 'model_stream_start',
                        'model_slug' => $modelSlug,
                        'reply'      => [
                            'model_slug'  => $modelSlug,
                            'model_label' => $modelLabel,
                        ],
                    ]);

                    if ($failed || $responseText === '') {
                        $this->emitSseJson('data', [
                            'type'       => 'model_stream_delta',
                            'model_slug' => $modelSlug,
                            'chunk'      => __('Failed to Respond.'),
                        ]);
                    } else {
                        foreach ($this->splitIntoChunks($responseText, 60) as $chunk) {
                            $this->emitSseJson('data', [
                                'type'       => 'model_stream_delta',
                                'model_slug' => $modelSlug,
                                'chunk'      => $chunk,
                            ]);
                        }
                    }

                    $this->emitSseJson('data', [
                        'type'             => 'model_stream_end',
                        'model_slug'       => $modelSlug,
                        'model_label'      => $modelLabel,
                        'failed'           => $failed,
                        'response_time_ms' => $result['response_time_ms'] ?? null,
                    ]);
                }
            }

            $successfulResults = collect($modelResults)->filter(fn ($item) => ! ($item['failed'] ?? true))->values();
            if ($successfulResults->isEmpty()) {
                $message->delete();
                $this->emitSseJson('data', [
                    'type'    => 'error',
                    'message' => __('All selected models failed to respond. Please try again.'),
                ]);
                $this->emitSseJson('data', ['type' => 'council_phase', 'phase' => 'done']);
                $this->emitSse('stop', '[DONE]');

                return;
            }

            if ($synthesisModel && ! $this->hasCreditForModel($synthesisModel->slug())) {
                $this->emitSseJson('data', [
                    'type'    => 'error',
                    'message' => __('You have no credits left. Please buy more credits to continue.'),
                ]);
                $this->emitSseJson('data', ['type' => 'council_phase', 'phase' => 'done']);
                $this->emitSse('stop', '[DONE]');

                return;
            }

            $this->emitSseJson('data', ['type' => 'council_phase', 'phase' => 'synthesizing']);

            $synthesisPayload = $this->synthesize(
                prompt: (string) ($chatParams['prompt'] ?? ''),
                modelResults: $modelResults,
                synthesisModel: $synthesisModel,
            );

            $normalizedSynthesis = $this->normalizeSynthesisPayload(
                prompt: (string) ($chatParams['prompt'] ?? ''),
                modelResults: $modelResults,
                synthesisPayload: $synthesisPayload,
            );

            $finalAnswer = (string) ($normalizedSynthesis['final_answer'] ?? '');
            if ($finalAnswer === '') {
                $finalAnswer = trim((string) ($successfulResults->first()['response_text'] ?? __('Unable to synthesize a final answer.')));
            }

            $respondedModelsCount = $successfulResults->count();
            $failedModelsCount = collect($modelResults)->filter(fn ($row) => (bool) ($row['failed'] ?? false))->count();
            $confidencePercent = (int) ($normalizedSynthesis['confidence_percent'] ?? $this->estimateConfidence($modelResults));
            $overlapPercent = (int) ($normalizedSynthesis['overlap_percent'] ?? 0);
            $councilLabels = $this->mergeCouncilLabels($synthesisPayload);
            $titles = $this->buildCouncilTitles($councilLabels);
            $agreementTableRows = $this->resolveAgreementTableRows($synthesisPayload, $respondedModelsCount, $failedModelsCount, $confidencePercent, $overlapPercent, $councilLabels);
            $agreementAnalysis = array_values((array) ($normalizedSynthesis['agreement_analysis'] ?? []));
            $disagreements = array_values((array) ($normalizedSynthesis['disagreements'] ?? []));
            $uniqueDiscoveries = array_values((array) ($normalizedSynthesis['unique_discoveries'] ?? []));

            $councilMeta = [
                'responded_models_count' => $respondedModelsCount,
                'failed_models_count'    => $failedModelsCount,
                'confidence_percent'     => $confidencePercent,
                'overlap_percent'        => $overlapPercent,
                'discovery_style'        => 'comparative_insights_v1',
                'labels'                 => [
                    'responded_models' => $councilLabels['responded_models'],
                    'confidence'       => $councilLabels['confidence'],
                ],
            ];

            $councilResponse = [
                'meta'                   => $councilMeta,
                'titles'                 => $titles,
                'agreement_table_rows'   => $agreementTableRows,
                'agreement_analysis'     => $agreementAnalysis,
                'disagreements'          => $disagreements,
                'unique_discoveries'     => $uniqueDiscoveries,
                'confidence_percent'     => $confidencePercent,
                'overlap_percent'        => $overlapPercent,
                'responded_models_count' => $respondedModelsCount,
                'failed_models_count'    => $failedModelsCount,
            ];

            $this->emitSseJson('data', ['type' => 'council_phase', 'phase' => 'final_streaming']);
            $this->emitSseJson('data', ['type' => 'summary_meta', 'meta' => $councilMeta]);

            foreach ($titles as $section => $title) {
                $this->emitSseJson('data', [
                    'type'    => 'summary_section_title',
                    'section' => (string) $section,
                    'title'   => (string) $title,
                ]);
            }

            foreach ($this->splitIntoChunks($finalAnswer, 60) as $chunk) {
                $this->emitSseJson('data', [
                    'type'  => 'summary_final_answer_delta',
                    'chunk' => $chunk,
                ]);
            }

            foreach ($agreementTableRows as $row) {
                $this->emitSseJson('data', [
                    'type'   => 'summary_table_row',
                    'level'  => (string) ($row['level'] ?? ''),
                    'impact' => (string) ($row['impact'] ?? ''),
                ]);
            }

            foreach ($agreementAnalysis as $item) {
                $this->emitSseJson('data', [
                    'type'    => 'summary_list_item',
                    'section' => 'agreement_analysis',
                    'item'    => (string) $item,
                ]);
            }

            foreach ($disagreements as $item) {
                $this->emitSseJson('data', [
                    'type'    => 'summary_list_item',
                    'section' => 'disagreements',
                    'item'    => (string) $item,
                ]);
            }

            foreach ($uniqueDiscoveries as $item) {
                $this->emitSseJson('data', [
                    'type'    => 'summary_list_item',
                    'section' => 'discoveries',
                    'item'    => (string) $item,
                ]);
            }

            $persisted = $this->persistCouncilResultFromShared(
                message: $message,
                chatParams: $chatParams,
                modelResults: $modelResults,
                finalAnswer: $finalAnswer,
                councilResponse: $councilResponse,
                synthesisModel: $synthesisModel,
                successfulResults: $successfulResults,
                sourceMessageIds: collect($sourceMessagesBySlug)->pluck('id')->filter()->values()->all()
            );

            if (! $persisted) {
                $this->emitSseJson('data', [
                    'type'    => 'error',
                    'message' => __('Unable to save Model Council response.'),
                ]);
            }

            $this->emitSseJson('data', [
                'type'             => 'summary_done',
                'final_answer'     => $finalAnswer,
                'council_response' => $councilResponse,
            ]);
            $this->emitSseJson('data', ['type' => 'council_phase', 'phase' => 'done']);
            $this->emitSse('stop', '[DONE]');
        }, 200, [
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
            'Content-Type'      => 'text/event-stream',
        ]);
    }

    private function buildErrorStreamResponse(string $errorMessage, int $status = 422): StreamedResponse
    {
        return response()->stream(function () use ($errorMessage) {
            $this->prepareStreamEnvironment();
            $this->emitSse('message', 0);
            $this->emitSseJson('data', [
                'type'    => 'error',
                'message' => $errorMessage,
            ]);
            $this->emitSseJson('data', ['type' => 'council_phase', 'phase' => 'done']);
            $this->emitSse('stop', '[DONE]');
        }, $status, [
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
            'Content-Type'      => 'text/event-stream',
        ]);
    }

    private function emitSse(string $event, string|int $data): void
    {
        echo "event: {$event}\n";
        echo 'data: ' . $data . "\n\n";
        $this->safeFlush();
    }

    private function emitSseJson(string $event, array $payload): void
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}';
        $this->emitSse($event, $json);
    }

    private function splitIntoChunks(string $text, int $maxChunkLength = 140): array
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return [];
        }

        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $trimmed));
        $chunks = [];
        $current = '';

        foreach ($lines as $lineIndex => $line) {
            $words = $line === '' ? [''] : explode(' ', $line);

            foreach ($words as $word) {
                if ($word === '' && $current === '') {
                    continue;
                }

                $candidate = $current === '' ? $word : "{$current} {$word}";

                if (mb_strlen($candidate) > $maxChunkLength && $current !== '') {
                    $chunks[] = $current;
                    $current = $word;

                    continue;
                }

                $current = $candidate;
            }

            if ($lineIndex < count($lines) - 1) {
                if ($current !== '') {
                    $chunks[] = $current . "\n";
                    $current = '';
                } else {
                    $chunks[] = "\n";
                }
            }
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    private function prepareStreamEnvironment(): void
    {
        if (function_exists('ini_set')) {
            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', '0');
            @ini_set('implicit_flush', '1');
        }

        if (function_exists('ob_implicit_flush')) {
            ob_implicit_flush(true);
        }

        $minLevel = config('octane.enabled') ? 1 : 0;
        while (ob_get_level() > $minLevel) {
            if (! @ob_end_flush()) {
                break;
            }
        }
    }

    private function safeFlush(): void
    {
        if (function_exists('ob_flush')) {
            @ob_flush();
        }
        if (function_exists('flush')) {
            @flush();
        }
    }

    /**
     * Default UI labels for the council card. Used as a fallback when the
     * synthesis AI does not provide its own labels (e.g. AI call failed).
     *
     * @return array<string, string>
     */
    private function councilLabelDefaults(): array
    {
        return [
            'final_answer_title'       => (string) __('Final Synthesized Answer'),
            'agreement_level_title'    => (string) __('Agreement Level'),
            'confidence_impact_title'  => (string) __('Confidence Impact'),
            'agreement_analysis_title' => (string) __('Where Models Agree'),
            'disagreements_title'      => (string) __('Where Models Disagree'),
            'discoveries_title'        => (string) __('Unique Discoveries'),
            'model_replies_title'      => (string) __('Model Replies'),
            'overlap_suffix'           => (string) __('overlap'),
            'impact_very_high'         => (string) __('Very High'),
            'impact_high'              => (string) __('High'),
            'impact_medium'            => (string) __('Medium'),
            'impact_low'               => (string) __('Low'),
            'responded_models'         => (string) __('Models Responded'),
            'confidence'               => (string) __('Confidence'),
        ];
    }

    /**
     * Merge AI-supplied labels (in user's language) over localized defaults.
     * Any blank/missing AI value falls back to its __() default.
     *
     * @return array<string, string>
     */
    private function mergeCouncilLabels(array $synthesisPayload): array
    {
        $defaults = $this->councilLabelDefaults();
        $aiLabels = $synthesisPayload['labels'] ?? null;

        if (! is_array($aiLabels)) {
            return $defaults;
        }

        $merged = $defaults;
        foreach ($defaults as $key => $default) {
            $candidate = $aiLabels[$key] ?? null;
            if (! is_string($candidate)) {
                continue;
            }
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }
            $merged[$key] = $candidate;
        }

        return $merged;
    }

    /**
     * Build the section title map consumed by the Blade view from a resolved
     * label set.
     *
     * @param  array<string, string>  $labels
     *
     * @return array<string, string>
     */
    private function buildCouncilTitles(array $labels): array
    {
        return [
            'final_answer'       => (string) ($labels['final_answer_title'] ?? __('Final Synthesized Answer')),
            'agreement_level'    => (string) ($labels['agreement_level_title'] ?? __('Agreement Level')),
            'confidence_impact'  => (string) ($labels['confidence_impact_title'] ?? __('Confidence Impact')),
            'agreement_analysis' => (string) ($labels['agreement_analysis_title'] ?? __('Where Models Agree')),
            'disagreements'      => (string) ($labels['disagreements_title'] ?? __('Where Models Disagree')),
            'discoveries'        => (string) ($labels['discoveries_title'] ?? __('Unique Discoveries')),
            'model_replies'      => (string) ($labels['model_replies_title'] ?? __('Model Replies')),
        ];
    }

    /**
     * @param  array<string, string>  $labels
     */
    private function resolveAgreementTableRows(
        array $synthesisPayload,
        int $respondedModelsCount,
        int $failedModelsCount,
        int $confidencePercent,
        int $overlapPercent = 0,
        array $labels = []
    ): array {
        $resolvedOverlap = max(0, min(100, $overlapPercent > 0 ? $overlapPercent : $confidencePercent));
        $overlapSuffix = (string) ($labels['overlap_suffix'] ?? __('overlap'));

        return [
            [
                'level'  => sprintf('%d%% %s', $resolvedOverlap, $overlapSuffix),
                'impact' => $this->resolveConfidenceImpactLabel($resolvedOverlap, $confidencePercent, $labels),
            ],
        ];
    }

    /**
     * @param  array<string, string>  $labels
     */
    private function resolveConfidenceImpactLabel(int $overlapPercent, int $confidencePercent = 0, array $labels = []): string
    {
        $overlap = max(0, min(100, $overlapPercent));

        if ($overlap >= 80) {
            return (string) ($labels['impact_very_high'] ?? __('Very High'));
        }

        if ($overlap >= 60) {
            return (string) ($labels['impact_high'] ?? __('High'));
        }

        if ($overlap >= 40) {
            return (string) ($labels['impact_medium'] ?? __('Medium'));
        }

        return (string) ($labels['impact_low'] ?? __('Low'));
    }

    private function prepareCouncilContext(Request $request, ?User $user): array
    {
        if (! MarketplaceHelper::isRegistered('ai-chat-pro') || ! MarketplaceHelper::isRegistered('model-council')) {
            return [
                'error'  => __('Model Council is not available.'),
                'status' => 404,
            ];
        }

        if ((int) setting('ai_chat_pro_model_council_feature', 1) !== 1) {
            return [
                'error'  => __('Model Council is disabled by admin settings.'),
                'status' => 403,
            ];
        }

        if (! $user) {
            return [
                'error'  => __('Please login to use Model Council.'),
                'status' => 403,
            ];
        }

        if (! $this->userHasCouncilAccess($user)) {
            return [
                'error'  => __('Your current plan does not include Model Council.'),
                'status' => 403,
            ];
        }

        $maxModels = max(2, min(5, (int) setting('ai_chat_pro_council_max_models', 5)));
        $selectedModelSlugs = collect(Arr::wrap($request->input('council_model_slugs', [])))
            ->filter(fn ($slug) => is_string($slug) && trim($slug) !== '')
            ->map(fn ($slug) => trim((string) $slug))
            ->unique()
            ->values();

        if (Helper::appIsDemo()) {
            $maxModels = min($maxModels, 3);

            $identifier = auth()->check() ? (string) auth()->id() : Helper::getRequestIp();
            $attemptAllowed = RateLimiter::make('model_council_demo', 1, 1440)->attempt($identifier);

            if (! $attemptAllowed) {
                return [
                    'error'  => __('Demo limit: one Model Council request per day.'),
                    'status' => 429,
                ];
            }
        }

        if ($selectedModelSlugs->count() < 2) {
            return [
                'error'  => __('You need to select more than 1 model to use Model Council.'),
                'status' => 422,
            ];
        }

        if ($selectedModelSlugs->count() > $maxModels) {
            $selectedModelSlugs = $selectedModelSlugs->take($maxModels)->values();
        }

        $allowedModelSlugs = $this->allowedModelSlugsForUser($user);
        if (! $user->isAdmin()) {
            $invalidModels = $selectedModelSlugs->filter(fn ($slug) => ! in_array($slug, $allowedModelSlugs, true));
            if ($invalidModels->isNotEmpty()) {
                return [
                    'error'  => __('Some selected models are not available in your plan.'),
                    'status' => 403,
                ];
            }
        }

        $hasCredit = $selectedModelSlugs->contains(function (string $slug) {
            try {
                $driver = EntityFacade::driver(EntityEnum::fromSlug($slug));

                return $driver->hasCreditBalance();
            } catch (Throwable) {
                return false;
            }
        });

        if (! $hasCredit) {
            return [
                'error'  => __('You have no credits left. Please buy more credits to continue.'),
                'status' => 402,
            ];
        }

        return [
            'selected_model_slugs' => $selectedModelSlugs,
            'allowed_model_slugs'  => $allowedModelSlugs,
        ];
    }

    private function executeSingleCall(string $slug, array $history): array
    {
        try {
            $model = EntityEnum::fromSlug($slug);
        } catch (Throwable $e) {
            return [
                'model_slug'       => $slug,
                'model_label'      => $slug,
                'response_text'    => null,
                'response_time_ms' => null,
                'failed'           => true,
            ];
        }

        $spec = $this->buildRequestSpec($model, $history);
        if (! $spec) {
            return [
                'model_slug'       => $model->slug(),
                'model_label'      => $model->label(),
                'response_text'    => null,
                'response_time_ms' => null,
                'failed'           => true,
            ];
        }

        $response = Http::withHeaders($spec['headers'])
            ->timeout(90)
            ->post($spec['url'], $spec['body']);

        if (! $response->successful()) {
            return [
                'model_slug'       => $model->slug(),
                'model_label'      => $spec['model_label'],
                'response_text'    => null,
                'response_time_ms' => $this->responseTime($response),
                'failed'           => true,
            ];
        }

        $responseText = trim($this->extractModelResponseText($response, $spec['parser']));

        return [
            'model_slug'       => $model->slug(),
            'model_label'      => $spec['model_label'],
            'response_text'    => $responseText !== '' ? $responseText : null,
            'response_time_ms' => $this->responseTime($response),
            'failed'           => $responseText === '',
        ];
    }

    private function streamParallelModelResponses(Collection $modelSlugs, array $history): array
    {
        $client = new GuzzleClient([
            'timeout'         => 90,
            'connect_timeout' => 20,
        ]);

        $resultsBySlug = [];
        $promises = [];

        foreach ($modelSlugs as $slug) {
            $modelSlug = (string) $slug;
            $modelLabel = $modelSlug;

            try {
                $model = EntityEnum::fromSlug($modelSlug);
                $modelLabel = $model->label();
            } catch (Throwable $e) {
                $this->emitSseJson('data', [
                    'type'       => 'model_stream_start',
                    'model_slug' => $modelSlug,
                    'reply'      => [
                        'model_slug'  => $modelSlug,
                        'model_label' => $modelLabel,
                    ],
                ]);
                $resultsBySlug[$modelSlug] = $this->emitFailedModelStream($modelSlug, $modelLabel);

                continue;
            }

            $spec = $this->buildRequestSpec($model, $history);
            $this->emitSseJson('data', [
                'type'       => 'model_stream_start',
                'model_slug' => $modelSlug,
                'reply'      => [
                    'model_slug'  => $modelSlug,
                    'model_label' => $modelLabel,
                ],
            ]);

            if (! $spec) {
                $resultsBySlug[$modelSlug] = $this->emitFailedModelStream($modelSlug, $modelLabel);

                continue;
            }

            $responseTimeMs = null;
            $promises[$modelSlug] = $client->requestAsync('POST', $spec['url'], [
                'headers'     => $spec['headers'],
                'json'        => $spec['body'],
                'http_errors' => false,
                'on_stats'    => function ($stats) use (&$responseTimeMs) {
                    $transferTime = (float) $stats->getTransferTime();
                    $responseTimeMs = $transferTime > 0 ? (int) round($transferTime * 1000) : null;
                },
            ])->then(
                function (ResponseInterface $response) use ($modelSlug, $modelLabel, $spec, &$responseTimeMs) {
                    if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                        return $this->emitFailedModelStream($modelSlug, $modelLabel, $responseTimeMs);
                    }

                    $decoded = json_decode((string) $response->getBody(), true);
                    $responseText = trim($this->extractModelResponseTextFromDecoded(
                        is_array($decoded) ? $decoded : [],
                        (string) ($spec['parser'] ?? 'openai')
                    ));

                    if ($responseText === '') {
                        return $this->emitFailedModelStream($modelSlug, $modelLabel, $responseTimeMs);
                    }

                    foreach ($this->splitIntoChunks($responseText, 60) as $chunk) {
                        $this->emitSseJson('data', [
                            'type'       => 'model_stream_delta',
                            'model_slug' => $modelSlug,
                            'chunk'      => $chunk,
                        ]);
                    }

                    $result = [
                        'model_slug'       => $modelSlug,
                        'model_label'      => $modelLabel,
                        'response_text'    => $responseText,
                        'response_time_ms' => $responseTimeMs,
                        'failed'           => false,
                    ];

                    $this->emitSseJson('data', [
                        'type'             => 'model_stream_end',
                        'model_slug'       => $modelSlug,
                        'model_label'      => $modelLabel,
                        'failed'           => false,
                        'response_time_ms' => $responseTimeMs,
                    ]);

                    return $result;
                },
                function ($reason) use ($modelSlug, $modelLabel, &$responseTimeMs) {
                    return $this->emitFailedModelStream($modelSlug, $modelLabel, $responseTimeMs);
                }
            );
        }

        if (! empty($promises)) {
            $settled = PromiseUtils::settle($promises)->wait();
            foreach ($settled as $modelSlug => $state) {
                if (isset($state['value']) && is_array($state['value'])) {
                    $resultsBySlug[(string) $modelSlug] = $state['value'];

                    continue;
                }

                if (! isset($resultsBySlug[(string) $modelSlug])) {
                    $resultsBySlug[(string) $modelSlug] = $this->emitFailedModelStream((string) $modelSlug, (string) $modelSlug);
                }
            }
        }

        return $modelSlugs
            ->map(function ($slug) use ($resultsBySlug) {
                $key = (string) $slug;

                return $resultsBySlug[$key] ?? [
                    'model_slug'       => $key,
                    'model_label'      => $key,
                    'response_text'    => null,
                    'response_time_ms' => null,
                    'failed'           => true,
                ];
            })
            ->values()
            ->all();
    }

    private function emitFailedModelStream(string $modelSlug, string $modelLabel, ?int $responseTimeMs = null): array
    {
        $this->emitSseJson('data', [
            'type'       => 'model_stream_delta',
            'model_slug' => $modelSlug,
            'chunk'      => __('Failed to Respond.'),
        ]);
        $this->emitSseJson('data', [
            'type'             => 'model_stream_end',
            'model_slug'       => $modelSlug,
            'model_label'      => $modelLabel,
            'failed'           => true,
            'response_time_ms' => $responseTimeMs,
        ]);

        return [
            'model_slug'       => $modelSlug,
            'model_label'      => $modelLabel,
            'response_text'    => null,
            'response_time_ms' => $responseTimeMs,
            'failed'           => true,
        ];
    }

    private function extractModelResponseTextFromDecoded(array $payload, string $parser): string
    {
        return match ($parser) {
            'anthropic' => collect((array) data_get($payload, 'content', []))
                ->filter(fn ($item) => data_get($item, 'type') === 'text')
                ->pluck('text')
                ->filter()
                ->implode("\n"),
            'gemini' => collect((array) data_get($payload, 'candidates.0.content.parts', []))
                ->pluck('text')
                ->filter()
                ->implode("\n"),
            default => (function () use ($payload) {
                $content = data_get($payload, 'choices.0.message.content');
                if (is_array($content)) {
                    return collect($content)
                        ->pluck('text')
                        ->filter()
                        ->implode("\n");
                }

                return is_string($content) ? $content : '';
            })(),
        };
    }

    private function persistCouncilResult(
        UserOpenaiChatMessage $message,
        array $chatParams,
        array $modelResults,
        string $finalAnswer,
        array $councilResponse,
        ?EntityEnum $synthesisModel,
        Collection $successfulResults
    ): bool {
        try {
            DB::transaction(function () use ($message, $chatParams, $modelResults, $finalAnswer, $councilResponse, $synthesisModel, $successfulResults) {
                ModelCouncilResponse::query()
                    ->where('user_openai_chat_message_id', $message->id)
                    ->delete();

                $message->input = (string) ($chatParams['prompt'] ?? '');
                $message->response = $finalAnswer;
                $message->output = $finalAnswer;
                $message->words = countWords($finalAnswer);
                $message->model_slug = $synthesisModel?->slug();
                $message->council_response = $councilResponse;
                $message->credits = 0;
                $message->save();

                $totalCredits = 0;
                foreach ($modelResults as $result) {
                    $responseRow = ModelCouncilResponse::create([
                        'user_openai_chat_message_id' => $message->id,
                        'model_slug'                  => (string) ($result['model_slug'] ?? ''),
                        'model_label'                 => (string) ($result['model_label'] ?? ''),
                        'response_text'               => $result['response_text'] ?? null,
                        'response_time_ms'            => $result['response_time_ms'] ?? null,
                        'failed'                      => (bool) ($result['failed'] ?? false),
                        'credits'                     => 0,
                    ]);

                    if (! ($result['failed'] ?? true) && ! empty($result['response_text'])) {
                        $usedCredit = $this->chargeModelOutput((string) $result['model_slug'], (string) $result['response_text']);
                        $responseRow->credits = $usedCredit;
                        $responseRow->save();
                        $totalCredits += $usedCredit;
                    }
                }

                if ($synthesisModel && $successfulResults->isNotEmpty() && $finalAnswer !== '') {
                    $totalCredits += $this->chargeModelOutput($synthesisModel->slug(), $finalAnswer);
                }

                $message->credits = $totalCredits;
                $message->save();

                if (! empty($chatParams['chat'])) {
                    $chat = $chatParams['chat'];
                    $chat->total_credits += $totalCredits;
                    $chat->save();
                }
            });

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function persistCouncilResultFromShared(
        UserOpenaiChatMessage $message,
        array $chatParams,
        array $modelResults,
        string $finalAnswer,
        array $councilResponse,
        ?EntityEnum $synthesisModel,
        Collection $successfulResults,
        array $sourceMessageIds = []
    ): bool {
        try {
            DB::transaction(function () use ($message, $chatParams, $modelResults, $finalAnswer, $councilResponse, $synthesisModel, $successfulResults, $sourceMessageIds) {
                ModelCouncilResponse::query()
                    ->where('user_openai_chat_message_id', $message->id)
                    ->delete();

                $message->input = (string) ($chatParams['prompt'] ?? '');
                $message->response = $finalAnswer;
                $message->output = $finalAnswer;
                $message->words = countWords($finalAnswer);
                $message->model_slug = $synthesisModel?->slug();
                $message->council_response = $councilResponse;
                $message->credits = 0;
                $message->save();

                $existingModelCredits = 0;
                foreach ($modelResults as $result) {
                    $rowCredit = (int) ($result['credits'] ?? 0);
                    $existingModelCredits += max(0, $rowCredit);

                    ModelCouncilResponse::create([
                        'user_openai_chat_message_id' => $message->id,
                        'model_slug'                  => (string) ($result['model_slug'] ?? ''),
                        'model_label'                 => (string) ($result['model_label'] ?? ''),
                        'response_text'               => $result['response_text'] ?? null,
                        'response_time_ms'            => $result['response_time_ms'] ?? null,
                        'failed'                      => (bool) ($result['failed'] ?? false),
                        'credits'                     => $rowCredit,
                    ]);
                }

                $synthesisCredit = 0;
                if ($synthesisModel && $successfulResults->isNotEmpty() && $finalAnswer !== '') {
                    $synthesisCredit = $this->chargeModelOutput($synthesisModel->slug(), $finalAnswer);
                }

                $message->credits = $existingModelCredits + $synthesisCredit;
                $message->save();

                if (! empty($chatParams['chat'])) {
                    $chat = $chatParams['chat'];
                    $chat->total_credits += $synthesisCredit;
                    $chat->save();
                }

                if (! empty($sourceMessageIds)) {
                    UserOpenaiChatMessage::query()
                        ->whereIn('id', $sourceMessageIds)
                        ->delete();
                }
            });

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function waitForSharedModelMessages(
        string $sharedMessageUuid,
        int $chatId,
        int $userId,
        Collection $selectedModelSlugs,
        int $timeoutSeconds = 45
    ): Collection {
        $deadline = microtime(true) + max(5, $timeoutSeconds);
        $latestBySlug = collect();
        $selectedSlugs = $selectedModelSlugs->map(fn ($slug) => (string) $slug)->values()->all();
        $lastStateSignature = null;
        $stableSince = null;

        do {
            $rows = UserOpenaiChatMessage::query()
                ->where('user_id', $userId)
                ->where('user_openai_chat_id', $chatId)
                ->where('shared_uuid', $sharedMessageUuid)
                ->whereIn('model_slug', $selectedSlugs)
                ->orderBy('id')
                ->get();

            $latestBySlug = $rows
                ->groupBy('model_slug')
                ->map(fn ($messages) => $messages->last());

            if ($latestBySlug->count() >= count($selectedSlugs)) {
                $allCompleted = collect($selectedSlugs)->every(function ($slug) use ($latestBySlug) {
                    $row = $latestBySlug->get($slug);

                    return $row instanceof UserOpenaiChatMessage && $this->isSharedModelMessageCompleted($row);
                });

                if ($allCompleted) {
                    break;
                }

                $signature = collect($selectedSlugs)
                    ->map(function ($slug) use ($latestBySlug) {
                        /** @var UserOpenaiChatMessage|null $row */
                        $row = $latestBySlug->get($slug);
                        if (! $row) {
                            return "{$slug}:missing";
                        }

                        $updatedAt = $row->updated_at?->timestamp ?? 0;
                        $responseLength = mb_strlen((string) ($row->response ?? ''));
                        $responseState = $row->response === null ? 'null' : 'set';

                        return implode(':', [
                            $slug,
                            (string) $row->id,
                            (string) $updatedAt,
                            $responseState,
                            (string) $responseLength,
                        ]);
                    })
                    ->implode('|');

                if ($signature === $lastStateSignature) {
                    $stableSince ??= microtime(true);
                    if ((microtime(true) - $stableSince) >= 2.0) {
                        break;
                    }
                } else {
                    $lastStateSignature = $signature;
                    $stableSince = null;
                }
            }

            if (microtime(true) >= $deadline) {
                break;
            }

            usleep(250000);
        } while (microtime(true) < $deadline);

        return $latestBySlug;
    }

    private function isSharedModelMessageCompleted(UserOpenaiChatMessage $message): bool
    {
        $responseText = trim((string) ($message->response ?? ''));
        if ($responseText !== '') {
            return true;
        }

        if ($message->response !== null && $message->updated_at && $message->created_at && $message->updated_at->gt($message->created_at)) {
            return true;
        }

        return false;
    }

    private function buildModelResultsFromSharedMessages(Collection $selectedModelSlugs, Collection $messagesBySlug): array
    {
        return $selectedModelSlugs->map(function ($slug) use ($messagesBySlug) {
            $modelSlug = (string) $slug;
            $modelLabel = $modelSlug;

            try {
                $modelLabel = EntityEnum::fromSlug($modelSlug)->label();
            } catch (Throwable $e) {
                // keep fallback
            }

            /** @var UserOpenaiChatMessage|null $source */
            $source = $messagesBySlug->get($modelSlug);
            if (! $source) {
                return [
                    'model_slug'        => $modelSlug,
                    'model_label'       => $modelLabel,
                    'response_text'     => null,
                    'response_time_ms'  => null,
                    'failed'            => true,
                    'credits'           => 0,
                    'source_message_id' => null,
                ];
            }

            $responseText = trim((string) ($source->response ?? ''));
            $responseTimeMs = null;
            if ($source->created_at && $source->updated_at) {
                $responseTimeMs = max(0, (int) $source->updated_at->diffInMilliseconds($source->created_at));
            }

            return [
                'model_slug'        => $modelSlug,
                'model_label'       => $modelLabel,
                'response_text'     => $responseText !== '' ? $responseText : null,
                'response_time_ms'  => $responseTimeMs,
                'failed'            => $responseText === '',
                'credits'           => (int) ($source->credits ?? 0),
                'source_message_id' => (int) $source->id,
            ];
        })->values()->all();
    }

    public function generateCouncilResponse(Request $request, array $chatParams, array $history, ?User $user): array
    {
        if (! MarketplaceHelper::isRegistered('ai-chat-pro') || ! MarketplaceHelper::isRegistered('model-council')) {
            return [
                'error'  => __('Model Council is not available.'),
                'status' => 404,
            ];
        }

        if ((int) setting('ai_chat_pro_model_council_feature', 1) !== 1) {
            return [
                'error'  => __('Model Council is disabled by admin settings.'),
                'status' => 403,
            ];
        }

        if (! $user) {
            return [
                'error'  => __('Please login to use Model Council.'),
                'status' => 403,
            ];
        }

        if (! $this->userHasCouncilAccess($user)) {
            return [
                'error'  => __('Your current plan does not include Model Council.'),
                'status' => 403,
            ];
        }

        $maxModels = max(2, min(5, (int) setting('ai_chat_pro_council_max_models', 5)));
        $selectedModelSlugs = collect(Arr::wrap($request->input('council_model_slugs', [])))
            ->filter(fn ($slug) => is_string($slug) && trim($slug) !== '')
            ->map(fn ($slug) => trim((string) $slug))
            ->unique()
            ->values();

        if (Helper::appIsDemo()) {
            $maxModels = min($maxModels, 3);

            $identifier = auth()->check() ? (string) auth()->id() : Helper::getRequestIp();
            $attemptAllowed = RateLimiter::make('model_council_demo', 1, 1440)->attempt($identifier);

            if (! $attemptAllowed) {
                return [
                    'error'  => __('Demo limit: one Model Council request per day.'),
                    'status' => 429,
                ];
            }
        }

        if ($selectedModelSlugs->count() < 2) {
            return [
                'error'  => __('You need to select more than 1 model to use Model Council.'),
                'status' => 422,
            ];
        }

        if ($selectedModelSlugs->count() > $maxModels) {
            $selectedModelSlugs = $selectedModelSlugs->take($maxModels)->values();
        }

        $allowedModelSlugs = $this->allowedModelSlugsForUser($user);
        if (! $user->isAdmin()) {
            $invalidModels = $selectedModelSlugs->filter(fn ($slug) => ! in_array($slug, $allowedModelSlugs, true));
            if ($invalidModels->isNotEmpty()) {
                return [
                    'error'  => __('Some selected models are not available in your plan.'),
                    'status' => 403,
                ];
            }
        }

        $normalizedHistory = $this->normalizeHistory($history);
        $modelResults = $this->executeParallelCalls($selectedModelSlugs, $normalizedHistory);

        $successfulResults = collect($modelResults)->filter(fn ($item) => ! ($item['failed'] ?? true))->values();
        if ($successfulResults->isEmpty()) {
            return [
                'error'  => __('All selected models failed to respond. Please try again.'),
                'status' => 422,
            ];
        }

        $synthesisModel = $this->resolveSynthesisModel((string) $request->input('synthesis_model_slug', ''), $allowedModelSlugs, $selectedModelSlugs);

        if ($synthesisModel && ! $this->hasCreditForModel($synthesisModel->slug())) {
            return [
                'error'  => __('You have no credits left. Please buy more credits to continue.'),
                'status' => 402,
            ];
        }

        $synthesisPayload = $this->synthesize(
            prompt: (string) ($chatParams['prompt'] ?? ''),
            modelResults: $modelResults,
            synthesisModel: $synthesisModel,
        );

        $normalizedSynthesis = $this->normalizeSynthesisPayload(
            prompt: (string) ($chatParams['prompt'] ?? ''),
            modelResults: $modelResults,
            synthesisPayload: $synthesisPayload,
        );

        $finalAnswer = (string) ($normalizedSynthesis['final_answer'] ?? '');
        if ($finalAnswer === '') {
            $finalAnswer = trim((string) ($successfulResults->first()['response_text'] ?? __('Unable to synthesize a final answer.')));
        }

        $respondedModelsCount = $successfulResults->count();
        $failedModelsCount = collect($modelResults)->filter(fn ($row) => (bool) ($row['failed'] ?? false))->count();
        $confidencePercent = (int) ($normalizedSynthesis['confidence_percent'] ?? $this->estimateConfidence($modelResults));
        $overlapPercent = (int) ($normalizedSynthesis['overlap_percent'] ?? 0);
        $councilLabels = $this->mergeCouncilLabels($synthesisPayload);
        $titles = $this->buildCouncilTitles($councilLabels);
        $agreementTableRows = $this->resolveAgreementTableRows($synthesisPayload, $respondedModelsCount, $failedModelsCount, $confidencePercent, $overlapPercent, $councilLabels);
        $councilResponse = [
            'meta' => [
                'responded_models_count' => $respondedModelsCount,
                'failed_models_count'    => $failedModelsCount,
                'confidence_percent'     => $confidencePercent,
                'overlap_percent'        => $overlapPercent,
                'discovery_style'        => 'comparative_insights_v1',
                'labels'                 => [
                    'responded_models' => $councilLabels['responded_models'],
                    'confidence'       => $councilLabels['confidence'],
                ],
            ],
            'titles'                 => $titles,
            'agreement_table_rows'   => $agreementTableRows,
            'agreement_analysis'     => array_values((array) ($normalizedSynthesis['agreement_analysis'] ?? [])),
            'disagreements'          => array_values((array) ($normalizedSynthesis['disagreements'] ?? [])),
            'unique_discoveries'     => array_values((array) ($normalizedSynthesis['unique_discoveries'] ?? [])),
            'confidence_percent'     => $confidencePercent,
            'overlap_percent'        => $overlapPercent,
            'responded_models_count' => $respondedModelsCount,
            'failed_models_count'    => $failedModelsCount,
        ];

        $message = null;
        DB::transaction(function () use (&$message, $chatParams, $modelResults, $finalAnswer, $councilResponse, $synthesisModel, $successfulResults) {
            $message = UserOpenaiChatMessage::create([
                'user_id'             => auth()->id(),
                'user_openai_chat_id' => $chatParams['chat_id'],
                'input'               => $chatParams['prompt'],
                'response'            => $finalAnswer,
                'output'              => $finalAnswer,
                'hash'                => Str::random(256),
                'credits'             => 0,
                'words'               => countWords($finalAnswer),
                'images'              => is_array($chatParams['images'] ?? null)
                    ? implode(',', $chatParams['images'])
                    : ($chatParams['images'] ?? null),
                'pdfName'          => $chatParams['pdfname'] ?? null,
                'pdfPath'          => $chatParams['pdfpath'] ?? null,
                'is_council'       => true,
                'model_slug'       => $synthesisModel?->slug(),
                'council_response' => $councilResponse,
            ]);

            $totalCredits = 0;

            foreach ($modelResults as $result) {
                $responseRow = ModelCouncilResponse::create([
                    'user_openai_chat_message_id' => $message->id,
                    'model_slug'                  => $result['model_slug'],
                    'model_label'                 => $result['model_label'],
                    'response_text'               => $result['response_text'] ?? null,
                    'response_time_ms'            => $result['response_time_ms'] ?? null,
                    'failed'                      => (bool) ($result['failed'] ?? false),
                    'credits'                     => 0,
                ]);

                if (! ($result['failed'] ?? true) && ! empty($result['response_text'])) {
                    $usedCredit = $this->chargeModelOutput($result['model_slug'], (string) $result['response_text']);
                    $responseRow->credits = $usedCredit;
                    $responseRow->save();
                    $totalCredits += $usedCredit;
                }
            }

            if ($synthesisModel && $successfulResults->isNotEmpty()) {
                $synthesisCredit = $this->chargeModelOutput($synthesisModel->slug(), $finalAnswer);
                $totalCredits += $synthesisCredit;
            }

            $message->credits = $totalCredits;
            $message->save();

            $chat = $chatParams['chat'];
            if ($chat) {
                $chat->total_credits += $totalCredits;
                $chat->save();
            }
        });

        if (! $message) {
            return [
                'error'  => __('Unable to save Model Council response.'),
                'status' => 500,
            ];
        }

        return [
            'message_id'     => $message->id,
            'stream_output'  => $this->buildStreamOutput($finalAnswer, $councilResponse),
            'stream_payload' => [
                'final_answer'     => $finalAnswer,
                'council_response' => $councilResponse,
                'model_replies'    => collect($modelResults)->map(function (array $result) {
                    return [
                        'model_slug'       => $result['model_slug'] ?? '',
                        'model_label'      => $result['model_label'] ?? '',
                        'response_text'    => $result['response_text'] ?? '',
                        'response_time_ms' => $result['response_time_ms'] ?? null,
                        'failed'           => (bool) ($result['failed'] ?? false),
                    ];
                })->values()->all(),
            ],
        ];
    }

    private function allowedModelSlugsForUser(User $user): array
    {
        if ($user->isAdmin()) {
            return collect(EntityEnum::cases())->map->slug()->all();
        }

        return Entity::planModels()
            ->pluck('key')
            ->map(function ($key) {
                if ($key instanceof EntityEnum) {
                    return $key->slug();
                }

                return (string) $key;
            })
            ->filter()
            ->values()
            ->all();
    }

    private function userHasCouncilAccess(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return (bool) ($user->activePlan()?->model_council_support ?? false);
    }

    private function normalizeHistory(array $history): array
    {
        return collect($history)->map(function ($message) {
            $role = (string) ($message['role'] ?? 'user');
            if (! in_array($role, ['system', 'user', 'assistant'], true)) {
                $role = 'user';
            }

            return [
                'role'    => $role,
                'content' => $this->extractContent((string) $role, $message['content'] ?? ''),
            ];
        })->values()->all();
    }

    private function extractContent(string $role, mixed $content): string
    {
        if (is_string($content)) {
            return trim($content);
        }

        if (! is_array($content)) {
            return trim((string) $content);
        }

        $parts = [];
        foreach ($content as $item) {
            if (is_string($item)) {
                $parts[] = $item;

                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            if (isset($item['text']) && is_string($item['text'])) {
                $parts[] = $item['text'];

                continue;
            }

            if (isset($item['content']) && is_string($item['content'])) {
                $parts[] = $item['content'];
            }
        }

        $text = trim(implode("\n", array_filter($parts)));

        return $text === '' ? __('No content provided.') : $text;
    }

    private function executeParallelCalls(Collection $modelSlugs, array $history): array
    {
        $requestSpecs = [];
        $results = [];

        foreach ($modelSlugs as $slug) {
            try {
                $model = EntityEnum::fromSlug((string) $slug);
            } catch (Throwable $e) {
                $results[] = [
                    'model_slug'       => (string) $slug,
                    'model_label'      => (string) $slug,
                    'response_text'    => null,
                    'response_time_ms' => null,
                    'failed'           => true,
                ];

                continue;
            }

            $spec = $this->buildRequestSpec($model, $history);
            if (! $spec) {
                $results[] = [
                    'model_slug'       => $model->slug(),
                    'model_label'      => $model->label(),
                    'response_text'    => null,
                    'response_time_ms' => null,
                    'failed'           => true,
                ];

                continue;
            }

            $requestSpecs[$model->slug()] = $spec;
        }

        $responses = [];
        if (! empty($requestSpecs)) {
            $responses = Http::pool(function (Pool $pool) use ($requestSpecs) {
                foreach ($requestSpecs as $slug => $spec) {
                    $pool->as($slug)
                        ->withHeaders($spec['headers'])
                        ->timeout(90)
                        ->post($spec['url'], $spec['body']);
                }
            });
        }

        foreach ($modelSlugs as $slug) {
            $slug = (string) $slug;

            if (! isset($requestSpecs[$slug])) {
                continue;
            }

            $spec = $requestSpecs[$slug];
            $response = $responses[$slug] ?? null;

            if (! $response instanceof Response || ! $response->successful()) {
                $results[] = [
                    'model_slug'       => $slug,
                    'model_label'      => $spec['model_label'],
                    'response_text'    => null,
                    'response_time_ms' => $this->responseTime($response),
                    'failed'           => true,
                ];

                continue;
            }

            $responseText = $this->extractModelResponseText($response, $spec['parser']);

            if (trim($responseText) === '') {
                $results[] = [
                    'model_slug'       => $slug,
                    'model_label'      => $spec['model_label'],
                    'response_text'    => null,
                    'response_time_ms' => $this->responseTime($response),
                    'failed'           => true,
                ];

                continue;
            }

            $results[] = [
                'model_slug'       => $slug,
                'model_label'      => $spec['model_label'],
                'response_text'    => trim($responseText),
                'response_time_ms' => $this->responseTime($response),
                'failed'           => false,
            ];
        }

        return $results;
    }

    private function buildRequestSpec(EntityEnum $model, array $history): ?array
    {
        $engine = $model->engine();
        $openAiMessages = $this->toOpenAiMessages($history);

        return match ($engine) {
            EngineEnum::OPEN_AI => [
                'url'     => 'https://api.openai.com/v1/chat/completions',
                'headers' => [
                    'Authorization' => 'Bearer ' . ApiHelper::setOpenAiKey($this->settings()),
                    'Content-Type'  => 'application/json',
                ],
                'body' => OpenAiParamHelper::sanitizeChatParams([
                    'model'       => $model->value,
                    'messages'    => $openAiMessages,
                    'temperature' => 0.7,
                ]),
                'parser'      => 'openai',
                'model_label' => $model->label(),
            ],
            EngineEnum::ANTHROPIC => $this->buildAnthropicSpec($model, $openAiMessages),
            EngineEnum::GEMINI    => [
                'url' => sprintf(
                    'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
                    $model->value,
                    ApiHelper::setGeminiKey($this->settings())
                ),
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => [
                    'contents' => convertHistoryToGemini($openAiMessages),
                ],
                'parser'      => 'gemini',
                'model_label' => $model->label(),
            ],
            EngineEnum::DEEP_SEEK => [
                'url'     => 'https://api.deepseek.com/chat/completions',
                'headers' => [
                    'Authorization' => 'Bearer ' . ApiHelper::setDeepseekKey($this->settings()),
                    'Content-Type'  => 'application/json',
                ],
                'body' => [
                    'model'       => $model->value,
                    'messages'    => $openAiMessages,
                    'temperature' => 0.7,
                ],
                'parser'      => 'openai',
                'model_label' => $model->label(),
            ],
            EngineEnum::X_AI => [
                'url'     => 'https://api.x.ai/v1/chat/completions',
                'headers' => [
                    'Authorization' => 'Bearer ' . ApiHelper::setXAiKey($this->settings()),
                    'Content-Type'  => 'application/json',
                ],
                'body' => [
                    'model'       => $model->value,
                    'messages'    => $openAiMessages,
                    'temperature' => 0.7,
                ],
                'parser'      => 'openai',
                'model_label' => $model->label(),
            ],
            EngineEnum::OPEN_ROUTER => [
                'url'     => 'https://openrouter.ai/api/v1/chat/completions',
                'headers' => [
                    'Authorization' => 'Bearer ' . setting('open_router_api'),
                    'Content-Type'  => 'application/json',
                    'HTTP-Referer'  => config('app.url'),
                    'X-Title'       => config('app.name', 'MagicAI'),
                ],
                'body' => [
                    'model'    => $model->value,
                    'messages' => $openAiMessages,
                    'stream'   => false,
                ],
                'parser'      => 'openai',
                'model_label' => $model->label(),
            ],
            default => null,
        };
    }

    private function buildAnthropicSpec(EntityEnum $model, array $openAiMessages): ?array
    {
        $systemPrompt = collect($openAiMessages)
            ->firstWhere('role', 'system')['content'] ?? null;

        $messages = collect($openAiMessages)
            ->filter(fn ($message) => $message['role'] !== 'system')
            ->values()
            ->all();

        if (empty($messages)) {
            return null;
        }

        $body = [
            'model'      => $model->value,
            'max_tokens' => max(256, (int) setting('anthropic_max_output_length', 1024)),
            'messages'   => $messages,
            'stream'     => false,
        ];

        if (! empty($systemPrompt)) {
            $body['system'] = $systemPrompt;
        }

        return [
            'url'     => 'https://api.anthropic.com/v1/messages',
            'headers' => [
                'x-api-key'         => ApiHelper::setAnthropicKey($this->settings()),
                'anthropic-version' => '2023-06-01',
                'Content-Type'      => 'application/json',
            ],
            'body'        => $body,
            'parser'      => 'anthropic',
            'model_label' => $model->label(),
        ];
    }

    private function toOpenAiMessages(array $history): array
    {
        return collect($history)->map(function ($message) {
            $role = (string) ($message['role'] ?? 'user');
            if (! in_array($role, ['system', 'user', 'assistant'], true)) {
                $role = 'user';
            }

            return [
                'role'    => $role,
                'content' => trim((string) ($message['content'] ?? '')),
            ];
        })->filter(fn ($message) => $message['content'] !== '')
            ->values()
            ->all();
    }

    private function extractModelResponseText(Response $response, string $parser): string
    {
        return match ($parser) {
            'anthropic' => $this->parseAnthropicText($response),
            'gemini'    => $this->parseGeminiText($response),
            default     => $this->parseOpenAiText($response),
        };
    }

    private function parseOpenAiText(Response $response): string
    {
        $content = data_get($response->json(), 'choices.0.message.content');

        if (is_array($content)) {
            return collect($content)
                ->pluck('text')
                ->filter()
                ->implode("\n");
        }

        return is_string($content) ? $content : '';
    }

    private function parseAnthropicText(Response $response): string
    {
        $content = data_get($response->json(), 'content', []);

        if (! is_array($content)) {
            return '';
        }

        return collect($content)
            ->filter(fn ($item) => data_get($item, 'type') === 'text')
            ->pluck('text')
            ->filter()
            ->implode("\n");
    }

    private function parseGeminiText(Response $response): string
    {
        $parts = data_get($response->json(), 'candidates.0.content.parts', []);

        if (! is_array($parts)) {
            return '';
        }

        return collect($parts)
            ->pluck('text')
            ->filter()
            ->implode("\n");
    }

    private function responseTime(?Response $response): ?int
    {
        if (! $response) {
            return null;
        }

        $totalTime = (float) data_get($response->handlerStats(), 'total_time', 0);

        return $totalTime > 0 ? (int) round($totalTime * 1000) : null;
    }

    private function resolveSynthesisModel(string $requestedSynthesisModel, array $allowedModelSlugs, ?Collection $selectedModelSlugs = null): ?EntityEnum
    {
        $candidateSlug = trim($requestedSynthesisModel);
        $userExplicitlyChose = $candidateSlug !== '';
        $resolved = null;

        if ($candidateSlug !== '') {
            try {
                $candidate = EntityEnum::fromSlug($candidateSlug);
                if (auth()->user()?->isAdmin() || in_array($candidate->slug(), $allowedModelSlugs, true)) {
                    $resolved = $candidate;
                }
            } catch (Throwable) {
                // fallback below
            }
        }

        if (! $resolved) {
            $settingModel = trim((string) setting('ai_chat_pro_council_synthesis_model', ''));
            if ($settingModel !== '') {
                try {
                    $resolved = EntityEnum::fromSlug($settingModel);
                } catch (Throwable) {
                    // fallback below
                }
            }
        }

        if (! $resolved) {
            try {
                $defaultEngine = EngineEnum::fromSlug(setting('default_ai_engine', EngineEnum::OPEN_AI->slug()));
                $resolved = match ($defaultEngine) {
                    EngineEnum::OPEN_ROUTER => EntityEnum::fromSlug(setting('default_open_router_model', EntityEnum::PERPLEXITY_LLAMA_31_SONAR_8B->slug())),
                    default                 => $defaultEngine->getDefaultWordModel($this->settings()),
                };
            } catch (Throwable) {
                $resolved = EntityEnum::GPT_4_O;
            }
        }

        if (! $userExplicitlyChose && $resolved && $selectedModelSlugs && ! $this->hasCreditForModel($resolved->slug())) {
            foreach ($selectedModelSlugs as $slug) {
                $slugString = (string) $slug;
                if ($this->hasCreditForModel($slugString)) {
                    try {
                        $resolved = EntityEnum::fromSlug($slugString);

                        break;
                    } catch (Throwable) {
                        continue;
                    }
                }
            }
        }

        return $resolved;
    }

    private function settings(): Setting
    {
        return Setting::getCache();
    }

    private function synthesize(string $prompt, array $modelResults, ?EntityEnum $synthesisModel): array
    {
        if (! $synthesisModel) {
            return [];
        }

        $responseSummary = collect($modelResults)->values()->map(function ($result, $index) {
            return [
                'model_id'    => 'M' . ($index + 1),
                'model_label' => $this->extractShortModelName($result['model_label'] ?? $result['model_slug'] ?? 'Unknown'),
                'failed'      => (bool) ($result['failed'] ?? true),
                'answer'      => $result['failed'] ? 'Failed to respond' : ($result['response_text'] ?? ''),
            ];
        })->values()->all();

        $modelLabelMap = collect($responseSummary)
            ->filter(fn ($row) => ! ($row['failed'] ?? true))
            ->mapWithKeys(fn ($row) => [$row['model_id'] => $row['model_label']])
            ->all();
        $modelNamesForPrompt = collect($modelLabelMap)
            ->map(fn ($label, $id) => "{$id} = {$label}")
            ->values()
            ->implode(', ');

        $firstModelLabel = (string) (reset($modelLabelMap) ?: 'M1');
        $lastModelLabel = (string) (end($modelLabelMap) ?: 'M2');

        $instruction = <<<TXT
You are an expert synthesis analyst in a model council workflow. Your job is to produce insightful, specific analysis — not generic meta-observations.

Model mapping: {$modelNamesForPrompt}

Return only valid JSON with this shape:
{
  "final_answer": "string (markdown with headings/lists/bold for structure)",
  "agreement_analysis": ["string"],
  "disagreements": ["string"],
  "unique_discoveries": ["string"],
  "confidence_percent": "<integer 0-100>",
  "labels": {
    "final_answer_title": "string",
    "agreement_level_title": "string",
    "confidence_impact_title": "string",
    "agreement_analysis_title": "string",
    "disagreements_title": "string",
    "discoveries_title": "string",
    "model_replies_title": "string",
    "overlap_suffix": "string",
    "impact_very_high": "string",
    "impact_high": "string",
    "impact_medium": "string",
    "impact_low": "string",
    "responded_models": "string",
    "confidence": "string"
  }
}

## final_answer
Produce a comprehensive, well-structured answer that directly addresses the user's question. Synthesize the BEST insights from all model outputs into a single authoritative response that is MORE useful than any individual model's response. Use markdown formatting (## headings, **bold**, bullet points, numbered lists, paragraphs). Never use generic lines like "How can I help?" or "Let me know if you need more."

## agreement_analysis — "Where Models Agree"
Each item MUST start with [M1,M2,...] tags, then state the SPECIFIC shared finding with evidence.
GOOD: "[M1,M2] Both confirm the Mall of Istanbul houses MOİPARK, described as Europe's largest indoor amusement park with rides for all ages."
BAD: "[M1,M2] Both models discuss the mall's features." (too vague — WHAT features?)
BAD: "Models generally agree on the topic." (no evidence tags, no specifics)

## disagreements — "Where Models Disagree"
Each item MUST start with [M1,M2,...] tags, then describe each model's SPECIFIC position and WHY they differ.
GOOD: "[M1,M2] {$firstModelLabel} provides a detailed 6-point breakdown of mall features, while {$lastModelLabel} only asks clarifying questions without providing substantive information — a fundamentally different response approach."
BAD: "[M1,M2] Models differ in detail level." (no specifics about WHAT differs)

## unique_discoveries — "Unique Findings"
Each item MUST start with [M1] or similar tag, name the model, state the UNIQUE finding, and explain WHY IT MATTERS.
GOOD: "[M1] {$firstModelLabel} uniquely identifies MOİ Sahne as a performing arts center hosting concerts and theater within the mall complex — a cultural dimension other models missed entirely."
BAD: "[M1] One model adds extra detail." (what detail? why does it matter?)

## labels
Provide UI labels translated into the SAME language as the user question. Each value is a short header/word the UI renders directly. English reference values (translate these — do NOT output the English):
- final_answer_title: "Final Synthesized Answer"
- agreement_level_title: "Agreement Level"
- confidence_impact_title: "Confidence Impact"
- agreement_analysis_title: "Where Models Agree"
- disagreements_title: "Where Models Disagree"
- discoveries_title: "Unique Discoveries"
- model_replies_title: "Model Replies"
- overlap_suffix: "overlap" (the word that follows a percentage, e.g. "82% overlap")
- impact_very_high: "Very High"
- impact_high: "High"
- impact_medium: "Medium"
- impact_low: "Low"
- responded_models: "Models Responded"
- confidence: "Confidence"
Keep each value concise (1–4 words). Keep the JSON keys in English.

## Rules
- Use the same language as the user question for ALL fields, including every value inside `labels`.
- Reference models by their actual names (e.g. "{$firstModelLabel}") in the text body, not just M1/M2.
- Every list item MUST start with model evidence tags in square brackets [M1,M2].
- Only include claims explicitly supported by the model outputs.
- Be SPECIFIC: mention actual facts, features, numbers, recommendations, or arguments — not meta-commentary about response style.
- confidence_percent: integer 0-100 reflecting how strongly models agree on factual content. Score 80-100 if models agree on core facts with minor wording differences. Score 50-79 if models agree on most points but diverge on some details. Score 20-49 if models give substantially different answers. Score 0-19 only if models directly contradict each other. You MUST evaluate this carefully — do not default to 0.
- Output raw JSON only, no markdown code fences.
TXT;

        $messages = [
            ['role' => 'system', 'content' => $instruction],
            ['role' => 'user', 'content' => json_encode([
                'question'      => $prompt,
                'model_outputs' => $responseSummary,
            ], JSON_THROW_ON_ERROR)],
        ];

        $spec = $this->buildRequestSpec($synthesisModel, $messages);
        if (! $spec) {
            return [];
        }

        $response = Http::withHeaders($spec['headers'])
            ->timeout(90)
            ->post($spec['url'], $spec['body']);

        if (! $response->successful()) {
            return [];
        }

        $rawText = trim($this->extractModelResponseText($response, $spec['parser']));
        if ($rawText === '') {
            return [];
        }

        $parsed = json_decode($rawText, true);
        if (! is_array($parsed) && preg_match('/\{.*\}/s', $rawText, $match)) {
            $parsed = json_decode($match[0], true);
        }

        return is_array($parsed) ? $parsed : [];
    }

    private function normalizeSynthesisPayload(string $prompt, array $modelResults, array $synthesisPayload): array
    {
        $metrics = $this->calculateConsensusMetrics($modelResults);
        $successful = collect($modelResults)
            ->filter(fn ($row) => ! ($row['failed'] ?? true) && trim((string) ($row['response_text'] ?? '')) !== '')
            ->values();
        $languageCode = $this->detectCouncilLanguage($prompt, $successful, $synthesisPayload);

        $finalAnswer = $this->resolveFinalAnswer(
            prompt: $prompt,
            candidate: (string) ($synthesisPayload['final_answer'] ?? ''),
            successfulResults: $successful,
            metrics: $metrics,
        );

        $validModelIds = collect($modelResults)->values()->map(fn ($_, $index) => 'M' . ($index + 1))->all();

        $agreementAnalysis = $this->extractEvidenceBackedItems(
            $synthesisPayload['agreement_analysis'] ?? [],
            $validModelIds,
            minRefs: 2,
            maxRefs: null
        );
        $disagreements = $this->extractEvidenceBackedItems(
            $synthesisPayload['disagreements'] ?? [],
            $validModelIds,
            minRefs: 2,
            maxRefs: null
        );
        $uniqueDiscoveries = $this->extractEvidenceBackedItems(
            $synthesisPayload['unique_discoveries'] ?? [],
            $validModelIds,
            minRefs: 1,
            maxRefs: null
        );

        $fallbackSections = $this->deriveFallbackSections($modelResults, $metrics);

        $agreementMin = ((int) ($metrics['responded_models'] ?? 0)) >= 3 ? 3 : 2;
        $uniqueMin = ((int) ($metrics['responded_models'] ?? 0)) >= 2 ? 2 : 1;

        $agreementAnalysis = $this->mergeSectionItems($agreementAnalysis, (array) ($fallbackSections['agreement_analysis'] ?? []), $agreementMin, 5);
        $disagreements = $this->mergeSectionItems($disagreements, (array) ($fallbackSections['disagreements'] ?? []), 1, 5);
        $discoverySeeds = $this->mergeSectionItems($uniqueDiscoveries, (array) ($fallbackSections['unique_discoveries'] ?? []), $uniqueMin, 10);
        $discoverySeeds = $this->enrichUniqueDiscoveries($successful, $discoverySeeds, $uniqueMin, 10);

        $respondedModels = (int) ($metrics['responded_models'] ?? 0);
        $agreementAnalysis = $this->normalizeConsensusPhrasing($agreementAnalysis, $respondedModels, $languageCode);
        $disagreements = $this->normalizeConsensusPhrasing($disagreements, $respondedModels, $languageCode);
        $disagreements = $this->normalizeDisagreementPhrasing($disagreements, $respondedModels, $metrics, $languageCode);
        $discoverySeeds = $this->filterDiscoveryItems($discoverySeeds);
        $uniqueDiscoveries = $this->buildComparativeDiscoveries(
            $discoverySeeds,
            $agreementAnalysis,
            $disagreements,
            $metrics,
            $respondedModels,
            $languageCode
        );
        [$disagreements, $agreementAnalysis, $uniqueDiscoveries] = $this->dedupeAcrossSections(
            $disagreements,
            $agreementAnalysis,
            $uniqueDiscoveries
        );

        if (count($uniqueDiscoveries) < $uniqueMin) {
            $uniqueDiscoveries = array_merge(
                $uniqueDiscoveries,
                $this->fallbackComparativeDiscoveries($metrics, $respondedModels, $languageCode)
            );
            [$disagreements, $agreementAnalysis, $uniqueDiscoveries] = $this->dedupeAcrossSections(
                $disagreements,
                $agreementAnalysis,
                $uniqueDiscoveries
            );
        }

        $agreementAnalysis = $this->enforceSectionLanguageConsistency(
            $agreementAnalysis,
            'agreement_analysis',
            $languageCode,
            $respondedModels,
            $metrics
        );
        $disagreements = $this->enforceSectionLanguageConsistency(
            $disagreements,
            'disagreements',
            $languageCode,
            $respondedModels,
            $metrics
        );
        $uniqueDiscoveries = $this->enforceSectionLanguageConsistency(
            $uniqueDiscoveries,
            'unique_discoveries',
            $languageCode,
            $respondedModels,
            $metrics
        );

        $confidencePercent = $this->resolveConfidencePercent(
            metrics: $metrics,
            disagreementCount: count($disagreements),
            requestedConfidence: (int) ($synthesisPayload['confidence_percent'] ?? 0)
        );

        return [
            'final_answer'       => $finalAnswer,
            'agreement_analysis' => array_slice(array_values(array_filter($agreementAnalysis)), 0, 5),
            'disagreements'      => array_slice(array_values(array_filter($disagreements)), 0, 5),
            'unique_discoveries' => array_slice(array_values(array_filter($uniqueDiscoveries)), 0, 5),
            'overlap_percent'    => (int) ($metrics['overlap_percent'] ?? 0),
            'confidence_percent' => $confidencePercent,
        ];
    }

    private function calculateConsensusMetrics(array $modelResults): array
    {
        $successful = collect($modelResults)
            ->filter(fn ($row) => ! ($row['failed'] ?? true) && trim((string) ($row['response_text'] ?? '')) !== '')
            ->values();

        $totalModels = max(1, count($modelResults));
        $respondedModels = $successful->count();
        $failedModels = max(0, $totalModels - $respondedModels);

        $scoredResponses = $successful->map(function ($row) {
            $text = trim((string) ($row['response_text'] ?? ''));
            $style = $this->classifyResponseStyle($text);

            return [
                'tokens'             => $this->tokenizeForSimilarity($text),
                'weight'             => $this->informationWeight($text),
                'is_low_information' => $this->isLowInformationResponse($text),
                'contains_question'  => str_contains($text, '?'),
                'question_led'       => (bool) ($style['question_led'] ?? false),
                'instruction_led'    => (bool) ($style['instruction_led'] ?? false),
            ];
        })->values();

        $pairwiseScores = [];
        $weightedScoreSum = 0.0;
        $weightedScoreTotal = 0.0;

        for ($i = 0; $i < $scoredResponses->count(); $i++) {
            for ($j = $i + 1; $j < $scoredResponses->count(); $j++) {
                $score = $this->tokenSimilarity(
                    (array) ($scoredResponses[$i]['tokens'] ?? []),
                    (array) ($scoredResponses[$j]['tokens'] ?? [])
                );
                $leftLow = (bool) ($scoredResponses[$i]['is_low_information'] ?? false);
                $rightLow = (bool) ($scoredResponses[$j]['is_low_information'] ?? false);
                if ($leftLow || $rightLow) {
                    // Treat clarification-style replies as weak evidence rather than contradiction.
                    $score = max($score, 0.5);
                }
                $pairWeight = max(
                    0.2,
                    (((float) ($scoredResponses[$i]['weight'] ?? 1.0)) + ((float) ($scoredResponses[$j]['weight'] ?? 1.0))) / 2
                );

                $pairwiseScores[] = $score;
                $weightedScoreSum += ($score * $pairWeight);
                $weightedScoreTotal += $pairWeight;
            }
        }

        if ($weightedScoreTotal > 0) {
            $averageSimilarity = $weightedScoreSum / $weightedScoreTotal;
        } elseif (! empty($pairwiseScores)) {
            $averageSimilarity = (float) (array_sum($pairwiseScores) / count($pairwiseScores));
        } else {
            $averageSimilarity = $respondedModels > 0 ? 1.0 : 0.0;
        }

        $informativeTokenSets = $scoredResponses
            ->filter(fn ($row) => ! ($row['is_low_information'] ?? false))
            ->pluck('tokens')
            ->all();
        $sharedTerms = count($informativeTokenSets) >= 2
            ? $this->extractSharedTerms($informativeTokenSets, count($informativeTokenSets))
            : [];
        $questionResponses = $scoredResponses
            ->filter(fn ($row) => (bool) ($row['contains_question'] ?? false))
            ->count();
        $questionLedModels = $scoredResponses
            ->filter(fn ($row) => (bool) ($row['question_led'] ?? false))
            ->count();
        $instructionLedModels = $scoredResponses
            ->filter(fn ($row) => (bool) ($row['instruction_led'] ?? false))
            ->count();
        $hasQuestionVsAnswerMix = $questionLedModels > 0
            && $instructionLedModels > 0
            && $questionLedModels < $respondedModels
            && $instructionLedModels < $respondedModels;
        $averageInformationWeight = $scoredResponses->isEmpty()
            ? 0.0
            : (float) $scoredResponses->avg(fn ($row) => (float) ($row['weight'] ?? 0.0));
        $lowInformationModels = $scoredResponses->filter(fn ($row) => (bool) ($row['is_low_information'] ?? false))->count();
        $weightedRespondedRatio = $totalModels > 0
            ? (float) min(1.0, $scoredResponses->sum(fn ($row) => (float) ($row['weight'] ?? 0.0)) / $totalModels)
            : 0.0;
        $informativeModels = $scoredResponses->filter(fn ($row) => ! ($row['is_low_information'] ?? false))->count();

        return [
            'total_models'               => $totalModels,
            'responded_models'           => $respondedModels,
            'failed_models'              => $failedModels,
            'responded_ratio'            => $respondedModels / $totalModels,
            'weighted_responded_ratio'   => max(0.0, min(1.0, $weightedRespondedRatio)),
            'average_similarity'         => max(0.0, min(1.0, $averageSimilarity)),
            'overlap_percent'            => (int) round(max(0.0, min(1.0, $averageSimilarity)) * 100),
            'shared_terms'               => $sharedTerms,
            'has_question_answer_mix'    => $hasQuestionVsAnswerMix,
            'question_led_models'        => $questionLedModels,
            'instruction_led_models'     => $instructionLedModels,
            'question_models'            => $questionResponses,
            'average_information_weight' => max(0.0, min(1.0, $averageInformationWeight)),
            'low_information_models'     => $lowInformationModels,
            'informative_models'         => $informativeModels,
        ];
    }

    private function classifyResponseStyle(string $text): array
    {
        $clean = trim(strip_tags($text));
        if ($clean === '') {
            return [
                'question_led'    => false,
                'instruction_led' => false,
            ];
        }

        $wordCount = countWords($clean);
        $questionCount = substr_count($clean, '?');
        $endsWithQuestion = str_ends_with(trim($clean), '?');
        $hasListStructure = preg_match('/(^|\n)\s*(?:[-*]|\d+[.)])\s+/u', $clean) === 1;
        $instructionSignal = preg_match('/\b(step|steps|first|then|finally|use|apply|wash|rinse|dry|guide)\b/iu', $clean) === 1;

        $questionLed = $questionCount > 0 && ($endsWithQuestion || $questionCount >= 2 || $wordCount <= 55);
        $instructionLed = $hasListStructure || $instructionSignal || ($wordCount >= 55 && $questionCount === 0);

        return [
            'question_led'    => $questionLed,
            'instruction_led' => $instructionLed,
        ];
    }

    private function detectCouncilLanguage(string $prompt, Collection $successfulResults, array $synthesisPayload = []): string
    {
        $sample = trim($prompt);

        if ($sample === '') {
            $sample = (string) $successfulResults
                ->pluck('response_text')
                ->filter()
                ->map(fn ($text) => trim((string) $text))
                ->first();
        }

        if ($sample === '') {
            $sample = trim((string) ($synthesisPayload['final_answer'] ?? ''));
        }

        if ($sample !== '' && preg_match('/[\x{0600}-\x{06FF}]/u', $sample)) {
            return 'ar';
        }

        if ($sample !== '' && preg_match('/[A-Za-z]/u', $sample)) {
            return 'en';
        }

        return 'other';
    }

    private function supportsTemplateLocalization(string $languageCode): bool
    {
        return in_array($languageCode, ['en', 'ar'], true);
    }

    private function oneModelLabel(string $languageCode, bool $capital = false): string
    {
        $text = $languageCode === 'ar' ? 'نموذج واحد' : 'one model';
        if ($capital && $languageCode !== 'ar') {
            return Str::ucfirst($text);
        }

        return $text;
    }

    private function bothModelsLabel(string $languageCode, bool $capital = false): string
    {
        $text = $languageCode === 'ar' ? 'كلا النموذجين' : 'both models';
        if ($capital && $languageCode !== 'ar') {
            return Str::ucfirst($text);
        }

        return $text;
    }

    private function twoOfModelsLabel(int $respondedModels, string $languageCode, bool $capital = false): string
    {
        if ($respondedModels <= 2) {
            return $this->bothModelsLabel($languageCode, $capital);
        }

        $text = $languageCode === 'ar'
            ? "نموذجان من أصل {$respondedModels} نماذج"
            : "two of {$respondedModels} models";

        if ($capital && $languageCode !== 'ar') {
            return Str::ucfirst($text);
        }

        return $text;
    }

    private function otherModelLabel(string $languageCode): string
    {
        return $languageCode === 'ar' ? 'النموذج الآخر' : 'the other model';
    }

    private function templateAllModelsAlign(int $respondedModels, string $languageCode): string
    {
        return $languageCode === 'ar'
            ? "جميع النماذج ({$respondedModels}) متفقة على التوصية الأساسية."
            : "All {$respondedModels} models align on the core recommendation.";
    }

    private function templateTwoModelsAlign(int $respondedModels, string $languageCode): string
    {
        if ($respondedModels <= 2) {
            return $languageCode === 'ar'
                ? 'كلا النموذجين متفقان على التوصية الأساسية.'
                : 'Both models align on the core recommendation.';
        }

        return $languageCode === 'ar'
            ? "نموذجان من أصل {$respondedModels} متفقان على التوصية الأساسية."
            : "Two of {$respondedModels} models align on the core recommendation.";
    }

    private function templateOneModelExtraDetail(int $respondedModels, string $languageCode): string
    {
        return $languageCode === 'ar'
            ? 'نموذج واحد يضيف تفاصيل إضافية بينما بقية النماذج أكثر اختصاراً.'
            : 'One model introduces extra detail while other models stay more concise.';
    }

    private function templateQuestionVsAnswer(int $respondedModels, string $languageCode): string
    {
        if ($languageCode === 'ar') {
            return "نموذج واحد يطلب توضيحاً بينما نموذجان من أصل {$respondedModels} يقدمان إجابة مباشرة.";
        }

        return "One model asks for clarification while two of {$respondedModels} models provide direct guidance.";
    }

    private function templateOneModelDiverges(int $respondedModels, string $languageCode): string
    {
        if ($respondedModels <= 2) {
            return $languageCode === 'ar'
                ? 'نموذج واحد يختلف في التفاصيل الثانوية بينما النموذج الآخر متوافق مع الخطوات الأساسية.'
                : 'One model diverges on secondary details while the other model stays aligned on core steps.';
        }

        return $languageCode === 'ar'
            ? "نموذج واحد يختلف في التفاصيل الثانوية بينما نموذجان من أصل {$respondedModels} متوافقان مع الخطوات الأساسية."
            : "One model diverges on secondary details while two of {$respondedModels} models stay aligned on core steps.";
    }

    private function templateModelsHighlightDetail(int $respondedModels, string $detail, string $languageCode): string
    {
        if ($respondedModels <= 2) {
            return $languageCode === 'ar'
                ? "كلا النموذجين يبرزان هذه النقطة: {$detail}."
                : "Both models highlight this point: {$detail}.";
        }

        return $languageCode === 'ar'
            ? "نموذجان من أصل {$respondedModels} يبرزان هذه النقطة: {$detail}."
            : "Two of {$respondedModels} models highlight this point: {$detail}.";
    }

    private function templateAllModelsShareIntent(int $respondedModels, string $languageCode): string
    {
        return $languageCode === 'ar'
            ? "جميع النماذج ({$respondedModels}) تشترك في نفس الهدف الأساسي."
            : "All {$respondedModels} models share the same core intent.";
    }

    private function templateTwoModelsShareIntent(int $respondedModels, string $languageCode): string
    {
        if ($respondedModels <= 2) {
            return $languageCode === 'ar'
                ? 'كلا النموذجين يشتركان في الهدف الأساسي مع اختلاف في التفاصيل.'
                : 'Both models share core intent with detail-level variation.';
        }

        return $languageCode === 'ar'
            ? "نموذجان من أصل {$respondedModels} يشتركان في الهدف الأساسي مع اختلاف في التفاصيل."
            : "Two of {$respondedModels} models share core intent with detail-level variation.";
    }

    private function templateOneModelAdditionalDetail(string $languageCode): string
    {
        return $languageCode === 'ar'
            ? 'نموذج واحد يضيف تفاصيل تنفيذية إضافية.'
            : 'One model contributes additional implementation detail.';
    }

    private function templateNoContradictions(string $languageCode): string
    {
        return $languageCode === 'ar'
            ? 'لا توجد تناقضات مباشرة.'
            : 'No direct contradictions detected.';
    }

    private function buildLanguageNativeDiscoveries(array $discoverySeeds, array $agreementAnalysis, array $disagreements): array
    {
        return collect(array_merge($discoverySeeds, $agreementAnalysis, $disagreements))
            ->map(fn ($line) => $this->normalizeComparativeBullet((string) $line, 220))
            ->filter()
            ->reject(fn ($line) => $this->isLowValueDiscoveryItem((string) $line))
            ->unique(fn ($line) => Str::lower((string) $line))
            ->take(5)
            ->values()
            ->all();
    }

    private function enforceSectionLanguageConsistency(
        array $items,
        string $section,
        string $languageCode,
        int $respondedModels,
        array $metrics
    ): array {
        $normalized = collect($items)
            ->map(fn ($item) => $this->ensureSentence($this->sanitizeUserFacingSynthesisText((string) $item)))
            ->filter()
            ->values()
            ->all();

        if (! in_array($languageCode, ['en', 'ar'], true)) {
            return $normalized;
        }

        $filtered = collect($normalized)
            ->filter(fn ($line) => $this->isLineInLanguage((string) $line, $languageCode))
            ->values()
            ->all();

        if (! empty($filtered)) {
            return $filtered;
        }

        return $this->sectionLanguageFallback($section, $languageCode, $respondedModels, $metrics);
    }

    private function isLineInLanguage(string $line, string $languageCode): bool
    {
        $text = trim($line);
        if ($text === '') {
            return false;
        }

        $hasArabic = preg_match('/[\x{0600}-\x{06FF}]/u', $text) === 1;
        $hasLatin = preg_match('/[A-Za-z]/u', $text) === 1;

        return match ($languageCode) {
            'ar'    => $hasArabic && ! $hasLatin,
            'en'    => $hasLatin && ! $hasArabic,
            default => true,
        };
    }

    private function sectionLanguageFallback(string $section, string $languageCode, int $respondedModels, array $metrics): array
    {
        $overlapPercent = (int) ($metrics['overlap_percent'] ?? 0);
        $questionMix = (bool) ($metrics['has_question_answer_mix'] ?? false);

        if ($section === 'agreement_analysis') {
            return [
                $languageCode === 'ar'
                    ? ($overlapPercent >= 60
                        ? 'تتفق النماذج على الاتجاه الأساسي مع اختلاف بسيط في التفاصيل.'
                        : 'تتفق النماذج على الاتجاه العام مع اختلاف في مستوى التفاصيل.')
                    : ($overlapPercent >= 60
                        ? 'Models agree on the core direction with minor detail variation.'
                        : 'Models generally agree on direction with variation in detail.'),
            ];
        }

        if ($section === 'disagreements') {
            return [
                $languageCode === 'ar'
                    ? ($questionMix
                        ? 'تطلب بعض النماذج توضيحاً بينما تقدم نماذج أخرى إجابة مباشرة.'
                        : 'يتركز الاختلاف بين النماذج في مستوى التفصيل.')
                    : ($questionMix
                        ? 'Some models ask for clarification while others provide direct answers.'
                        : 'Differences across models are mainly in level of detail.'),
            ];
        }

        if ($section === 'unique_discoveries') {
            return $this->fallbackComparativeDiscoveries($metrics, $respondedModels, $languageCode);
        }

        return [];
    }

    private function tokenizeForSimilarity(string $text): array
    {
        $clean = Str::lower(strip_tags($text));
        $clean = preg_replace('/[^\pL\pN\s]+/u', ' ', $clean) ?? '';
        $parts = preg_split('/\s+/u', trim($clean)) ?: [];

        $stopwords = [
            'the', 'and', 'for', 'with', 'that', 'this', 'from', 'you', 'your', 'are', 'was', 'were', 'have', 'has', 'had',
            'will', 'would', 'could', 'should', 'can', 'may', 'might', 'into', 'about', 'what', 'when', 'where', 'why', 'how',
            'they', 'them', 'their', 'there', 'here', 'also', 'than', 'then', 'just', 'like', 'more', 'most', 'very', 'each',
            'some', 'such', 'over', 'under', 'only', 'been', 'being', 'does', 'did', 'done', 'our', 'out', 'all', 'any',
            'not', 'but', 'too', 'its', 'it', 'as', 'on', 'in', 'of', 'to', 'is', 'a', 'an', 'or',
        ];

        return collect($parts)
            ->map(fn ($token) => trim((string) $token))
            ->filter(fn ($token) => mb_strlen($token) >= 3)
            ->reject(fn ($token) => in_array($token, $stopwords, true))
            ->unique()
            ->values()
            ->all();
    }

    private function tokenSimilarity(array $tokensA, array $tokensB): float
    {
        if (empty($tokensA) && empty($tokensB)) {
            return 1.0;
        }

        if (empty($tokensA) || empty($tokensB)) {
            return 0.0;
        }

        $intersection = array_values(array_intersect($tokensA, $tokensB));
        $union = array_values(array_unique(array_merge($tokensA, $tokensB)));

        if (count($union) === 0) {
            return 0.0;
        }

        return count($intersection) / count($union);
    }

    private function extractSharedTerms(array $tokenSets, int $respondedModels): array
    {
        if ($respondedModels <= 1 || empty($tokenSets)) {
            return [];
        }

        $counts = [];
        foreach ($tokenSets as $tokens) {
            foreach (array_unique($tokens) as $token) {
                $counts[$token] = ($counts[$token] ?? 0) + 1;
            }
        }

        $threshold = max(2, (int) ceil($respondedModels * 0.66));

        return collect($counts)
            ->filter(fn ($count) => $count >= $threshold)
            ->sortDesc()
            ->keys()
            ->take(4)
            ->values()
            ->all();
    }

    private function resolveFinalAnswer(string $prompt, string $candidate, Collection $successfulResults, array $metrics): string
    {
        $cleanCandidate = trim($candidate);
        $best = $this->selectMostInformativeModelAnswer($successfulResults);
        if ($cleanCandidate !== '' && ! $this->isLowValueFinalAnswer($cleanCandidate)) {
            $resolved = $cleanCandidate;
            if ($this->shouldPreferInformativeAnswer($prompt, $cleanCandidate, $best)) {
                $resolved = $best;
            }

            return $this->finalizeFinalAnswerForPrompt($prompt, $resolved);
        }

        if ($best !== '') {
            return $this->finalizeFinalAnswerForPrompt($prompt, $best);
        }

        return trim((string) __('Unable to synthesize a final answer.'));
    }

    private function shouldPreferInformativeAnswer(string $prompt, string $candidate, string $best): bool
    {
        if ($best === '') {
            return false;
        }

        if ($this->isLowInformationResponse($candidate) && ! $this->isLowInformationResponse($best)) {
            return true;
        }

        if (! $this->isBroadPrompt($prompt)) {
            return false;
        }

        $candidateWords = max(1, countWords($candidate));
        $bestWords = countWords($best);

        if (str_ends_with(trim($candidate), '?') && ! str_ends_with(trim($best), '?')) {
            return true;
        }

        return $candidateWords < 45 && $bestWords >= max(60, $candidateWords + 20);
    }

    private function finalizeFinalAnswerForPrompt(string $prompt, string $answer): string
    {
        $clean = $this->sanitizeUserFacingSynthesisText($answer);
        if ($clean === '') {
            return trim((string) __('Unable to synthesize a final answer.'));
        }

        return $clean;
    }

    private function isLowValueFinalAnswer(string $text): bool
    {
        $normalized = Str::lower(trim($text));
        $wordCount = countWords($normalized);

        $genericPhrases = [
            'what would you like',
            'how can i help',
            'let me know if',
            'i can help with that',
            'sure, what would you like',
            'please go ahead and ask',
        ];

        foreach ($genericPhrases as $phrase) {
            if (str_contains($normalized, $phrase)) {
                return true;
            }
        }

        if ($wordCount <= 14) {
            return true;
        }

        if (str_ends_with($normalized, '?') && $wordCount < 30) {
            return true;
        }

        return false;
    }

    private function selectMostInformativeModelAnswer(Collection $successfulResults): string
    {
        if ($successfulResults->isEmpty()) {
            return '';
        }

        $best = $successfulResults
            ->map(function ($row) {
                $text = trim((string) ($row['response_text'] ?? ''));
                $wordCount = countWords($text);
                $sentenceCount = max(1, preg_match_all('/[.!?]+/u', $text));
                $questionPenalty = str_ends_with(trim($text), '?') ? 15 : 0;
                $score = ($wordCount * 1.0) + ($sentenceCount * 5) - $questionPenalty;

                return [
                    'text'  => $text,
                    'score' => $score,
                ];
            })
            ->sortByDesc('score')
            ->first();

        if (! is_array($best) || empty($best['text'])) {
            return '';
        }

        return Str::of((string) $best['text'])->trim()->limit(2400, '')->toString();
    }

    private function isBroadPrompt(string $prompt): bool
    {
        $normalized = Str::of(strip_tags($prompt))->lower()->squish()->toString();
        if ($normalized === '') {
            return false;
        }

        $wordCount = countWords($normalized);
        if ($wordCount <= 1) {
            return true;
        }

        if ($wordCount > 5) {
            return false;
        }

        if (str_contains($normalized, '?')) {
            return false;
        }

        $directiveVerbs = ['compare', 'explain', 'fix', 'build', 'write', 'create', 'summarize', 'list', 'debug', 'solve'];
        foreach ($directiveVerbs as $verb) {
            if (str_contains($normalized, $verb)) {
                return false;
            }
        }

        return true;
    }

    private function isLowInformationResponse(string $text): bool
    {
        $normalized = Str::of(strip_tags($text))->lower()->squish()->toString();
        if ($normalized === '') {
            return true;
        }

        $wordCount = countWords($normalized);
        $genericPhrases = [
            'what would you like',
            'how can i help',
            'let me know what',
            'i can help with that',
            'how can i assist',
            'sure, what would you like',
            'please go ahead and ask',
        ];

        foreach ($genericPhrases as $phrase) {
            if (str_contains($normalized, $phrase)) {
                return true;
            }
        }

        if ($wordCount <= 18) {
            return true;
        }

        if (str_ends_with($normalized, '?') && $wordCount < 45) {
            return true;
        }

        return false;
    }

    private function informationWeight(string $text): float
    {
        $clean = trim(strip_tags($text));
        if ($clean === '') {
            return 0.0;
        }

        if ($this->isLowInformationResponse($clean)) {
            return 0.35;
        }

        $wordCount = countWords($clean);
        $weight = 0.65;

        if ($wordCount >= 35) {
            $weight += 0.15;
        }
        if ($wordCount >= 80) {
            $weight += 0.15;
        }
        if (preg_match('/(^|\n)\s*(?:[-*]|\d+\.)\s+/u', $clean)) {
            $weight += 0.05;
        }

        return max(0.2, min(1.0, $weight));
    }

    private function sanitizeUserFacingSynthesisText(string $text): string
    {
        $clean = trim(strip_tags($text));
        if ($clean === '') {
            return '';
        }

        $clean = preg_replace('/\[M\d+(?:\s*[,&]\s*M\d+)*\]\s*/u', '', $clean) ?? $clean;
        $clean = preg_replace('/\*{3,}\s*$/u', '', $clean) ?? $clean;
        $clean = str_replace(["\r\n", "\r"], "\n", $clean);
        $clean = preg_replace('/\n{3,}/u', "\n\n", $clean) ?? $clean;

        return collect(explode("\n", $clean))
            ->map(fn ($line) => Str::of((string) $line)->squish()->toString())
            ->implode("\n");
    }

    private function normalizeConsensusPhrasing(array $items, int $respondedModels, string $languageCode = 'en'): array
    {
        return collect($items)
            ->map(function ($item) {
                $line = $this->sanitizeUserFacingSynthesisText((string) $item);
                if ($line === '') {
                    return '';
                }

                return $this->ensureSentence(trim((string) $line));
            })
            ->filter()
            ->unique(fn ($line) => Str::lower((string) $line))
            ->values()
            ->all();
    }

    private function filterDiscoveryItems(array $items): array
    {
        return collect($items)
            ->map(fn ($item) => $this->normalizeComparativeBullet((string) $item, 220))
            ->filter(fn ($item) => $item !== '')
            ->reject(fn ($item) => $this->isLowValueDiscoveryItem((string) $item))
            ->unique(fn ($item) => Str::lower((string) $item))
            ->values()
            ->all();
    }

    private function isLowValueDiscoveryItem(string $text): bool
    {
        $line = Str::of(strip_tags($text))->squish()->toString();
        if ($line === '') {
            return true;
        }

        if ($this->isLowInformationResponse($line)) {
            return true;
        }

        $normalized = Str::lower($line);
        $lowValuePatterns = [
            'let me know',
            'how can i help',
            'i can help',
            'are you looking for',
            'what would you like',
            'view full response',
            'أخبرني بما تحتاجه',
            'كيف يمكنني مساعدتك',
            'هل ترغب',
        ];

        foreach ($lowValuePatterns as $pattern) {
            if (str_contains($normalized, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeDisagreementPhrasing(array $items, int $respondedModels, array $metrics, string $languageCode = 'en'): array
    {
        return collect($items)
            ->map(function ($item) {
                $line = $this->sanitizeUserFacingSynthesisText((string) $item);
                if ($line === '') {
                    return '';
                }

                return $this->ensureSentence($line);
            })
            ->filter()
            ->unique(fn ($line) => Str::lower((string) $line))
            ->values()
            ->all();
    }

    private function buildComparativeDiscoveries(
        array $discoverySeeds,
        array $agreementAnalysis,
        array $disagreements,
        array $metrics,
        int $respondedModels,
        string $languageCode = 'en'
    ): array {
        $discoveries = [];

        foreach ($discoverySeeds as $line) {
            $normalized = $this->normalizeComparativeBullet((string) $line, 220);
            if ($normalized !== '' && ! $this->isLowValueDiscoveryItem($normalized)) {
                $discoveries[] = $normalized;
            }

            if (count($discoveries) >= 5) {
                break;
            }
        }

        if (count($discoveries) < 2) {
            foreach (array_merge($agreementAnalysis, $disagreements) as $line) {
                $normalized = $this->normalizeComparativeBullet((string) $line, 220);
                if ($normalized !== '' && ! $this->isLowValueDiscoveryItem($normalized)) {
                    $discoveries[] = $normalized;
                }

                if (count($discoveries) >= 5) {
                    break;
                }
            }
        }

        return collect($discoveries)
            ->filter()
            ->unique(fn ($line) => Str::lower((string) $line))
            ->take(5)
            ->values()
            ->all();
    }

    private function toComparativeDiscoveryLine(string $line, int $respondedModels, string $languageCode = 'en'): string
    {
        $normalized = $this->normalizeComparativeBullet($line, 220);
        if ($normalized === '') {
            return '';
        }

        if (preg_match('/\b(all|two of|one model|no direct)\b/iu', $normalized)) {
            return $normalized;
        }

        if (str_contains(Str::lower($normalized), 'while')) {
            return $this->templateOneModelDiverges($respondedModels, $languageCode);
        }

        $detail = $this->summarizeComparativeDetail($normalized);
        if ($detail === '') {
            return '';
        }

        return $this->templateModelsHighlightDetail($respondedModels, $detail, $languageCode);
    }

    private function fallbackComparativeDiscoveries(array $metrics, int $respondedModels, string $languageCode = 'en'): array
    {
        if (! $this->supportsTemplateLocalization($languageCode)) {
            return [];
        }

        $overlapPercent = (int) ($metrics['overlap_percent'] ?? 0);
        $sharedTerms = (array) ($metrics['shared_terms'] ?? []);
        $items = [];

        if (! empty($sharedTerms)) {
            $themes = implode(', ', array_slice($sharedTerms, 0, 4));
            if ($overlapPercent >= 60) {
                $items[] = $languageCode === 'ar'
                    ? "جميع النماذج تتناول مواضيع مشتركة تشمل: {$themes}."
                    : "All {$respondedModels} models address shared themes including: {$themes}.";
            } else {
                $items[] = $languageCode === 'ar'
                    ? "النماذج تتقاطع في بعض النقاط مثل: {$themes}، لكنها تختلف في المنهج."
                    : "Models overlap on key topics ({$themes}) but differ in approach and depth.";
            }
        }

        if ((bool) ($metrics['has_question_answer_mix'] ?? false)) {
            $items[] = $languageCode === 'ar'
                ? 'نموذج واحد يطلب توضيحاً قبل الإجابة، بينما تقدم نماذج أخرى إجابة مباشرة ومفصلة.'
                : 'One model requests clarification before answering, while others provide direct substantive responses — showing different interaction strategies.';
        }

        return collect($items)
            ->map(fn ($line) => $this->normalizeComparativeBullet((string) $line, 220))
            ->filter()
            ->unique(fn ($line) => Str::lower((string) $line))
            ->take(5)
            ->values()
            ->all();
    }

    private function dedupeAcrossSections(array $disagreements, array $agreementAnalysis, array $uniqueDiscoveries): array
    {
        $seenFingerprints = [];
        $seenTexts = [];

        $disagreements = $this->appendUniqueSectionItems($disagreements, $seenFingerprints, $seenTexts, 5);
        $agreementAnalysis = $this->appendUniqueSectionItems($agreementAnalysis, $seenFingerprints, $seenTexts, 5);
        $uniqueDiscoveries = $this->appendUniqueSectionItems($uniqueDiscoveries, $seenFingerprints, $seenTexts, 5);

        return [$disagreements, $agreementAnalysis, $uniqueDiscoveries];
    }

    private function appendUniqueSectionItems(array $items, array &$seenFingerprints, array &$seenTexts, int $maxItems): array
    {
        $accepted = [];

        foreach ($items as $item) {
            $line = $this->ensureSentence($this->sanitizeUserFacingSynthesisText((string) $item));
            if ($line === '') {
                continue;
            }

            $fingerprint = $this->fingerprintSectionItem($line);
            if ($fingerprint !== '' && isset($seenFingerprints[$fingerprint])) {
                continue;
            }

            $tokens = $this->tokenizeForSimilarity($line);
            $hasSemanticDuplicate = collect($seenTexts)->contains(function ($existing) use ($tokens) {
                return $this->tokenSimilarity($tokens, $this->tokenizeForSimilarity((string) $existing)) >= 0.78;
            });
            if ($hasSemanticDuplicate) {
                continue;
            }

            if ($fingerprint !== '') {
                $seenFingerprints[$fingerprint] = true;
            }
            $seenTexts[] = $line;
            $accepted[] = $line;

            if (count($accepted) >= $maxItems) {
                break;
            }
        }

        return $accepted;
    }

    private function fingerprintSectionItem(string $line): string
    {
        $normalized = Str::lower($line);
        $normalized = preg_replace('/[^a-z0-9\s]+/u', ' ', $normalized) ?? '';
        $normalized = preg_replace('/\s+/u', ' ', trim($normalized)) ?? '';

        return $normalized;
    }

    private function normalizeComparativeBullet(string $line, int $maxLength = 220): string
    {
        $clean = $this->sanitizeUserFacingSynthesisText($line);
        if ($clean === '') {
            return '';
        }

        $clean = str_replace(['**', '__', '`'], '', $clean);
        $clean = preg_replace('/(^|\s)#{1,6}\s*/u', ' ', $clean) ?? $clean;
        $clean = preg_replace('/^\s*(?:[-*•]+|\d+[.)])\s+/u', '', $clean) ?? $clean;
        $clean = preg_replace('/\s+\d+\.$/u', '', $clean) ?? $clean;
        $clean = preg_replace('/\s*\.\.\.$/u', '', $clean) ?? $clean;
        $clean = trim((string) preg_replace('/\s+/u', ' ', $clean));

        if ($clean === '' || str_starts_with($clean, '###')) {
            return '';
        }

        if (mb_strlen($clean) > $maxLength) {
            $clean = Str::of($clean)->limit($maxLength, '')->toString();
            $clean = rtrim($clean, ' ,;:-');
        }

        return $this->ensureSentence($clean);
    }

    private function summarizeComparativeDetail(string $detail): string
    {
        $clean = $this->sanitizeUserFacingSynthesisText($detail);
        if ($clean === '') {
            return '';
        }

        $clean = Str::before($clean, "\n");
        if (str_contains($clean, ':')) {
            $clean = Str::before($clean, ':');
        }
        $clean = preg_replace('/\s+-\s+.+$/u', '', $clean) ?? $clean;
        $clean = preg_replace('/\s+\d+\.$/u', '', $clean) ?? $clean;
        $clean = rtrim(trim((string) $clean), '.!؟?');

        if ($clean === '' || mb_strlen($clean) < 8) {
            return '';
        }

        return Str::of($clean)->limit(95, '')->toString();
    }

    private function startsWithComparativePrefix(string $line, string $languageCode = 'en'): bool
    {
        $trimmed = trim($line);

        if ($languageCode === 'ar') {
            return preg_match('/^(جميع النماذج|كلا النموذجين|نموذجان من أصل \d+|نموذج واحد|لا توجد تناقضات مباشرة)/u', $trimmed) === 1;
        }

        if ($languageCode === 'en') {
            return preg_match('/^(All\s+\d+\s+models|Both models|Two of \d+ models|One model|No direct contradictions)/u', $trimmed) === 1;
        }

        return true;
    }

    private function ensureSentence(string $line): string
    {
        $clean = trim($line);
        if ($clean === '') {
            return '';
        }

        if (! preg_match('/[.!?]$/u', $clean)) {
            $clean .= '.';
        }

        return $clean;
    }

    private function mergeSectionItems(array $primary, array $fallback, int $minimum = 1, int $maxItems = 5): array
    {
        $merged = [];

        foreach ($primary as $item) {
            $line = $this->sanitizeUserFacingSynthesisText((string) $item);
            if ($line === '') {
                continue;
            }

            $key = Str::lower($line);
            if (isset($merged[$key])) {
                continue;
            }

            $merged[$key] = $line;

            if (count($merged) >= $maxItems) {
                break;
            }
        }

        if (count($merged) < $minimum) {
            foreach ($fallback as $item) {
                $line = $this->sanitizeUserFacingSynthesisText((string) $item);
                if ($line === '') {
                    continue;
                }

                $key = Str::lower($line);
                if (isset($merged[$key])) {
                    continue;
                }

                $merged[$key] = $line;

                if (count($merged) >= $minimum) {
                    break;
                }
            }
        }

        return array_values($merged);
    }

    private function enrichUniqueDiscoveries(Collection $successfulResults, array $items, int $minimum, int $maxItems = 4): array
    {
        $normalized = collect($items)
            ->map(fn ($item) => $this->sanitizeUserFacingSynthesisText((string) $item))
            ->filter()
            ->values()
            ->all();

        if (count($normalized) >= $minimum || $successfulResults->isEmpty()) {
            return array_slice($normalized, 0, $maxItems);
        }

        $candidates = [];
        foreach ($successfulResults as $row) {
            $response = trim((string) ($row['response_text'] ?? ''));
            if ($response === '' || $this->isLowInformationResponse($response)) {
                continue;
            }

            $addedForModel = false;
            foreach ($this->extractSubstantiveSentences($response, 2) as $sentence) {
                $line = $this->sanitizeUserFacingSynthesisText($sentence);
                if ($line === '') {
                    continue;
                }

                $tokens = $this->tokenizeForSimilarity($line);
                $isDistinct = true;
                foreach (array_merge($normalized, $candidates) as $existing) {
                    if ($this->tokenSimilarity($tokens, $this->tokenizeForSimilarity((string) $existing)) >= 0.60) {
                        $isDistinct = false;

                        break;
                    }
                }

                if ($isDistinct) {
                    $candidates[] = $line;
                    $addedForModel = true;
                }

                if (count($normalized) + count($candidates) >= $maxItems) {
                    break 2;
                }

                if ($addedForModel) {
                    break;
                }
            }
        }

        $merged = $this->mergeSectionItems($normalized, $candidates, $minimum, $maxItems);

        return array_slice($merged, 0, $maxItems);
    }

    private function extractSubstantiveSentences(string $text, int $limit = 2): array
    {
        $clean = trim(strip_tags($text));
        if ($clean === '') {
            return [];
        }

        $sentences = preg_split('/(?<=[.!?])\s+/u', $clean) ?: [$clean];

        return collect($sentences)
            ->map(fn ($sentence) => $this->sanitizeUserFacingSynthesisText((string) $sentence))
            ->filter(fn ($sentence) => $sentence !== '')
            ->reject(fn ($sentence) => countWords($sentence) < 7)
            ->reject(fn ($sentence) => str_ends_with(trim($sentence), '?'))
            ->reject(fn ($sentence) => $this->isLowValueDiscoveryItem((string) $sentence))
            ->unique(fn ($sentence) => Str::lower((string) $sentence))
            ->take(max(1, $limit))
            ->values()
            ->all();
    }

    private function normalizeListItems(mixed $items): array
    {
        if (is_string($items)) {
            $items = preg_split('/\r?\n/u', $items) ?: [];
        } elseif (is_array($items)) {
            $items = collect($items)
                ->map(function ($item) {
                    if (is_string($item)) {
                        return $item;
                    }
                    if (is_array($item)) {
                        return $item['item'] ?? $item['text'] ?? $item['value'] ?? '';
                    }

                    return '';
                })
                ->all();
        } else {
            return [];
        }

        return collect($items)
            ->map(fn ($item) => trim((string) $item))
            ->filter(fn ($item) => $item !== '')
            ->values()
            ->all();
    }

    private function extractEvidenceBackedItems(
        mixed $items,
        array $validModelIds,
        int $minRefs = 1,
        ?int $maxRefs = null
    ): array {
        $normalizedItems = $this->normalizeListItems($items);
        $validMap = collect($validModelIds)->mapWithKeys(fn ($id) => [Str::upper(trim((string) $id)) => true])->all();
        $accepted = [];

        foreach ($normalizedItems as $item) {
            $line = trim((string) $item);
            if ($line === '') {
                continue;
            }

            if (! preg_match('/^\[([^\]]+)\]\s*(.+)$/u', $line, $matches)) {
                continue;
            }

            $refs = collect(preg_split('/[,\s]+/u', (string) ($matches[1] ?? '')) ?: [])
                ->map(fn ($ref) => Str::upper(trim((string) $ref)))
                ->filter()
                ->filter(fn ($ref) => isset($validMap[$ref]))
                ->unique()
                ->values();

            $refCount = $refs->count();
            if ($refCount < $minRefs) {
                continue;
            }
            if ($maxRefs !== null && $refCount > $maxRefs) {
                continue;
            }

            $text = trim((string) ($matches[2] ?? ''));
            if ($text === '') {
                continue;
            }

            $text = $this->sanitizeUserFacingSynthesisText($text);
            if ($text === '') {
                continue;
            }

            $accepted[] = $text;
        }

        return collect($accepted)->unique(fn ($item) => Str::lower((string) $item))->values()->all();
    }

    private function deriveFallbackSections(array $modelResults, array $metrics): array
    {
        $successful = collect($modelResults)
            ->filter(fn ($row) => ! ($row['failed'] ?? true) && trim((string) ($row['response_text'] ?? '')) !== '')
            ->values();
        $informativeSuccessful = $successful
            ->reject(fn ($row) => $this->isLowInformationResponse((string) ($row['response_text'] ?? '')))
            ->values();

        $agreement = [];
        $disagreements = [];
        $unique = [];

        $avgSim = (float) ($metrics['average_similarity'] ?? 0.0);
        $sharedTerms = (array) ($metrics['shared_terms'] ?? []);

        if (! empty($sharedTerms)) {
            $sharedThemes = implode(', ', array_slice($sharedTerms, 0, 5));
            if ($avgSim >= 0.75) {
                $agreement[] = __('Models strongly agree on key topics including: ') . $sharedThemes . '.';
            } elseif ($avgSim >= 0.50) {
                $agreement[] = __('Models share common ground on: ') . $sharedThemes . __(' with variation in depth and approach.');
            } else {
                $agreement[] = __('Models cover overlapping themes including: ') . $sharedThemes . __(' but take different approaches.');
            }
        } elseif ($avgSim >= 0.75) {
            $agreement[] = __('Model outputs are strongly aligned on the core answer.');
        } elseif ($avgSim >= 0.50) {
            $agreement[] = __('Model outputs generally agree on the core direction, with detail-level variation.');
        } else {
            $agreement[] = __('Model outputs show limited overlap, indicating multiple plausible directions.');
        }

        if ($avgSim < 0.65 && $successful->count() >= 2) {
            $wordCounts = $successful->map(fn ($row) => countWords(trim((string) ($row['response_text'] ?? ''))));
            $maxWords = $wordCounts->max();
            $minWords = $wordCounts->min();
            if ($maxWords > 0 && $minWords > 0 && $maxWords > $minWords * 2) {
                $disagreements[] = __('Models differ significantly in response depth — the most detailed response is over twice the length of the shortest.');
            }
        }

        if ((bool) ($metrics['has_question_answer_mix'] ?? false)) {
            $disagreements[] = __('One model requests clarification before answering, while another provides a direct, substantive response.');
        }

        if (empty($disagreements)) {
            $disagreements[] = __('No direct factual contradictions were found; differences are primarily in detail level and framing.');
        }

        if ($informativeSuccessful->count() <= 1) {
            $firstInformative = $informativeSuccessful->first();
            if ($firstInformative) {
                $leadSentence = $this->extractSubstantiveSentences((string) ($firstInformative['response_text'] ?? ''), 1)[0] ?? '';
                $leadSentence = $this->sanitizeUserFacingSynthesisText($leadSentence);
                if ($leadSentence !== '') {
                    $unique[] = $leadSentence;
                }
            }

            if ($successful->count() > $informativeSuccessful->count()) {
                $unique[] = __('Other models focused on clarification rather than adding new factual details.');
            }
        }

        foreach ($informativeSuccessful as $row) {
            $text = trim((string) ($row['response_text'] ?? ''));
            $candidateSentences = $this->extractSubstantiveSentences($text, 2);
            $addedForModel = false;

            foreach ($candidateSentences as $sentence) {
                $tokenizedSentence = $this->tokenizeForSimilarity($sentence);
                $isUnique = true;

                foreach ($informativeSuccessful as $other) {
                    if (($other['model_slug'] ?? '') === ($row['model_slug'] ?? '')) {
                        continue;
                    }

                    foreach ($this->extractSubstantiveSentences((string) ($other['response_text'] ?? ''), 2) as $otherSentence) {
                        if ($this->tokenSimilarity($tokenizedSentence, $this->tokenizeForSimilarity($otherSentence)) >= 0.55) {
                            $isUnique = false;

                            break 2;
                        }
                    }
                }

                if (! $isUnique) {
                    continue;
                }

                $line = $this->sanitizeUserFacingSynthesisText($sentence);
                if ($line === '') {
                    continue;
                }

                $duplicate = collect($unique)->contains(function ($existing) use ($line) {
                    return $this->tokenSimilarity(
                        $this->tokenizeForSimilarity((string) $existing),
                        $this->tokenizeForSimilarity($line)
                    ) >= 0.7;
                });

                if (! $duplicate) {
                    $unique[] = $line;
                    $addedForModel = true;
                }

                if (count($unique) >= 5) {
                    break 2;
                }

                if ($addedForModel) {
                    break;
                }
            }
        }

        return [
            'agreement_analysis' => array_slice(array_values(array_filter(array_unique(array_map(fn ($item) => $this->sanitizeUserFacingSynthesisText((string) $item), $agreement)))), 0, 5),
            'disagreements'      => array_slice(array_values(array_filter(array_unique(array_map(fn ($item) => $this->sanitizeUserFacingSynthesisText((string) $item), $disagreements)))), 0, 5),
            'unique_discoveries' => array_slice(array_values(array_filter(array_unique(array_map(fn ($item) => $this->sanitizeUserFacingSynthesisText((string) $item), $unique)))), 0, 5),
        ];
    }

    private function resolveConfidencePercent(array $metrics, int $disagreementCount, int $requestedConfidence = 0): int
    {
        $respondedRatio = (float) ($metrics['weighted_responded_ratio'] ?? $metrics['responded_ratio'] ?? 0.0);
        $averageSimilarity = (float) ($metrics['average_similarity'] ?? 0.0);
        $overlapPercent = (int) ($metrics['overlap_percent'] ?? max(0, min(100, (int) round($averageSimilarity * 100))));
        $averageInformationWeight = (float) ($metrics['average_information_weight'] ?? 0.0);
        $lowInformationModels = (int) ($metrics['low_information_models'] ?? 0);
        $informativeModels = (int) ($metrics['informative_models'] ?? 0);
        $disagreementPenalty = max(0, $disagreementCount - 1) * 3;
        $lowInformationPenalty = max(0, $lowInformationModels - 1) * 3;

        $calculated = (int) round(
            22
            + ($respondedRatio * 37)
            + ($averageSimilarity * 45)
            + ($averageInformationWeight * 10)
            - $disagreementPenalty
            - $lowInformationPenalty
        );

        $overlapGuard = (int) round(25 + ($overlapPercent * 0.65));
        $calculated = (int) round(($calculated * 0.72) + ($overlapGuard * 0.28));

        if ($informativeModels >= 1) {
            $calculated = max($calculated, 45);
        }
        $calculated = max(10, min(100, $calculated));

        // The synthesis LLM evaluates semantic agreement between model responses.
        // Give its assessment significant weight since token-based Jaccard similarity
        // produces inherently low scores for paraphrased AI responses.
        if ($requestedConfidence > 0) {
            $calculated = (int) round(($calculated * 0.45) + (max(0, min(100, $requestedConfidence)) * 0.55));
        }

        return max(10, min(100, $calculated));
    }

    private function estimateConfidence(array $modelResults): int
    {
        $total = count($modelResults);
        if ($total === 0) {
            return 0;
        }

        $responded = collect($modelResults)->where('failed', false)->count();

        return (int) min(100, max(10, round(($responded / $total) * 100)));
    }

    private function hasCreditForModel(string $modelSlug): bool
    {
        try {
            return EntityFacade::driver(EntityEnum::fromSlug($modelSlug))->hasCreditBalance();
        } catch (Throwable) {
            return false;
        }
    }

    private function chargeModelOutput(string $modelSlug, string $responseText): int
    {
        if ($responseText === '') {
            return 0;
        }

        try {
            $driver = EntityFacade::driver(EntityEnum::fromSlug($modelSlug));
            $driver->input($responseText)->calculateCredit();

            $usedCredit = (int) ceil(max(1, $driver->getCalculatedInputCredit()));
            $driver->decreaseCredit();
            Usage::getSingle()->updateWordCounts((int) max(1, round($driver->calculate())));

            return $usedCredit;
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function buildStreamOutput(string $finalAnswer, array $councilResponse): string
    {
        $titles = (array) ($councilResponse['titles'] ?? []);
        $metaLabels = (array) data_get($councilResponse, 'meta.labels', []);

        $lines = [
            (string) ($titles['final_answer'] ?? __('Final Synthesized Answer')),
            '',
            $finalAnswer,
            '',
            (string) ($titles['agreement_analysis'] ?? __('Where Models Agree')),
        ];

        foreach ((array) ($councilResponse['agreement_analysis'] ?? []) as $item) {
            $lines[] = '- ' . $item;
        }

        $lines[] = '';
        $lines[] = (string) ($titles['disagreements'] ?? __('Where Models Disagree'));

        foreach ((array) ($councilResponse['disagreements'] ?? []) as $item) {
            $lines[] = '- ' . $item;
        }

        $lines[] = '';
        $lines[] = (string) ($titles['discoveries'] ?? __('Unique Discoveries'));

        foreach ((array) ($councilResponse['unique_discoveries'] ?? []) as $item) {
            $lines[] = '- ' . $item;
        }

        $lines[] = '';
        $lines[] = sprintf(
            '%s %d%%',
            (string) ($metaLabels['confidence'] ?? __('Confidence')),
            (int) ($councilResponse['confidence_percent'] ?? 0)
        );

        return implode("\n", $lines);
    }

    private function extractShortModelName(string $label): string
    {
        $clean = trim($label);
        if ($clean === '') {
            return 'Unknown';
        }

        $clean = preg_replace('/\s*\(.*$/', '', $clean) ?? $clean;

        if (preg_match('/^((?:GPT[-\w.]*|Claude|Gemini|Llama|Mistral|Qwen|DeepSeek|Grok|Command|Perplexity|o\d[-\w]*|DALL[-\w]*|Flux|Stable[-\w]*)(?:\s+[\d.]+)?(?:\s+(?:Pro|Max|Ultra|Mini|Nano|Flash|Haiku|Sonnet|Opus|Turbo|Plus|Light|Large|Medium|Small|Preview|Thinking|R1))?(?:\s+\d+[BbKkMm])?)/iu', $clean, $matches)) {
            $candidate = trim($matches[1]);
            if (mb_strlen($candidate) >= 3) {
                return $candidate;
            }
        }

        $words = preg_split('/\s+/u', $clean) ?: [$clean];
        $nameWords = [];

        foreach ($words as $i => $word) {
            if ($i >= 4) {
                break;
            }

            if ($i >= 2 && preg_match('/^[a-z]/u', $word) && ! preg_match('/^\d/u', $word)) {
                break;
            }

            $nameWords[] = $word;
        }

        return implode(' ', $nameWords) ?: $clean;
    }
}
