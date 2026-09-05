<?php

namespace App\Extensions\SocialMedia\System\Http\Controllers\Oauth;

use App\Extensions\SocialMedia\System\Enums\PlatformEnum;
use App\Extensions\SocialMedia\System\Helpers\Youtube;
use App\Extensions\SocialMedia\System\Http\Controllers\Oauth\Traits\HasBackRoute;
use App\Extensions\SocialMedia\System\Models\SocialMediaPlatform;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

class YoutubeController extends Controller
{
    use HasBackRoute;

    public function redirectYoutube(Request $request): RedirectResponse
    {
        return $this->redirect($request, PlatformEnum::youtube);
    }

    public function redirectYoutubeShorts(Request $request): RedirectResponse
    {
        return $this->redirect($request, PlatformEnum::youtube_shorts);
    }

    public function callbackYoutube(Request $request): RedirectResponse
    {
        return $this->callback($request, PlatformEnum::youtube);
    }

    public function callbackYoutubeShorts(Request $request): RedirectResponse
    {
        return $this->callback($request, PlatformEnum::youtube_shorts);
    }

    private function redirect(Request $request, PlatformEnum $platform): RedirectResponse
    {
        if (Helper::appIsDemo()) {
            return back()->with([
                'type'    => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        $this->setBackCacheRoute();

        if ($request->filled('platform_id')) {
            cache()->put($this->platformCacheKey($platform), $request->get('platform_id'), 300);
        }

        $state = $this->cacheState($platform);

        $api = $this->makeApi($platform);

        return redirect()->away($api->authorizationUrl($state));
    }

    private function callback(Request $request, PlatformEnum $platform): RedirectResponse
    {
        $code = $request->get('code');

        if (! $code) {
            return $this->redirectWith('error', 'Something went wrong, please try again.');
        }

        if (! $this->stateIsValid($request->get('state'), $platform)) {
            return $this->redirectWith('error', 'Invalid authorization state, please try again.');
        }

        $api = $this->makeApi($platform);

        try {
            $tokenResponse = $api->getAccessToken($code)->throw();
        } catch (Throwable $exception) {
            return $this->redirectWith('error', $exception->getMessage());
        }

        $tokenData = $tokenResponse->json();

        $accessToken = data_get($tokenData, 'access_token');

        if (! $accessToken) {
            return $this->redirectWith('error', 'Authorization failed, missing access token.');
        }

        $refreshToken = data_get($tokenData, 'refresh_token');
        $expiresIn = (int) data_get($tokenData, 'expires_in', 3600);

        $api->setAccessToken($accessToken)->setRefreshToken($refreshToken);

        $channelResponse = $api->getChannelInfo();

        if ($channelResponse->failed()) {
            return $this->redirectWith('error', 'Unable to fetch channel information.');
        }

        $channelData = $channelResponse->json('items.0', []);

        $this->syncPlatform($platform, $tokenData, $channelData, $expiresIn);

        return $this->redirectWith('success', $platform->label() . ' account connected successfully.');
    }

    private function syncPlatform(PlatformEnum $platform, array $tokenData, array $channelData, int $expiresIn): void
    {
        $cacheKey = $this->platformCacheKey($platform);
        $platformIdFromCache = cache()->pull($cacheKey);

        $channelId = data_get($channelData, 'id');
        $channelTitle = data_get($channelData, 'snippet.title', 'YouTube Channel');
        $channelHandle = data_get($channelData, 'snippet.customUrl');
        $thumbnails = (array) data_get($channelData, 'snippet.thumbnails', []);
        $thumbnail = data_get($thumbnails, 'high.url')
            ?? data_get($thumbnails, 'medium.url')
            ?? data_get($thumbnails, 'default.url');

        $followersCount = (int) data_get($channelData, 'statistics.subscriberCount', 0);

        $expiresAt = now()->addSeconds($expiresIn);

        $credentialsPayload = [
            'platform_id'            => $channelId,
            'channel_id'             => $channelId,
            'name'                   => $channelTitle,
            'username'               => $channelHandle ?? $channelTitle,
            'picture'                => $thumbnail,
            'meta'                   => $channelData,
            'access_token'           => data_get($tokenData, 'access_token'),
            'access_token_expire_at' => $expiresAt,
            'token_type'             => data_get($tokenData, 'token_type'),
            'scope'                  => data_get($tokenData, 'scope'),
        ];

        if ($refreshToken = data_get($tokenData, 'refresh_token')) {
            $credentialsPayload['refresh_token'] = $refreshToken;
            $credentialsPayload['refresh_token_expire_at'] = now()->addMonths(6);
        }

        if ($platformIdFromCache && is_numeric($platformIdFromCache)) {
            $existing = SocialMediaPlatform::query()
                ->where('user_id', Auth::id())
                ->where('platform', $platform->value)
                ->where('id', $platformIdFromCache)
                ->first();

            if ($existing) {
                $existingCredentials = $existing->credentials ?? [];

                if (empty($credentialsPayload['refresh_token']) && isset($existingCredentials['refresh_token'])) {
                    $credentialsPayload['refresh_token'] = $existingCredentials['refresh_token'];
                    $credentialsPayload['refresh_token_expire_at'] = $existingCredentials['refresh_token_expire_at'] ?? null;
                }

                $existing->update([
                    'credentials'     => array_merge($existingCredentials, array_filter($credentialsPayload, fn ($value) => $value !== null)),
                    'connected_at'    => now(),
                    'expires_at'      => $expiresAt,
                    'followers_count' => $followersCount,
                ]);

                return;
            }
        }

        SocialMediaPlatform::query()->create([
            'user_id'         => Auth::id(),
            'platform'        => $platform->value,
            'credentials'     => array_filter($credentialsPayload, fn ($value) => $value !== null),
            'connected_at'    => now(),
            'expires_at'      => $expiresAt,
            'followers_count' => $followersCount,
        ]);
    }

    private function platformCacheKey(PlatformEnum $platform): string
    {
        return 'platforms.' . Auth::id() . '.' . $platform->value;
    }

    private function cacheState(PlatformEnum $platform): string
    {
        $state = (string) Str::uuid();

        cache()->put($this->stateCacheKey($state), [
            'user_id'  => Auth::id(),
            'platform' => $platform->value,
        ], 600);

        return $state;
    }

    private function stateIsValid(?string $state, PlatformEnum $platform): bool
    {
        if (! $state) {
            return false;
        }

        $payload = cache()->pull($this->stateCacheKey($state));

        if (! $payload) {
            return false;
        }

        return (int) data_get($payload, 'user_id') === Auth::id()
            && data_get($payload, 'platform') === $platform->value;
    }

    private function stateCacheKey(string $state): string
    {
        return 'youtube.oauth.state.' . $state;
    }

    private function makeApi(PlatformEnum $platform): Youtube
    {
        return new Youtube($platform);
    }

    private function redirectWith(string $type, string $message): RedirectResponse
    {
        return to_route($this->getBackCacheRoute())->with([
            'type'    => $type,
            'message' => trans($message),
        ]);
    }
}
