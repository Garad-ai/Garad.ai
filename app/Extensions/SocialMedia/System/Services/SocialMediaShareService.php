<?php

namespace App\Extensions\SocialMedia\System\Services;

use App\Extensions\SocialMedia\System\Enums\PlatformEnum;
use App\Extensions\SocialMedia\System\Models\SocialMediaPlatform;
use App\Extensions\SocialMedia\System\Models\SocialMediaPost;
use Exception;
use Illuminate\Http\Request;

class SocialMediaShareService
{
    public Request $request;

    public PlatformEnum $platform;

    public function storeBulk($data)
    {
        $selectedUserPlatforms = $data['selectedUserPlatforms'];

        $posts = [];

        foreach ($selectedUserPlatforms as $selectedUserPlatform) {
            $created = array_merge($data, [
                'social_media_platform_id' => $selectedUserPlatform,
            ]);

            $posts[] = SocialMediaPost::query()->create($created)->getKey();
        }

        return SocialMediaPost::query()->whereIn('id', $posts)->get();
    }

    public function store($data)
    {
        return SocialMediaPost::query()->create($data);
    }

    public function update(SocialMediaPost $post, array $data): void
    {
        $post->update($data);
    }

    public function selectedPlatform(): PlatformEnum
    {
        try {
            if ($this->request->get('platform') && in_array($this->request->get('platform'), PlatformEnum::toArray(), true)) {
                return PlatformEnum::from($this->request->get('platform'));
            }

            return PlatformEnum::facebook;
        } catch (Exception $e) {
            return PlatformEnum::facebook;
        }
    }

    public function setRequest(Request $request): self
    {
        $this->request = $request;

        return $this;
    }

    public function setPlatform(SocialMediaPlatform $platform): self
    {
        $this->platform = $platform;

        return $this;
    }
}
