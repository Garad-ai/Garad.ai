<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProDeepResearch\System;

use App\Domains\Marketplace\Contracts\ExtensionRegisterKeyProviderInterface;
use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Extensions\AIChatProDeepResearch\System\Http\Controllers\DeepResearchController;
use App\Extensions\AIChatProDeepResearch\System\Http\Controllers\DrCanvasController;
use App\Helpers\Classes\MarketplaceHelper;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AIChatProDeepResearchServiceProvider extends ServiceProvider implements ExtensionRegisterKeyProviderInterface, UninstallExtensionServiceProviderInterface
{
    public function register(): void
    {
        $this->registerConfig();
    }

    public function boot(): void
    {
        $this->registerViews()
            ->registerRoutes()
            ->registerMigrations();
    }

    public function registerConfig(): static
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/deep-research.php', 'deep-research');

        return $this;
    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'ai-chat-pro-deep-research');

        return $this;
    }

    public function registerMigrations(): static
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        return $this;
    }

    private function registerRoutes(): static
    {
        $this->router()
            ->group([
                'middleware' => ['web', 'auth'],
            ], function (Router $router) {
                // Deep Research routes
                $router->post('dashboard/user/deep-research/start', [DeepResearchController::class, 'start'])
                    ->name('deep-research.start');
                $router->get('dashboard/user/deep-research/poll/{sessionId}', [DeepResearchController::class, 'poll'])
                    ->name('deep-research.poll');
                $router->post('dashboard/user/deep-research/cancel/{sessionId}', [DeepResearchController::class, 'cancel'])
                    ->name('deep-research.cancel');
                $router->get('dashboard/user/deep-research/session/{id}', [DeepResearchController::class, 'getSession'])
                    ->name('deep-research.session');
                $router->get('dashboard/user/deep-research/chat-sessions/{chatId}', [DeepResearchController::class, 'chatSessions'])
                    ->name('deep-research.chat-sessions');
            });

        // Register Canvas-equivalent routes only when Canvas extension is NOT installed
        if (! MarketplaceHelper::isRegistered('canvas')) {
            $this->router()
                ->group([
                    'middleware' => ['web', 'auth', 'is_not_demo'],
                ], function (Router $router) {
                    $router->post('tiptap-content-store', [DrCanvasController::class, 'storeContent'])
                        ->name('tiptap-content-store');
                    $router->post('tiptap-title-save', [DrCanvasController::class, 'saveTitle'])
                        ->name('tiptap-title-save');
                });
        }

        return $this;
    }

    private function router(): Router|Route
    {
        return $this->app['router'];
    }

    public static function uninstall(): void
    {
        // TODO: Implement uninstall() method.
    }

    public function registerKey(): string
    {
        return 'ai-chat-pro-deep-research';
    }
}
