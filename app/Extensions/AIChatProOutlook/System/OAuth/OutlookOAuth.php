<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProOutlook\System\OAuth;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class OutlookOAuth
{
    private const GRAPH_PROFILE_URL = 'https://graph.microsoft.com/v1.0/me';

    protected array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? config('ai-chat-pro-outlook', []);

        foreach (['client_id', 'client_secret', 'redirect_uri'] as $key) {
            $value = setting('ai_chat_pro_outlook_' . $key);
            if (! empty($value)) {
                $this->config[$key] = $value;
            }
        }

        // The settings UI stores the tenant under the key `tenant`, but every
        // Microsoft endpoint here reads `tenant_id`. Map between the two so
        // admin overrides aren't silently ignored.
        $tenantOverride = setting('ai_chat_pro_outlook_tenant');
        if (! empty($tenantOverride)) {
            $this->config['tenant_id'] = $tenantOverride;
        }
    }

    public function authorizationUrl(int $userId): string
    {
        $state = $this->cacheState($userId);
        $tenant = $this->config['tenant_id'] ?? 'common';
        $authUrl = "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/authorize";

        $params = array_filter([
            'client_id'     => $this->config['client_id'] ?? null,
            'redirect_uri'  => $this->redirectUri(),
            'response_type' => 'code',
            'scope'         => implode(' ', $this->config['scopes'] ?? []),
            'response_mode' => 'query',
            'state'         => $state,
        ], fn ($value) => $value !== null && $value !== '');

        return $authUrl . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeCode(string $code, string $state, int $userId): array
    {
        $this->validateState($state, $userId);

        $tenant = $this->config['tenant_id'] ?? 'common';
        $tokenUrl = "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token";

        $response = Http::asForm()->post($tokenUrl, [
            'client_id'     => $this->config['client_id'] ?? null,
            'client_secret' => $this->config['client_secret'] ?? null,
            'code'          => $code,
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => $this->redirectUri(),
        ])->throw();

        return $response->json();
    }

    public function refreshToken(string $refreshToken): Response
    {
        $tenant = $this->config['tenant_id'] ?? 'common';
        $tokenUrl = "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token";

        return Http::asForm()->post($tokenUrl, [
            'client_id'     => $this->config['client_id'] ?? null,
            'client_secret' => $this->config['client_secret'] ?? null,
            'refresh_token' => $refreshToken,
            'grant_type'    => 'refresh_token',
        ]);
    }

    /**
     * Microsoft does not expose a generic OAuth token revocation endpoint for refresh
     * tokens issued via the v2 endpoint. The closest server-side equivalent is
     * `me/revokeSignInSessions`, which requires an active access token and the
     * `User.RevokeSessions.All` permission. We attempt it best-effort.
     */
    public function revokeToken(string $accessToken): void
    {
        Http::withToken($accessToken)
            ->post('https://graph.microsoft.com/v1.0/me/revokeSignInSessions');
    }

    public function getProfile(string $accessToken): array
    {
        return Http::withToken($accessToken)
            ->get(self::GRAPH_PROFILE_URL)
            ->throw()
            ->json();
    }

    private function cacheState(int $userId): string
    {
        $state = (string) Str::uuid();

        cache()->put($this->stateCacheKey($state), ['user_id' => $userId], 600);

        return $state;
    }

    private function validateState(string $state, int $userId): void
    {
        $payload = cache()->pull($this->stateCacheKey($state));

        if (! $payload || (int) data_get($payload, 'user_id') !== $userId) {
            throw new RuntimeException('Invalid or expired OAuth state.');
        }
    }

    private function stateCacheKey(string $state): string
    {
        return 'ai_chat_pro_outlook.oauth.state.' . $state;
    }

    private function redirectUri(): string
    {
        return $this->config['redirect_uri']
            ?? secure_url(route('dashboard.user.ai-chat-pro.connectors.outlook.callback', [], false));
    }
}
