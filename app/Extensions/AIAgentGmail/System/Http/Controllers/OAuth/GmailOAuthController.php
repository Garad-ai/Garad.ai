<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentGmail\System\Http\Controllers\OAuth;

use App\Extensions\AIAgentGmail\System\Models\AIAgentConnector;
use App\Extensions\AIAgentGmail\System\OAuth\GmailOAuth;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class GmailOAuthController extends Controller
{
    public function __construct(private readonly GmailOAuth $oauth) {}

    public function redirect(Request $request): RedirectResponse
    {
        if (Helper::appIsDemo()) {
            return back()->with(['type' => 'error', 'message' => __('This feature is disabled in demo mode.')]);
        }

        if ($request->boolean('popup')) {
            session(['gmail_oauth_popup' => true]);
        }

        return redirect()->away($this->oauth->authorizationUrl(Auth::id()));
    }

    public function callback(Request $request): RedirectResponse|View
    {
        $isPopup = session()->pull('gmail_oauth_popup', false);

        if (Helper::appIsDemo()) {
            return $isPopup
                ? $this->popupClose(false, 'This feature is disabled in demo mode.', null)
                : $this->redirectWith('error', 'This feature is disabled in demo mode.');
        }

        $code = $request->get('code');
        $state = $request->get('state');

        if (! $code) {
            return $isPopup
                ? $this->popupClose(false, 'Something went wrong, please try again.', null)
                : $this->redirectWith('error', 'Something went wrong, please try again.');
        }

        try {
            $tokenData = $this->oauth->exchangeCode($code, (string) $state, Auth::id());
        } catch (Throwable $exception) {
            return $isPopup
                ? $this->popupClose(false, $exception->getMessage(), null)
                : $this->redirectWith('error', $exception->getMessage());
        }

        $accessToken = data_get($tokenData, 'access_token');

        if (! $accessToken) {
            return $isPopup
                ? $this->popupClose(false, 'Authorization failed, missing access token.', null)
                : $this->redirectWith('error', 'Authorization failed, missing access token.');
        }

        try {
            $profile = $this->oauth->getProfile($accessToken);
        } catch (Throwable) {
            $profile = [];
        }

        $refreshToken = data_get($tokenData, 'refresh_token');
        $expiresIn = (int) data_get($tokenData, 'expires_in', 3600);
        $expiresAt = now()->addSeconds($expiresIn);

        $credentials = array_filter([
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at'    => $expiresAt->toDateTimeString(),
            'token_type'    => data_get($tokenData, 'token_type'),
            'scope'         => data_get($tokenData, 'scope'),
            'email'         => data_get($profile, 'email'),
            'name'          => data_get($profile, 'name'),
            'picture'       => data_get($profile, 'picture'),
        ], fn ($value) => $value !== null);

        $existing = AIAgentConnector::query()
            ->where('user_id', Auth::id())
            ->where('type', 'gmail')
            ->where('credentials->email', data_get($profile, 'email'))
            ->first();

        if ($existing) {
            $existingCredentials = $existing->credentials ?? [];

            if (empty($credentials['refresh_token']) && isset($existingCredentials['refresh_token'])) {
                $credentials['refresh_token'] = $existingCredentials['refresh_token'];
            }

            $existing->update([
                'credentials'  => array_merge($existingCredentials, $credentials),
                'is_active'    => true,
                'connected_at' => now(),
                'expires_at'   => $expiresAt,
            ]);

            $connector = $existing->fresh();
        } else {
            $connector = AIAgentConnector::query()->create([
                'user_id'      => Auth::id(),
                'type'         => 'gmail',
                'credentials'  => $credentials,
                'is_active'    => true,
                'connected_at' => now(),
                'expires_at'   => $expiresAt,
            ]);
        }

        if ($isPopup) {
            $connectorData = [
                'id'            => $connector->id,
                'type'          => $connector->type,
                'name'          => $connector->getCredential('name'),
                'email'         => $connector->getCredential('email'),
                'picture'       => $connector->getCredential('picture'),
                'is_active'     => true,
                'reconnect_url' => route('dashboard.user.ai-agent.connectors.gmail.redirect'),
                'delete_url'    => route('dashboard.user.ai-agent.connectors.destroy', $connector),
            ];

            return $this->popupClose(true, 'Gmail account connected successfully.', $connectorData);
        }

        return $this->redirectWith('success', 'Gmail account connected successfully.');
    }

    private function popupClose(bool $success, string $message, ?array $connector): View
    {
        return view('ai-agent-gmail::oauth.popup-close', [
            'success'   => $success,
            'message'   => __($message),
            'connector' => $connector,
        ]);
    }

    private function redirectWith(string $type, string $message): RedirectResponse
    {
        return redirect()->route('dashboard.user.ai-agent.workflows.index')
            ->with(['type' => $type, 'message' => __($message)]);
    }
}
