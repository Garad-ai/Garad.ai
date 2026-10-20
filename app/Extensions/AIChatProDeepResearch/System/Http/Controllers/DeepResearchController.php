<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProDeepResearch\System\Http\Controllers;

use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity;
use App\Enums\Plan\FrequencyEnum;
use App\Extensions\AIChatProDeepResearch\System\Models\DeepResearchSession;
use App\Extensions\AIChatProDeepResearch\System\Services\DeepResearchService;
use App\Http\Controllers\Controller;
use App\Models\Usage;
use App\Models\UserOpenaiChat;
use App\Models\UserOpenaiChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class DeepResearchController extends Controller
{
    public function __construct(
        private DeepResearchService $service
    ) {}

    public function start(Request $request): JsonResponse
    {
        $request->validate([
            'prompt'  => 'required|string|max:5000',
            'chat_id' => 'required|integer',
        ]);

        $user = auth()->user();
        $chat = UserOpenaiChat::query()
            ->where('user_id', $user->id)
            ->findOrFail($request->integer('chat_id'));

        // Plan access check
        $plan = $user->relationPlan;
        if (! $user->isAdmin() && (! $plan || ! $plan->checkOpenAiItem('deep_research'))) {
            return response()->json([
                'error'  => __('Your current plan does not include Deep Research. Please upgrade your plan.'),
                'status' => 'plan_restricted',
            ], 403);
        }

        // Request limit check (based on plan period)
        if (! $user->isAdmin() && $plan) {
            $requestLimit = (int) ($plan->deep_research_request_limit ?? 5);

            if ($requestLimit === 0) {
                return response()->json([
                    'error'  => __('Deep Research is disabled on your current plan.'),
                    'status' => 'plan_restricted',
                ], 403);
            }

            if ($requestLimit > 0) {
                $periodStart = $this->getPlanPeriodStart($plan->frequency);
                $periodCount = DeepResearchSession::where('user_id', $user->id)
                    ->where('created_at', '>=', $periodStart)
                    ->count();

                if ($periodCount >= $requestLimit) {
                    return response()->json([
                        'error'  => __('You have reached your deep research request limit of :limit for this period.', ['limit' => $requestLimit]),
                        'status' => 'request_limit',
                    ], 429);
                }
            }
        }

        // Determine engine and model
        $engine = $this->service->getDefaultEngine();
        $model = $this->service->getDefaultModel($engine);

        // Create user message record (input only — no output, so it renders as a user bubble only)
        $userMessage = UserOpenaiChatMessage::create([
            'user_id'             => $user->id,
            'user_openai_chat_id' => $chat->id,
            'input'               => $request->prompt,
            'response'            => null,
            'output'              => null,
            'hash'                => Str::random(256),
            'credits'             => 0,
            'words'               => 0,
        ]);

        // Create AI placeholder message
        $aiMessage = UserOpenaiChatMessage::create([
            'user_id'             => $user->id,
            'user_openai_chat_id' => $chat->id,
            'input'               => null,
            'response'            => null,
            'output'              => __('Researching...'),
            'hash'                => Str::random(256),
            'credits'             => 0,
            'words'               => 0,
            'is_chatbot'          => 1,
        ]);

        // Create session
        $session = DeepResearchSession::create([
            'user_id'     => $user->id,
            'message_id'  => $aiMessage->id,
            'chat_id'     => $chat->id,
            'query'       => $request->prompt,
            'status'      => 'researching',
            'engine_used' => $engine,
            'model_used'  => $model,
        ]);

        try {
            $providerDriver = $this->service->getDriver($engine);

            if ($engine === 'openai') {
                $responseId = $providerDriver->startResearch($request->prompt, $model);
            } else {
                $responseId = $providerDriver->startResearch($request->prompt);
            }

            $session->update(['provider_response_id' => $responseId]);
        } catch (Throwable $e) {
            logger()?->error('Deep research failed to start', [
                'session_id' => $session->id,
                'engine'     => $engine,
                'model'      => $model,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);

            [$failureCode, $categoryMessage] = self::classifyProviderError($e->getMessage());
            $userMessage = $categoryMessage ?? __('Deep research failed to start. Please try again.');
            if (auth()->user()?->isAdmin()) {
                $userMessage = __('Failed to start deep research: ') . $e->getMessage();
            }

            $session->update(['status' => 'failed']);

            $aiMessage->update([
                'output'   => $userMessage,
                'response' => $userMessage,
            ]);

            return response()->json([
                'error'  => $userMessage,
                'status' => 'failed',
                'code'   => $failureCode,
            ], 500);
        }

        return response()->json([
            'session_id'  => $session->id,
            'message_id'  => $aiMessage->id,
            'status'      => 'researching',
            'auto_canvas' => $this->service->isAutoCanvasEnabled(),
        ]);
    }

    public function poll(int $sessionId): JsonResponse
    {
        $session = DeepResearchSession::where('id', $sessionId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        // If already completed or failed, return cached result
        if (in_array($session->status, ['completed', 'failed', 'cancelled'])) {
            return response()->json([
                'status'         => $session->status,
                'error'          => $session->status === 'failed' ? $session->message?->output : null,
                'report'         => $session->report_output,
                'sources'        => $session->sources,
                'sources_count'  => $session->sources_count,
                'searches_count' => $session->searches_count,
                'duration'       => $session->duration_seconds,
                'thinking_steps' => $session->thinking_steps,
            ]);
        }

        if (! $session->provider_response_id) {
            return $this->markFailed($session, 'The provider did not return a research session ID.');
        }

        try {
            $providerDriver = $this->service->getDriver($session->engine_used);
            $result = $providerDriver->checkStatus($session->provider_response_id);

            $providerStatus = $result['status'] ?? 'in_progress';

            // Save thinking steps from the driver response
            $thinkingSteps = $result['thinking_steps'] ?? [];

            if (! empty($thinkingSteps)) {
                $session->update(['thinking_steps' => $thinkingSteps]);
            }

            if ($providerStatus === 'completed') {
                return $this->handleCompletion($session, $result, $providerDriver);
            }

            if ($providerStatus === 'failed') {
                $providerError = $result['error'] ?? 'Unknown provider error';

                logger()?->error('Deep research failed', [
                    'session_id'  => $session->id,
                    'engine'      => $session->engine_used,
                    'model'       => $session->model_used,
                    'response_id' => $session->provider_response_id,
                    'error'       => $providerError,
                ]);

                return $this->markFailed($session, $providerError);
            }

            // Still in progress
            return response()->json([
                'status'         => 'researching',
                'thinking_steps' => $thinkingSteps ?: ($session->thinking_steps ?? []),
            ]);
        } catch (Throwable $e) {
            logger()?->error('Deep research poll exception', [
                'session_id'  => $session->id,
                'engine'      => $session->engine_used,
                'model'       => $session->model_used,
                'response_id' => $session->provider_response_id,
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);

            return $this->markFailed($session, $e->getMessage());
        }
    }

    public function cancel(int $sessionId): JsonResponse
    {
        $session = DeepResearchSession::where('id', $sessionId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        if ($session->status !== 'researching') {
            return response()->json(['status' => $session->status]);
        }

        try {
            if ($session->provider_response_id && $session->engine_used === 'openai') {
                $providerDriver = $this->service->getDriver($session->engine_used);
                $providerDriver->cancel($session->provider_response_id);
            }
        } catch (Throwable) {
            // Best effort cancellation
        }

        $session->update(['status' => 'cancelled']);

        $session->message?->update([
            'output'   => __('Deep research was cancelled.'),
            'response' => __('Deep research was cancelled.'),
        ]);

        return response()->json(['status' => 'cancelled']);
    }

    public function getSession(int $id): JsonResponse
    {
        $session = DeepResearchSession::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        return response()->json([
            'id'             => $session->id,
            'status'         => $session->status,
            'query'          => $session->query,
            'report'         => $session->report_output,
            'sources'        => $session->sources,
            'sources_count'  => $session->sources_count,
            'searches_count' => $session->searches_count,
            'duration'       => $session->duration_seconds,
            'thinking_steps' => $session->thinking_steps,
            'credits_used'   => $session->credits_used,
            'created_at'     => $session->created_at,
        ]);
    }

    public function chatSessions(int $chatId): JsonResponse
    {
        $sessions = DeepResearchSession::where('chat_id', $chatId)
            ->where('user_id', auth()->id())
            ->where('status', 'completed')
            ->get(['id', 'message_id', 'thinking_steps', 'sources', 'duration_seconds']);

        return response()->json(
            $sessions->map(fn ($s) => [
                'message_id'     => $s->message_id,
                'thinking_steps' => $s->thinking_steps ?? [],
                'sources'        => $s->sources ?? [],
                'duration'       => $s->duration_seconds,
            ])
        );
    }

    private function handleCompletion(DeepResearchSession $session, array $result, $providerDriver): JsonResponse
    {
        $outputText = is_string($result['output_text'] ?? null) ? $result['output_text'] : '';
        $output = $result['output'] ?? $result['outputs'] ?? [];

        $sources = $providerDriver->extractSources($output);
        $searchesCount = $providerDriver->countSearches($output);
        $thinkingSteps = $result['thinking_steps'] ?? [];
        $duration = (int) now()->diffInSeconds($session->created_at);
        $wordCount = countWords($outputText);

        return DB::transaction(function () use ($session, $outputText, $output, $sources, $searchesCount, $thinkingSteps, $duration, $wordCount): JsonResponse {
            $session = DeepResearchSession::query()
                ->lockForUpdate()
                ->findOrFail($session->getKey());

            if ($session->status === 'completed') {
                return $this->completedResponse($session);
            }

            if ($session->status !== 'researching') {
                return response()->json([
                    'status' => $session->status,
                    'error'  => $session->status === 'failed' ? $session->message?->output : null,
                ]);
            }

            if (trim($outputText) === '') {
                return $this->markFailed($session, 'The provider completed without returning a research report.');
            }

            $creditsUsed = 0;
            $entityEnum = $session->engine_used === 'gemini'
                ? EntityEnum::GEMINI_DEEP_RESEARCH
                : EntityEnum::fromSlug($session->model_used);
            $driver = Entity::driver($entityEnum)->forUser($session->user_id);
            $driver->input($outputText)->calculateCredit()->decreaseCredit();
            Usage::getSingle()->updateWordCounts((int) max(1, round($driver->calculate())));
            $creditsUsed = (int) ceil(max(1, $driver->getCalculatedInputCredit()));

            $session->update([
                'status'           => 'completed',
                'sources'          => $sources,
                'sources_count'    => count($sources),
                'searches_count'   => $searchesCount,
                'duration_seconds' => $duration,
                'credits_used'     => $creditsUsed,
                'report_output'    => $outputText,
                'thinking_steps'   => $thinkingSteps,
                'raw_response'     => json_encode($output),
            ]);

            $richOutput = $this->buildRichOutput($outputText, $thinkingSteps, $sources, $duration);
            $session->message?->update([
                'response' => $richOutput,
                'output'   => $richOutput,
                'credits'  => $creditsUsed ?: $wordCount,
                'words'    => $wordCount,
            ]);

            if ($session->chat) {
                $session->chat->increment('total_credits', $wordCount);
            }

            return $this->completedResponse($session);
        });
    }

    private function completedResponse(DeepResearchSession $session): JsonResponse
    {
        return response()->json([
            'status'         => 'completed',
            'report'         => $session->report_output,
            'sources'        => $session->sources ?? [],
            'sources_count'  => $session->sources_count,
            'searches_count' => $session->searches_count,
            'duration'       => $session->duration_seconds,
            'thinking_steps' => $session->thinking_steps ?? [],
            'auto_canvas'    => $this->service->isAutoCanvasEnabled(),
        ]);
    }

    /**
     * Map a raw provider error to a stable category and user-safe message.
     * Provider access/quota problems are reported as such, never as an internal Garad credit error.
     *
     * @return array{0: string, 1: ?string}
     */
    public static function classifyProviderError(string $error): array
    {
        $e = strtolower($error);

        if (preg_match('/\b(403|401)\b|does not have access|not have access|permission|not authorized|unauthorized|invalid api key|incorrect api key|model_not_found/', $e)) {
            return ['provider_access_denied', __('The AI provider denied access to this research model for the configured account. Provider access must be enabled by the platform administrator.')];
        }

        if (preg_match('/\b429\b|quota|rate limit|resource_exhausted|insufficient_quota|billing|exceeded your current/', $e)) {
            return ['provider_quota', __('The AI provider quota or billing limit for deep research has been reached. Please try again later.')];
        }

        return ['provider_error', null];
    }

    private function markFailed(DeepResearchSession $session, string $providerError): JsonResponse
    {
        [$code, $categoryMessage] = self::classifyProviderError($providerError);

        $userMessage = $categoryMessage ?? __('Deep research failed. Please try again.');

        if (auth()->user()?->isAdmin()) {
            $userMessage = __('Deep research failed: ') . $providerError;
        }

        DB::transaction(function () use ($session, $userMessage): void {
            $session->update(['status' => 'failed']);
            $session->message?->update([
                'output'   => $userMessage,
                'response' => $userMessage,
            ]);
        });

        return response()->json([
            'status' => 'failed',
            'error'  => $userMessage,
            'code'   => $code,
        ]);
    }

    private function getPlanPeriodStart(?string $frequency): Carbon
    {
        $freq = FrequencyEnum::tryFrom($frequency ?? '');

        return match ($freq) {
            FrequencyEnum::YEARLY, FrequencyEnum::LIFETIME_YEARLY => now()->startOfYear(),
            FrequencyEnum::LIFETIME                               => now()->subYears(100),
            default                                               => now()->startOfMonth(),
        };
    }

    /**
     * Build the stored output: report text only.
     * Thinking steps and sources are rendered by JS (fetched from the session)
     * and placed outside .chat-content so they don't appear in Canvas.
     */
    private function buildRichOutput(string $reportText, array $thinkingSteps, array $sources, int $durationSeconds): string
    {
        // Strip Gemini's embedded "Sources:" section from the end of the report
        // (we render sources separately via JS from the session data)
        $reportText = preg_replace(
            '/\n*\*{0,2}Sources:?\*{0,2}\s*\n+(\d+\.\s+\[.*?\]\(.*?\)\s*\n?)+\s*$/si',
            '',
            $reportText
        );

        return trim($reportText);
    }
}
