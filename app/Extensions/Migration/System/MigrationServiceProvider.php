<?php

declare(strict_types=1);

namespace App\Extensions\Migration\System;

use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Extensions\Migration\System\Enums\MigrationDriverEnum;
use App\Extensions\Migration\System\Http\Controllers\MigrationController;
use App\Extensions\Migration\System\Services\MigrationService;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Author: MagicAI Team <info@liquid-themes.com>
 *
 * @note When you create a new service provider, make sure to add it to the "MarketplaceServiceProvider". Otherwise, your Laravel application won’t recognize this provider, and the related functions won’t work properly.
 * @note If you want to perform a specific action when an extension is uninstalled, you can use the UninstallExtensionServiceProviderInterface. By implementing this interface, you can define custom operations that will be triggered during the uninstallation of the extension.
 */
class MigrationServiceProvider extends ServiceProvider implements UninstallExtensionServiceProviderInterface
{
    public function register(): void
    {
        $this->registerConfig()->registerServices();
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
        //        $this->loadViewComponentsAs('migration', []);

        return $this;
    }

    public function publishAssets(): static
    {
        $this->publishes([
            //            __DIR__ . '/../resources/assets/js' => public_path('vendor/migration/js'),
            //            __DIR__ . '/../resources/assets/images' => public_path('vendor/migration/images'),
        ], 'extension');

        return $this;
    }

    public function registerConfig(): static
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/migration.php', 'migration');

        return $this;
    }

    public function registerServices(): static
    {
        $this->app->singleton(MigrationService::class, function ($app) {
            $drivers = collect(MigrationDriverEnum::cases())
                ->map(function (MigrationDriverEnum $driver) {
                    return app($driver->driver());
                })->toArray();

            return new MigrationService($drivers);
        });

        return $this;
    }

    protected function registerTranslations(): static
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'migration');

        return $this;
    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'migration');

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
                'as'         => 'migration::',
                'prefix'     => 'dashboard/admin/migration',
                'controller' => MigrationController::class,
            ], function (Router $router) {
                $router->get('/', 'index')->name('welcome');
                $router->get('/start', 'start')->name('start');
                $router->post('/migrate', 'migrate')->name('migrate');
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
