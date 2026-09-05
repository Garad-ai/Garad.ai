<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProNotion\System\OAuth;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class NotionOAuth
{
    private const AUTH_URL = 'https://api.notion.com/v1/oauth/authorize';

    private const TOKEN_URL = 'https://api.notion.com/v1/oauth/token';

    private const ME_URL = 'https://api.notion.com/v1/users/me';

    private const NOTION_VERSION = '2022-06-28';

    protected array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? config('ai-chat-pro-notion', []);

        foreach (['client_id', 'client_secret', 'redirect_uri'] as $key) {
            $value = setting('ai_chat_pro_notion_' . $key);
            if (! empty($value)) {
                $this->config[$key] = $value;
            }
        }
    }

    public function authorizationUrl(int $userId): string
    {
        $state = $this->cacheState($userId);

        $params = array_filter([
            'client_id'     => $this->config['client_id'] ?? null,
            'redirect_uri'  => $this->redirectUri(),
            'response_type' => 'code',
            'owner'         => 'user',
            'state'         => $state,
        ], fn ($value) => $value !== null && $value !== '');

        return self::AUTH_URL . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeCode(string $code, string $state, int $userId): array
    {
        $this->validateState($state, $userId);

        $clientId = (string) ($this->config['client_id'] ?? '');
        $clientSecret = (string) ($this->config['client_secret'] ?? '');

        $response = Http::withHeaders([
            'Authorization'  => 'Basic ' . base64_encode($clientId . ':' . $clientSecret),
            'Notion-Version' => self::NOTION_VERSION,
            'Accept'         => 'application/json',
        ])->asJson()->post(self::TOKEN_URL, [
            'grant_type'   => 'authorization_code',
            'code'         => $code,
            'redirect_uri' => $this->redirectUri(),
        ])->throw();

        return $response->json();
    }

    public function getProfile(string $accessToken): array
    {
        return Http::withToken($accessToken)
            ->withHeaders(['Notion-Version' => self::NOTION_VERSION])
            ->get(self::ME_URL)
            ->throw()
            ->json();
    }

    public function revokeToken(string $token): void
    {
        $clientId = (string) ($this->config['client_id'] ?? '');
        $clientSecret = (string) ($this->config['client_secret'] ?? '');

        Http::withHeaders([
            'Authorization'  => 'Basic ' . base64_encode($clientId . ':' . $clientSecret),
            'Notion-Version' => self::NOTION_VERSION,
            'Accept'         => 'application/json',
        ])->asJson()->post('https://api.notion.com/v1/oauth/revoke', [
            'token'           => $token,
            'token_type_hint' => 'access_token',
        ]);
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
        return 'ai_chat_pro_notion.oauth.state.' . $state;
    }

    private function redirectUri(): string
    {
        return $this->config['redirect_uri']
            ?? secure_url(route('dashboard.user.ai-chat-pro.connectors.notion.callback', [], false));
    }
}
