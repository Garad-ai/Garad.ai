<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentGmail\System;

use App\Extensions\AIAgent\System\AIAgentServiceProvider;
use App\Extensions\AIAgent\System\Engine\AIAgentActionRegistry;
use App\Extensions\AIAgentGmail\System\Actions\GMailAction;
use App\Extensions\AIAgentGmail\System\Http\Controllers\AIAgentGmailSettingController;
use App\Extensions\AIAgentGmail\System\Http\Controllers\ConnectorController;
use App\Extensions\AIAgentGmail\System\Http\Controllers\GmailApiController;
use App\Extensions\AIAgentGmail\System\Http\Controllers\OAuth\GmailOAuthController;
use App\Extensions\AIAgentGmail\System\Models\AIAgentConnector;
use App\Extensions\AIAgentGmail\System\Policies\AIAgentConnectorPolicy;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AIAgentGmailServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! class_exists(AIAgentServiceProvider::class)) {
            return;
        }

        $this->registerConfig()
            ->registerViews()
            ->registerMigrations()
            ->registerPolicies()
            ->registerRoutes()
            ->registerAction();
    }

    private function registerConfig(): static
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/ai-agent-gmail.php', 'ai-agent-gmail');

        return $this;
    }

    private function registerViews(): static
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'ai-agent-gmail');

        return $this;
    }

    private function registerMigrations(): static
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        return $this;
    }

    private function registerPolicies(): static
    {
        Gate::policy(AIAgentConnector::class, AIAgentConnectorPolicy::class);

        return $this;
    }

    private function registerAction(): static
    {
        $registry = $this->app->make(AIAgentActionRegistry::class);
        $registry->register('gmail', GMailAction::class);

        return $this;
    }

    private function registerRoutes(): static
    {
        /** @var Router $router */
        $router = $this->app['router'];

        $router->group([
            'middleware' => ['web', 'auth'],
            'prefix'     => 'dashboard/user/ai-agent/connectors',
            'as'         => 'dashboard.user.ai-agent.connectors.',
        ], function (Router $router): void {
            $router->delete('{connector}', [ConnectorController::class, 'destroy'])->name('destroy');
            $router->get('gmail/redirect', [GmailOAuthController::class, 'redirect'])->name('gmail.redirect');
            $router->get('gmail/callback', [GmailOAuthController::class, 'callback'])->name('gmail.callback');
            $router->get('gmail/api/messages', [GmailApiController::class, 'messages'])->name('gmail.api.messages');
            $router->get('gmail/api/threads', [GmailApiController::class, 'threads'])->name('gmail.api.threads');
        });

        $router->group([
            'middleware' => ['web', 'auth', 'admin'],
            'prefix'     => 'dashboard/admin/ai-agent',
            'as'         => 'dashboard.admin.ai-agent.',
        ], function (Router $router): void {
            $router->get('gmail/settings', [AIAgentGmailSettingController::class, 'index'])->name('gmail.settings');
            $router->post('gmail/settings', [AIAgentGmailSettingController::class, 'update'])->name('gmail.settings.update');
        });

        return $this;
    }
}
