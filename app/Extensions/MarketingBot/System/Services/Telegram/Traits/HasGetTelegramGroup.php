<?php

namespace App\Extensions\MarketingBot\System\Services\Telegram\Traits;

use App\Extensions\MarketingBot\System\Models\Telegram\TelegramBot;
use App\Extensions\MarketingBot\System\Models\Telegram\TelegramGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait HasGetTelegramGroup
{
    public function getTelegramGroup(
        TelegramBot $telegramBot,
        ?string $chatId = null
    ): Model|Builder|null {
        $telegramBotId = $telegramBot->getKey();

        $supergroupSubscriberId = $chatId . '-' . $telegramBotId;

        $telegramGroup = TelegramGroup::query()
            ->where('supergroup_subscriber_id', $supergroupSubscriberId)
            ->where('user_id', $telegramBot->getAttribute('user_id'))
            ->first();

        if ($telegramGroup) {
            return $telegramGroup;
        }

        return null;
    }
}
