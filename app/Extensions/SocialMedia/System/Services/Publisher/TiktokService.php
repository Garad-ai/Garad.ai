<?php

namespace App\Extensions\SocialMedia\System\Services\Publisher;

use App\Extensions\SocialMedia\System\Helpers\Tiktok;
use App\Extensions\SocialMedia\System\Services\Publisher\Contracts\BasePublisherService;
use Illuminate\Http\Client\Response;

class TiktokService extends BasePublisherService
{
    public function handle(): Response
    {
        $media = $this->post->video;

        $message = $this->post->content;

        $tiktok = new Tiktok;

        $tiktok->setToken($this->accessToken);

        $postData = [
            'post_info' => [
                'title'                    => str($message)->limit(150)->toString(),
                'privacy_level'            => $options['privacy_level'] ?? config('social-media.tiktok.options.privacy_level'),
                'disable_duet'             => $options['disable_duet'] ?? config('social-media.tiktok.options.disable_duet'),
                'disable_comment'          => $options['disable_comment'] ?? config('social-media.tiktok.options.disable_comment'),
                'disable_stitch'           => $options['disable_stitch'] ?? config('social-media.tiktok.options.disable_stitch'),
                'video_cover_timestamp_ms' => $options['video_cover_timestamp_ms'] ?? config('social-media.tiktok.options.video_cover_timestamp_ms'),
            ],
            'source_info' => [
                'source'    => 'PULL_FROM_URL',
                'video_url' => url($media),
            ],
        ];

        return $tiktok->postVideo($postData);
    }
}
