<?php

namespace App\Extensions\SocialMedia\System\Helpers;

use App\Extensions\SocialMedia\System\Enums\PlatformEnum;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class Youtube
{
    protected array $config;

    public function __construct(
        protected PlatformEnum $platform = PlatformEnum::youtube,
        ?array $config = null,
        protected ?string $accessToken = null,
        protected ?string $refreshToken = null,
    ) {
        $this->config = $config ?? config('social-media.' . $this->platform->value, []);

        $this->config['client_id'] = setting('YOUTUBE_CLIENT_ID', $this->config['client_id'] ?? null);
        $this->config['client_secret'] = setting('YOUTUBE_CLIENT_SECRET', $this->config['client_secret'] ?? null);
        $this->config['redirect_uri'] = secure_url($this->config['redirect_uri'] ?? url('/social-media/oauth/callback/' . $this->platform->value));
    }

    public function platform(): PlatformEnum
    {
        return $this->platform;
    }

    public function authorizationUrl(?string $state = null): string
    {
        $scopes = trim(collect($this->config['scope'] ?? [])->join(' '));

        $params = array_filter([
            'client_id'              => $this->config['client_id'],
            'redirect_uri'           => $this->config['redirect_uri'],
            'response_type'          => 'code',
            'scope'                  => $scopes,
            'access_type'            => 'offline',
            'include_granted_scopes' => 'true',
            'prompt'                 => 'consent',
            'state'                  => $state,
        ], fn ($value) => $value !== null && $value !== '');

        return ($this->config['auth_url'] ?? 'https://accounts.google.com/o/oauth2/v2/auth') . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public function getAccessToken(string $code): Response
    {
        return Http::asForm()->post($this->config['token_url'] ?? 'https://oauth2.googleapis.com/token', [
            'client_id'     => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'code'          => $code,
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => $this->config['redirect_uri'],
        ]);
    }

    public function refreshAccessToken(?string $refreshToken = null): Response
    {
        return Http::asForm()->post($this->config['token_url'] ?? 'https://oauth2.googleapis.com/token', [
            'client_id'     => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'refresh_token' => $refreshToken ?? $this->refreshToken,
            'grant_type'    => 'refresh_token',
        ]);
    }

    public function getChannelInfo(array $parts = ['snippet', 'statistics']): Response
    {
        return Http::withToken($this->accessToken ?? '')
            ->get(($this->config['api_url'] ?? 'https://www.googleapis.com/youtube/v3') . '/channels', [
                'part' => implode(',', $parts),
                'mine' => 'true',
            ]);
    }

    public function setAccessToken(?string $accessToken): static
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    public function setRefreshToken(?string $refreshToken): static
    {
        $this->refreshToken = $refreshToken;

        return $this;
    }

    public function configs(): array
    {
        return $this->config;
    }
}
