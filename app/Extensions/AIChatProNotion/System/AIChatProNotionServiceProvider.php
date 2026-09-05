<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProNotion\System;

use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Extensions\AIChatPro\System\Connectors\ConnectorRegistry;
use App\Extensions\AIChatProNotion\System\Connectors\NotionConnectorDefinition;
use App\Extensions\AIChatProNotion\System\Http\Controllers\OAuth\NotionOAuthController;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class AIChatProNotionServiceProvider extends ServiceProvider implements UninstallExtensionServiceProviderInterface
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
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'ai-chat-pro-notion');

        return $this;
    }

    private function registerConfig(): static
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/ai-chat-pro-notion.php', 'ai-chat-pro-notion');

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
            $router->get('notion/redirect', [NotionOAuthController::class, 'redirect'])->name('notion.redirect');
            $router->get('notion/callback', [NotionOAuthController::class, 'callback'])->name('notion.callback');
        });

        return $this;
    }

    private function registerDefinition(): static
    {
        app(ConnectorRegistry::class)->register('notion', NotionConnectorDefinition::class);

        return $this;
    }

    public static function uninstall(): void {}
}
