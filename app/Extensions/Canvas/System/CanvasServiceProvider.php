<?php

declare(strict_types=1);

namespace App\Extensions\Canvas\System;

use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Extensions\Canvas\System\Http\Controllers\CanvasController;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Author: MagicAI Team <info@liquid-themes.com>
 *
 * @note When you create a new service provider, make sure to add it to the "MarketplaceServiceProvider". Otherwise, your Laravel application won’t recognize this provider, and the related functions won’t work properly.
 * @note If you want to perform a specific action when an extension is uninstalled, you can use the UninstallExtensionServiceProviderInterface. By implementing this interface, you can define custom operations that will be triggered during the uninstallation of the extension.
 */
class CanvasServiceProvider extends ServiceProvider implements UninstallExtensionServiceProviderInterface
{
    public function register(): void {}

    public function boot(Kernel $kernel): void
    {
        $this->registerViews()
            ->registerRoutes()
            ->publishAssets()
            ->registerMigrations();
    }

    public function registerMigrations(): static
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database');

        return $this;
    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'canvas');

        return $this;
    }

    public function publishAssets(): static
    {
        $this->publishes([
            // __DIR__ . '/../resources/assets/js' => public_path('vendor/canvas/js'),
        ], 'extension');

        return $this;
    }

    private function registerRoutes(): static
    {
        Route::middleware(['web', 'auth', 'is_not_demo'])->group(function () {
            Route::post('tiptap-content-store', [CanvasController::class, 'storeContent'])->name('tiptap-content-store');
            Route::post('tiptap-title-save', [CanvasController::class, 'saveTitle'])->name('tiptap-title-save');
        });

        return $this;
    }

    public static function uninstall(): void
    {
        // TODO: Implement uninstall() method.
    }
}
