<?php

namespace App\Extensions\MarketingBot\System\Services\Telegram;

use App\Extensions\MarketingBot\System\Models\Telegram\TelegramBot;
use App\Extensions\MarketingBot\System\Models\Telegram\TelegramContact;
use App\Extensions\MarketingBot\System\Models\Telegram\TelegramGroup;
use Illuminate\Http\Request;

class TelegramGroupService
{
    use Traits\HasGetTelegramGroup;

    public function handle(Request $request, TelegramBot $telegramBot)
    {
        $userId = $telegramBot->getAttribute('user_id');

        $telegramBotId = $telegramBot->getKey();

        $chatId = $request->input('message.chat.id');

        $chatType = $request->input('message.chat.type');

        $addGroupInfo = $chatType === 'group' || $chatType === 'supergroup';

        if (! $addGroupInfo) {
            return null;
        }

        $groupName = $request->input('message.chat.title', '');

        $supergroupSubscriberId = $chatId . '-' . $telegramBotId;

        $group = TelegramGroup::query()
            ->where('supergroup_subscriber_id', $supergroupSubscriberId)
            ->where('user_id', $userId)
            ->first();

        if (
            empty($chatId)
        ) {
            return null;
        }

        if ($group) {

            $doesntExist = TelegramContact::query()
                ->where('group_chat_id', $chatId)
                ->where('user_id', $userId)
                ->doesntExist();

            if ($doesntExist) {
                TelegramContact::query()
                    ->create([
                        'user_id'       => $userId,
                        'telegram_id'   => $request->input('message.from.id'),
                        'contact_id'    => $chatId,
                        'name'          => $groupName,
                        'username'      => $groupName,
                        'group_chat_id' => $chatId,
                        'group_id'      => $group->getKey(),
                    ]);

            }

            return $group;
        }

        $group = TelegramGroup::query()
            ->create([
                'user_id'                  => $userId,
                'name'                     => $groupName,
                'group_id'                 => $chatId,
                'bot_id'                   => $telegramBot->getKey(),
                'type'                     => 'telegram',
                'group_type'               => $chatType,
                'supergroup_subscriber_id' => $supergroupSubscriberId,
                'status'                   => 1,
            ]);

        $doesntExist = TelegramContact::query()
            ->where('group_chat_id', $chatId)
            ->where('user_id', $userId)
            ->doesntExist();

        if ($doesntExist) {
            TelegramContact::query()
                ->create([
                    'user_id'       => $userId,
                    'telegram_id'   => $request->input('message.from.id'),
                    'contact_id'    => $chatId,
                    'name'          => $groupName,
                    'username'      => $groupName,
                    'group_chat_id' => $chatId,
                    'group_id'      => $group->getKey(),
                ]);

            return $group;
        }

        return $group;
    }
}
