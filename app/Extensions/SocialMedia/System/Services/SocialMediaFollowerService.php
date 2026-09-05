<?php

namespace App\Extensions\SocialMedia\System\Services;

use App\Extensions\SocialMedia\System\Enums\PlatformEnum;
use App\Extensions\SocialMedia\System\Helpers\Facebook;
use App\Extensions\SocialMedia\System\Helpers\Instagram;
use App\Extensions\SocialMedia\System\Helpers\Linkedin;
use App\Extensions\SocialMedia\System\Helpers\Tiktok;
use App\Extensions\SocialMedia\System\Helpers\X;
use App\Extensions\SocialMedia\System\Models\SocialMediaPlatform;

class SocialMediaFollowerService
{
    public function sync(SocialMediaPlatform $platform): ?int
    {
        $count = $this->fetch($platform);

        if ($count === null) {
            return null;
        }

        $platform->forceFill(['followers_count' => $count])->save();

        return $count;
    }

    public function fetch(SocialMediaPlatform $platform): ?int
    {
        if (! $platform->isConnected()) {
            return null;
        }

        $platformEnum = PlatformEnum::tryFrom($platform->platform);

        if (! $platformEnum) {
            return null;
        }

        return match ($platformEnum) {
            PlatformEnum::facebook  => $this->fetchFacebookFollowers($platform),
            PlatformEnum::instagram => $this->fetchInstagramFollowers($platform),
            PlatformEnum::tiktok    => $this->fetchTiktokFollowers($platform),
            PlatformEnum::x         => $this->fetchXFollowers($platform),
            PlatformEnum::linkedin  => $this->fetchLinkedinFollowers($platform),
            default                 => 0,
        };
    }

    private function fetchFacebookFollowers(SocialMediaPlatform $platform): ?int
    {
        $pageId = data_get($platform->credentials, 'platform_id');
        $accessToken = data_get($platform->credentials, 'access_token');

        if (! $pageId || ! $accessToken) {
            return null;
        }

        $facebook = new Facebook(accessToken: $accessToken);
        $response = $facebook->getPageProfile($pageId, ['followers_count', 'fan_count']);

        if ($response->failed()) {
            return null;
        }

        $data = $response->json();

        return (int) ($data['followers_count'] ?? $data['fan_count'] ?? 0);
    }

    private function fetchInstagramFollowers(SocialMediaPlatform $platform): ?int
    {
        $igId = data_get($platform->credentials, 'platform_id');
        $accessToken = data_get($platform->credentials, 'access_token');

        if (! $igId || ! $accessToken) {
            return null;
        }

        $instagram = new Instagram(accessToken: $accessToken);
        $response = $instagram->getInstagramInfo($igId, ['id', 'followers_count']);

        if ($response->failed()) {
            return null;
        }

        return (int) ($response->json('followers_count') ?? 0);
    }

    private function fetchTiktokFollowers(SocialMediaPlatform $platform): ?int
    {
        $accessToken = data_get($platform->credentials, 'access_token');

        if (! $accessToken) {
            return null;
        }

        $tiktok = new Tiktok(accessToken: $accessToken);
        $response = $tiktok->getAccountInfo([
            'open_id',
            'follower_count',
            'followers_count',
            'fan_count',
        ]);

        if ($response->failed()) {
            return null;
        }

        $user = data_get($response->json(), 'data.user', []);

        return (int) (
            data_get($user, 'follower_count')
            ?? data_get($user, 'followers_count')
            ?? data_get($user, 'fan_count')
            ?? data_get($user, 'fans_count')
            ?? 0
        );
    }

    private function fetchXFollowers(SocialMediaPlatform $platform): ?int
    {
        $accessToken = data_get($platform->credentials, 'access_token');

        if (! $accessToken) {
            return null;
        }

        $x = new X(accessToken: $accessToken);
        $response = $x->getUserInfo(['name', 'profile_image_url', 'username', 'public_metrics']);

        if ($response->failed()) {
            return null;
        }

        return (int) data_get($response->json(), 'data.public_metrics.followers_count', 0);
    }

    private function fetchLinkedinFollowers(SocialMediaPlatform $platform): ?int
    {
        $memberId = data_get($platform->credentials, 'platform_id');
        $accessToken = data_get($platform->credentials, 'access_token');

        if (! $memberId || ! $accessToken) {
            return null;
        }

        $linkedin = new Linkedin(accessToken: $accessToken);
        $response = $linkedin->getNetworkSize($memberId);

        if ($response->failed()) {
            return null;
        }

        $value = $response->json('firstDegreeSize', $response->json('value'));

        return is_numeric($value) ? (int) $value : null;
    }
}
