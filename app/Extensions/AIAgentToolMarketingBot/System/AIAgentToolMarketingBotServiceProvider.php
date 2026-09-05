<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentToolMarketingBot\System;

use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Extensions\AIAgent\System\Actions\GenerateReportAction;
// use App\Extensions\AIAgent\System\Engine\AIAgentActionRegistry;
use App\Extensions\AIAgentToolMarketingBot\System\Actions\EnhancedGenerateReportAction;
use App\Extensions\AIAgentToolMarketingBot\System\Actions\MarketingBotAction;
use Illuminate\Support\ServiceProvider;

class AIAgentToolMarketingBotServiceProvider extends ServiceProvider implements UninstallExtensionServiceProviderInterface
{
    public function register(): void
    {
        if (! $this->isMarketingBotAvailable()) {
            return;
        }

        $this->app->bind(GenerateReportAction::class, EnhancedGenerateReportAction::class);
    }

    public function boot(): void
    {
        if (! $this->isMarketingBotAvailable()) {
            return;
        }

        //$registry = $this->app->make(AIAgentActionRegistry::class);
        //$registry->register('marketing_bot', MarketingBotAction::class);
    }

    public static function uninstall(): void
    {
        // No database tables or assets to clean up.
    }

    private function isMarketingBotAvailable(): bool
    {
        return class_exists(\App\Extensions\MarketingBot\System\Models\MarketingCampaign::class);
    }
}
