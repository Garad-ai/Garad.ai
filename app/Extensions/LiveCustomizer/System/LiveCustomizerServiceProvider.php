<?php

declare(strict_types=1);

namespace App\Extensions\LiveCustomizer\System;

use App\Extensions\LiveCustomizer\System\Http\Controllers\LiveCustomizerController;
use App\Extensions\LiveCustomizer\System\Http\Controllers\LiveCustomizerSettingController;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class LiveCustomizerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerConfig();
    }

    public function boot(Kernel $kernel): void
    {
        $this->registerTranslations()
            ->registerViews()
            ->registerMigrations()
            ->registerRoutes();
    }

    public function registerMigrations(): static
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        return $this;
    }

    public function registerConfig(): static
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/example.php', 'live-customizer');

        return $this;
    }

    protected function registerTranslations(): static
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'live-customizer');

        return $this;
    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'live-customizer');

        return $this;
    }

    private function registerRoutes(): void
    {
        $this->router()
            ->group([
                'middleware' => ['web', 'auth', 'admin'],
            ], function (Router $router) {
                $router->post('dashboard/admin/live-customizer', LiveCustomizerController::class)->name('dashboard.admin.live-customizer');
                $router->get('dashboard/admin/live-customizer/setting', [LiveCustomizerSettingController::class, 'index'])->name('dashboard.admin.live-customizer.setting');
                $router->post('dashboard/admin/live-customizer/setting', [LiveCustomizerSettingController::class, 'update']);
            });
    }

    private function router(): Router|Route
    {
        return $this->app['router'];
    }
}
