<?php

namespace App\Extensions\MarketingBot\System\Http\Controllers\Setting;

use App\Extensions\MarketingBot\System\Models\Telegram\TelegramBot;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Telegram\Bot\Api;

class TelegramSettingController extends Controller
{
    public function __invoke(Request $request)
    {
        if (Helper::appIsDemo()) {
            return back()->with([
                'status'  => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        $request->validate(['access_token' => 'required|string']);

        try {
            $webhook = route('api.marketing-bot.telegram.webhook', base64_encode($request['access_token']));

            $telegram = new Api($request['access_token']);

            // Example usage
            $response = $telegram->getMe();

            if (! $response) {
                throw ValidationException::withMessages([
                    'access_token' => __('The provided access token is invalid or the bot is not accessible.'),
                ]);
            }

            $data = $response->jsonSerialize();

            TelegramBot::query()->updateOrCreate([
                'user_id' => Auth::id(),
            ], [
                'access_token'     => $request['access_token'],
                'is_connected'     => true,
                'webhook_verified' => true,
                'bot_id'           => $data['id'],
                'name'             => $data['first_name'],
                'username'         => $data['username'],
                'scopes'           => [
                    'can_join_groups' => true,
                ],
            ]);

            $telegram->setWebhook([
                'url' => $webhook,
            ]);

            return back()->with([
                'status'  => 'success',
                'message' => __('Telegram bot settings updated successfully.'),
                'type'    => 'success',
            ]);
        } catch (Exception $exception) {
            return back()->with([
                'status'  => 'error',
                'message' => __('Failed to connect to Telegram bot. Please check the access token.'),
                'type'    => 'error',
            ]);
        }

    }
}
