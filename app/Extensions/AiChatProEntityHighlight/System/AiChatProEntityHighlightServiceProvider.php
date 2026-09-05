<?php

declare(strict_types=1);

namespace App\Extensions\AiChatProEntityHighlight\System;

use App\Domains\Marketplace\Contracts\ExtensionRegisterKeyProviderInterface;
use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Extensions\AiChatProEntityHighlight\System\Http\Controllers\AiChatProEntityHighlightSettingsController;
use App\Extensions\AiChatProEntityHighlight\System\Http\Controllers\EntityDetailController;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

class AiChatProEntityHighlightServiceProvider extends ServiceProvider implements ExtensionRegisterKeyProviderInterface, UninstallExtensionServiceProviderInterface
{
    public function register(): void
    {
        $this->registerConfig();
    }

    public function boot(Kernel $kernel): void
    {
        $this->registerTranslations()
            ->registerViews()
            ->registerRoutes()
            ->registerMigrations();
    }

    public function registerConfig(): static
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/ai-chat-pro-entity-highlight.php', 'ai-chat-pro-entity-highlight');

        return $this;
    }

    protected function registerTranslations(): static
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'ai-chat-pro-entity-highlight');

        return $this;
    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'ai-chat-pro-entity-highlight');

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
                'prefix'     => LaravelLocalization::setLocale(),
                'middleware' => ['web', 'localeSessionRedirect', 'localizationRedirect', 'localeViewPath'],
            ], function (Router $router) {
                // Admin settings routes
                $router
                    ->middleware(['auth', 'admin'])
                    ->controller(AiChatProEntityHighlightSettingsController::class)
                    ->prefix('dashboard/admin/ai-chat-pro-entity-highlight')
                    ->name('dashboard.admin.ai-chat-pro-entity-highlight.')
                    ->group(function (Router $router) {
                        $router->get('settings', 'index')->name('settings');
                        $router->put('settings', 'update')->name('settings.update');
                    });

                // User-facing entity detail API route
                $router
                    ->middleware(['auth', 'throttle:30,1'])
                    ->controller(EntityDetailController::class)
                    ->prefix('dashboard/user/ai-chat-pro-entity-highlight')
                    ->name('dashboard.user.ai-chat-pro-entity-highlight.')
                    ->group(function (Router $router) {
                        $router->post('entity-detail', 'show')->name('entity-detail');
                        $router->post('entity-image', 'image')->name('entity-image');
                    });
            });

        return $this;
    }

    private function router(): Router|Route
    {
        return $this->app['router'];
    }

    public static function uninstall(): void
    {
        Schema::dropIfExists('chat_entity_highlights');

        Schema::table('settings', function ($table) {
            $columns = [
                'ai_chat_pro_entity_highlight_enabled',
                'ai_chat_pro_entity_highlight_max',
                'ai_chat_pro_entity_highlight_threshold',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function registerKey(): string
    {
        return 'ai-chat-pro-entity-highlight';
    }
}
