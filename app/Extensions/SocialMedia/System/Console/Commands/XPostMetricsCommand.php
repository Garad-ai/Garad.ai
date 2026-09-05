<?php

namespace App\Extensions\SocialMedia\System\Console\Commands;

use App\Extensions\SocialMedia\System\Enums\PlatformEnum;
use App\Extensions\SocialMedia\System\Helpers\X;
use App\Extensions\SocialMedia\System\Models\SocialMediaPlatform;
use App\Extensions\SocialMedia\System\Models\SocialMediaPost;
use Exception;
use Illuminate\Console\Command;

class XPostMetricsCommand extends Command
{
    protected $signature = 'app:social-media-x-post-metrics';

    protected $description = 'Post metrics for X (formerly Twitter)';

    public function handle(): void
    {
        $posts = SocialMediaPost::query()
            ->where('social_media_platform', PlatformEnum::x->value)
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
                    $x = new X(accessToken: $platform->credentials['access_token']);

                    $json = $x->getTweet($post['post_id']);

                    if (is_array($json) && isset($json['retweet_count'])) {
                        $post->update([
                            'post_metrics'   => $json,
                            'post_metric_at' => now()->addMinutes(15),
                        ]);
                    }
                }
            } catch (Exception $exception) {

            }
        }
    }
}
