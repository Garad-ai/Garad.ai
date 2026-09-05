<?php

namespace App\Extensions\SocialMediaAgent\System\Console\Commands;

use App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgentPost;
use App\Extensions\SocialMediaAgent\System\Services\ImageGenerationService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RegeneratePostImagesCommand extends Command
{
    protected $signature = 'social-media-agent:regenerate-images
                            {--agent= : Only process posts for a specific agent ID}
                            {--status= : Filter by post status (draft, scheduled, published)}
                            {--limit=0 : Maximum number of posts to process (0 = all)}
                            {--force : Regenerate all posts regardless of current image status}
                            {--delay=2 : Delay in seconds between API calls}';

    protected $description = 'Regenerate images for agent posts that have no images';

    protected ImageGenerationService $imageService;

    public function __construct(ImageGenerationService $imageService)
    {
        parent::__construct();
        $this->imageService = $imageService;
    }

    public function handle(): int
    {
        $agentId = $this->option('agent');
        $status = $this->option('status');
        $limit = (int) $this->option('limit');
        $force = $this->option('force');
        $delay = max(1, (int) $this->option('delay'));

        $this->info('Regenerating images for agent posts...');
        $this->info('Using model: ' . $this->imageService->getModel());

        if ($force) {
            $this->warn('Force mode: processing ALL posts regardless of image status.');
        }

        $query = SocialMediaAgentPost::query()
            ->whereHas('agent', fn ($q) => $q->where('has_image', true));

        if (! $force) {
            $query->whereIn('image_status', ['none', 'failed', '']);
        }

        if ($agentId) {
            $query->where('agent_id', $agentId);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $posts = $query->orderBy('id')->get();

        if ($posts->isEmpty()) {
            $this->info('No posts found that need image regeneration.');

            return self::SUCCESS;
        }

        $this->info("Found {$posts->count()} post(s) to process.");
        $this->newLine();

        $bar = $this->output->createProgressBar($posts->count());
        $bar->start();

        $success = 0;
        $pending = 0;
        $failed = 0;

        foreach ($posts as $post) {
            try {
                $content = $post->content ?? '';

                if (empty($content)) {
                    $bar->advance();
                    $failed++;

                    continue;
                }

                $result = $this->imageService->generateImageForPost($content, [
                    'tone'     => $post->agent?->tone,
                    'language' => $post->agent?->language,
                ]);

                $imageModel = $this->imageService->getModel();

                if (! $result['success']) {
                    $post->update([
                        'image_status' => 'failed',
                        'image_model'  => $imageModel,
                    ]);
                    $failed++;
                    Log::warning("RegenerateImages: Failed for post {$post->id}", [
                        'error' => $result['error'] ?? 'Unknown',
                    ]);
                    $bar->advance();
                    sleep($delay);

                    continue;
                }

                $updateData = [
                    'image_request_id' => $result['request_id'] ?? null,
                    'image_status'     => 'pending',
                    'image_model'      => $imageModel,
                ];

                if (! empty($result['image_url'])) {
                    $updateData['media_urls'] = [$result['image_url']];
                    $updateData['image_status'] = 'completed';
                    $success++;
                } else {
                    $pending++;
                }

                $post->update($updateData);
            } catch (Exception $e) {
                $failed++;
                $post->update(['image_status' => 'failed']);
                Log::error("RegenerateImages: Error for post {$post->id}: " . $e->getMessage());
            }

            $bar->advance();
            sleep($delay);
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Completed: {$success}");
        $this->info("Pending (polling needed): {$pending}");

        if ($failed > 0) {
            $this->warn("Failed: {$failed}");
        }

        if ($pending > 0) {
            $this->newLine();
            $this->info('Run "php artisan social-media-agent:check-pending-images" to poll pending images.');
        }

        return self::SUCCESS;
    }
}
