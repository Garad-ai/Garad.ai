<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Services;

use App\Extensions\AiVideoPro\System\Http\Controllers\AiVideoProController;
use App\Extensions\AiVideoPro\System\Models\UserFall;
use App\Helpers\Classes\MarketplaceHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class GaradVideoToolServiceV5
{
    private const START_MARKER = 'GARAD_VIDEO_JOB';
    private const STALE_AFTER_MINUTES = 60;

    public function create(array $arguments): string
    {
        $this->ensureAuthenticatedAndAvailable();

        $prompt = trim((string) ($arguments['prompt'] ?? ''));
        if ($prompt === '') {
            throw new RuntimeException('Video prompt is required.');
        }

        // Keep one generation engine: Chat Pro now reuses AI Video Pro's configured
        // model selector, provider handling, billing, database record and status flow.
        // This prevents the former duplicate Sora/Veo logic from creating jobs that
        // the main Video Pro status checker did not understand.
        try {
            $message = app(GaradVideoToolService::class)->create($arguments);
        } catch (Throwable $e) {
            Log::error('Garad Chat Pro video generation could not start through AI Video Pro', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('Video generation could not be started. Please try again.');
        }

        if (! preg_match('/Video\s*ID:\s*\*\*(\d+)\*\*/i', $message, $match)
            && ! preg_match('/Video\s*ID:\s*`?(\d+)`?/i', $message, $match)) {
            Log::error('Garad Chat Pro AI Video Pro start response did not contain a video ID', [
                'user_id' => Auth::id(),
                'message' => $message,
            ]);

            throw new RuntimeException('Video generation could not be started. Please try again.');
        }

        return $this->startedMessage((int) $match[1]);
    }

    public function status(array $arguments): string
    {
        if (! Auth::check()) {
            throw new RuntimeException('Please sign in to check video status.');
        }

        $videoId = (int) ($arguments['video_id'] ?? 0);
        if ($videoId <= 0) {
            throw new RuntimeException('A valid video job is required.');
        }

        $entry = UserFall::query()
            ->whereKey($videoId)
            ->where('user_id', Auth::id())
            ->first();

        if (! $entry) {
            throw new RuntimeException('Video job not found for this account.');
        }

        $storedStatus = strtolower(trim((string) $entry->status));
        if (in_array($storedStatus, ['complete', 'completed', 'success', 'done'], true) && $entry->video_url) {
            return $this->completedMessage((string) $entry->video_url);
        }

        if (in_array($storedStatus, ['error', 'failed', 'cancelled', 'canceled', 'expired'], true)) {
            throw new RuntimeException('Video generation could not be completed. Please try again.');
        }

        // Never leave a broken provider request spinning for an entire day.
        // We still check the provider once below; only jobs that remain unfinished
        // for an hour are converted to a clear terminal state.
        $result = app(AiVideoProController::class)->videoStatusFromChatPro($videoId);

        $status = strtolower(trim((string) ($result['status'] ?? 'processing')));
        if (in_array($status, ['complete', 'completed', 'success', 'done'], true) && ! empty($result['video_url'])) {
            return $this->completedMessage((string) $result['video_url']);
        }

        if ($status === 'error' || ! ($result['success'] ?? true)) {
            throw new RuntimeException('Video generation could not be completed. Please try again.');
        }

        $entry->refresh();
        if ($entry->created_at && $entry->created_at->lt(now()->subMinutes(self::STALE_AFTER_MINUTES))) {
            $entry->update([
                'status' => 'error',
                'error_message' => 'Video generation timed out before the provider returned a usable result.',
            ]);

            throw new RuntimeException('Video generation took too long and was stopped. Please try again.');
        }

        return 'Video is still processing.';
    }

    private function ensureAuthenticatedAndAvailable(): void
    {
        if (! Auth::check()) {
            throw new RuntimeException('Please sign in before generating a video.');
        }

        if (! MarketplaceHelper::isRegistered('ai-video-pro')) {
            throw new RuntimeException('AI Video Pro is not installed or enabled.');
        }
    }

    private function startedMessage(int $videoId): string
    {
        return "[[" . self::START_MARKER . ":{$videoId}]]";
    }

    private function completedMessage(string $url): string
    {
        $safeUrl = $this->safeVideoUrl($url);
        if ($safeUrl === null) {
            Log::warning('Garad Chat Pro rejected an unsafe video result URL', [
                'video_url' => $url,
                'user_id' => Auth::id(),
            ]);

            throw new RuntimeException('Video completed, but the result could not be opened safely.');
        }

        return "Video completed.\n\n[Open / Download video]({$safeUrl})";
    }

    private function safeVideoUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, '/')) {
            return $url;
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        return in_array($scheme, ['http', 'https'], true) ? $url : null;
    }
}
