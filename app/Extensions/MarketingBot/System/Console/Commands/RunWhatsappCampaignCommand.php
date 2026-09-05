<?php

namespace App\Extensions\MarketingBot\System\Console\Commands;

use App\Extensions\MarketingBot\System\Enums\CampaignStatus;
use App\Extensions\MarketingBot\System\Enums\CampaignType;
use App\Extensions\MarketingBot\System\Models\MarketingCampaign;
use App\Extensions\MarketingBot\System\Services\Whatsapp\WhatsappSenderService;
use Exception;
use Illuminate\Console\Command;

class RunWhatsappCampaignCommand extends Command
{
    protected $signature = 'app:run-whatsapp-campaign';

    protected $description = 'Run a new Whatsapp campaign';

    public function handle()
    {
        $now = now();

        $whatsappService = app(WhatsappSenderService::class);

        $campaigns = MarketingCampaign::query()
            ->where('type', CampaignType::whatsapp)
            ->where('status', CampaignStatus::scheduled)
            ->where('scheduled_at', '<=', $now)
            ->get();

        $campaigns->map(function (MarketingCampaign $campaign) use ($whatsappService) {
            try {
                $whatsappService
                    ->setMarketingCampaign($campaign)
                    ->send();
            } catch (Exception $e) {
            }
        });
    }
}
