<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentGmail\System\Gmail;

use App\Extensions\AIAgentGmail\System\Models\AIAgentConnector;
use App\Extensions\AIAgentGmail\System\OAuth\GmailOAuth;
use Google\Service\Gmail;
use Google_Client;
use RuntimeException;

class GmailClientFactory
{
    public function __construct(private readonly GmailOAuth $oauth) {}

    public function make(int $userId): Gmail
    {
        $connector = AIAgentConnector::query()
            ->where('user_id', $userId)
            ->where('type', 'gmail')
            ->where('is_active', true)
            ->firstOrFail();

        $credentials = $connector->credentials ?? [];
        $accessToken = data_get($credentials, 'access_token');
        $refreshToken = data_get($credentials, 'refresh_token');
        $expiresAt = data_get($credentials, 'expires_at');

        if (! $accessToken) {
            throw new RuntimeException('Gmail connector has no access token. Please reconnect.');
        }

        if ($expiresAt && now()->gte($expiresAt) && $refreshToken) {
            $tokenResponse = $this->oauth->refreshToken($refreshToken);

            if ($tokenResponse->successful()) {
                $tokenData = $tokenResponse->json();
                $accessToken = data_get($tokenData, 'access_token', $accessToken);
                $expiresIn = (int) data_get($tokenData, 'expires_in', 3600);

                $newCredentials = array_merge($credentials, [
                    'access_token' => $accessToken,
                    'expires_at'   => now()->addSeconds($expiresIn)->toDateTimeString(),
                ]);

                $connector->update(['credentials' => $newCredentials, 'expires_at' => now()->addSeconds($expiresIn)]);
            }
        }

        $client = new Google_Client;
        $client->setClientId(setting('google_client_id'));
        $client->setClientSecret(setting('google_client_secret'));
        $client->setAccessToken(['access_token' => $accessToken]);

        return new Gmail($client);
    }
}
