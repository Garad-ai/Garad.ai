<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Services;

use App\Domains\Entity\Enums\EntityEnum;
use App\Extensions\AiVideoPro\System\Http\Controllers\AiVideoProController;
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

        $duration = (int) ($arguments['duration'] ?? 8);
        $duration = in_array($duration, [4, 6, 8], true) ? $duration : 8;

        $resolution = (string) ($arguments['resolution'] ?? '720p');
        $resolution = in_array($resolution, ['720p', '1080p'], true) ? $resolution : '720p';

        $aspectRatio = (string) ($arguments['aspect_ratio'] ?? '16:9');
        $aspectRatio = in_array($aspectRatio, ['16:9', '9:16', '1:1'], true) ? $aspectRatio : '16:9';

        $quality = strtolower((string) ($arguments['quality'] ?? 'fast'));
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

        return sprintf(
            '🎬 Video generation started successfully. Video ID: <strong>%d</strong>. Ask me to check video status using this ID.',
            $videoId,
        );
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

        if (($result['status'] ?? null) === 'complete' && ! empty($result['video_url'])) {
            return sprintf(
                '✅ Video completed: <a href="%s" target="_blank" rel="noopener">Open / Download video</a>',
                e((string) $result['video_url']),
            );
        }

        if (($result['status'] ?? null) === 'error') {
            return '❌ Video generation failed: ' . e((string) ($result['error_message'] ?? 'Unknown error'));
        }

        return sprintf('⏳ Video #%d is still processing.', $videoId);
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
