<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProGoogleCalendar\System\Calendar;

use App\Extensions\AIChatPro\System\Connectors\Models\AIChatProConnector;
use App\Extensions\AIChatPro\System\Events\ConnectorTokenInvalidated;
use App\Extensions\AIChatProGoogleCalendar\System\OAuth\GoogleCalendarOAuth;
use Google\Service\Calendar;
use Google_Client;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class CalendarClientFactory
{
    public function __construct(private readonly GoogleCalendarOAuth $oauth) {}

    public function make(AIChatProConnector $connector): Calendar
    {
        $accessToken = $connector->getCredential('access_token');
        $refreshToken = $connector->getCredential('refresh_token');
        $expiresAt = data_get($connector->credentials, 'expires_at');

        if (! $accessToken) {
            throw new RuntimeException('Google Calendar connector has no access token. Please reconnect.');
        }

        if ($expiresAt && now()->gte($expiresAt) && $refreshToken) {
            $tokenResponse = $this->oauth->refreshToken($refreshToken);

            if ($tokenResponse->successful()) {
                $tokenData = $tokenResponse->json();
                $accessToken = data_get($tokenData, 'access_token', $accessToken);
                $expiresIn = (int) data_get($tokenData, 'expires_in', 3600);

                $credentials = $connector->credentials ?? [];

                try {
                    $credentials['access_token'] = Crypt::encryptString((string) $accessToken);
                } catch (Throwable) {
                    $credentials['access_token'] = $accessToken;
                }
                $credentials['expires_at'] = now()->addSeconds($expiresIn)->toDateTimeString();

                $connector->update([
                    'credentials' => $credentials,
                    'expires_at'  => now()->addSeconds($expiresIn),
                ]);
            } else {
                Log::warning('[connectors] Google Calendar token refresh failed; marking connector inactive', [
                    'connector_id' => $connector->id,
                    'status'       => $tokenResponse->status(),
                ]);

                $connector->markInactive();
                ConnectorTokenInvalidated::dispatch($connector);

                throw new RuntimeException('Google Calendar connector authorization expired. Please reconnect.');
            }
        }

        $client = new Google_Client;
        $client->setClientId(setting('ai_chat_pro_google_calendar_client_id') ?: config('ai-chat-pro-google-calendar.client_id'));
        $client->setClientSecret(setting('ai_chat_pro_google_calendar_client_secret') ?: config('ai-chat-pro-google-calendar.client_secret'));
        $client->setAccessToken(['access_token' => $accessToken]);

        return new Calendar($client);
    }
}
