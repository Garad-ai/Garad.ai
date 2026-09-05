<?php

namespace App\Extensions\MarketingBot\System\Http\Controllers\Telegram;

use App\Extensions\MarketingBot\System\Models\Telegram\TelegramGroup;
use App\Extensions\MarketingBot\System\Models\Telegram\TelegramGroupSubscriber;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class TelegramSubscriberController extends Controller
{
    public function index()
    {
        $items = TelegramGroupSubscriber::query()
            ->where('user_id', Auth::id())
            ->get();

        $groups = TelegramGroup::query()
            ->where('user_id', Auth::id())
            ->get();

        return view('marketing-bot::telegram-subscriber.index', [
            'items'  => $items,
            'groups' => $groups,
        ]);
    }

    public function destroy(TelegramGroupSubscriber $telegramSubscriber): JsonResponse
    {
        $telegramSubscriber->delete();

        return response()->json([
            'status'  => 'success',
            'message' => __('Contact deleted successfully'),
        ]);
    }
}
