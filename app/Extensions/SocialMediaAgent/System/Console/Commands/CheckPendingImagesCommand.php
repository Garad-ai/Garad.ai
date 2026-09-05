<?php

namespace App\Extensions\SocialMediaAgent\System\Console\Commands;

use App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgentPost;
use App\Extensions\SocialMediaAgent\System\Services\ImageGenerationService;
use App\Helpers\Classes\Helper;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class CheckPendingImagesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'social-media-agent:check-pending-images
                            {--post= : Check a specific post ID}
                            {--timeout=10 : Maximum age in minutes for pending images}';

    /**
     * The console command description.
     */
    protected $description = 'Check status of pending image generations and download completed images';

    protected ImageGenerationService $imageService;

    public function __construct(ImageGenerationService $imageService)
    {
        parent::__construct();
        $this->imageService = $imageService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (Helper::appIsDemo()) {
            return 1;
        }

        $this->info('🖼️  Checking pending image generations...');

        $postId = $this->option('post');
        $timeoutMinutes = (int) $this->option('timeout');

        Log::info('social-media-agent:check-pending-images started', [
            'post_option'     => $postId,
            'timeout_minutes' => $timeoutMinutes,
        ]);

        // Get posts with pending images
        $posts = $this->getPendingImagePosts($postId, $timeoutMinutes);

        if ($posts->isEmpty()) {
            $this->info('No pending images to check.');
            Log::info('social-media-agent:check-pending-images finished', [
                'status' => 'no_pending_posts',
            ]);

            return self::SUCCESS;
        }

        $this->info("Found {$posts->count()} post(s) with pending images.");

        $completed = 0;
        $failed = 0;
        $stillPending = 0;

        foreach ($posts as $post) {
            $this->line('');
            $this->info("Checking Post ID: {$post->id}");
            $this->line("  Request ID: {$post->image_request_id}");
            $this->line('  Image Model: ' . ($post->image_model ?? 'default'));

            try {
                $result = $this->checkAndUpdatePost($post);

                if ($result === 'completed') {
                    $completed++;
                    $this->info('  ✓ Image downloaded and stored');
                } elseif ($result === 'failed') {
                    $failed++;
                    $this->warn('  ✗ Image generation failed');
                } else {
                    $stillPending++;
                    $this->line('  • Still processing...');
                }
            } catch (Exception $e) {
                $failed++;
                $this->error("  ✗ Error: {$e->getMessage()}");
                Log::error("CheckPendingImages error for post {$post->id}", [
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

        Log::info('social-media-agent:check-pending-images finished', [
            'completed'     => $completed,
            'still_pending' => $stillPending,
            'failed'        => $failed,
        ]);

        return self::SUCCESS;
    }

    /**
     * Get posts with pending image generations
     */
    protected function getPendingImagePosts(?string $postId, int $timeoutMinutes): null|Collection|array|SocialMediaAgentPost
    {
        $query = SocialMediaAgentPost::query()
            ->whereNotNull('image_request_id')
            ->whereIn('image_status', ['pending', 'generating']);

        if ($postId) {
            $query->where('id', $postId);
        } else {
            // Only check images older than 1 minute but younger than timeout
            $query->where('created_at', '<=', now()->subMinute())
                ->where('created_at', '>=', now()->subMinutes($timeoutMinutes));
        }

        return $query->get();
    }

    /**
     * Check status and update post
     *
     * @return string 'completed', 'failed', or 'pending'
     */
    protected function checkAndUpdatePost(SocialMediaAgentPost $post): string
    {
        // Use the model that was used to generate the image
        if ($post->image_model) {
            $this->imageService->setModel($post->image_model);
        }

        $result = $this->imageService->checkStatus($post->image_request_id);

        if (! $result['success']) {
            // API call failed or error occurred
            $post->update([
                'image_status' => 'failed',
            ]);

            return 'failed';
        }

        $status = $result['status'];

        if ($status === 'completed' && isset($result['image_url'])) {
            // Image is ready, update post
            $mediaUrls = $post->media_urls ?? [];
            $mediaUrls[] = $result['image_url'];

            $post->update([
                'media_urls'   => $mediaUrls,
                'image_status' => 'completed',
            ]);

            $this->line("    Image URL: {$result['image_url']}");

            return 'completed';
        }

        if ($status === 'failed') {
            // Image generation failed
            $post->update([
                'image_status' => 'failed',
            ]);

            return 'failed';
        }

        // Still pending/processing
        $post->update([
            'image_status' => 'generating',
        ]);

        return 'pending';
    }
}
