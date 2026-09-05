<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProGmail\System;

use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Extensions\AIChatPro\System\Connectors\ConnectorRegistry;
use App\Extensions\AIChatProGmail\System\Connectors\GmailConnectorDefinition;
use App\Extensions\AIChatProGmail\System\Http\Controllers\OAuth\GmailOAuthController;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class AIChatProGmailServiceProvider extends ServiceProvider implements UninstallExtensionServiceProviderInterface
{
    public function register(): void {}

    public function boot(): void
    {
        $this->registerConfig()
            ->registerViews();

        if (! class_exists(ConnectorRegistry::class)) {
            // AI Chat Pro host extension is not installed — provider stays dormant.
            return;
        }

        $this->registerRoutes()
            ->registerDefinition();
    }

    private function registerViews(): static
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'ai-chat-pro-gmail');

        return $this;
    }

    private function registerConfig(): static
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/ai-chat-pro-gmail.php', 'ai-chat-pro-gmail');

        return $this;
    }

    private function registerRoutes(): static
    {
        /** @var Router $router */
        $router = $this->app['router'];

        $router->group([
            'middleware' => ['web', 'auth'],
            'prefix'     => 'dashboard/user/ai-chat-pro/connectors',
            'as'         => 'dashboard.user.ai-chat-pro.connectors.',
        ], function (Router $router): void {
            $router->get('gmail/redirect', [GmailOAuthController::class, 'redirect'])->name('gmail.redirect');
            $router->get('gmail/callback', [GmailOAuthController::class, 'callback'])->name('gmail.callback');
        });

        return $this;
    }

    private function registerDefinition(): static
    {
        app(ConnectorRegistry::class)->register('gmail', GmailConnectorDefinition::class);

        return $this;
    }

    public static function uninstall(): void {}
}
