<?php

namespace App\Extensions\MarketingBot\System\Http\Controllers\Telegram;

use App\Extensions\MarketingBot\System\Models\Telegram\TelegramGroup;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class TelegramGroupController extends Controller
{
    public function index()
    {
        return view('marketing-bot::telegram-group.index', [
            'items' => TelegramGroup::query()->where('user_id', Auth::id())->get(),
        ]);
    }

    public function destroy(TelegramGroup $telegramGroup): JsonResponse
    {
        $this->authorize('delete', $telegramGroup);

        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        $telegramGroup->delete();

        return response()->json([
            'status'  => 'success',
            'message' => __('Contact deleted successfully'),
        ]);
    }
}
