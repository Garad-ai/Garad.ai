<?php

namespace App\Extensions\MarketingBot\System\Services\Telegram;

use App\Extensions\MarketingBot\System\Models\Telegram\TelegramBot;

class TelegramReplyService
{
    public TelegramBot $telegramBot;

    public function sendReply($request, $marketingConversation) {}

    public function setTelegramBot(TelegramBot $telegramBot): TelegramReplyService
    {
        $this->telegramBot = $telegramBot;

        return $this;
    }
}
