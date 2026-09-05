<?php

declare(strict_types=1);

namespace App\Extensions\MegaMenu\System;

use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Extensions\MegaMenu\System\Http\Controllers\MegaMenuController;
use App\Extensions\MegaMenu\System\Http\Controllers\MegaMenuItemController;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class MegaMenuServiceProvider extends ServiceProvider implements UninstallExtensionServiceProviderInterface
{
    public function register(): void {}

    public function boot(Kernel $kernel): void
    {
        $this->registerViews()
            ->registerRoutes()
            ->registerMigrations();

    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'mega-menu');

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
                Route::prefix('dashboard/admin')
                    ->middleware('admin')
                    ->name('dashboard.admin.')
                    ->group(function (Router $router) {
                        $router->resource('mega-menu', MegaMenuController::class)->except('destroy', 'show');
                        $router->get('mega-menu/{mega_menu}/destroy', [MegaMenuController::class, 'destroy'])->name('mega-menu.destroy');
                        $router->get('mega-menu/{mega_menu}/items', [MegaMenuItemController::class, 'index'])->name('mega-menu.items');
                        $router->post('mega-menu/{mega_menu}/items', [MegaMenuItemController::class, 'store'])->name('mega-menu.items.store');
                        $router->post('mega-menu/{mega_menu}/items/number-of-columns', [MegaMenuItemController::class, 'numberOfColumns'])->name('mega-menu.items.number-of-columns');
                        $router->post('mega-menu/{mega_menu}/items/order', [MegaMenuItemController::class, 'order'])->name('mega-menu.items.order');
                        $router->post('mega-menu/{mega_menu}/items/{mega_menu_item}/update/{type}', [MegaMenuItemController::class, 'update'])->name('mega-menu.items.update');
                        $router->post('mega-menu/{mega_menu}/items/{mega_menu_item}', [MegaMenuItemController::class, 'status'])->name('mega-menu.items.status');
                        $router->post('mega-menu/{mega_menu}/items/{mega_menu_item}/upload', [MegaMenuItemController::class, 'upload'])->name('mega-menu.items.upload');
                        $router->get('mega-menu/{mega_menu}/items/{mega_menu_item}/destroy', [MegaMenuItemController::class, 'destroy'])->name('mega-menu.items.delete');
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
}
