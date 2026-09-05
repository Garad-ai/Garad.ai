<?php

namespace App\Extensions\MarketingBot\System\Services\Telegram;

use App\Extensions\MarketingBot\System\Enums\CampaignStatus;
use App\Extensions\MarketingBot\System\Models\MarketingConversation;
use App\Extensions\MarketingBot\System\Models\MarketingMessageHistory;
use App\Extensions\MarketingBot\System\Models\Telegram\TelegramBot;
use App\Extensions\MarketingBot\System\Models\Telegram\TelegramContact;
use App\Extensions\MarketingBot\System\Services\Common\Traits\HasMarketingCampaign;
use App\Extensions\MarketingBot\System\Services\MarketingBotLimitService;
use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramSenderService
{
    use HasMarketingCampaign;

    public TelegramBot $telegramBot;

    public ?MarketingBotLimitService $limitService = null;

    public function setBot($userId): self
    {
        $this->telegramBot = TelegramBot::query()->where('user_id', $userId)->first();

        if (! $this->telegramBot) {
            throw new Exception('Telegram bot not found for user ID: ' . $userId);
        }

        return $this;
    }

    public function send(): void
    {
        $marketingCampaign = $this->getMarketingCampaign();

        $this->setBot($marketingCampaign->getAttribute('user_id'));

        $user = User::find($marketingCampaign->getAttribute('user_id'));
        $this->limitService = new MarketingBotLimitService($user);

        if (! $this->limitService->canAccessChannel('telegram')) {
            throw new Exception('Telegram channel is not enabled in your current plan.');
        }

        $contactArray = $marketingCampaign->getAttribute('contacts');

        $contacts = TelegramContact::query()->whereIn('id', $contactArray)->get();

        foreach ($contacts as $contact) {
            if (! $this->limitService->canSendMessage()) {
                break;
            }

            $this->sendMessageToContact($contact, $marketingCampaign->getAttribute('content'));
        }

        // After sending, you might want to update the status of the campaign
        $marketingCampaign->update(['status' => CampaignStatus::published]);
    }

    public function sendMessageToContact(TelegramContact $contact, $message)
    {
        try {
            $this->sendText($message, $contact->getAttribute('contact_id'));

            $marketingConversation = $this->updateOrCreateMarketingConversation($contact->getAttribute('contact_id'));

            MarketingMessageHistory::query()->create([
                'conversation_id' => $marketingConversation->getKey(),
                'message_id'      => random_int(100000000, 999999999),
                'model'           => null,
                'role'            => 'user',
                'message'         => $message,
                'type'            => 'default',
                'message_type'    => 'text',
                'content_type'    => 'text',
                'created_at'      => now(),
            ]);

            $this->limitService?->incrementMonthlyMessages();
        } catch (Exception $e) {
            Log::error($e->getMessage());
        }
    }

    public function updateOrCreateMarketingConversation($contactId): Model|Builder
    {
        $supergroupSubscriberId = $contactId . '-' . $this->telegramBot->getKey();

        return MarketingConversation::query()
            ->updateOrCreate([
                'user_id'           => $this->telegramBot->getAttribute('user_id'),
                'type'              => 'telegram',
                'session_id'        => $supergroupSubscriberId,
                'telegram_group_id' => $this->telegramBot->getKey(),
            ], [
                'conversation_name' => $this->telegramBot->getAttribute('name'),
                'customer_payload'  => [
                    'contact_id'      => $contactId,
                    'telegram_bot_id' => $this->telegramBot->getKey(),
                ],
            ]);
    }

    public function sendText($message, $receiver = null): void
    {
        $token = $this->telegramBot->access_token;

        $receiver = $receiver ?: $this->telegramBot->getAttribute('group_id');

        $url = "https://api.telegram.org/bot{$token}/sendMessage";

        $data = [
            'chat_id'    => $receiver,
            'text'       => $message,
        ];

        $url .= '?' . http_build_query($data);

        $http = Http::get($url);

        if ($http->failed()) {
            throw new Exception('Failed to send message: ' . $http->body());
        }

        if (! $http->json('ok')) {
            throw new Exception('Failed to send message: ' . $http->json('description'));
        }
    }
}
