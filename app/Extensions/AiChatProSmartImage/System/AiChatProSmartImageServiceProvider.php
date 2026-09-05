<?php

declare(strict_types=1);

namespace App\Extensions\AiChatProSmartImage\System;

use App\Domains\Marketplace\Contracts\ExtensionRegisterKeyProviderInterface;
use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Extensions\AiChatProSmartImage\System\Http\Controllers\AiChatProSmartImageSettingsController;
use App\Extensions\AiChatProSmartImage\System\Http\Controllers\SmartImageSearchController;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

class AiChatProSmartImageServiceProvider extends ServiceProvider implements ExtensionRegisterKeyProviderInterface, UninstallExtensionServiceProviderInterface
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
        $this->mergeConfigFrom(__DIR__ . '/../config/ai-chat-pro-smart-image.php', 'ai-chat-pro-smart-image');

        return $this;
    }

    protected function registerTranslations(): static
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'ai-chat-pro-smart-image');

        return $this;
    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'ai-chat-pro-smart-image');

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
                $router
                    ->middleware(['auth', 'admin'])
                    ->controller(AiChatProSmartImageSettingsController::class)
                    ->prefix('dashboard/admin/ai-chat-pro-smart-image')
                    ->name('dashboard.admin.ai-chat-pro-smart-image.')
                    ->group(function (Router $router) {
                        $router->get('settings', 'index')->name('settings');
                        $router->put('settings', 'update')->name('settings.update');
                    });

                // User-facing async image search endpoint (called by frontend in parallel with text streaming)
                $router
                    ->middleware(['auth', 'throttle:30,1'])
                    ->controller(SmartImageSearchController::class)
                    ->prefix('dashboard/user/smart-image')
                    ->name('dashboard.user.smart-image.')
                    ->group(function (Router $router) {
                        $router->post('search', 'search')->name('search');
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
        // TODO: Implement uninstall() method.
    }

    public function registerKey(): string
    {
        return 'ai-chat-pro-smart-image';
    }
}
