<?php

namespace App\Extensions\SocialMedia\System\Services\Publisher;

use App\Extensions\SocialMedia\System\Helpers\Linkedin;
use App\Extensions\SocialMedia\System\Services\Publisher\Contracts\BasePublisherService;

class LinkedinService extends BasePublisherService
{
    public function handle()
    {
        $images = $this->post->images ?? [];
        $media = $this->post->image;

        $message = $this->post->content;

        $linkedin = new Linkedin;
        $linkedin->setToken($this->accessToken);

        $mediaFiles = count($images) > 0
            ? array_map(fn ($img) => public_path($img), $images)
            : ($media ? [public_path($media)] : []);

        return match (count($mediaFiles) > 0) {
            true    => $linkedin->publishImage($this->platformId, $mediaFiles, $message),
            default => $linkedin->publishText($this->platformId, $message),
        };
    }
}
