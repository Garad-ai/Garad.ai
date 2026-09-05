<?php

namespace App\Extensions\SocialMedia\System\Http\Controllers\Oauth;

use App\Extensions\SocialMedia\System\Enums\PlatformEnum;
use App\Extensions\SocialMedia\System\Helpers\X;
use App\Extensions\SocialMedia\System\Http\Controllers\Oauth\Traits\HasBackRoute;
use App\Extensions\SocialMedia\System\Models\SocialMediaPlatform;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class XController extends Controller
{
    use HasBackRoute;

    public function __construct(public X $x) {}

    private function cacheKey(): string
    {
        return 'platforms.' . Auth::id() . '.x';
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

        if (setting('X_CLIENT_ID') && setting('X_CLIENT_SECRET')) {
            if ($request->has('platform_id') && $request->get('platform_id')) {
                Cache::remember($this->cacheKey(), 60, function () use ($request) {
                    return $request->get('platform_id');
                });
            }

            return $this->x->authRedirect();
        }

        return back()->with([
            'type'    => 'error',
            'message' => 'X app id and secret not set. Please contact the administrator.',
        ]);
    }

    public function callback(Request $request): RedirectResponse
    {
        $code = $request->get('code');

        if (! $code) {
            return to_route($this->getBackCacheRoute())->with([
                'type'    => 'error',
                'message' => 'Something went wrong, please try again.',
            ]);
        }

        $response = $this->x->getAccessToken($code)->throw();

        $this->setPlatformInfo($response->json());

        return to_route($this->getBackCacheRoute())->with([
            'type'    => 'success',
            'message' => 'X account connected successfully.',
        ]);
    }

    protected function setPlatformInfo($tokenData): void
    {
        $accessToken = $tokenData['access_token'];

        $platformId = Cache::get($this->cacheKey());

        $this->x->setToken($accessToken);

        $response = $this->x->getUserInfo()->throw();

        $userData = $response->json('data');
        $followersCount = (int) data_get($userData, 'public_metrics.followers_count', 0);

        if ($platformId && is_numeric($platformId)) {

            $platform = SocialMediaPlatform::query()
                ->where('id', $platformId)
                ->where('user_id', Auth::id())
                ->where('platform', PlatformEnum::x->value)
                ->first();

            if ($platform) {
                $platform->update([
                    'credentials' => [
                        'platform_id'             => $userData['id'],
                        'name'                    => $userData['name'] ?? '',
                        'picture'                 => $userData['profile_image_url'] ?? '',
                        'username'                => $userData['username'] ?? '',
                        'access_token'            => $tokenData['access_token'] ?? '',
                        'access_token_expire_at'  => now()->addHours(2),
                        'refresh_token'           => $tokenData['refresh_token'] ?? '',
                        'refresh_token_expire_at' => now()->addHours(2),
                        'type'                    => 'user',
                    ],
                    'connected_at'    => now(),
                    'expires_at'      => now()->addHours(2),
                    'followers_count' => $followersCount,
                ]);
            }

            Cache::forget($this->cacheKey());

        } else {
            SocialMediaPlatform::query()->create([
                'user_id'     => Auth::id(),
                'platform'    => PlatformEnum::x->value,
                'credentials' => [
                    'platform_id'             => $userData['id'],
                    'name'                    => $userData['name'] ?? '',
                    'picture'                 => $userData['profile_image_url'] ?? '',
                    'username'                => $userData['username'] ?? '',
                    'access_token'            => $tokenData['access_token'] ?? '',
                    'access_token_expire_at'  => now()->addHours(2),
                    'refresh_token'           => $tokenData['refresh_token'] ?? '',
                    'refresh_token_expire_at' => now()->addHours(2),
                    'type'                    => 'user',
                ],
                'connected_at'    => now(),
                'expires_at'      => now()->addHours(2),
                'followers_count' => $followersCount,
            ]);
        }
    }
}
