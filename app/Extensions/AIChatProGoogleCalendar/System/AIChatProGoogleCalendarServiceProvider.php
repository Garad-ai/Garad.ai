<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProGoogleCalendar\System;

use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Extensions\AIChatPro\System\Connectors\ConnectorRegistry;
use App\Extensions\AIChatProGoogleCalendar\System\Connectors\GoogleCalendarConnectorDefinition;
use App\Extensions\AIChatProGoogleCalendar\System\Http\Controllers\OAuth\GoogleCalendarOAuthController;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class AIChatProGoogleCalendarServiceProvider extends ServiceProvider implements UninstallExtensionServiceProviderInterface
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
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'ai-chat-pro-google-calendar');

        return $this;
    }

    private function registerConfig(): static
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/ai-chat-pro-google-calendar.php', 'ai-chat-pro-google-calendar');

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
            $router->get('google-calendar/redirect', [GoogleCalendarOAuthController::class, 'redirect'])->name('google-calendar.redirect');
            $router->get('google-calendar/callback', [GoogleCalendarOAuthController::class, 'callback'])->name('google-calendar.callback');
        });

        return $this;
    }

    private function registerDefinition(): static
    {
        app(ConnectorRegistry::class)->register('google_calendar', GoogleCalendarConnectorDefinition::class);

        return $this;
    }

    public static function uninstall(): void {}
}
