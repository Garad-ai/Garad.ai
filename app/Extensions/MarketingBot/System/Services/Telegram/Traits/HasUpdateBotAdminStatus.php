<?php

namespace App\Extensions\MarketingBot\System\Services\Telegram\Traits;

use App\Extensions\MarketingBot\System\Models\Telegram\TelegramBot;
use App\Extensions\MarketingBot\System\Models\Telegram\TelegramGroup;
use Illuminate\Http\Request;

trait HasUpdateBotAdminStatus
{
    public function updateBotAdminStatus(Request $request, TelegramBot $telegramBot)
    {
        $chatType = $request->input('my_chat_member.chat.type');
        $username = $request->input('my_chat_member.new_chat_member.user.username');

        if (
            ($chatType === 'group' || $chatType === 'supergroup') &&
            $username === $telegramBot->getAttribute('username')
        ) {
            $groupId = $request->input('my_chat_member.chat.id');

            $supergroupSubscriberId = $groupId . '-' . $telegramBot->getKey();
            $chatMemberStatus = $request->input('my_chat_member.new_chat_member.status', '');
            $isAdmin = ! empty($groupId) && $chatMemberStatus === 'administrator';

            TelegramGroup::query()
                ->where('supergroup_subscriber_id', $supergroupSubscriberId)
                ->update(['is_admin' => $isAdmin ? 1 : 0]);

            return $isAdmin;
        }
    }
}
