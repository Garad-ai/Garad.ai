<?php

namespace App\Extensions\SocialMedia\System\Http\Controllers\Oauth;

use App\Extensions\SocialMedia\System\Enums\PlatformEnum;
use App\Extensions\SocialMedia\System\Helpers\Facebook;
use App\Extensions\SocialMedia\System\Http\Controllers\Oauth\Traits\HasBackRoute;
use App\Extensions\SocialMedia\System\Http\Controllers\Oauth\Traits\HasSaveImage;
use App\Extensions\SocialMedia\System\Models\SocialMediaPlatform;
use App\Extensions\SocialMediaAutomation\System\Services\WebhookProcessor;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class FacebookController extends Controller
{
    use HasBackRoute;
    use HasSaveImage;

    private function cacheKey(): string
    {
        return 'platforms.' . Auth::id() . '.facebook';
    }

    public function redirect(Request $request): RedirectResponse
    {
        if (Helper::appIsDemo()) {
            return back()->with([
                'type'    => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        $this->setBackCacheRoute();

        if (setting('FACEBOOK_APP_ID') && setting('FACEBOOK_APP_SECRET')) {

            if ($request->has('platform_id') && $request->get('platform_id')) {
                Cache::remember($this->cacheKey(), 60, function () use ($request) {
                    return $request->get('platform_id');
                });
            }

            return Facebook::authRedirect([
                'pages_manage_posts',
                'pages_show_list',
                'pages_read_user_content',
                'pages_read_engagement',
                'pages_messaging',
                'pages_manage_metadata',
                'read_insights',
            ]);
        }

        return back()->with([
            'type'    => 'error',
            'message' => 'Facebook app id and secret not set. Please contact the administrator.',
        ]);
    }

    public function callback(Request $request): RedirectResponse
    {

        $fb = new Facebook;

        $code = $request->get('code');

        if (! $code) {
            return redirect()->route($this->getBackCacheRoute())
                ->with([
                    'type'    => 'error',
                    'message' => 'Something went wrong, please try again.',
                ]);
        }

        try {

            $token = $fb->getAccessToken($code)->throw()->json('access_token');

            $fb->setToken($token);

            $page = $fb->getPagesInfo(['name,username,picture,access_token,followers_count,fan_count'])->throw()->json('data');

            $page = Arr::first($page);

            if (! $page) {
                return redirect()->route($this->getBackCacheRoute())
                    ->with([
                        'type'    => 'error',
                        'message' => 'Something went wrong, please try again.',
                    ]);
            }

        } catch (Exception $exception) {
            return redirect()->route($this->getBackCacheRoute())
                ->with([
                    'type'    => 'error',
                    'message' => 'Something went wrong, please try again.',
                ]);
        }

        $platformId = Cache::get($this->cacheKey());
        $followersCount = (int) (
            data_get($page, 'followers_count')
            ?? data_get($page, 'fan_count')
            ?? 0
        );

        if ($platformId && is_numeric($platformId)) {
            $platform = SocialMediaPlatform::query()
                ->where('id', $platformId)
                ->where('user_id', Auth::id())
                ->where('platform', PlatformEnum::facebook->value)
                ->first();

            if ($platform) {
                $pageAccessToken = data_get($page, 'access_token');
                $pageId = data_get($page, 'id');

                $platform->update([
                    'credentials' => [
                        'type'                    => 'user',
                        'platform_id'             => $pageId,
                        'name'                    => data_get($page, 'name'),
                        'username'                => data_get($page, 'username'),
                        'picture'                 => self::downloadImageToStorage(data_get($page, 'picture.data.url')),

                        'access_token'           => $pageAccessToken,
                        'access_token_expire_at' => config('social-media.facebook.access_token_expire_at', now()->addDay()),

                        'refresh_token'           => $pageAccessToken,
                        'refresh_token_expire_at' => config('social-media.facebook.access_token_expire_at', now()->addDay()),
                    ],
                    'connected_at'     => now(),
                    'expires_at'       => config('social-media.facebook.access_token_expire_at', now()->addDay()),
                    'followers_count'  => $followersCount,
                ]);

                $this->subscribePageToWebhook($pageId, $pageAccessToken);
            }

            Cache::forget($this->cacheKey());

        } else {
            $pageAccessToken = data_get($page, 'access_token');
            $pageId = data_get($page, 'id');

            SocialMediaPlatform::query()->create([
                'user_id'     => Auth::id(),
                'platform'    => PlatformEnum::facebook->value,
                'credentials' => [
                    'type'                    => 'user',
                    'platform_id'             => $pageId,
                    'name'                    => data_get($page, 'name'),
                    'username'                => data_get($page, 'username'),
                    'picture'                 => self::downloadImageToStorage(data_get($page, 'picture.data.url')),

                    'access_token'           => $pageAccessToken,
                    'access_token_expire_at' => config('social-media.facebook.access_token_expire_at', now()->addDay()),

                    'refresh_token'           => $pageAccessToken,
                    'refresh_token_expire_at' => config('social-media.facebook.access_token_expire_at', now()->addDay()),
                ],
                'connected_at'    => now(),
                'expires_at'      => config('social-media.facebook.access_token_expire_at', now()->addDay()),
                'followers_count' => $followersCount,
            ]);

            $this->subscribePageToWebhook($pageId, $pageAccessToken);
        }

        return redirect()->route($this->getBackCacheRoute())
            ->with([
                'type'    => 'success',
                'message' => trans('Facebook account connected successfully.'),
            ]);
    }

    public function webhook(Request $request)
    {
        // GET: Verification handshake
        if ($request->isMethod('GET')) {
            $verify_token = setting('FACEBOOK_WEBHOOK_SECRET', 'default-password');

            Log::info('Facebook webhook verification attempt', [
                'hub_mode'         => $request->get('hub_mode'),
                'hub_verify_token' => $request->get('hub_verify_token'),
                'expected_token'   => $verify_token,
                'match'            => $request->get('hub_verify_token') === $verify_token,
            ]);

            if ($request->get('hub_mode') === 'subscribe' && $request->get('hub_verify_token') === $verify_token) {
                return response($request->get('hub_challenge'), 200)
                    ->header('Content-Type', 'text/plain');
            }

            return response('Token invalid', 403);
        }

        // POST: Process incoming webhook events
        if ($request->isMethod('POST')) {
            Log::info('Facebook webhook POST received', [
                'payload' => $request->json()->all(),
            ]);

            try {
                if (class_exists(WebhookProcessor::class)) {
                    $processor = app(WebhookProcessor::class);

                    $appSecret = setting('FACEBOOK_APP_SECRET');

                    if ($appSecret && ! $processor->verifySignature($request, $appSecret)) {
                        Log::warning('Facebook webhook signature verification failed', [
                            'signature_header' => $request->header('X-Hub-Signature-256'),
                        ]);

                        return response('Invalid signature', 403);
                    }

                    $processor->processFacebookPayload($request->json()->all());
                } else {
                    Log::warning('Facebook webhook: SocialMediaAutomation extension not found');
                }
            } catch (Throwable $e) {
                Log::error('Facebook webhook processing failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            return response('EVENT_RECEIVED', 200);
        }

        return response('Method not allowed', 405);
    }

    private function subscribePageToWebhook(string $pageId, string $pageAccessToken): void
    {
        try {
            $fb = new Facebook(null, $pageAccessToken);
            $response = $fb->subscribePageToWebhook($pageId);

            if ($response->failed()) {
                Log::warning('Facebook page webhook subscription failed', [
                    'page_id' => $pageId,
                    'status'  => $response->status(),
                    'body'    => $response->body(),
                ]);
            } else {
                Log::info('Facebook page subscribed to webhook', [
                    'page_id'  => $pageId,
                    'response' => $response->json(),
                ]);
            }
        } catch (Throwable $e) {
            Log::error('Facebook page webhook subscription exception', [
                'page_id' => $pageId,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
