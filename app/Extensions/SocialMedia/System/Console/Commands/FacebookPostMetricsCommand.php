<?php

namespace App\Extensions\SocialMedia\System\Console\Commands;

use App\Extensions\SocialMedia\System\Enums\PlatformEnum;
use App\Extensions\SocialMedia\System\Helpers\Facebook;
use App\Extensions\SocialMedia\System\Models\SocialMediaPlatform;
use App\Extensions\SocialMedia\System\Models\SocialMediaPost;
use Exception;
use Illuminate\Console\Command;

class FacebookPostMetricsCommand extends Command
{
    protected $signature = 'app:social-media-facebook-post-metrics';

    protected $description = 'Post metrics for Facebook posts';

    public function handle(): void
    {
        $posts = SocialMediaPost::query()
            ->where('social_media_platform', PlatformEnum::facebook->value)
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
                    $x = new Facebook(accessToken: $platform->credentials['access_token']);

                    $json = $x->getPostAnalytics($post['post_id']);

                    if ($json->successful()) {
                        $data = $json->json();
                    } else {
                        $data = [];
                    }

                    if (is_array($data) && isset($data['likes'])) {
                        $post->update([
                            'post_metrics'   => [
                                'like_count'    => $data['likes']['summary']['total_count'] ?? 0,
                                'comment_count' => $data['comments']['summary']['total_count'] ?? 0,
                                'share_count'   => $data['shares']['count'] ?? 0,
                                'view_count'    => $data['views']['summary']['total_count'] ?? 0,
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
