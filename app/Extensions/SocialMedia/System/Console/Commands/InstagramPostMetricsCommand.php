<?php

namespace App\Extensions\SocialMedia\System\Console\Commands;

use App\Extensions\SocialMedia\System\Enums\PlatformEnum;
use App\Extensions\SocialMedia\System\Helpers\Instagram;
use App\Extensions\SocialMedia\System\Models\SocialMediaPlatform;
use App\Extensions\SocialMedia\System\Models\SocialMediaPost;
use Exception;
use Illuminate\Console\Command;

class InstagramPostMetricsCommand extends Command
{
    protected $signature = 'app:social-media-instagram-post-metrics';

    protected $description = 'Post metrics for Instagram posts';

    public function handle(): void
    {
        $posts = SocialMediaPost::query()
            ->where('social_media_platform', PlatformEnum::instagram->value)
            ->with('platform')
            ->where('post_metric_at', '<', now())
            ->get();

        foreach ($posts as $post) {
            try {

                /**
                 * @var SocialMediaPlatform $platform
                 */
                $platform = $post->platform;

                if ($platform && $platform->isConnected()) {
                    $x = new Instagram(accessToken: $platform->credentials['access_token']);

                    $json = $x->getPostAnalytics($post['post_id']);

                    if ($json->successful()) {
                        $data = $json->json();
                    } else {
                        $data = [];
                    }

                    if (is_array($data) && isset($data['like_count'])) {
                        $post->update([
                            'post_metrics'   => [
                                'like_count'    => $data['like_count'] ?? 0,
                                'comment_count' => $data['comments_count'] ?? 0,
                            ],
                            'post_metric_at' => now()->addMinutes(15),
                        ]);
                    }
                }
            } catch (Exception $exception) {
            }
        }
    }
}
