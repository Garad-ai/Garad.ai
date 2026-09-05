<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProOutlook\System;

use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Extensions\AIChatPro\System\Connectors\ConnectorRegistry;
use App\Extensions\AIChatProOutlook\System\Connectors\OutlookConnectorDefinition;
use App\Extensions\AIChatProOutlook\System\Http\Controllers\OAuth\OutlookOAuthController;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class AIChatProOutlookServiceProvider extends ServiceProvider implements UninstallExtensionServiceProviderInterface
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
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'ai-chat-pro-outlook');

        return $this;
    }

    private function registerConfig(): static
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/ai-chat-pro-outlook.php', 'ai-chat-pro-outlook');

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
            $router->get('outlook/redirect', [OutlookOAuthController::class, 'redirect'])->name('outlook.redirect');
            $router->get('outlook/callback', [OutlookOAuthController::class, 'callback'])->name('outlook.callback');
        });

        return $this;
    }

    private function registerDefinition(): static
    {
        app(ConnectorRegistry::class)->register('outlook', OutlookConnectorDefinition::class);

        return $this;
    }

    public static function uninstall(): void {}
}
