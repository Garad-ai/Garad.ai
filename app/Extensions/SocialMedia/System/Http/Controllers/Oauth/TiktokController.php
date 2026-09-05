<?php

namespace App\Extensions\SocialMedia\System\Http\Controllers\Oauth;

use App\Extensions\SocialMedia\System\Enums\PlatformEnum;
use App\Extensions\SocialMedia\System\Helpers\Tiktok;
use App\Extensions\SocialMedia\System\Http\Controllers\Oauth\Traits\HasBackRoute;
use App\Extensions\SocialMedia\System\Models\SocialMediaPlatform;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class TiktokController extends Controller
{
    use HasBackRoute;

    public function __construct(public Tiktok $api) {}

    private function cacheKey(): string
    {
        return 'platforms.' . Auth::id() . '.tiktok';
    }

    public function redirect(Request $request)
    {
        if (Helper::appIsDemo()) {
            return back()->with([
                'type'    => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        $this->setBackCacheRoute();

        if ($request->has('platform_id') && $request->get('platform_id')) {
            Cache::remember($this->cacheKey(), 60, function () use ($request) {
                return $request->get('platform_id');
            });
        }

        return $this->api::authRedirect();
    }

    public function callback(Request $request)
    {
        $code = $request->get('code');

        if (! $code) {
            return back()->with([
                'type'    => 'error',
                'message' => trans('Something went wrong, please try again.'),
            ]);
        }

        $response = $this->api->getAccessToken($code)
            ->throw();

        if ($response->json('error')) {
            echo $response->status();
            exit();
        }

        $tokenData = $response->object();

        $platformId = Cache::get($this->cacheKey());

        if ($platformId && is_numeric($platformId)) {

            $item = SocialMediaPlatform::query()
                ->where('user_id', Auth::id())
                ->where('platform', PlatformEnum::tiktok->value)
                ->where('id', $platformId)
                ->first();

            if ($item) {
                $item->update([
                    'credentials' => [
                        'platform_id'            => $tokenData?->open_id,
                        'access_token'           => $tokenData?->access_token ?? '',
                        'access_token_expire_at' => now()->addSeconds($tokenData?->expires_in ?? 0),

                        'refresh_token'           => $tokenData?->refresh_token ?? '',
                        'refresh_token_expire_at' => now()->addSeconds($tokenData?->refresh_expires_in ?? 0),
                    ],
                    'connected_at' => now(),
                    'expires_at'   => now()->addSeconds($tokenData?->expires_in ?? 0),
                ]);

                $this->api->setToken($tokenData?->access_token);

                $this->setProfileInfo($item);
            }

            Cache::forget($this->cacheKey());
        } else {
            $item = SocialMediaPlatform::query()->create([
                'user_id'     => Auth::id(),
                'platform'    => PlatformEnum::tiktok->value,
                'credentials' => [
                    'platform_id'            => $tokenData?->open_id,
                    'access_token'           => $tokenData?->access_token ?? '',
                    'access_token_expire_at' => now()->addSeconds($tokenData?->expires_in ?? 0),

                    'refresh_token'           => $tokenData?->refresh_token ?? '',
                    'refresh_token_expire_at' => now()->addSeconds($tokenData?->refresh_expires_in ?? 0),
                ],
                'connected_at' => now(),
                'expires_at'   => now()->addSeconds($tokenData?->expires_in ?? 0),
            ]);

            $this->api->setToken($tokenData?->access_token);

            $this->setProfileInfo($item);
        }

        return $this->redirectToPlatforms('success', 'Tiktok account connected successfully.');
    }

    protected function setProfileInfo(SocialMediaPlatform|Model|Builder $item): void
    {
        //        $userData = $this->api->getAccountInfo([
        //            'open_id',
        //        ])
        //            ->throw()
        //            ->json('data.user');

        $creatorInfoData = $this->api->getCreatorInfo();

        $creatorInfo = [];

        if (isset($creatorInfoData['error']['code']) && $creatorInfoData['error']['code'] === 'ok') {
            $creatorInfo = $creatorInfoData['data'] ?? [];
        }

        $followersCount = (int) (
            data_get($creatorInfo, 'follower_count')
            ?? data_get($creatorInfo, 'followers_count')
            ?? data_get($creatorInfo, 'fan_count')
            ?? data_get($creatorInfo, 'fans_count')
            ?? 0
        );

        if ($followersCount === 0) {
            $accountInfo = $this->api->getAccountInfo([
                'open_id',
                'follower_count',
                'followers_count',
                'fan_count',
            ])->json('data.user', []);

            $followersCount = (int) (
                data_get($accountInfo, 'follower_count')
                ?? data_get($accountInfo, 'followers_count')
                ?? data_get($accountInfo, 'fan_count')
                ?? data_get($accountInfo, 'fans_count')
                ?? 0
            );
        }

        $item->update([
            'credentials' => array_merge($item->credentials, [
                'name'     => $creatorInfo['creator_nickname'] ?? '',
                'username' => $creatorInfo['creator_username'] ?? '',
                'picture'  => $creatorInfo['creator_avatar_url'] ?? '',
                'meta'     => $creatorInfo ?? [],
            ]),
            'followers_count' => $followersCount,
        ]);
    }

    public function redirectToPlatforms(string $type = 'success', string $message = 'Tiktok account connected successfully.'): RedirectResponse
    {
        return to_route($this->getBackCacheRoute())->with([
            'type'    => $type,
            'message' => trans($message),
        ]);
    }

    public function verify()
    {
        return setting('TIKTOK_OAUTH_VERIFY', 'tiktok-developers-site-verification=U4IyiClYTw8yPBShtWnQkY01ncYucsC3');
    }
}
