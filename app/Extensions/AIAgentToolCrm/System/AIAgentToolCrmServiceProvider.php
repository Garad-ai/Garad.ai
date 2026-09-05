<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentToolCrm\System;

use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Extensions\AIAgent\System\Actions\GenerateReportAction;
use App\Extensions\AIAgent\System\Engine\AIAgentActionRegistry;
use App\Extensions\AIAgentToolCrm\System\Actions\CrmAgentAction;
use App\Extensions\AIAgentToolCrm\System\Actions\EnhancedGenerateReportAction;
use Illuminate\Support\ServiceProvider;

class AIAgentToolCrmServiceProvider extends ServiceProvider implements UninstallExtensionServiceProviderInterface
{
    public function register(): void
    {
        if (! $this->isCrmAvailable()) {
            return;
        }

        $this->app->bind(GenerateReportAction::class, EnhancedGenerateReportAction::class);
    }

    public function boot(): void
    {
        if (! $this->isCrmAvailable()) {
            return;
        }

        $registry = $this->app->make(AIAgentActionRegistry::class);
        $registry->register('crm_agent', CrmAgentAction::class);
    }

    public static function uninstall(): void
    {
        // No database tables or assets to clean up.
    }

    private function isCrmAvailable(): bool
    {
        return class_exists(AIAgentActionRegistry::class)
            && class_exists(\App\Extensions\Crm\System\Models\CrmContact::class);
    }
}
