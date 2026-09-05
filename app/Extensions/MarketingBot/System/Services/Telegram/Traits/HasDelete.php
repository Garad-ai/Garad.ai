<?php

namespace App\Extensions\MarketingBot\System\Services\Telegram\Traits;

use App\Extensions\MarketingBot\System\Models\Telegram\TelegramBot;
use App\Extensions\MarketingBot\System\Models\Telegram\TelegramGroupSubscriber;
use App\Extensions\MarketingBot\System\Services\Telegram\TelegramGroupService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

trait HasDelete
{
    public function removeBotOrSubscriber(Request $request, TelegramBot $telegramBot): void
    {
        $chatId = $request->input('message.chat.id');

        $chatType = $request->input('message.chat.type');

        $telegramGroup = app(TelegramGroupService::class)->getTelegramGroup($telegramBot, $chatId);

        try {
            if ($telegramGroup) {

                $leftChatMember = $updates['message']['left_chat_member'] ?? false;

                if ($leftChatMember && ($chatType === 'group' || $chatType === 'supergroup')) {
                    $this->deleteLeftChatMember($request, $telegramGroup->getKey(), $telegramBot);
                }
            }

        } catch (Exception $e) {
            Log::error('Exception: ', [$e->getMessage()]);
        }
    }

    public function deleteLeftChatMember(Request $request, int $telegramGroupId, TelegramBot $telegramBot): void
    {
        try {
            $getUserName = $request->input('message.left_chat_member.username');

            if ($getUserName !== $telegramBot->getAttribute('username')) {
                $subscriberId = $request->input('message.left_chat_member.id') ?? '';
                $groupSubscriberId = $subscriberId . '-' . $telegramGroupId;
                $messageFromId = $request->input('message.from.id') ?? '';
                if ($subscriberId === $messageFromId) {
                    TelegramGroupSubscriber::where('group_subscriber_id', $groupSubscriberId)
                        ->update(['is_left_group' => '1']);
                } else {
                    TelegramGroupSubscriber::where('group_subscriber_id', $groupSubscriberId)
                        ->update(['is_left_group' => '1']);
                }
            }
        } catch (Exception $exception) {
            Log::error('Error deleting left chat member: ', [$exception->getMessage()]);
        }
    }
}
