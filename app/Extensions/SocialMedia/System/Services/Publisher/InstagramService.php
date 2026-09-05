<?php

namespace App\Extensions\SocialMedia\System\Services\Publisher;

use App\Extensions\SocialMedia\System\Enums\LogStatusEnum;
use App\Extensions\SocialMedia\System\Enums\PostTypeEnum;
use App\Extensions\SocialMedia\System\Enums\StatusEnum;
use App\Extensions\SocialMedia\System\Helpers\Instagram;
use App\Extensions\SocialMedia\System\Models\SocialMediaSharedLog;
use App\Extensions\SocialMedia\System\Services\Publisher\Contracts\BasePublisherService;

class InstagramService extends BasePublisherService
{
    public function handle()
    {
        $images = $this->post->images ?? [];
        $media = $this->post->image;

        $message = $this->post->content;

        $instagram = new Instagram;

        $instagram->setToken($this->accessToken);

        if (! $media && empty($images)) {

            $this->post->update([
                'status' => StatusEnum::failed,
            ]);

            SocialMediaSharedLog::query()->create([
                'social_media_post_id' => $this->post->id,
                'response'             => [
                    'message' => 'Media not found.',
                ],
                'status'     => LogStatusEnum::failed,
                'created_at' => now(),
            ]);

            return false;
        }

        if ($this->post->post_type === PostTypeEnum::Story) {
            return $instagram->publishStory($this->platformId, url($media));
        }

        if (count($images) > 1) {
            $imageUrls = array_map(fn ($img) => url($img), $images);

            return $instagram->publishCarouselPost($this->platformId, $imageUrls, 'image', $message);
        }

        $postData = [
            'image_url' => url($media),
            'caption'   => $message,
        ];

        return $instagram->publishSingleMediaPost($this->platformId, $postData);
    }
}
