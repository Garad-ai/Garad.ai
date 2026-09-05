<?php

namespace App\Extensions\SocialMediaAgent\System\Console\Commands;

use App\Extensions\SocialMedia\System\Models\SocialMediaPost;
use App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgentPost;
use App\Extensions\SocialMediaAgent\System\Services\VideoGenerationService;
use App\Helpers\Classes\Helper;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class CheckPendingVideosCommand extends Command
{
    protected $signature = 'social-media-agent:check-pending-videos
                            {--post= : Check a specific post ID}
                            {--timeout=30 : Maximum age in minutes for pending videos}';

    protected $description = 'Check status of pending video generations and attach completed videos to posts';

    public function __construct(protected VideoGenerationService $videoService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (Helper::appIsDemo()) {
            return 1;
        }

        $this->info('🎬 Checking pending video generations...');

        $postId = $this->option('post');
        $timeoutMinutes = (int) $this->option('timeout');

        Log::info('social-media-agent:check-pending-videos started', [
            'post_option'     => $postId,
            'timeout_minutes' => $timeoutMinutes,
        ]);

        $posts = $this->getPendingVideoPosts($postId, $timeoutMinutes);

        if ($posts->isEmpty()) {
            $this->info('No pending videos to check.');
            Log::info('social-media-agent:check-pending-videos finished', [
                'status' => 'no_pending_posts',
            ]);

            return self::SUCCESS;
        }

        $this->info("Found {$posts->count()} post(s) with pending videos.");

        $completed = 0;
        $failed = 0;
        $stillPending = 0;

        foreach ($posts as $post) {
            $this->line('');
            $this->info("Checking Post ID: {$post->id}");
            $this->line("  Request ID: {$post->video_request_id}");

            try {
                $result = $this->checkAndUpdateVideo($post);

                if ($result === 'completed') {
                    $completed++;
                    $this->info('  ✓ Video downloaded and stored');
                } elseif ($result === 'failed') {
                    $failed++;
                    $this->warn('  ✗ Video generation failed');
                } else {
                    $stillPending++;
                    $this->line('  • Still processing...');
                }
            } catch (Exception $e) {
                $failed++;
                $this->error("  ✗ Error: {$e->getMessage()}");
                Log::error("CheckPendingVideos error for post {$post->id}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->line('');
        $this->info("✅ Completed: {$completed}");
        $this->warn("⏳ Still Pending: {$stillPending}");

        if ($failed > 0) {
            $this->error("❌ Failed: {$failed}");
        }

        Log::info('social-media-agent:check-pending-videos finished', [
            'completed'     => $completed,
            'still_pending' => $stillPending,
            'failed'        => $failed,
        ]);

        return self::SUCCESS;
    }

    protected function getPendingVideoPosts(?string $postId, int $timeoutMinutes): Collection
    {
        $query = SocialMediaAgentPost::query()
            ->whereNotNull('video_request_id')
            ->whereIn('video_status', ['pending', 'generating']);

        if ($postId) {
            $query->where('id', $postId);
        } else {
            $query->where('created_at', '<=', now()->subMinute())
                ->where('created_at', '>=', now()->subMinutes($timeoutMinutes));
        }

        return $query->get();
    }

    protected function checkAndUpdateVideo(SocialMediaAgentPost $post): string
    {
        $result = $this->videoService->checkStatus($post->video_request_id);

        if (! $result['success']) {
            $post->update(['video_status' => 'failed']);

            return 'failed';
        }

        $status = $result['status'] ?? 'pending';

        if ($status === 'completed' && isset($result['video_url'])) {
            $videoUrls = $post->video_urls ?? [];
            $videoUrls[] = $result['video_url'];

            $post->update([
                'video_urls'   => $videoUrls,
                'video_status' => 'completed',
            ]);

            $this->line("    Video URL: {$result['video_url']}");

            $this->syncSocialMediaVideo($post, $result['video_url']);

            return 'completed';
        }

        if ($status === 'failed') {
            $post->update(['video_status' => 'failed']);

            return 'failed';
        }

        $post->update(['video_status' => 'generating']);

        return 'pending';
    }

    protected function syncSocialMediaVideo(SocialMediaAgentPost $post, string $videoPath): void
    {
        if (empty($post->platform_post_id)) {
            return;
        }

        $socialMediaPost = SocialMediaPost::find($post->platform_post_id);

        if (! $socialMediaPost) {
            return;
        }

        $socialMediaPost->update([
            'video' => $videoPath,
        ]);
    }
}
