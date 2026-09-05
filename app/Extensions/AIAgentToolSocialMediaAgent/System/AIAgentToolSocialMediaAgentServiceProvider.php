<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentToolSocialMediaAgent\System;

use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Extensions\AIAgent\System\Actions\GenerateReportAction;
// use App\Extensions\AIAgent\System\Engine\AIAgentActionRegistry;
use App\Extensions\AIAgentToolSocialMediaAgent\System\Actions\EnhancedGenerateReportAction;
use App\Extensions\AIAgentToolSocialMediaAgent\System\Actions\SocialMediaAgentAction;
use Illuminate\Support\ServiceProvider;

class AIAgentToolSocialMediaAgentServiceProvider extends ServiceProvider implements UninstallExtensionServiceProviderInterface
{
    public function register(): void
    {
        if (! $this->isSocialMediaAgentAvailable()) {
            return;
        }

        $this->app->bind(GenerateReportAction::class, EnhancedGenerateReportAction::class);
    }

    public function boot(): void
    {
        if (! $this->isSocialMediaAgentAvailable()) {
            return;
        }

        //$registry = $this->app->make(AIAgentActionRegistry::class);
        //$registry->register('social_media_agent', SocialMediaAgentAction::class);
    }

    public static function uninstall(): void
    {
        // No database tables or assets to clean up.
    }

    private function isSocialMediaAgentAvailable(): bool
    {
        return class_exists(\App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgent::class);
    }
}
