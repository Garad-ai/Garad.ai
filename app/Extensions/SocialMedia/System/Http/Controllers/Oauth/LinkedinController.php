<?php

namespace App\Extensions\SocialMedia\System\Http\Controllers\Oauth;

use App\Extensions\SocialMedia\System\Enums\PlatformEnum;
use App\Extensions\SocialMedia\System\Helpers\Linkedin;
use App\Extensions\SocialMedia\System\Models\SocialMediaPost;
use App\Extensions\SocialMedia\System\Models\SocialMediaPostDailyMetric;
use App\Extensions\SocialMedia\System\Models\SocialMediaPlatform;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class LinkedinController extends Controller
{
    public function __construct(public Linkedin $linkedin) {}

    public function redirect(Request $request): RedirectResponse
    {
        if (Helper::appIsDemo()) {
            return back()->with([
                'type'    => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        if (! setting('LINKEDIN_APP_ID') || ! setting('LINKEDIN_APP_SECRET')) {
            return back()->with([
                'type'    => 'error',
                'message' => 'Linkedin app id and secret not set. Please contact the administrator.',
            ]);
        }

        $state = Str::random(64);
        $scopes = array_values(array_unique(array_merge(
            (array) config('social-media.linkedin.scopes', []),
            (array) config('social-media.linkedin.organization_scopes', [])
        )));
        Cache::put($this->stateCacheKey($state), [
            'user_id'      => Auth::id(),
            'platform_id'  => $request->get('platform_id'),
            'popup_id'     => $request->string('garad_popup_id')->toString(),
            'opener_origin'=> $request->getSchemeAndHttpHost(),
            'requested_scopes' => $scopes,
        ], 600);

        return Linkedin::authRedirect($scopes, $state);
    }

    public function callback(Request $request)
    {
        $state = $request->string('state')->toString();
        $stateData = $state === '' ? null : Cache::pull($this->stateCacheKey($state));

        if (! $stateData || (int) data_get($stateData, 'user_id') < 1) {
            return $this->complete(null, 'error', 'LinkedIn authorization expired or could not be verified. Please try again.');
        }

        if ($request->filled('error') || ! $request->filled('code')) {
            return $this->complete($stateData, 'error', 'LinkedIn authorization was cancelled or denied.');
        }

        try {
            $tokenResponse = $this->linkedin->getAccessToken($request->string('code')->toString());
            if ($tokenResponse->failed() || ! $tokenResponse->json('access_token')) {
                Log::warning('LinkedIn OAuth token exchange failed.', [
                    'status' => $tokenResponse->status(),
                    'error' => $tokenResponse->json('error'),
                ]);

                $message = $tokenResponse->json('error') === 'invalid_client'
                    ? 'LinkedIn rejected the configured app credentials. Verify the Client ID and Client Secret for the same LinkedIn app, then try again.'
                    : 'LinkedIn did not issue an access token. Check the LinkedIn app permissions and try again.';

                return $this->complete($stateData, 'error', $message);
            }

            $tokenData = $tokenResponse->json();
            $accessToken = (string) data_get($tokenData, 'access_token');
            $expiresIn = max(1, (int) data_get($tokenData, 'expires_in', 3600));

            $this->linkedin->setToken($accessToken);
            $profileResponse = $this->linkedin->getAccountInfo();

            if ($profileResponse->failed()) {
                return $this->complete($stateData, 'error', 'LinkedIn authorized the app, but the member profile could not be loaded.');
            }
        } catch (Throwable $exception) {
            report($exception);

            return $this->complete($stateData, 'error', 'Unable to complete LinkedIn authorization. Please try again.');
        }

        $profile = $profileResponse->json();
        $memberId = (string) data_get($profile, 'sub');

        if ($memberId === '') {
            return $this->complete($stateData, 'error', 'LinkedIn did not return a member profile for this account.');
        }

        $organizations = [];
        $organizationError = null;
        $requestedScopes = (array) data_get($stateData, 'requested_scopes', config('social-media.linkedin.scopes', []));
        $organizationScopes = (array) config('social-media.linkedin.organization_scopes', []);

        if (! array_intersect($organizationScopes, ['r_organization_admin', 'rw_organization_admin'])) {
            $organizationError = 'Your LinkedIn account is connected, but this LinkedIn app does not currently have approved organization permissions for Page discovery. Request r_organization_admin or rw_organization_admin access in the LinkedIn Developer Portal, then add only the approved permission to LINKEDIN_ORGANIZATION_SCOPES.';
        } else {
            try {
                $aclResponse = $this->linkedin->getManagedOrganizations();

                if ($aclResponse->failed()) {
                    $errorCode = $aclResponse->json('serviceErrorCode')
                        ?? $aclResponse->json('code')
                        ?? $aclResponse->json('error');
                    $errorMessage = $aclResponse->json('message')
                        ?? $aclResponse->json('error_description');
                    Log::warning('LinkedIn organization discovery request failed.', [
                        'endpoint' => '/rest/organizationAcls',
                        'http_status' => $aclResponse->status(),
                        'error_code' => is_scalar($errorCode) ? (string) $errorCode : null,
                        'error_message' => $this->safeProviderMessage($errorMessage, $accessToken),
                        'requested_scopes' => $requestedScopes,
                    ]);

                    $organizationError = match ($aclResponse->status()) {
                        403 => 'Your LinkedIn account is connected, but this LinkedIn app does not currently have approved organization permissions for Page discovery. Request r_organization_admin or rw_organization_admin access in the LinkedIn Developer Portal.',
                        426 => 'LinkedIn rejected the organization API version. The integration now requests the current version; retry the connection. If this continues, check the safe LinkedIn API diagnostic in the server log.',
                        default => 'LinkedIn Page discovery failed (HTTP ' . $aclResponse->status() . '). Your personal profile can still be connected.',
                    };
                } else {
                    foreach ((array) $aclResponse->json('elements', []) as $acl) {
                        if (data_get($acl, 'state') !== 'APPROVED') {
                            continue;
                        }

                        $organizationUrn = (string) (data_get($acl, 'organization')
                            ?? data_get($acl, 'organizationTarget')
                            ?? '');
                        if (! preg_match('/^urn:li:organization:(\d+)$/', $organizationUrn, $matches)) {
                            continue;
                        }

                        $role = (string) data_get($acl, 'role', 'UNKNOWN');
                        $organizationResponse = $this->linkedin->getOrganization($matches[1]);
                        if ($organizationResponse->failed()) {
                            $errorCode = $organizationResponse->json('serviceErrorCode')
                                ?? $organizationResponse->json('code')
                                ?? $organizationResponse->json('error');
                            $errorMessage = $organizationResponse->json('message')
                                ?? $organizationResponse->json('error_description');
                            Log::warning('LinkedIn organization details request failed.', [
                                'endpoint' => '/rest/organizations/' . $matches[1],
                                'http_status' => $organizationResponse->status(),
                                'error_code' => is_scalar($errorCode) ? (string) $errorCode : null,
                                'error_message' => $this->safeProviderMessage($errorMessage, $accessToken),
                                'requested_scopes' => $requestedScopes,
                            ]);
                            continue;
                        }

                        $organization = $organizationResponse->json();
                        $name = trim((string) (data_get($organization, 'localizedName')
                            ?? data_get($organization, 'name.localized.en_US')
                            ?? data_get($organization, 'name.localizedName')
                            ?? ''));
                        if ($name === '') {
                            continue;
                        }

                        $vanityName = (string) data_get($organization, 'vanityName', '');
                        $logo = data_get($organization, 'logoV2.original~.elements.0.identifiers.0.identifier')
                            ?? data_get($organization, 'logoV2.cropped~.elements.0.identifiers.0.identifier');
                        $canPublishRole = in_array($role, [
                            'ADMINISTRATOR',
                            'CONTENT_ADMIN',
                            'CONTENT_ADMINISTRATOR',
                            'DIRECT_SPONSORED_CONTENT_POSTER',
                        ], true);
                        $canPublish = $canPublishRole && in_array('w_organization_social', $requestedScopes, true);
                        $canReadSocial = in_array('r_organization_social', $requestedScopes, true);
                        $canManage = $role === 'ADMINISTRATOR'
                            && in_array('rw_organization_admin', $requestedScopes, true);

                        $organizations[] = [
                            'urn' => $organizationUrn,
                            'id' => $matches[1],
                            'name' => $name,
                            'vanity_name' => $vanityName,
                            'url' => $vanityName !== '' ? 'https://www.linkedin.com/company/' . rawurlencode($vanityName) . '/' : null,
                            'picture' => is_string($logo) && preg_match('/^https?:\/\//i', $logo) ? $logo : null,
                            'role' => $role,
                            'state' => (string) data_get($acl, 'state'),
                            'can_read' => in_array('r_organization_admin', $requestedScopes, true)
                                || in_array('rw_organization_admin', $requestedScopes, true),
                            'can_read_social' => $canReadSocial,
                            'can_manage' => $canManage,
                            'can_publish' => $canPublish,
                            'followers' => null,
                            'metadata' => $organization,
                        ];
                    }

                    if ($organizations === []) {
                        $organizationError = 'No LinkedIn Pages that this member can administer were returned by LinkedIn. Only organizations with approved roles are shown.';
                    }
                }
            } catch (Throwable $exception) {
                report($exception);
                $organizationError = 'LinkedIn Page discovery could not be completed. Your personal profile can still be connected.';
            }
        }

        $selectionKey = Str::random(48);
        Cache::put($this->selectionCacheKey($selectionKey), [
            'user_id'        => (int) data_get($stateData, 'user_id'),
            'platform_id'    => data_get($stateData, 'platform_id'),
            'popup_id'       => data_get($stateData, 'popup_id'),
            'opener_origin'  => data_get($stateData, 'opener_origin'),
            'access_token'   => $accessToken,
            'token_data'     => $tokenData,
            'requested_scopes' => $requestedScopes,
            'expires_in'     => $expiresIn,
            'profile'        => $profile,
            'organizations'  => $organizations,
        ], 600);

        return response()->view('social-media::oauth.linkedin-select', [
            'selectionKey'       => $selectionKey,
            'profile'            => $profile,
            'organizations'      => $organizations,
            'organizationError'  => $organizationError,
            'requestedScopes'    => $requestedScopes,
        ]);
    }

    public function selectAccount(Request $request)
    {
        $request->validate([
            'selection_key' => ['required', 'string'],
            'account'       => ['required', 'string'],
        ]);

        $selectionKey = $request->string('selection_key')->toString();
        $selection = Cache::pull($this->selectionCacheKey($selectionKey));

        if (! $selection || (int) data_get($selection, 'user_id') < 1) {
            return $this->complete(null, 'error', 'The LinkedIn account selection expired. Please connect again.');
        }

        $userId = (int) data_get($selection, 'user_id');
        $accountChoice = $request->string('account')->toString();
        $profile = (array) data_get($selection, 'profile', []);

        if ($accountChoice === 'profile') {
            $authorUrn = 'urn:li:person:' . (string) data_get($profile, 'sub');
            $account = [
                'id'        => (string) data_get($profile, 'sub'),
                'name'      => (string) data_get($profile, 'name', 'LinkedIn Profile'),
                'username'  => (string) data_get($profile, 'email', ''),
                'url'       => null,
                'picture'   => data_get($profile, 'picture'),
                'type'      => 'profile',
                'role'      => 'MEMBER',
                'can_read'  => true,
                'can_read_social' => false,
                'can_manage' => false,
                'can_publish' => in_array('w_member_social', (array) data_get($selection, 'requested_scopes', []), true),
                'followers' => $this->fetchFollowersCount((string) data_get($profile, 'sub')),
            ];
        } else {
            $account = collect((array) data_get($selection, 'organizations', []))
                ->firstWhere('urn', $accountChoice);

            if (! $account) {
                return $this->complete($selection, 'error', 'That LinkedIn Page was not returned by LinkedIn for this account.');
            }

            $authorUrn = (string) $account['urn'];
            $account['type'] = 'page';
        }

        $expiresAt = now()->addSeconds((int) data_get($selection, 'expires_in', 3600));
        $tokenData = (array) data_get($selection, 'token_data', []);
        $credentials = [
            'provider'                => PlatformEnum::linkedin->value,
            'account_type'            => $account['type'] === 'page' ? 'organization' : 'member',
            'platform_id'             => $account['id'],
            'provider_account_id'     => $account['id'],
            'provider_urn'            => $authorUrn,
            'author_urn'              => $authorUrn,
            'type'                    => $account['type'],
            'name'                    => $account['name'],
            'username'                => $account['username'] ?? $account['vanity_name'] ?? $account['name'],
            'vanity_name'             => $account['vanity_name'] ?? null,
            'profile_url'             => $account['url'] ?? null,
            'picture'                 => $account['picture'] ?? null,
            'access_token'            => data_get($selection, 'access_token'),
            'access_token_expire_at'  => $expiresAt,
            'scope'                   => data_get($tokenData, 'scope'),
            'scopes'                  => (array) (data_get($tokenData, 'scope')
                ? preg_split('/\s+/', (string) data_get($tokenData, 'scope'))
                : data_get($selection, 'requested_scopes', [])),
            'role'                    => $account['role'] ?? 'MEMBER',
            'can_read'                => (bool) ($account['can_read'] ?? true),
            'can_read_social'         => (bool) ($account['can_read_social'] ?? false),
            'can_manage'              => (bool) ($account['can_manage'] ?? false),
            'can_publish'             => (bool) ($account['can_publish'] ?? false),
            'metadata'                => [
                'organization_state' => $account['state'] ?? null,
                'organization_id' => $account['type'] === 'page' ? $account['id'] : null,
                'organization_urn' => $account['type'] === 'page' ? $authorUrn : null,
                'role' => $account['role'] ?? 'MEMBER',
                'vanity_name' => $account['vanity_name'] ?? null,
                'url' => $account['url'] ?? null,
                'organization' => $account['metadata'] ?? null,
            ],
        ];

        if ($refreshToken = data_get($tokenData, 'refresh_token')) {
            $credentials['refresh_token'] = $refreshToken;
            $credentials['refresh_token_expire_at'] = now()->addSeconds((int) data_get($tokenData, 'refresh_token_expires_in', 0));
        }

        $matchingAccounts = SocialMediaPlatform::query()
            ->where('user_id', $userId)
            ->where('platform', PlatformEnum::linkedin->value)
            ->get()
            ->filter(fn (SocialMediaPlatform $platform) => (string) data_get($platform->credentials, 'author_urn') === $authorUrn)
            ->sortBy('id')
            ->values();
        $updateId = data_get($selection, 'platform_id');
        $existing = $matchingAccounts->firstWhere('id', (int) $updateId)
            ?? $matchingAccounts->first();

        if ($existing) {
            $existingCredentials = (array) $existing->credentials;
            if (empty($credentials['refresh_token']) && isset($existingCredentials['refresh_token'])) {
                $credentials['refresh_token'] = $existingCredentials['refresh_token'];
                $credentials['refresh_token_expire_at'] = $existingCredentials['refresh_token_expire_at'] ?? null;
            }

            DB::transaction(function () use ($existing, $matchingAccounts, $credentials, $expiresAt, $account): void {
                $existing->update([
                    'credentials'     => array_merge($existing->credentials ?? [], $credentials),
                    'connected_at'    => now(),
                    'expires_at'      => $expiresAt,
                    'followers_count' => (int) ($account['followers'] ?? 0),
                ]);

                $duplicateIds = $matchingAccounts
                    ->reject(fn (SocialMediaPlatform $platform) => $platform->is($existing))
                    ->modelKeys();

                if ($duplicateIds === []) {
                    return;
                }

                SocialMediaPost::query()->whereIn('social_media_platform_id', $duplicateIds)
                    ->update(['social_media_platform_id' => $existing->id]);

                if (Schema::hasTable('ext_social_media_post_daily_metrics')) {
                    SocialMediaPostDailyMetric::query()->whereIn('social_media_platform_id', $duplicateIds)
                        ->update(['social_media_platform_id' => $existing->id]);
                }

                SocialMediaPlatform::query()->whereIn('id', $duplicateIds)->delete();
            });
        } else {
            SocialMediaPlatform::query()->create([
                'user_id'         => $userId,
                'platform'        => PlatformEnum::linkedin->value,
                'credentials'     => $credentials,
                'connected_at'    => now(),
                'expires_at'      => $expiresAt,
                'followers_count' => (int) ($account['followers'] ?? 0),
            ]);
        }

        return $this->complete($selection, 'success', 'LinkedIn ' . $account['type'] . ' connected successfully.');
    }

    private function complete(?array $context, string $status, string $message)
    {
        return response()->view('social-media::oauth.popup-complete', [
            'oauthStatus' => $status,
            'oauthMessage' => trans($message),
            'popupId' => data_get($context, 'popup_id'),
            'openerOrigin' => data_get($context, 'opener_origin'),
        ]);
    }

    private function fetchFollowersCount(?string $memberId): int
    {
        if (! $memberId) {
            return 0;
        }

        try {
            $response = $this->linkedin->getNetworkSize($memberId);
        } catch (Throwable $exception) {
            return 0;
        }

        if ($response->failed()) {
            return 0;
        }

        return (int) ($response->json('firstDegreeSize') ?? $response->json('value') ?? 0);
    }

    private function stateCacheKey(string $state): string
    {
        return 'linkedin.oauth.state.' . hash('sha256', $state);
    }

    private function selectionCacheKey(string $selectionKey): string
    {
        return 'linkedin.oauth.selection.' . hash('sha256', $selectionKey);
    }

    private function safeProviderMessage(mixed $message, string $accessToken): ?string
    {
        if (! is_string($message)) {
            return null;
        }

        $message = strip_tags($message);
        foreach ([$accessToken, (string) setting('LINKEDIN_APP_SECRET')] as $sensitiveValue) {
            if ($sensitiveValue !== '') {
                $message = str_replace($sensitiveValue, '[REDACTED]', $message);
            }
        }
        $message = preg_replace('/\bBearer\s+[A-Za-z0-9._~+\/=-]+/i', 'Bearer [REDACTED]', $message) ?? $message;

        return mb_substr($message, 0, 300);
    }
}
