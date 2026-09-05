<?php

namespace App\Extensions\MarketingBot\System\Services\Telegram\Traits;

use App\Extensions\MarketingBot\System\Models\Telegram\TelegramBot;
use Illuminate\Http\Request;

trait HasIsAdmin
{
    public function groupAdmin(Request $request, TelegramBot $telegramBot): bool
    {
        $isBot = $request->input('message.new_chat_member.user.is_bot', 0);

        $chatType = $request->input('message.chat.type', '');

        $username = $request->input('message.new_chat_member.user.username', '');

        return $isBot &&
            ($chatType === 'group' || $chatType === 'supergroup') &&
            $username === $telegramBot->getAttribute('username');
    }
}
