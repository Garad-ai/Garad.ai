<?php

declare(strict_types=1);

namespace App\Extensions\VideoDubbing\System;

use App\Extensions\VideoDubbing\System\Http\Controllers\VideoDubbingController;
use App\Extensions\VideoDubbing\System\Http\Controllers\VideoDubbingSettingController;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class VideoDubbingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/videodubbing.php', 'videodubbing');
    }

    public function boot(Kernel $kernel): void
    {
        $this->registerTranslations()
            ->registerViews()
            ->registerRoutes()
            ->registerMigrations();
    }

    protected function registerTranslations(): static
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'video-dubbing');

        return $this;
    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'video-dubbing');

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
                $router
                    ->prefix('dashboard')
                    ->name('dashboard.')
                    ->group(function (Router $router) {
                        $router->prefix('user')
                            ->name('user.')
                            ->group(function (Router $router) {
                                $router->get('video-dubbing', [VideoDubbingController::class, 'index'])->name('video-dubbing.index');
                                $router->post('video-dubbing', [VideoDubbingController::class, 'store'])->name('video-dubbing.store');
                                $router->get('video-dubbing-delete/{id}', [VideoDubbingController::class, 'delete'])->name('video-dubbing.delete');
                                $router->post('video-dubbing-bulk-delete', [VideoDubbingController::class, 'bulkDelete'])->name('video-dubbing.bulk-delete');
                                $router->get('video-dubbing-check', [VideoDubbingController::class, 'checkStatus'])->name('video-dubbing.check');
                            });

                        $router->prefix('admin/video-dubbing')
                            ->name('admin.video-dubbing.')
                            ->middleware('admin')
                            ->group(function (Router $router) {
                                $router->get('settings', [VideoDubbingSettingController::class, 'index'])->name('settings');
                                $router->post('settings', [VideoDubbingSettingController::class, 'update'])->name('update');
                            });
                    });
            });

        return $this;
    }

    private function router(): Router|Route
    {
        return $this->app['router'];
    }
}
