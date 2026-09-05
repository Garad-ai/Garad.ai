<?php

namespace App\Extensions\SocialMedia\System\Console\Commands;

use App\Extensions\SocialMedia\System\Helpers\Facebook;
use App\Extensions\SocialMedia\System\Helpers\Instagram;
use App\Extensions\SocialMedia\System\Helpers\Linkedin;
use App\Extensions\SocialMedia\System\Helpers\X;
use App\Extensions\SocialMedia\System\Models\SocialMediaPlatform;
use Illuminate\Console\Command;

class RefreshTokenCommand extends Command
{
    protected $signature = 'app:social-media-access-token-command';

    protected $description = 'Publish scheduled social media posts';

    public function handle(): void
    {
        $tokens = SocialMediaPlatform::query()
            ->where('expires_at', '<', now()->addHours(12)->format('Y-m-d h:i:s'))
            ->get();

        foreach ($tokens as $token) {
            $this->refreshAccessToken($token);
        }
    }

    public function refreshAccessToken(SocialMediaPlatform $platform)
    {
        $credentials = $platform->credentials;

        if (isset($credentials['refresh_token'])) {
            $accessToken = $credentials['refresh_token'] ?: '';
        } else {
            $accessToken = $credentials['access_token'] ?: '';
        }

        $platformName = $platform->platform;

        if ($platformName === 'facebook') {
            $facebook = new Facebook(accessToken: $accessToken);
            $response = $facebook->refreshAccessToken($accessToken);

            if ($response->successful()) {
                $newAccessToken = $response->json('access_token');

                $platform->update([
                    'credentials'           => array_merge($credentials, [
                        'access_token'           => $newAccessToken,
                        'access_token_expire_at' => now()->addMonths(2),
                    ]),
                    'expires_at'            => now()->addMonths(2),
                ]);
            }
        }
        if ($platformName === 'instagram') {
            $instagram = new Instagram(accessToken: $accessToken);
            $response = $instagram->refreshAccessToken($accessToken);
            if ($response->successful()) {
                $tokenData = $response->json();
                $platform->update([
                    'credentials'           => array_merge($credentials, [
                        'access_token'           => $tokenData['access_token'],
                        'access_token_expire_at' => now()->addMonths(2),
                    ]),
                    'expires_at'            => now()->addMonths(2),
                ]);
            }
        }

        if ($platformName === 'linkedin') {
            $linkedin = new Linkedin(accessToken: $accessToken);
            $response = $linkedin->refreshAccessToken($accessToken);

            if ($response->successful()) {
                $tokenData = $response->json();
                $credentials = array_merge($credentials, [
                    'access_token'           => $tokenData['access_token'],
                    'access_token_expire_at' => now()->seconds($tokenData['expires_in']),

                    'refresh_token'           => $tokenData['refresh_token'],
                    'refresh_token_expire_at' => now()->seconds($tokenData['refresh_token_expires_in']),
                ]);
                $platform->update([
                    'credentials' => $credentials,
                    'expires_at'  => now()->seconds($tokenData['expires_in']),
                ]);
            }
        }

        if ($platformName === 'x') {
            $twitter = new X(accessToken: $accessToken);
            $response = $twitter->refreshAccessToken($accessToken);

            if ($response->successful()) {
                $credentials = array_merge($credentials, [
                    'access_token'           => $response->json('data.access_token'),
                    'access_token_expire_at' => now()->addHours(2),

                    'refresh_token'           => $response->json('data.refresh_token'),
                    'refresh_token_expire_at' => now()->addHours(2),
                ]);

                $platform->update([
                    'credentials' => $credentials,
                    'expires_at'  => now()->addHours(2),
                ]);

                return true;
            }
        }
    }
}
