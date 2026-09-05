<?php

declare(strict_types=1);

namespace App\Extensions\DiscountManager\System;

use App\Domains\Marketplace\Contracts\ExtensionRegisterKeyProviderInterface;
use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Extensions\DiscountManager\System\Http\Controllers\DiscountManagerController;
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
class DiscountManagerServiceProvider extends ServiceProvider implements ExtensionRegisterKeyProviderInterface, UninstallExtensionServiceProviderInterface
{
    public function boot(Kernel $kernel): void
    {
        $this->registerViews()
            ->registerRoutes()
            ->registerMigrations()
            ->publishAssets();

    }

    public function publishAssets(): static
    {
        $this->publishes([
            //            __DIR__ . '/../resources/assets/js' => public_path('vendor/discount-manager/js'),
            __DIR__ . '/../resources/assets/images' => public_path('vendor/discount-manager/images'),
        ], 'extension');

        return $this;
    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'discount-manager');

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
                'middleware' => ['web', 'auth', 'admin'],
                // 'middleware' => ['web', 'auth', 'healthy:' . $this->registerKey()],
                'prefix' => 'dashboard/admin/discount-manager',
                'as'     => 'dashboard.admin.discount-manager.',
            ], function (Router $router) {
                $router->get('/', [DiscountManagerController::class, 'index'])->name('index');
                $router->get('/discount/{discount?}', [DiscountManagerController::class, 'discount'])->name('discount');
                $router->get('/banner/{discount?}', [DiscountManagerController::class, 'banner'])->name('banner');

                $router->group([
                    'middleware' => ['is_not_demo'],
                ], function (Router $router) {
                    $router->put('/discount-save/{discount?}', [DiscountManagerController::class, 'saveDiscount'])->name('discount-save');
                    $router->post('/banner-save/{banner?}', [DiscountManagerController::class, 'saveBanner'])->name('banner-save');

                    $router->post('/discount-duplicate', [DiscountManagerController::class, 'discountDuplicate'])->name('discount-duplicate');
                    $router->delete('/discount-delete', [DiscountManagerController::class, 'discountDelete'])->name('discount-delete');
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
        return 'discount-manager';
    }
}
