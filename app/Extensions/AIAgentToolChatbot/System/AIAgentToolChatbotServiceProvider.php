<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentToolChatbot\System;

use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Extensions\AIAgent\System\Actions\GenerateReportAction;
// use App\Extensions\AIAgent\System\Engine\AIAgentActionRegistry;
use App\Extensions\AIAgentToolChatbot\System\Actions\EnhancedGenerateReportAction;
use App\Extensions\AIAgentToolChatbot\System\Actions\ExternalChatbotAction;
use Illuminate\Support\ServiceProvider;

class AIAgentToolChatbotServiceProvider extends ServiceProvider implements UninstallExtensionServiceProviderInterface
{
    public function register(): void
    {
        if (! $this->isChatbotAvailable()) {
            return;
        }

        $this->app->bind(GenerateReportAction::class, EnhancedGenerateReportAction::class);
    }

    public function boot(): void
    {
        if (! $this->isChatbotAvailable()) {
            return;
        }

        //$registry = $this->app->make(AIAgentActionRegistry::class);
        //$registry->register('external_chatbot', ExternalChatbotAction::class);
    }

    public static function uninstall(): void
    {
        // No database tables or assets to clean up.
    }

    private function isChatbotAvailable(): bool
    {
        return class_exists(\App\Extensions\Chatbot\System\Models\Chatbot::class);
    }
}
