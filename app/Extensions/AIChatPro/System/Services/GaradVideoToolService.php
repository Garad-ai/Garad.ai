<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Services;

use App\Domains\Entity\Enums\EntityEnum;
use App\Extensions\AiVideoPro\System\Http\Controllers\AiVideoProController;
use App\Extensions\AiVideoPro\System\Services\ModelConfigurationService;
use App\Helpers\Classes\MarketplaceHelper;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class GaradVideoToolService
{
    public function create(array $arguments): string
    {
        $this->ensureAvailable();

        $prompt = trim((string) ($arguments['prompt'] ?? ''));
        if ($prompt === '') {
            throw new RuntimeException('Video prompt is required.');
        }

        $prompt = app(GaradBrandContextService::class)->augmentPrompt($prompt, 'video');

        $duration = (int) ($arguments['duration'] ?? 8);
        $duration = in_array($duration, [4, 6, 8], true) ? $duration : 8;

        $resolution = strtolower(trim((string) ($arguments['resolution'] ?? '720p')));
        $resolution = in_array($resolution, ['720p', '1080p'], true) ? $resolution : '720p';

        $aspectRatio = trim((string) ($arguments['aspect_ratio'] ?? '16:9'));
        $aspectRatio = in_array($aspectRatio, ['16:9', '9:16'], true) ? $aspectRatio : '16:9';

        $quality = strtolower(trim((string) ($arguments['quality'] ?? 'fast')));
        $quality = $quality === 'quality' ? 'quality' : 'fast';

        $target = $this->resolveActiveTextToVideoTarget($quality);

        $payload = [
            'action'          => $target['action'],
            'sub_model_value' => $target['sub_model_value'],
            'feature'         => $target['feature'],
            'prompt'          => mb_substr($prompt, 0, 5000),
            'duration'        => $duration . 's',
            'resolution'      => $resolution,
            'aspect_ratio'    => $aspectRatio,
            'generate_audio'  => (bool) ($arguments['generate_audio'] ?? true),
            'auto_fix'        => true,
        ];

        $negativePrompt = trim((string) ($arguments['negative_prompt'] ?? ''));
        if ($negativePrompt !== '') {
            $payload['negative_prompt'] = mb_substr($negativePrompt, 0, 2000);
        }

        $result = app(AiVideoProController::class)->generateFromChatPro($payload);

        if (! ($result['success'] ?? false)) {
            $message = trim((string) ($result['message'] ?? ''));
            throw new RuntimeException($message !== '' ? $message : 'Video generation failed.');
        }

        $videoId = (int) ($result['video_id'] ?? 0);
        if ($videoId <= 0) {
            throw new RuntimeException('Video generation did not return a video ID.');
        }

        return "Video generation started successfully.\n\nVideo ID: **{$videoId}**\n\nAsk me to check the video status when you are ready.";
    }

    public function status(array $arguments): string
    {
        $this->ensureAvailable();

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
            $url = (string) $result['video_url'];
            return "Video completed successfully.\n\n[Open / Download video]({$url})";
        }

        if ($status === 'error') {
            throw new RuntimeException((string) ($result['error_message'] ?? 'Video generation failed.'));
        }

        return "Video #{$videoId} is still processing.";
    }

    /** @return array{action:string,sub_model_value:string,feature:string} */
    private function resolveActiveTextToVideoTarget(string $quality): array
    {
        $config = ModelConfigurationService::getConfig();
        if (! is_array($config) || $config === []) {
            throw new RuntimeException('AI Video Pro model configuration is unavailable.');
        }

        $preferred = $quality === 'quality'
            ? [EntityEnum::VEO_3_1_TEXT_TO_VIDEO->value, EntityEnum::VEO_3_1_TEXT_TO_VIDEO_FAST->value]
            : [EntityEnum::VEO_3_1_TEXT_TO_VIDEO_FAST->value, EntityEnum::VEO_3_1_TEXT_TO_VIDEO->value];

        $candidates = [];
        foreach ($config as $action => $actionConfig) {
            if (! is_array($actionConfig) || (($actionConfig['isActive'] ?? true) === false)) {
                continue;
            }

            foreach ((array) ($actionConfig['subModels'] ?? []) as $subModel => $subModelConfig) {
                if (! is_array($subModelConfig) || (($subModelConfig['isActive'] ?? true) === false)) {
                    continue;
                }

                $features = (array) ($subModelConfig['features'] ?? []);
                foreach ($preferred as $preferenceIndex => $feature) {
                    if (! array_key_exists($feature, $features)) {
                        continue;
                    }

                    $featureConfig = $features[$feature];
                    if (is_array($featureConfig) && (($featureConfig['isActive'] ?? true) === false)) {
                        continue;
                    }

                    $score = max(0, 50 - ($preferenceIndex * 10));
                    $subModelName = strtolower((string) $subModel);
                    if (str_contains($subModelName, 'veo 3.1') || str_contains($subModelName, 'veo3.1')) {
                        $score += 100;
                    }
                    if (strtolower((string) $action) === 'veo') {
                        $score += 25;
                    }

                    $candidates[] = [
                        'score' => $score,
                        'action' => (string) $action,
                        'sub_model_value' => (string) $subModel,
                        'feature' => (string) $feature,
                    ];
                }
            }
        }

        if ($candidates === []) {
            throw new RuntimeException('No active Veo 3.1 text-to-video model is enabled in AI Video Pro. Verify the AI Video Pro model configuration and FAL API key.');
        }

        usort($candidates, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        $selected = $candidates[0];

        return [
            'action' => $selected['action'],
            'sub_model_value' => $selected['sub_model_value'],
            'feature' => $selected['feature'],
        ];
    }

    private function ensureAvailable(): void
    {
        if (! Auth::check()) {
            throw new RuntimeException('Please sign in to use AI Video Pro from Chat Pro.');
        }

        if (! MarketplaceHelper::isRegistered('ai-video-pro')) {
            throw new RuntimeException('AI Video Pro is not available on this installation.');
        }
    }
}
