<?php

namespace App\Extensions\MarketingBot\System\Http\Controllers\Setting;

use App\Extensions\MarketingBot\System\Models\Telegram\TelegramBot;
use App\Extensions\MarketingBot\System\Models\Whatsapp\WhatsappChannel;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ViewSettingController extends Controller
{
    public function __invoke(Request $request)
    {
        return view('marketing-bot::settings.index', [
            'telegram' => TelegramBot::query()->where('user_id', Auth::id())->first(),
            'whatsapp' => WhatsappChannel::query()->where('user_id', Auth::id())->first(),
        ]);
    }
}
