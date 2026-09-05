<?php

namespace App\Extensions\SocialMedia\System\Services\Token;

use App\Extensions\SocialMedia\System\Helpers\X;
use App\Extensions\SocialMedia\System\Models\SocialMediaPlatform;

class XRefreshAccessToken
{
    public SocialMediaPlatform $platform;

    public function generate(): bool
    {
        $platform = $this->platform;

        $credentials = $platform->credentials;

        $refreshToken = $platform->credentials['refresh_token'] ?: '';

        if (empty($refreshToken)) {
            return false;
        }

        $response = app(X::class)->refreshAccessToken($refreshToken);

        if ($response->successful()) {
            $credentials = array_merge($credentials, [
                'access_token'           => $response->json('access_token'),
                'access_token_expire_at' => (string) now()->addHours(2),

                'refresh_token'           => $response->json('refresh_token'),
                'refresh_token_expire_at' => (string) now()->addHours(2),
            ]);

            $platform->update([
                'credentials' => $credentials,
                'expires_at'  => (string) now()->addHours(2),
            ]);

            return true;
        }

        return false;
    }

    public function setPlatform(SocialMediaPlatform $platform): XRefreshAccessToken
    {
        $this->platform = $platform;

        return $this;
    }
}
