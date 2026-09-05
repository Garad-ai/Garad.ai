<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProGmail\System\OAuth;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class GmailOAuth
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const USERINFO_URL = 'https://www.googleapis.com/oauth2/v3/userinfo';

    protected array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? config('ai-chat-pro-gmail', []);

        // Admin-managed settings override env-driven config.
        foreach (['client_id', 'client_secret', 'redirect_uri'] as $key) {
            $value = setting('ai_chat_pro_gmail_' . $key);
            if (! empty($value)) {
                $this->config[$key] = $value;
            }
        }
    }

    public function authorizationUrl(int $userId): string
    {
        $state = $this->cacheState($userId);

        $params = array_filter([
            'client_id'              => $this->config['client_id'] ?? null,
            'redirect_uri'           => $this->redirectUri(),
            'response_type'          => 'code',
            'scope'                  => implode(' ', $this->config['scopes'] ?? []),
            'access_type'            => 'offline',
            'include_granted_scopes' => 'true',
            'prompt'                 => 'consent',
            'state'                  => $state,
        ], fn ($value) => $value !== null && $value !== '');

        return self::AUTH_URL . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeCode(string $code, string $state, int $userId): array
    {
        $this->validateState($state, $userId);

        $response = Http::asForm()->post(self::TOKEN_URL, [
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
        return Http::asForm()->post(self::TOKEN_URL, [
            'client_id'     => $this->config['client_id'] ?? null,
            'client_secret' => $this->config['client_secret'] ?? null,
            'refresh_token' => $refreshToken,
            'grant_type'    => 'refresh_token',
        ]);
    }

    public function revokeToken(string $token): void
    {
        Http::asForm()->post('https://oauth2.googleapis.com/revoke', [
            'token' => $token,
        ]);
    }

    public function getProfile(string $accessToken): array
    {
        return Http::withToken($accessToken)
            ->get(self::USERINFO_URL)
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
        return 'ai_chat_pro_gmail.oauth.state.' . $state;
    }

    private function redirectUri(): string
    {
        return $this->config['redirect_uri']
            ?? secure_url(route('dashboard.user.ai-chat-pro.connectors.gmail.callback', [], false));
    }
}
