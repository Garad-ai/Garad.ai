<?php

namespace App\Extensions\MarketingBot\System\Services\Telegram;

use App\Extensions\MarketingBot\System\Models\MarketingConversation;
use App\Extensions\MarketingBot\System\Models\MarketingMessageHistory;
use App\Extensions\MarketingBot\System\Models\Telegram\TelegramBot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class TelegramWebhookService
{
    use Traits\HasGetTelegramGroup;

    public function handle(Request $request, TelegramBot $telegramBot): void
    {
        $message = $request->input('message.text');

        $marketingConversation = $this->updateOrCreateMarketingConversation($request, $telegramBot);

        if ($message) {
            MarketingMessageHistory::query()->create([
                'conversation_id' => $marketingConversation->getKey(),
                'message_id'      => $request->input('message.message_id'),
                'model'           => null,
                'role'            => 'user',
                'message'         => $message,
                'type'            => 'default',
                'message_type'    => 'text',
                'content_type'    => 'text',
                'created_at'      => now(),
            ]);

            app(TelegramReplyService::class)
                ->setTelegramBot($telegramBot)
                ->sendReply($request, $marketingConversation);

        }
    }

    public function updateOrCreateMarketingConversation(Request $request, TelegramBot $telegramBot): Model|Builder
    {
        $telegramBotId = $telegramBot->getKey();

        $chatId = $request->input('message.chat.id');

        $supergroupSubscriberId = $chatId . '-' . $telegramBotId;

        $marketingConversation = MarketingConversation::query()
            ->updateOrCreate([
                'user_id'           => $telegramBot->getAttribute('user_id'),
                'type'              => 'telegram',
                'session_id'        => $supergroupSubscriberId,
                'telegram_group_id' => $telegramBot->getKey(),
            ], [
                'conversation_name' => $request->input('message.chat.title', 'Telegram Group'),
                'customer_payload'  => $request->input('message'),
            ]);

        return $marketingConversation;
    }
}
