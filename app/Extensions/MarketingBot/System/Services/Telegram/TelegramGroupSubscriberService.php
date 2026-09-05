<?php

namespace App\Extensions\MarketingBot\System\Services\Telegram;

use App\Extensions\MarketingBot\System\Models\Telegram\TelegramBot;
use App\Extensions\MarketingBot\System\Models\Telegram\TelegramGroupSubscriber;
use Illuminate\Http\Request;

class TelegramGroupSubscriberService
{
    use Traits\HasAddNewGroupSubscriber;
    use Traits\HasDelete;
    use Traits\HasIsAdmin;
    use Traits\HasUpdateBotAdminStatus;
    use Traits\HasUpdateExistingGroupSubscriber;

    public function handle(Request $request, TelegramBot $telegramBot): void
    {
        $chatId = $request->input('message.chat.id');

        $chatType = $request->input('message.chat.type');

        $telegramGroup = app(TelegramGroupService::class)->getTelegramGroup($telegramBot, $chatId);

        if ($telegramGroup) {
            $isGroupMessage = $chatType === 'group' || $chatType === 'supergroup';

            if ($isGroupMessage) {
                if ($request->input('message.new_chat_member')) {

                    $this->addNewGroupSubscriber($request, $telegramGroup->getKey(), $telegramBot);
                } else {
                    $this->updateExistingGroupSubscriber($request, $telegramGroup->getKey(), $telegramBot);
                }
            }
        }

        $from = $request->input('message.from');

        if ($from) {

            $telegramGroupId = $telegramGroup?->getKey();

            $subscriberId = $request->input('message.from.id');
            $firstName = $request->input('message.from.first_name');
            $lastName = $request->input('message.from.last_name');
            $userName = $request->input('message.from.username');
            $groupSubscriberId = $subscriberId . '-' . $telegramGroupId;

            $doesntExistRow = $this->doesntExistTelegramGroupSubscriber(
                $groupSubscriberId,
                $telegramBot->getAttribute('user_id')
            );

            if (
                ! empty($subscriberId) &&
                $doesntExistRow
            ) {
                TelegramGroupSubscriber::query()->create([
                    'unique_id'            => $subscriberId,
                    'name'                 => $firstName . ' ' . $lastName,
                    'username'             => $userName,
                    'avatar'               => null, // Avatar can be set if available
                    'group_subscriber_id'  => $groupSubscriberId,
                    'group_id'             => $telegramGroupId,
                    'user_id'              => $telegramBot->getAttribute('user_id'),
                    'is_bot'               => 0,
                    'is_left_group'        => 0,
                    'type'                 => 'telegram',
                    'is_blacklist'         => 0,
                    'status'               => 1,
                ]);
            }
        }
    }

    public function doesntExistTelegramGroupSubscriber(
        $groupSubscriberId = null,
        $userId = null,
    ): bool {
        return TelegramGroupSubscriber::query()
            ->where('group_subscriber_id', $groupSubscriberId)
            ->where('user_id', $userId)
            ->doesntExist();
    }
}
