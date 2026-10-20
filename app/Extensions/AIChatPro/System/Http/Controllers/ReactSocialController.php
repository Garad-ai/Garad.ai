<?php

namespace App\Extensions\AIChatPro\System\Http\Controllers;

use App\Domains\Entity\Facades\Entity;
use App\Extensions\SocialMedia\System\Enums\LogStatusEnum;
use App\Extensions\SocialMedia\System\Enums\PlatformEnum;
use App\Extensions\SocialMedia\System\Enums\StatusEnum;
use App\Extensions\SocialMedia\System\Http\Controllers\SocialMediaCampaignController;
use App\Extensions\SocialMedia\System\Models\SocialMediaCampaign;
use App\Extensions\SocialMedia\System\Models\SocialMediaPlatform;
use App\Extensions\SocialMedia\System\Models\SocialMediaPost;
use App\Extensions\SocialMedia\System\Models\SocialMediaSharedLog;
use App\Extensions\SocialMedia\System\Services\Publisher\Contracts\BasePublisherService;
use App\Extensions\SocialMedia\System\Services\Publisher\PublisherDriver;
use App\Helpers\Classes\Helper;
use App\Helpers\Classes\MarketplaceHelper;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Usage;
use App\Services\Ai\AiCompletionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Thin JSON facade over the existing SocialMedia extension. It owns no tables:
 * posts, accounts, campaigns, publishing and scheduling all stay in the extension.
 */
class ReactSocialController extends Controller
{
    private const VIDEO_ONLY = ['tiktok', 'youtube', 'youtube-shorts'];

    private function guard(): ?JsonResponse
    {
        if (! MarketplaceHelper::isRegistered('social-media')) {
            return response()->json(['message' => 'The Social Media extension is not enabled.'], 403);
        }

        return null;
    }

    private function ownPost(int $id): ?SocialMediaPost
    {
        return SocialMediaPost::query()->where('user_id', Auth::id())->whereKey($id)->first();
    }

    private function isConfigured(PlatformEnum $p): bool
    {
        $needed = collect($p->credentials())->filter(
            fn ($v, $k) => preg_match('/(app_id|app_secret|app_key|client_id|client_secret|api_key|api_secret)$/', (string) $k)
        );

        return $needed->isNotEmpty() && $needed->every(fn ($v) => filled($v));
    }

    private function friendly(?string $raw, string $platform): string
    {
        $label = ucfirst(str_replace('-', ' ', $platform));
        $raw = trim((string) $raw);

        if (preg_match('/not connected|token|expired|oauth|authenticat|unauthori[sz]ed|authoriz|forbidden|permission|\b40[13]\b/i', $raw)) {
            return "Your {$label} connection has expired. Reconnect to continue publishing.";
        }
        if ($raw === '' || mb_strlen($raw) > 200 || preg_match('/^[\{\[]|SQLSTATE|Exception|\\\\|\.php|Stack trace|::/i', $raw)) {
            return "We couldn't publish to {$label}. Please try again later.";
        }

        return $raw;
    }

    private function platformCatalog(): array
    {
        return collect(PlatformEnum::all())->map(function (PlatformEnum $p) {
            $cfg = (array) config('social-media.' . $p->value, []);

            return [
                'configured' => $this->isConfigured($p),
                'key'        => $p->value,
                'label'      => ucfirst(str_replace('-', ' ', $p->value)),
                'textLimit'  => (int) data_get($cfg, 'requirements.text.limit', $p->contentCharacterLength()),
                'maxImages'  => data_get($cfg, 'requirements.images.limit'),
                'imageRequired' => $p->value === 'instagram',
                'videoRequired' => in_array($p->value, self::VIDEO_ONLY, true),
                'supportsImages' => ! in_array($p->value, self::VIDEO_ONLY, true),
                'supportsStory' => \App\Extensions\SocialMedia\System\Enums\PostTypeEnum::platformSupportsStory($p),
                'connectUrl' => url('social-media/oauth/redirect/' . $p->value),
                'callbackUrl' => $p->value === 'linkedin'
                    ? config('social-media.linkedin.redirect_uri')
                    : null,
            ];
        })->values()->all();
    }

    private function accountRow(SocialMediaPlatform $a): array
    {
        $creds = (array) $a->credentials;

        return [
            'id'          => $a->id,
            'platform'    => $a->platform,
            'name'        => $a->username(),
            'picture'     => data_get($creds, 'picture'),
            'type'        => is_string(data_get($creds, 'type')) ? data_get($creds, 'type') : null,
            'accountType' => is_string(data_get($creds, 'account_type')) ? data_get($creds, 'account_type') : null,
            'role'        => is_string(data_get($creds, 'role')) ? data_get($creds, 'role') : null,
            'canPublish'  => is_bool(data_get($creds, 'can_publish')) ? data_get($creds, 'can_publish') : null,
            'profileUrl'  => is_string(data_get($creds, 'profile_url')) ? data_get($creds, 'profile_url') : null,
            'followers'   => $a->followers_count,
            'connectedAt' => optional($a->connected_at)->toIso8601String(),
            'expiresAt'   => optional($a->expires_at)->toIso8601String(),
            'status'      => $a->isConnected() ? 'connected' : 'expired',
        ];
    }

    private function postRow(SocialMediaPost $p, ?array $accounts = null): array
    {
        $status = $p->status instanceof StatusEnum ? $p->status->value : (string) $p->status;
        $platformKey = $p->social_media_platform instanceof PlatformEnum
            ? $p->social_media_platform->value
            : (string) $p->social_media_platform;
        $error = null;
        if ($status === 'failed') {
            $log = SocialMediaSharedLog::query()
                ->where('social_media_post_id', $p->id)
                ->where('status', LogStatusEnum::failed->value)
                ->orderByDesc('id')
                ->first();
            $msg = data_get($log?->response, 'message');
            $error = $this->friendly(is_string($msg) ? $msg : null, $platformKey);
        }

        return [
            'id'          => $p->id,
            'platform'    => $platformKey,
            'accountId'   => $p->social_media_platform_id,
            'content'     => (string) $p->content,
            'images'      => $p->images ?: ($p->image ? [$p->image] : []),
            'video'       => $p->video,
            'status'      => $status,
            'error'       => $error,
            'scheduledAt' => optional($p->scheduled_at)->toIso8601String(),
            'postedAt'    => optional($p->posted_at)->toIso8601String(),
            'campaignId'  => $p->campaign_id,
            'tone'        => $p->tone,
            'link'        => $p->link,
            'metrics'     => $p->post_metrics,
            'engagement'  => $p->post_engagement_count,
            'createdAt'   => optional($p->created_at)->toIso8601String(),
        ];
    }

    public function overview(): JsonResponse
    {
        if ($denied = $this->guard()) {
            return $denied;
        }

        $uid = Auth::id();

        $accounts = SocialMediaPlatform::query()->where('user_id', $uid)->orderByDesc('id')->get()
            ->unique(function (SocialMediaPlatform $account): string {
                $credentials = (array) $account->credentials;
                $identity = $credentials['author_urn']
                    ?? $credentials['channel_id']
                    ?? $credentials['platform_id']
                    ?? null;

                return $identity === null
                    ? 'account:' . $account->id
                    : $account->platform . ':' . $identity;
            })
            ->map(fn ($a) => $this->accountRow($a))->values();

        $posts = SocialMediaPost::query()->where('user_id', $uid)->orderByDesc('id')->limit(300)->get();

        $campaigns = SocialMediaCampaign::query()->where('user_id', $uid)->get()
            ->map(fn ($c) => [
                'id'    => $c->id,
                'name'  => $c->name,
                'audience' => $c->target_audience,
                'posts' => $posts->where('campaign_id', $c->id)->count(),
            ])->values();

        $companies = Company::query()->where('user_id', $uid)->get()
            ->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->name, 'industry' => $c->industry,
                'description' => $c->description, 'tagline' => $c->tagline,
                'website' => $c->website, 'instructions' => $c->specific_instructions,
            ])->values();

        $count = fn (string $s) => $posts->filter(fn ($p) => ($p->status?->value ?? $p->status) === $s)->count();

        $metrics = $posts->filter(fn ($p) => is_array($p->post_metrics) && $p->post_metrics !== []);

        return response()->json([
            'platforms' => $this->platformCatalog(),
            'accounts'  => $accounts,
            'posts'     => $posts->map(fn ($p) => $this->postRow($p))->values(),
            'campaigns' => $campaigns,
            'companies' => $companies,
            'stats'     => [
                'published' => $count('published'),
                'scheduled' => $count('scheduled'),
                'drafts'    => $count('draft'),
                'failed'    => $count('failed'),
                'withMetrics' => $metrics->count(),
                'engagement'  => $metrics->isEmpty() ? null : (int) $posts->sum('post_engagement_count'),
                'followers'   => $accounts->isEmpty() ? null : (int) $accounts->sum('followers'),
            ],
            'timezone'  => (string) (data_get(Auth::user(), 'timezone') ?: config('app.timezone')),
            'workerNote' => 'Scheduled posts are published by the Laravel scheduler (php artisan schedule:run, every 2 minutes).',
            'serverTime' => now()->toIso8601String(),
        ])->header('Cache-Control', 'no-store');
    }

    private function parseWhen(?string $iso): ?Carbon
    {
        if (! $iso) {
            return null;
        }

        return Carbon::parse($iso)->setTimezone(config('app.timezone'));
    }

    private function validateForPlatform(string $platform, string $content, array $images, ?string $video): ?string
    {
        $enum = PlatformEnum::tryFrom($platform);
        if (! $enum) {
            return 'Unknown platform.';
        }
        $limit = (int) data_get(config('social-media.' . $platform), 'requirements.text.limit', $enum->contentCharacterLength());
        if (trim($content) === '') {
            return 'Post text is required.';
        }
        if ($limit > 0 && mb_strlen($content) > $limit) {
            return ucfirst($platform) . ' allows at most ' . $limit . ' characters (you have ' . mb_strlen($content) . ').';
        }
        if ($platform === 'instagram' && ! $images) {
            return 'Instagram posts need at least one image.';
        }
        if (in_array($platform, self::VIDEO_ONLY, true) && ! $video) {
            return ucfirst($platform) . ' posts need a video.';
        }

        return null;
    }

    public function store(Request $request): JsonResponse
    {
        if ($denied = $this->guard()) {
            return $denied;
        }
        if (Helper::appIsDemo()) {
            return response()->json(['message' => 'This feature is disabled in demo mode.'], 403);
        }

        $data = $request->validate([
            'accountIds'   => 'required|array|min:1',
            'accountIds.*' => 'integer',
            'content'      => 'required|string',
            'mode'         => 'required|in:draft,schedule,now',
            'scheduledAt'  => 'nullable|string',
            'images'       => 'nullable|array',
            'images.*'     => 'string',
            'video'        => 'nullable|string',
            'campaignId'   => 'nullable|integer',
            'tone'         => 'nullable|string|max:60',
            'postType'     => 'nullable|in:post,story',
            'clientId'     => 'nullable|string|max:80',
            'variations'   => 'nullable|array',
            'variations.*.content' => 'nullable|string',
            'variations.*.images'  => 'nullable|array',
            'variations.*.images.*' => 'string',
            'variations.*.video'   => 'nullable|string',
        ]);

        // One click must produce exactly one operation, even if the request is retried.
        $idemKey = $data['clientId'] ?? null ? 'garad.social.store.' . Auth::id() . '.' . $data['clientId'] : null;
        if ($idemKey && Cache::has($idemKey)) {
            $ids = (array) Cache::get($idemKey);

            return response()->json([
                'posts' => SocialMediaPost::query()->where('user_id', Auth::id())->whereIn('id', $ids)->get()
                    ->map(fn ($p) => $this->postRow($p))->values(),
                'duplicate' => true,
            ]);
        }

        $accounts = SocialMediaPlatform::query()->where('user_id', Auth::id())->whereIn('id', $data['accountIds'])->get();
        if ($accounts->count() !== count(array_unique($data['accountIds']))) {
            return response()->json(['message' => 'One of the selected accounts does not belong to you.'], 403);
        }

        $images = array_values(array_filter($data['images'] ?? []));
        $when = $this->parseWhen($data['scheduledAt'] ?? null);

        if ($data['mode'] === 'schedule') {
            if (! $when) {
                return response()->json(['message' => 'Choose a date and time to schedule.'], 422);
            }
            if ($when->lte(now())) {
                return response()->json(['message' => 'The scheduled time must be in the future.'], 422);
            }
        }

        $plan = [];
        foreach ($accounts as $account) {
            $v = (array) ($data['variations'][(string) $account->id] ?? []);
            $plan[$account->id] = [
                'content' => filled($v['content'] ?? null) ? $v['content'] : $data['content'],
                'images'  => array_key_exists('images', $v) ? array_values(array_filter($v['images'] ?? [])) : $images,
                'video'   => array_key_exists('video', $v) ? ($v['video'] ?: null) : ($data['video'] ?? null),
            ];
        }

        if ($data['mode'] !== 'draft') {
            foreach ($accounts as $account) {
                $pl = $plan[$account->id];
                if ($err = $this->validateForPlatform($account->platform, $pl['content'], $pl['images'], $pl['video'])) {
                    return response()->json(['message' => $err], 422);
                }
            }
        }

        $created = [];
        foreach ($accounts as $account) {
            $pl = $plan[$account->id];
            $created[] = SocialMediaPost::query()->create([
                'user_id'                  => Auth::id(),
                'social_media_platform_id' => $account->id,
                'social_media_platform'    => $account->platform,
                'post_type'                => $data['postType'] ?? 'post',
                'campaign_id'              => $data['campaignId'] ?? null,
                'tone'                     => $data['tone'] ?? 'neutral',
                'content'                  => $pl['content'],
                'image'                    => $pl['images'][0] ?? null,
                'images'                   => $pl['images'] ?: null,
                'video'                    => $pl['video'],
                'is_repeated'              => 0,
                'status'                   => $data['mode'] === 'draft' ? StatusEnum::draft->value : StatusEnum::scheduled->value,
                'scheduled_at'             => $data['mode'] === 'now' ? now() : ($data['mode'] === 'schedule' ? $when : null),
            ]);
        }

        if ($idemKey) {
            Cache::put($idemKey, collect($created)->pluck('id')->all(), 600);
        }

        if ($data['mode'] === 'now') {
            foreach ($created as $post) {
                $this->publishNow($post);
            }
        }

        return response()->json([
            'posts' => collect($created)->map(fn ($p) => $this->postRow($p->fresh()))->values(),
        ], 201);
    }

    private function publishNow(SocialMediaPost $post): void
    {
        try {
            $driver = app(PublisherDriver::class)->setPost($post->load('platform'))->getDriver();
            if ($driver instanceof BasePublisherService) {
                $driver->publish();
            } else {
                $post->update(['status' => StatusEnum::failed->value]);
                SocialMediaSharedLog::query()->create([
                    'social_media_post_id' => $post->id,
                    'response'             => ['message' => 'No publisher is available for this platform.'],
                    'status'               => LogStatusEnum::failed,
                    'created_at'           => now(),
                ]);
            }
        } catch (Throwable $e) {
            report($e);
            $post->update(['status' => StatusEnum::failed->value]);
            SocialMediaSharedLog::query()->create([
                'social_media_post_id' => $post->id,
                'response'             => ['message' => 'The platform could not be reached.'],
                'status'               => LogStatusEnum::failed,
                'created_at'           => now(),
            ]);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->guard()) {
            return $denied;
        }
        $post = $this->ownPost($id);
        if (! $post) {
            return response()->json(['message' => 'Post not found.'], 404);
        }
        if (($post->status?->value ?? $post->status) === 'published') {
            return response()->json(['message' => 'Published posts cannot be edited.'], 422);
        }

        $data = $request->validate([
            'content'     => 'sometimes|string',
            'mode'        => 'sometimes|in:draft,schedule,now',
            'scheduledAt' => 'nullable|string',
            'images'      => 'nullable|array',
            'images.*'    => 'string',
            'video'       => 'nullable|string',
            'campaignId'  => 'nullable|integer',
        ]);

        $content = $data['content'] ?? $post->content;
        $images = array_key_exists('images', $data) ? array_values(array_filter($data['images'] ?? [])) : ($post->images ?: []);
        $video = array_key_exists('video', $data) ? $data['video'] : $post->video;
        $mode = $data['mode'] ?? null;
        $platform = $post->social_media_platform instanceof PlatformEnum ? $post->social_media_platform->value : (string) $post->social_media_platform;

        $fill = ['content' => $content, 'image' => $images[0] ?? null, 'images' => $images ?: null, 'video' => $video];
        if (array_key_exists('campaignId', $data)) {
            $fill['campaign_id'] = $data['campaignId'];
        }

        if ($mode && $mode !== 'draft') {
            if ($err = $this->validateForPlatform($platform, $content, $images, $video)) {
                return response()->json(['message' => $err], 422);
            }
        }

        if ($mode === 'schedule') {
            $when = $this->parseWhen($data['scheduledAt'] ?? null);
            if (! $when || $when->lte(now())) {
                return response()->json(['message' => 'Choose a future date and time.'], 422);
            }
            $fill += ['status' => StatusEnum::scheduled->value, 'scheduled_at' => $when];
        } elseif ($mode === 'draft') {
            $fill += ['status' => StatusEnum::draft->value, 'scheduled_at' => null];
        } elseif ($mode === 'now') {
            $fill += ['status' => StatusEnum::scheduled->value, 'scheduled_at' => now()];
        } elseif (array_key_exists('scheduledAt', $data) && ($when = $this->parseWhen($data['scheduledAt']))) {
            $fill['scheduled_at'] = $when;
        }

        $post->update($fill);

        if ($mode === 'now') {
            $this->publishNow($post->fresh());
        }

        return response()->json($this->postRow($post->fresh()));
    }

    public function destroy(int $id): JsonResponse
    {
        if ($denied = $this->guard()) {
            return $denied;
        }
        $post = $this->ownPost($id);
        if (! $post) {
            return response()->json(['message' => 'Post not found.'], 404);
        }
        $post->delete();

        return response()->json(['deleted' => $id]);
    }

    public function duplicate(int $id): JsonResponse
    {
        if ($denied = $this->guard()) {
            return $denied;
        }
        $post = $this->ownPost($id);
        if (! $post) {
            return response()->json(['message' => 'Post not found.'], 404);
        }
        $copy = $post->replicate();
        $copy->status = StatusEnum::draft->value;
        $copy->post_id = null;
        $copy->posted_at = null;
        $copy->scheduled_at = null;
        $copy->post_metrics = null;
        $copy->save();

        return response()->json($this->postRow($copy->fresh()), 201);
    }

    public function generate(Request $request): JsonResponse
    {
        if ($denied = $this->guard()) {
            return $denied;
        }

        $data = $request->validate([
            'platform'   => 'required|string',
            'content'    => 'required|string|max:4000',
            'tone'       => 'nullable|string|max:60',
            'language'   => 'nullable|string|max:40',
            'goal'       => 'nullable|string|max:120',
            'companyId'  => 'nullable|integer',
            'campaignId' => 'nullable|integer',
        ]);

        if (! PlatformEnum::tryFrom($data['platform'])) {
            return response()->json(['message' => 'Unknown platform.'], 422);
        }

        $brief = $data['content'];
        if (! empty($data['language'])) {
            $brief .= "\n\nWrite the post in " . $data['language'] . '.';
        }
        if (! empty($data['goal'])) {
            $brief .= "\nGoal of the post: " . $data['goal'] . '.';
        }

        $company = ! empty($data['companyId'])
            ? Company::query()->where('user_id', Auth::id())->find($data['companyId'])
            : null;

        $inner = Request::create('/', 'POST', [
            'platform'                => $data['platform'],
            'content'                 => $brief,
            'tone'                    => $data['tone'] ?? 'professional',
            'is_personalized_content' => $company || ! empty($data['campaignId']) ? 1 : 0,
            'selected_company'        => $company?->id,
            'campaign_id'             => $data['campaignId'] ?? null,
        ]);

        try {
            $res = app()->call([app(SocialMediaCampaignController::class), 'generate'], ['request' => $inner]);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'The AI assistant is unavailable right now. Please try again.'], 500);
        }

        if ($res instanceof JsonResponse) {
            $payload = $res->getData(true);
            if ($res->getStatusCode() >= 400 || isset($payload['type']) && $payload['type'] === 'error') {
                return response()->json(['message' => $payload['message'] ?? 'AI generation failed.'], $res->getStatusCode() >= 400 ? $res->getStatusCode() : 422);
            }

            return response()->json(['text' => trim((string) ($payload['result'] ?? ''))]);
        }

        return response()->json(['message' => 'Generation was not completed (check your credit balance).'], 402);
    }

    private const ACTIONS = [
        'generate'     => 'Write a polished, engaging social media post from the idea below.',
        'improve'      => 'Improve the writing of the post below: clearer, more engaging, same meaning and language.',
        'grammar'      => 'Fix grammar, spelling and punctuation only. Keep the wording, meaning and language.',
        'rewrite'      => 'Rewrite the post below with fresh wording. Keep the meaning and language.',
        'shorten'      => 'Make the post below shorter while keeping the key message.',
        'expand'       => 'Expand the post below with more useful detail while staying natural.',
        'professional' => 'Rewrite the post below in a professional tone.',
        'friendly'     => 'Rewrite the post below in a warm, friendly tone.',
        'marketing'    => 'Rewrite the post below as persuasive marketing copy with a clear benefit.',
        'educational'  => 'Rewrite the post below in an educational, informative tone.',
        'translate'    => 'Translate the post below. Keep emojis, hashtags and formatting.',
        'hashtags'     => 'Return only 5 to 10 relevant hashtags for the post below, separated by spaces.',
        'cta'          => 'Return the post below with a strong call to action added at the end.',
        'reply'        => 'Write a reply to the comment below on behalf of the brand. Be helpful and concise.',
        'reply_professional' => 'Write a professional reply to the comment below on behalf of the brand.',
        'reply_friendly'     => 'Write a friendly reply to the comment below on behalf of the brand.',
        'reply_short'        => 'Write a very short reply (one or two sentences) to the comment below on behalf of the brand.',
        'reply_thanks'       => 'Write a warm thank-you reply to the comment below on behalf of the brand.',
        'reply_answer'       => 'Answer the question in the comment below clearly. If the answer needs facts you do not have, invite them to contact the team instead of inventing details.',
        'reply_improve'      => 'Improve the draft reply below: clearer, polite, same meaning and language.',
    ];

    public function assist(Request $request): JsonResponse
    {
        if ($denied = $this->guard()) {
            return $denied;
        }

        $data = $request->validate([
            'action'    => 'required|string',
            'text'      => 'nullable|string|max:6000',
            'context'   => 'nullable|string|max:2000',
            'platform'  => 'nullable|string',
            'platforms' => 'nullable|array',
            'platforms.*' => 'string',
            'tone'      => 'nullable|string|max:60',
            'language'  => 'nullable|string|max:40',
            'companyId' => 'nullable|integer',
        ]);

        $action = $data['action'];
        $isVariations = $action === 'variations';
        if (! $isVariations && ! isset(self::ACTIONS[$action])) {
            return response()->json(['message' => 'This AI action is not available.'], 422);
        }
        if (trim((string) ($data['text'] ?? '')) === '') {
            return response()->json(['message' => 'Write something first so the assistant has context.'], 422);
        }
        if (Helper::appIsDemo()) {
            return response()->json(['message' => 'This feature is disabled in demo mode.'], 403);
        }

        $platformKeys = $isVariations
            ? array_values(array_filter($data['platforms'] ?? [], fn ($k) => PlatformEnum::tryFrom($k)))
            : array_filter([$data['platform'] ?? null], fn ($k) => $k && PlatformEnum::tryFrom($k));
        if ($isVariations && ! $platformKeys) {
            return response()->json(['message' => 'Select at least one account first.'], 422);
        }

        $limitFor = fn (string $k) => (int) data_get(config('social-media.' . $k), 'requirements.text.limit', PlatformEnum::from($k)->contentCharacterLength());

        $system = "You are the writing assistant inside Garad AI's social media manager. Return only the final text, with no explanations, quotes or labels. Never invent links, prices, statistics or contact details.";
        $company = ! empty($data['companyId']) ? Company::query()->where('user_id', Auth::id())->find($data['companyId']) : null;
        if ($company) {
            $system .= " Brand: {$company->name}. " . trim((string) $company->description) . ' ' . trim((string) $company->specific_instructions);
        }

        if ($isVariations) {
            $spec = collect($platformKeys)->map(fn ($k) => "\"{$k}\" (max " . $limitFor($k) . ' characters)')->implode(', ');
            $instruction = "Adapt the post below to each of these networks: {$spec}. Match each network's style and keep every version within its limit. "
                . 'Respond with a single JSON object whose keys are the network names exactly as given and whose values are the post text. No markdown fences.';
        } else {
            $instruction = self::ACTIONS[$action];
            if ($platformKeys) {
                $k = reset($platformKeys);
                $instruction .= " Target network: {$k}; stay within " . $limitFor($k) . ' characters including emojis and hashtags.';
            }
        }
        if (! empty($data['tone']) && in_array($action, ['generate', 'rewrite', 'improve'], true)) {
            $instruction .= " Tone: {$data['tone']}.";
        }
        if (! empty($data['language'])) {
            $instruction .= " Language: {$data['language']}.";
        }
        $user = $instruction . "\n\n" . (! empty($data['context']) ? "Original post or comment:\n{$data['context']}\n\n" : '') . "Text:\n{$data['text']}";

        try {
            $driver = Entity::driver(Helper::defaultWordModel());
            $driver->redirectIfNoCreditBalance();

            $out = trim(app(AiCompletionService::class)->complete($system, $user));
            if ($out === '') {
                throw new \RuntimeException('empty');
            }

            $driver->input($out)->calculateCredit()->decreaseCredit();
            Usage::getSingle()->updateWordCounts($driver->calculate());
        } catch (Throwable $e) {
            if (preg_match('/credit/i', $e->getMessage())) {
                return response()->json(['message' => "You don't have enough AI credits to generate this content."], 402);
            }
            report($e);

            return response()->json(['message' => 'The AI assistant is unavailable right now. Please try again in a moment.'], 503);
        }

        if ($isVariations) {
            $clean = trim(preg_replace('/^```(?:json)?|```$/m', '', $out));
            $json = json_decode($clean, true);
            if (! is_array($json)) {
                return response()->json(['message' => 'The AI could not produce platform variations. Please try again.'], 502);
            }
            $variations = [];
            foreach ($platformKeys as $k) {
                if (isset($json[$k]) && is_string($json[$k])) {
                    $variations[$k] = trim($json[$k]);
                }
            }

            return response()->json(['variations' => $variations]);
        }

        return response()->json(['text' => $out]);
    }

    public function storeCampaign(Request $request): JsonResponse
    {
        if ($denied = $this->guard()) {
            return $denied;
        }
        $data = $request->validate(['name' => 'required|string|max:120', 'audience' => 'required|string|max:500']);
        $c = SocialMediaCampaign::query()->create([
            'user_id' => Auth::id(), 'name' => $data['name'], 'target_audience' => $data['audience'],
        ]);

        return response()->json(['id' => $c->id, 'name' => $c->name, 'audience' => $c->target_audience, 'posts' => 0], 201);
    }

    public function destroyCampaign(int $id): JsonResponse
    {
        if ($denied = $this->guard()) {
            return $denied;
        }
        $c = SocialMediaCampaign::query()->where('user_id', Auth::id())->whereKey($id)->first();
        if (! $c) {
            return response()->json(['message' => 'Campaign not found.'], 404);
        }
        $c->delete();

        return response()->json(['deleted' => $id]);
    }

    public function disconnect(int $id): JsonResponse
    {
        if ($denied = $this->guard()) {
            return $denied;
        }
        $a = SocialMediaPlatform::query()->where('user_id', Auth::id())->whereKey($id)->first();
        if (! $a) {
            return response()->json(['message' => 'Account not found.'], 404);
        }
        $a->delete();

        return response()->json(['deleted' => $id]);
    }
}
