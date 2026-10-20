<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Services;

use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity;
use App\Extensions\AiPresentation\System\Models\AiPresentation;
use App\Extensions\AiPresentation\System\Services\GammaCreditPredictor;
use App\Extensions\AiPresentation\System\Services\GammaService;
use App\Extensions\AiVideoPro\System\Http\Controllers\AiVideoProController;
use App\Helpers\Classes\MarketplaceHelper;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Safe orchestration layer for Chat Pro.
 *
 * This class deliberately delegates to Garad's existing working services/controllers.
 * It does not replace the Chat Pro UI, streaming layer, artifact viewer, routes, or
 * existing extension pages. Its job is to normalize requests and inject the selected
 * Brand Voice before calling the already-installed production capabilities.
 */
final class GaradUnifiedToolService
{
    public function __construct(
        private readonly GaradBrandContextService $brandContext,
    ) {
    }

    public function generateImage(array $arguments): string
    {
        $prompt = $this->requiredPrompt($arguments, 'Image prompt is required.');
        $arguments['prompt'] = $this->brandContext->augmentPrompt($prompt, 'image');

        return app(GaradImageToolService::class)->generate($arguments);
    }

    public function editImage(array $arguments): string
    {
        $prompt = $this->requiredPrompt($arguments, 'Image edit instruction is required.');
        $arguments['prompt'] = $this->brandContext->augmentPrompt($prompt, 'image');

        return app(GaradImageToolService::class)->edit($arguments);
    }

    public function createXlsx(array $arguments): string
    {
        $arguments = $this->normalizeSpreadsheetArguments($arguments);

        // GaradArtifactService already applies selected Brand Voice colors and
        // preserves the existing artifact card/view/download experience.
        return app(GaradArtifactService::class)->createXlsx($arguments);
    }

    public function createPresentation(array $arguments): string
    {
        $this->ensureSignedIn('Please sign in before creating presentations.');

        if (! MarketplaceHelper::isRegistered('ai-presentation')) {
            throw new RuntimeException('AI Presentation is not available on this installation.');
        }

        $topic = trim((string) ($arguments['topic'] ?? $arguments['description'] ?? ''));
        if ($topic === '') {
            throw new RuntimeException('Presentation topic is required.');
        }

        $topic = $this->brandContext->augmentPrompt($topic, 'presentation');

        $slideCount = max(1, min(10, (int) ($arguments['slide_count'] ?? 10)));
        $language = trim((string) ($arguments['language'] ?? app()->getLocale() ?? 'en')) ?: 'en';
        $language = mb_substr($language, 0, 20);

        $exportAs = strtolower(trim((string) ($arguments['export_as'] ?? 'pptx')));
        if (! in_array($exportAs, ['pptx', 'pdf'], true)) {
            $exportAs = 'pptx';
        }

        $textLength = ucfirst(strtolower(trim((string) ($arguments['text_length'] ?? 'Medium'))));
        if (! in_array($textLength, ['Brief', 'Medium', 'Detailed', 'Extensive'], true)) {
            $textLength = 'Medium';
        }

        $imageSource = $this->normalizeGammaImageSource((string) ($arguments['image_source'] ?? 'webFreeToUseCommercially'));
        $tone = trim((string) ($arguments['tone'] ?? ''));
        $audience = trim((string) ($arguments['audience'] ?? ''));

        $validated = [
            'description'        => mb_substr($topic, 0, 390000),
            'language'           => $language,
            'theme'              => 'Auto',
            'theme_id'           => 'auto',
            'presentation_count' => (string) $slideCount,
            'text_length'        => $textLength,
            'exportAs'           => $exportAs,
            // Gamma v1 requires textMode; explicit format/cardSplit keeps the
            // existing GammaService payload valid and deterministic.
            'textMode'           => 'generate',
            'format'             => 'presentation',
            'cardSplit'          => 'auto',
            'imageOptions'       => [
                'source' => $imageSource,
            ],
            'cardOptions'        => [
                'dimensions' => '16x9',
            ],
            'textOptions'        => [],
        ];

        if ($tone !== '') {
            $validated['textOptions']['tone'] = mb_substr($tone, 0, 500);
        }

        if ($audience !== '') {
            $validated['textOptions']['audience'] = mb_substr($audience, 0, 500);
        }

        $brandSummary = $this->brandContext->summaryForPresentation();
        if ($brandSummary !== '' && $tone === '') {
            $validated['textOptions']['tone'] = mb_substr('professional, polished, on-brand; ' . $brandSummary, 0, 500);
        }

        $predictor = new GammaCreditPredictor;
        $prediction = $predictor->predictCredits($validated);
        $requiredCredits = (float) ($prediction['max'] ?? 0);

        $driver = Entity::driver(EntityEnum::GAMMA_AI)
            ->inputPresentation($requiredCredits)
            ->calculateCredit();

        if (! $driver->hasCreditBalanceForInput()) {
            throw new RuntimeException(sprintf(
                'Not enough presentation credits. At least %s credits are required for this request.',
                rtrim(rtrim(number_format($requiredCredits, 2, '.', ''), '0'), '.'),
            ));
        }

        $response = app(GammaService::class)->generatePresentation($validated);
        if (($response->type ?? 'error') !== 'success') {
            $detail = data_get($response, 'data.message')
                ?: data_get($response, 'data.error.message')
                ?: ($response->msg ?? 'Presentation generation failed.');

            throw new RuntimeException((string) $detail);
        }

        $generationId = trim((string) data_get($response, 'data.generationId', ''));
        if ($generationId === '') {
            throw new RuntimeException('Presentation generation did not return a generation ID.');
        }

        return "Presentation generation started successfully.\n\nGeneration ID: **{$generationId}**\n\nI can check the presentation status with this ID when you ask.";
    }

    public function presentationStatus(array $arguments): string
    {
        $this->ensureSignedIn('Please sign in to check presentation status.');

        $generationId = trim((string) ($arguments['generation_id'] ?? ''));
        if ($generationId === '') {
            throw new RuntimeException('generation_id is required.');
        }

        $owned = AiPresentation::query()
            ->where('generation_id', $generationId)
            ->where('user_id', Auth::id())
            ->exists();

        if (! $owned) {
            throw new RuntimeException('Presentation not found.');
        }

        $presentation = app(GammaService::class)->updatePresentationStatus($generationId);
        if (! $presentation) {
            throw new RuntimeException('Presentation not found.');
        }

        $status = strtolower((string) $presentation->status);

        if ($status === 'completed') {
            $url = (string) ($presentation->pptx_url ?: $presentation->pdf_url ?: $presentation->gamma_url ?: '');
            if ($url !== '') {
                $url = $this->absoluteUrlIfLocal($url);

                return "Presentation completed successfully.\n\n[Open / Download presentation]({$url})";
            }

            return 'Presentation completed successfully.';
        }

        if ($status === 'failed') {
            throw new RuntimeException((string) ($presentation->error_message ?: 'Presentation generation failed.'));
        }

        return "Presentation is still processing.\n\nGeneration ID: **{$generationId}**";
    }

    public function generateVideo(array $arguments): string
    {
        $this->ensureSignedIn('Please sign in to use AI Video Pro from Chat Pro.');

        if (! MarketplaceHelper::isRegistered('ai-video-pro')) {
            throw new RuntimeException('AI Video Pro is not available on this installation.');
        }

        $prompt = $this->requiredPrompt($arguments, 'Video prompt is required.');
        $prompt = $this->brandContext->augmentPrompt($prompt, 'video');

        $duration = (int) ($arguments['duration'] ?? 8);
        if (! in_array($duration, [4, 6, 8], true)) {
            $duration = 8;
        }

        $resolution = strtolower(trim((string) ($arguments['resolution'] ?? '720p')));
        if (! in_array($resolution, ['720p', '1080p'], true)) {
            $resolution = '720p';
        }

        $aspectRatio = trim((string) ($arguments['aspect_ratio'] ?? '16:9'));
        if (! in_array($aspectRatio, ['16:9', '9:16'], true)) {
            // Veo 3.1 text-to-video accepts landscape or portrait. Keep a safe
            // production default instead of passing unsupported 1:1 values.
            $aspectRatio = '16:9';
        }

        $quality = strtolower(trim((string) ($arguments['quality'] ?? 'fast')));
        $feature = $quality === 'quality'
            ? EntityEnum::VEO_3_1_TEXT_TO_VIDEO->value
            : EntityEnum::VEO_3_1_TEXT_TO_VIDEO_FAST->value;

        $result = app(AiVideoProController::class)->generateFromChatPro([
            'action'          => 'veo',
            'sub_model_value' => 'Veo 3.1',
            'feature'         => $feature,
            'prompt'          => mb_substr($prompt, 0, 5000),
            'duration'        => $duration . 's',
            'resolution'      => $resolution,
            'aspect_ratio'    => $aspectRatio,
            'generate_audio'  => (bool) ($arguments['generate_audio'] ?? true),
            'enhance_prompt'  => true,
            'auto_fix'        => true,
        ]);

        if (! ($result['success'] ?? false)) {
            throw new RuntimeException((string) ($result['message'] ?? 'Video generation failed.'));
        }

        $videoId = (int) ($result['video_id'] ?? 0);
        if ($videoId <= 0) {
            throw new RuntimeException('Video generation did not return a video ID.');
        }

        return "Video generation started successfully.\n\nVideo ID: **{$videoId}**\n\nI can check the video status with this ID when you ask.";
    }

    public function videoStatus(array $arguments): string
    {
        $this->ensureSignedIn('Please sign in to check video status.');

        $videoId = (int) ($arguments['video_id'] ?? 0);
        if ($videoId <= 0) {
            throw new RuntimeException('video_id is required.');
        }

        $result = app(AiVideoProController::class)->videoStatusFromChatPro($videoId);

        if (! ($result['success'] ?? false) && ($result['status'] ?? null) !== 'error') {
            throw new RuntimeException((string) ($result['message'] ?? 'Unable to check video status.'));
        }

        $status = strtolower((string) ($result['status'] ?? 'processing'));

        if ($status === 'complete' && ! empty($result['video_url'])) {
            $url = $this->absoluteUrlIfLocal((string) $result['video_url']);

            return "Video completed successfully.\n\n[Open / Download video]({$url})";
        }

        if ($status === 'error') {
            throw new RuntimeException((string) ($result['error_message'] ?? 'Video generation failed.'));
        }

        return "Video #{$videoId} is still processing.";
    }

    private function normalizeSpreadsheetArguments(array $arguments): array
    {
        $rows = array_values((array) ($arguments['rows'] ?? []));
        $headers = array_values(array_map('strval', (array) ($arguments['headers'] ?? [])));

        if ($headers === [] && isset($rows[0]) && is_array($rows[0]) && ! array_is_list($rows[0])) {
            $headers = array_values(array_map('strval', array_keys($rows[0])));
        }

        if ($headers !== []) {
            foreach ($rows as $index => $row) {
                if (is_array($row) && ! array_is_list($row)) {
                    $normalized = [];
                    foreach ($headers as $header) {
                        $normalized[] = $row[$header] ?? '';
                    }
                    $rows[$index] = $normalized;
                }
            }
        }

        if ($headers === [] && $rows === []) {
            throw new RuntimeException('Excel creation requires headers or rows.');
        }

        $arguments['headers'] = $headers;
        $arguments['rows'] = $rows;

        return $arguments;
    }

    private function normalizeGammaImageSource(string $source): string
    {
        $source = trim($source);

        $aliases = [
            'unsplash' => 'webFreeToUseCommercially',
            'web' => 'webFreeToUseCommercially',
        ];
        $source = $aliases[$source] ?? $source;

        $allowed = [
            'aiGenerated',
            'pictographic',
            'pexels',
            'giphy',
            'webAllImages',
            'webFreeToUse',
            'webFreeToUseCommercially',
            'themeAccent',
            'placeholder',
            'noImages',
        ];

        return in_array($source, $allowed, true)
            ? $source
            : 'webFreeToUseCommercially';
    }

    private function requiredPrompt(array $arguments, string $message): string
    {
        $prompt = trim((string) ($arguments['prompt'] ?? ''));
        if ($prompt === '') {
            throw new RuntimeException($message);
        }

        return $prompt;
    }

    private function ensureSignedIn(string $message): void
    {
        if (! Auth::check()) {
            throw new RuntimeException($message);
        }
    }

    private function absoluteUrlIfLocal(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return $url;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return url('/' . ltrim($url, '/'));
    }
}
