<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProFolders\System;

use App\Domains\Marketplace\Contracts\ExtensionRegisterKeyProviderInterface;
use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Extensions\AIChatProFolders\System\Http\Controllers\AIChatProFoldersController;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Author: MagicAI Team <info@liquid-themes.com>
 *
 * @note When you create a new service provider, make sure to add it to the "MarketplaceServiceProvider". Otherwise, your Laravel application won’t recognize this provider, and the related functions won’t work properly.
 * @note If you want to perform a specific action when an extension is uninstalled, you can use the UninstallExtensionServiceProviderInterface. By implementing this interface, you can define custom operations that will be triggered during the uninstallation of the extension.
 * @note The registerKey() method is used to provide a unique identifier for the extension, which is essential for the healthy check and other functionalities.
 */
class AIChatProFoldersServiceProvider extends ServiceProvider implements ExtensionRegisterKeyProviderInterface, UninstallExtensionServiceProviderInterface
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
        //        $this->loadViewComponentsAs('ai-chat-pro-folders', []);

        return $this;
    }

    public function publishAssets(): static
    {
        $this->publishes([
            //            __DIR__ . '/../resources/assets/js' => public_path('vendor/ai-chat-pro-folders/js'),
            //            __DIR__ . '/../resources/assets/images' => public_path('vendor/ai-chat-pro-folders/images'),
        ], 'extension');

        return $this;
    }

    public function registerConfig(): static
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/ai-chat-pro-folders.php', 'ai-chat-pro-folders');

        return $this;
    }

    protected function registerTranslations(): static
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'ai-chat-pro-folders');

        return $this;
    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'ai-chat-pro-folders');

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
                'prefix'     => 'dashboard/user/ai-chat-pro',
            ], function (Router $router) {
                // Fetch chats and folders (paginated)
                $router->get('/chats', [AIChatProFoldersController::class, 'getChats'])->name('ai-chat-pro-folders.chats');
                $router->get('/folders', [AIChatProFoldersController::class, 'getFolders'])->name('ai-chat-pro-folders.folders');

                // Folder management routes
                $router->post('/folders', [AIChatProFoldersController::class, 'store'])->name('ai-chat-pro-folders.store');
                $router->put('/folders/{id}', [AIChatProFoldersController::class, 'update'])->name('ai-chat-pro-folders.update');
                $router->delete('/folders/{id}', [AIChatProFoldersController::class, 'destroy'])->name('ai-chat-pro-folders.destroy');

                // Move chat to folder
                $router->post('/chats/{chatId}/move-to-folder', [AIChatProFoldersController::class, 'moveChat'])->name('ai-chat-pro-folders.move-chat');
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
        return 'ai-chat-pro-folders';
    }
}
