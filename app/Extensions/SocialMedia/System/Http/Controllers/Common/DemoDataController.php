<?php

namespace App\Extensions\SocialMedia\System\Http\Controllers\Common;

use App\Extensions\SocialMedia\System\Enums\PlatformEnum;
use App\Extensions\SocialMedia\System\Models\SocialMediaPlatform;
use Illuminate\Support\Facades\Auth;

class DemoDataController
{
    public function __invoke()
    {
        SocialMediaPlatform::query()->updateOrCreate([
            'user_id'  => Auth::id(),
            'platform' => PlatformEnum::instagram->value,
        ], [
            'credentials' => [
                'type'                    => 'user',
                'id'                      => 'test',
                'name'                    => 'test',
                'username'                => 'test',
                'picture'                 => 'test',
                'access_token'            => 'test',
            ],
            'connected_at'    => now(),
            'expires_at'      => now()->addDays(100),
            'followers_count' => 25000,
        ]);

        SocialMediaPlatform::query()->updateOrCreate([
            'user_id'  => Auth::id(),
            'platform' => PlatformEnum::x->value,
        ], [
            'credentials' => [
                'name'                   => $userData['name'] ?? 'test',
                'picture'                => $userData['profile_image_url'] ?? 'test',
                'username'               => $userData['username'] ?? 'test',
                'access_token'           => $tokenData['access_token'] ?? 'test',
                'access_token_expire_at' => now()->addMonths(2),

                'refresh_token'           => $tokenData['refresh_token'] ?? 'test',
                'refresh_token_expire_at' => now()->addMonths(2),
                'type'                    => 'user',
            ],
            'connected_at'    => now(),
            'expires_at'      => now()->addDays(10),
            'followers_count' => 15000,
        ]);

        $page = [];
        SocialMediaPlatform::query()->updateOrCreate([
            'user_id'  => Auth::id(),
            'platform' => PlatformEnum::facebook->value,
        ], [
            'credentials' => [
                'type'                    => 'user',
                'name'                    => data_get($page, 'name') ?: 'test',
                'username'                => data_get($page, 'username') ?: 'test',
                'picture'                 => data_get($page, 'picture.data.url') ?: 'test',

                'access_token'           => data_get($page, 'access_token') ?: 'test',
                'access_token_expire_at' => config('social-media.facebook.access_token_expire_at', now()->addDay()),

                'refresh_token'           => data_get($page, 'access_token') ?: 'test',
                'refresh_token_expire_at' => config('social-media.facebook.access_token_expire_at', now()->addDay()),
            ],
            'connected_at'    => now(),
            'expires_at'      => now()->addDays(10),
            'followers_count' => 32000,
        ]);

        $userData = [];

        SocialMediaPlatform::query()->updateOrCreate([
            'user_id'  => Auth::id(),
            'platform' => PlatformEnum::linkedin->value,
        ], [
            'credentials' => [
                'name'     => $userData['name'] ?? 'test',
                'username' => $userData['email'] ?? 'test',
                'picture'  => $userData['picture'] ?? 'test',

                'access_token'           => 'test',
                'access_token_expire_at' => now()->seconds(3000),

                'refresh_token'           => 'test',
                'refresh_token_expire_at' => now()->seconds(3000),
            ],
            'connected_at'    => now(),
            'expires_at'      => now()->addDays(10),
            'followers_count' => 18000,
        ]);

        SocialMediaPlatform::query()->updateOrCreate([
            'user_id'  => Auth::id(),
            'platform' => PlatformEnum::tiktok->value,
        ], [
            'credentials' => [
                'name'     => $userData['name'] ?? 'test',
                'username' => $userData['email'] ?? 'test',
                'picture'  => $userData['picture'] ?? 'test',

                'access_token'           => 'test',
                'access_token_expire_at' => now()->seconds(3000),

                'refresh_token'           => 'test',
                'refresh_token_expire_at' => now()->seconds(3000),
            ],
            'connected_at'    => now(),
            'expires_at'      => now()->addDays(10),
            'followers_count' => 22000,
        ]);
    }
}
