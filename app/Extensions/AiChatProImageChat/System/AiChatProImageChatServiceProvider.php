<?php

declare(strict_types=1);

namespace App\Extensions\AiChatProImageChat\System;

use App\Domains\Marketplace\Contracts\ExtensionRegisterKeyProviderInterface;
use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Extensions\AiChatProImageChat\System\Http\Controllers\AiChatProImageChatController;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

/**
 * Author: MagicAI Team <info@liquid-themes.com>
 *
 * @note When you create a new service provider, make sure to add it to the "MarketplaceServiceProvider". Otherwise, your Laravel application won’t recognize this provider, and the related functions won’t work properly.
 * @note If you want to perform a specific action when an extension is uninstalled, you can use the UninstallExtensionServiceProviderInterface. By implementing this interface, you can define custom operations that will be triggered during the uninstallation of the extension.
 * @note The registerKey() method is used to provide a unique identifier for the extension, which is essential for the healthy check and other functionalities.
 */
class AiChatProImageChatServiceProvider extends ServiceProvider implements ExtensionRegisterKeyProviderInterface, UninstallExtensionServiceProviderInterface
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
            ->registerMigrations()
            ->publishAssets()
            ->registerComponents();

    }

    public function registerComponents(): static
    {
        //        $this->loadViewComponentsAs('ai-chat-pro-image-chat', []);

        return $this;
    }

    public function publishAssets(): static
    {
        $this->publishes([
            //            __DIR__ . '/../resources/assets/js' => public_path('vendor/ai-chat-pro-image-chat/js'),
            //            __DIR__ . '/../resources/assets/images' => public_path('vendor/ai-chat-pro-image-chat/images'),
        ], 'extension');

        return $this;
    }

    public function registerConfig(): static
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/ai-chat-pro-image-chat.php', 'ai-chat-pro-image-chat');

        return $this;
    }

    protected function registerTranslations(): static
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'ai-chat-pro-image-chat');

        return $this;
    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'ai-chat-pro-image-chat');

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
                $router->group([
                    'prefix'     => 'ai-chat-image',
                    'as'         => 'ai-chat-image.',
                ], function (Router $router) {
                    $router->get('/chat', AiChatProImageChatController::class)->name('index');
                });

                $router->group([
                    'prefix'     => 'dashboard/user',
                    'as'         => 'dashboard.user.',
                ], function (Router $router) {
                    $router->get('generator/check-image-status/{recordId}', [AiChatProImageChatController::class, 'checkImageStatus'])->name('generator.check-image-status');
                    $router->get('generator/get-message-image-data/{messageId}', [AiChatProImageChatController::class, 'getMessageImageData'])->name('generator.get-message-image-data');
                });

                $router
                    ->middleware('admin')
                    ->controller(AiChatProImageChatController::class)
                    ->prefix('dashboard/admin/ai-chat-pro-image-chat')
                    ->name('dashboard.admin.ai-chat-pro-image-chat.')
                    ->group(function (Router $router) {
                        $router->get('settings', 'edit')->name('settings');
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
        // TODO: Implement uninstall() method.
    }

    public function registerKey(): string
    {
        return 'ai-chat-pro-image-chat';
    }
}
