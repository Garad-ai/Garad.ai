<?php

declare(strict_types=1);

namespace App\Extensions\AiChatProHighlightToAsk\System;

use App\Domains\Marketplace\Contracts\ExtensionRegisterKeyProviderInterface;
use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Extensions\AiChatProHighlightToAsk\System\Http\Controllers\AiChatProHighlightToAskSettingsController;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

class AiChatProHighlightToAskServiceProvider extends ServiceProvider implements ExtensionRegisterKeyProviderInterface, UninstallExtensionServiceProviderInterface
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
        $this->mergeConfigFrom(__DIR__ . '/../config/ai-chat-pro-highlight-to-ask.php', 'ai-chat-pro-highlight-to-ask');

        return $this;
    }

    protected function registerTranslations(): static
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'ai-chat-pro-highlight-to-ask');

        return $this;
    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'ai-chat-pro-highlight-to-ask');

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
                    ->controller(AiChatProHighlightToAskSettingsController::class)
                    ->prefix('dashboard/admin/ai-chat-pro-highlight-to-ask')
                    ->name('dashboard.admin.ai-chat-pro-highlight-to-ask.')
                    ->group(function (Router $router) {
                        $router->get('settings', 'index')->name('settings');
                        $router->put('settings', 'update')->name('settings.update');
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
        Schema::table('settings', function ($table) {
            if (Schema::hasColumn('settings', 'ai_chat_pro_highlight_to_ask_enabled')) {
                $table->dropColumn('ai_chat_pro_highlight_to_ask_enabled');
            }
        });

        Schema::table('user_openai_chat_messages', function ($table) {
            if (Schema::hasColumn('user_openai_chat_messages', 'highlight_context')) {
                $table->dropColumn('highlight_context');
            }
        });
    }

    public function registerKey(): string
    {
        return 'ai-chat-pro-highlight-to-ask';
    }
}
