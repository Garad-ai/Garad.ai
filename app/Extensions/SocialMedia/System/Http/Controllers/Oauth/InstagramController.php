<?php

namespace App\Extensions\SocialMedia\System\Http\Controllers\Oauth;

use App\Extensions\SocialMedia\System\Enums\PlatformEnum;
use App\Extensions\SocialMedia\System\Helpers\Instagram;
use App\Extensions\SocialMedia\System\Http\Controllers\Oauth\Traits\HasBackRoute;
use App\Extensions\SocialMedia\System\Http\Controllers\Oauth\Traits\HasSaveImage;
use App\Extensions\SocialMedia\System\Models\SocialMediaPlatform;
use App\Extensions\SocialMediaAutomation\System\Services\WebhookProcessor;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class InstagramController extends Controller
{
    use HasBackRoute;
    use HasSaveImage;

    private function cacheKey(): string
    {
        return 'platforms.' . Auth::id() . '.instagram';
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

        if (setting('INSTAGRAM_APP_ID') && setting('INSTAGRAM_APP_SECRET')) {
            if ($request->has('platform_id') && $request->get('platform_id')) {
                Cache::remember($this->cacheKey(), 60, function () use ($request) {
                    return $request->get('platform_id');
                });
            }

            return Instagram::authRedirect([
                'instagram_basic',
                'instagram_content_publish',
                'instagram_manage_comments',
                'instagram_manage_messages',
                'pages_read_engagement',
                'pages_show_list',
                'business_management',
                'instagram_manage_insights',
            ]);
        }

        return back()->with([
            'type'    => 'error',
            'message' => 'Instagram app id and secret not set. Please contact the administrator.',
        ]);
    }

    public function callback(Request $request)
    {
        $code = $request->get('code');

        if (! $code) {
            return redirect()->route($this->getBackCacheRoute())
                ->with([
                    'type'    => 'error',
                    'message' => trans('Something went wrong, please try again.'),
                ]);
        }

        $instagram = new Instagram;

        try {

            $token = $instagram->getAccessToken($code)->throw()->json('access_token');
            $instagram->setToken($token);

            $page = $instagram->getAccountInfo(['connected_instagram_account,name,access_token'])
                ->throw()
                ->json('data.0');
        } catch (Exception $exception) {
            return redirect()->route($this->getBackCacheRoute())
                ->with([
                    'type'    => 'error',
                    'message' => 'Something went wrong, please try again.',
                ]);
        }

        if (! isset($page['connected_instagram_account'])) {
            return redirect()->route($this->getBackCacheRoute())
                ->with([
                    'type'    => 'error',
                    'message' => trans('Something went wrong, please try again.'),
                ]);
        }

        $igAccount = $instagram->getInstagramInfo($page['connected_instagram_account']['id'], ['id,name,username,profile_picture_url,followers_count'])
            ->throw()
            ->json();

        $followersCount = (int) ($igAccount['followers_count'] ?? 0);

        $platformId = Cache::get($this->cacheKey());

        if ($platformId && is_numeric($platformId)) {

            $platform = SocialMediaPlatform::query()
                ->where('id', $platformId)
                ->where('user_id', Auth::id())
                ->where('platform', PlatformEnum::instagram->value)
                ->first();

            if ($platform) {
                $platform->update([
                    'credentials' => [
                        'type'                    => 'user',
                        'id'                      => $igAccount['id'],
                        'platform_id'             => $igAccount['id'],
                        'name'                    => $igAccount['name'],
                        'username'                => $igAccount['username'],
                        'picture'                 => $igAccount['profile_picture_url'],
                        'access_token'            => $igAccount['access_token'] ?? $token,
                    ],
                    'connected_at'    => now(),
                    'expires_at'      => now()->addMonths(2),
                    'followers_count' => $followersCount,
                ]);
            }

            Cache::forget($this->cacheKey());
        } else {
            SocialMediaPlatform::query()->create([
                'user_id'     => Auth::id(),
                'platform'    => PlatformEnum::instagram->value,
                'credentials' => [
                    'type'                    => 'user',
                    'id'                      => $igAccount['id'],
                    'platform_id'             => $igAccount['id'],
                    'name'                    => $igAccount['name'],
                    'username'                => $igAccount['username'],
                    'picture'                 => $igAccount['profile_picture_url'],
                    'access_token'            => $igAccount['access_token'] ?? $token,
                ],
                'connected_at'    => now(),
                'expires_at'      => now()->addMonths(2),
                'followers_count' => $followersCount,
            ]);
        }

        return to_route($this->getBackCacheRoute())->with([
            'type'    => 'success',
            'message' => trans('Instagram account connected successfully.'),
        ]);
    }

    public function webhook(Request $request)
    {
        // GET: Verification handshake
        if ($request->isMethod('GET')) {
            $verify_token = setting('INSTAGRAM_WEBHOOK_SECRET', 'default-password');

            if ($request->get('hub_mode') === 'subscribe' && $request->get('hub_verify_token') === $verify_token) {
                return response($request->get('hub_challenge'), 200)
                    ->header('Content-Type', 'text/plain');
            }

            return response('Token invalid', 403);
        }

        // POST: Process incoming webhook events
        if ($request->isMethod('POST')) {
            try {
                if (class_exists(WebhookProcessor::class)) {
                    $processor = app(WebhookProcessor::class);

                    $appSecret = setting('INSTAGRAM_APP_SECRET');

                    if ($appSecret && ! $processor->verifySignature($request, $appSecret)) {
                        return response('Invalid signature', 403);
                    }

                    $processor->processInstagramPayload($request->json()->all());
                }
            } catch (Throwable $e) {
                Log::error('Instagram webhook processing failed', [
                    'error' => $e->getMessage(),
                ]);
            }

            return response('EVENT_RECEIVED', 200);
        }

        return response('Method not allowed', 405);
    }
}
