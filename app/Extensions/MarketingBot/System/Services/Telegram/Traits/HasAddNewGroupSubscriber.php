<?php

namespace App\Extensions\MarketingBot\System\Services\Telegram\Traits;

use App\Extensions\MarketingBot\System\Models\Telegram\TelegramBot;
use App\Extensions\MarketingBot\System\Models\Telegram\TelegramGroupSubscriber;
use Illuminate\Http\Request;

trait HasAddNewGroupSubscriber
{
    public function addNewGroupSubscriber(
        Request $request,
        $telegramGroupId,
        TelegramBot $telegramBot
    ): void {
        $subscriberId = $request->input('message.new_chat_member.id') ?? $request->input('message.from.id');

        $firstName = $request->input('message.new_chat_member.first_name', '');

        $lastName = $request->input('message.new_chat_member.last_name', '');
        $userName = $request->input('message.new_chat_member.username', '');
        $groupSubscriberId = $subscriberId . '-' . $telegramGroupId;

        $isBot = $request->input('message.new_chat_member.is_bot', 0);
        $doesntExistRow = $this->doesntExistTelegramGroupSubscriber(
            $groupSubscriberId,
            $telegramBot->getAttribute('user_id')
        );
        if (! $isBot) {

            if (
                ! empty($subscriberId) &&
                $doesntExistRow
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
                    'is_admin'             => $this->groupAdmin($request, $telegramBot), // Assuming not admin by default
                    'is_bot'               => $isBot,
                    'is_blacklist'         => 0,
                    'status'               => 1,
                ]);

            } else {
                TelegramGroupSubscriber::query()
                    ->where('group_subscriber_id', $groupSubscriberId)
                    ->update([
                        'is_left_group' => '0',
                        'is_blacklist'  => '0',
                    ]);
            }
        }

        if ($isBot === true) {
            if (
                ! empty($subscriberId) &&
                $doesntExistRow
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
                    'is_admin'             => $this->groupAdmin($request, $telegramBot), // Assuming not admin by default
                    'is_bot'               => $isBot,
                    'is_blacklist'         => 0,
                    'is_left_group'        => 0,
                    'status'               => 1,
                ]);
            }

        }

        $status = $request->input('my_chat_member.new_chat_member.status');

        if ($status === 'administrator') {
            TelegramGroupSubscriber::query()->create([
                'unique_id'            => $subscriberId,
                'user_id'              => $telegramBot->getAttribute('user_id'),
                'name'                 => $firstName . ' ' . $lastName,
                'username'             => $userName,
                'avatar'               => null, // Avatar can be set if available
                'phone'                => null, // Phone can be set if available
                'group_chat_id'        => $request->input('message.chat.id'), // Group chat ID can be set if available
                'group_subscriber_id'  => $groupSubscriberId,
                'group_id'             => $telegramGroupId,
                'is_admin'             => $this->groupAdmin($request, $telegramBot), // Admin status
                'is_bot'               => $isBot,
                'is_blacklist'         => 0,
                'is_left_group'        => 0,
                'status'               => 1,
            ]);
        }
    }
}
