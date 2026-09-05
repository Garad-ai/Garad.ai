<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProNotion\System\Http\Controllers\OAuth;

use App\Extensions\AIChatPro\System\Connectors\Models\AIChatProConnector;
use App\Extensions\AIChatProNotion\System\OAuth\NotionOAuth;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class NotionOAuthController extends Controller
{
    private const CONNECTOR_TYPE = 'notion';

    public function __construct(private readonly NotionOAuth $oauth) {}

    public function redirect(Request $request): RedirectResponse
    {
        if (Helper::appIsDemo()) {
            return back()->with(['type' => 'error', 'message' => __('This feature is disabled in demo mode.')]);
        }

        if ($request->boolean('popup')) {
            session(['ai_chat_pro_notion_oauth_popup' => true]);
        }

        return redirect()->away($this->oauth->authorizationUrl(Auth::id()));
    }

    public function callback(Request $request): RedirectResponse|View
    {
        $isPopup = session()->pull('ai_chat_pro_notion_oauth_popup', false);

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

        $workspaceId = data_get($tokenData, 'workspace_id');
        $workspaceName = data_get($tokenData, 'workspace_name');
        $workspaceIcon = data_get($tokenData, 'workspace_icon');
        $botId = data_get($tokenData, 'bot_id');
        $ownerEmail = data_get($tokenData, 'owner.user.person.email');
        $ownerName = data_get($tokenData, 'owner.user.name')
            ?? data_get($profile, 'name');

        // Notion access tokens do not expire; store null so we don't hit the
        // TIMESTAMP column's 2038 ceiling. The model treats null expires_at as
        // permanently active.
        $expiresAt = null;

        $credentials = array_filter([
            'access_token'    => $accessToken,
            'token_type'      => data_get($tokenData, 'token_type'),
            'workspace_id'    => $workspaceId,
            'workspace_name'  => $workspaceName,
            'workspace_icon'  => $workspaceIcon,
            'bot_id'          => $botId,
            'owner_email'     => $ownerEmail,
            'name'            => $ownerName ?: $workspaceName,
            'email'           => $ownerEmail,
            'picture'         => $workspaceIcon,
        ], fn ($value) => $value !== null);

        $existing = AIChatProConnector::query()
            ->where('user_id', Auth::id())
            ->where('type', self::CONNECTOR_TYPE)
            ->first();

        if ($existing) {
            $existing->setCredentials($credentials);
            $existing->is_active = true;
            $existing->connected_at = now();
            $existing->expires_at = $expiresAt;
            $existing->save();

            $connector = $existing->fresh();
        } else {
            $connector = new AIChatProConnector;
            $connector->user_id = Auth::id();
            $connector->type = self::CONNECTOR_TYPE;
            $connector->setCredentials($credentials);
            $connector->is_active = true;
            $connector->connected_at = now();
            $connector->expires_at = $expiresAt;
            $connector->save();
        }

        if ($isPopup) {
            return $this->popupClose(true, 'Notion workspace connected successfully.', ['key' => self::CONNECTOR_TYPE]);
        }

        $accessUrl = route('dashboard.user.ai-chat-pro.connectors.access.show', $connector);

        return redirect()->to($accessUrl)->with(['type' => 'success', 'message' => __('Notion workspace connected successfully.')]);
    }

    private function popupClose(bool $success, string $message, ?array $connector): View
    {
        return view('ai-chat-pro::connectors.popup-close', [
            'success'   => $success,
            'message'   => __($message),
            'connector' => $connector,
        ]);
    }

    private function redirectWith(string $type, string $message): RedirectResponse
    {
        return back()->with(['type' => $type, 'message' => __($message)]);
    }
}
