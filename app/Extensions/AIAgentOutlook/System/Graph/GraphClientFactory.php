<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProOutlook\System\Graph;

use App\Extensions\AIChatPro\System\Connectors\Models\AIChatProConnector;
use App\Extensions\AIChatPro\System\Events\ConnectorTokenInvalidated;
use App\Extensions\AIChatProOutlook\System\OAuth\OutlookOAuth;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class GraphClientFactory
{
    public function __construct(private readonly OutlookOAuth $oauth) {}

    public function make(AIChatProConnector $connector): PendingRequest
    {
        $accessToken = $connector->getCredential('access_token');
        $refreshToken = $connector->getCredential('refresh_token');
        $expiresAt = data_get($connector->credentials, 'expires_at');

        if (! $accessToken) {
            throw new RuntimeException('Outlook connector has no access token. Please reconnect.');
        }

        if ($expiresAt && now()->gte($expiresAt) && $refreshToken) {
            $tokenResponse = $this->oauth->refreshToken($refreshToken);

            if ($tokenResponse->successful()) {
                $tokenData = $tokenResponse->json();
                $accessToken = (string) data_get($tokenData, 'access_token', $accessToken);
                $expiresIn = (int) data_get($tokenData, 'expires_in', 3600);
                // Microsoft rotates refresh_token on every refresh and revokes the
                // previous one — must persist the new value or the next refresh fails.
                $newRefreshToken = (string) data_get($tokenData, 'refresh_token', '');

                $credentials = $connector->credentials ?? [];

                try {
                    $credentials['access_token'] = Crypt::encryptString($accessToken);
                } catch (Throwable) {
                    $credentials['access_token'] = $accessToken;
                }

                if ($newRefreshToken !== '') {
                    try {
                        $credentials['refresh_token'] = Crypt::encryptString($newRefreshToken);
                    } catch (Throwable) {
                        $credentials['refresh_token'] = $newRefreshToken;
                    }
                }

                $credentials['expires_at'] = now()->addSeconds($expiresIn)->toDateTimeString();

                $connector->update([
                    'credentials' => $credentials,
                    'expires_at'  => now()->addSeconds($expiresIn),
                ]);
            } else {
                Log::warning('[connectors] Outlook token refresh failed; marking connector inactive', [
                    'connector_id' => $connector->id,
                    'status'       => $tokenResponse->status(),
                ]);

                $connector->markInactive();
                ConnectorTokenInvalidated::dispatch($connector);

                throw new RuntimeException('Outlook connector authorization expired. Please reconnect.');
            }
        }

        return Http::withToken($accessToken)
            ->acceptJson()
            ->baseUrl('https://graph.microsoft.com/v1.0');
    }
}
