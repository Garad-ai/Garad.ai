<?php

namespace App\Extensions\MarketingBot\System\Http\Controllers\Webhook;

use App\Extensions\MarketingBot\System\Models\Telegram\TelegramBot;
use App\Extensions\MarketingBot\System\Services\Telegram\TelegramGroupService;
use App\Extensions\MarketingBot\System\Services\Telegram\TelegramGroupSubscriberService;
use App\Extensions\MarketingBot\System\Services\Telegram\TelegramWebhookService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function __construct(
        public TelegramGroupService $telegramGroupService,
        public TelegramGroupSubscriberService $telegramGroupSubscriberService,
        public TelegramWebhookService $telegramWebhookService,
    ) {}

    public function __invoke(Request $request, string $accessToken)
    {
        $accessToken = base64_decode($accessToken);

        /**
         * @var TelegramBot $telegramBot
         */
        $telegramBot = TelegramBot::query()
            ->where('access_token', $accessToken)
            ->firstOrFail();

        $this->telegramGroupService->handle($request, $telegramBot);

        $this->telegramGroupSubscriberService->handle($request, $telegramBot);

        $this->telegramGroupSubscriberService->updateBotAdminStatus($request, $telegramBot);

        $this->telegramGroupSubscriberService->removeBotOrSubscriber($request, $telegramBot);

        $this->telegramWebhookService->handle($request, $telegramBot);
    }
}
