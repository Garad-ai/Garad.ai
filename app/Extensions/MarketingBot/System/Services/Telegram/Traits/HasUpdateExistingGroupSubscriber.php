<?php

namespace App\Extensions\MarketingBot\System\Services\Telegram\Traits;

use App\Extensions\MarketingBot\System\Models\Telegram\TelegramBot;
use App\Extensions\MarketingBot\System\Models\Telegram\TelegramGroupSubscriber;
use Illuminate\Http\Request;

trait HasUpdateExistingGroupSubscriber
{
    public function updateExistingGroupSubscriber(
        Request $request,
        $telegramGroupId,
        TelegramBot $telegramBot
    ): void {
        $subscriberId = $request->input('message.new_chat_member.id') ?? $request->input('message.from.id');

        $firstName = $request->input('message.new_chat_member.first_name', '');
        $lastName = $request->input('message.new_chat_member.last_name', '');
        $userName = $request->input('message.new_chat_member.username', '');

        $groupSubscriberId = $subscriberId . '-' . $telegramGroupId;

        $doesntExistRow = $this->doesntExistTelegramGroupSubscriber(
            $groupSubscriberId,
            $telegramBot->getAttribute('user_id')
        );

        if (
            ! empty($subscriberId)
            && $doesntExistRow
            && $userName !== $telegramBot->getAttribute('username')
        ) {
            TelegramGroupSubscriber::query()->create([
                'unique_id'            => $subscriberId,
                'user_id'              => $telegramBot->getAttribute('user_id'),
                'name'                 => $firstName . ' ' . $lastName,
                'username'             => $userName,
                'avatar'               => null, // Avatar can be set if available
                'phone'                => null, // Phone can be set if available
                'group_chat_id'        => null, // Group chat ID can be set if available
                'group_subscriber_id'  => $groupSubscriberId,
                'group_id'             => $telegramGroupId,
                'is_bot'               => 0,
                'is_blacklist'         => 0,
                'status'               => 1,
            ]);
        }
    }
}
