<?php

declare(strict_types=1);

namespace App\Extensions\AiCaptions\System;

use App\Extensions\AiCaptions\System\Http\Controllers\AiCaptionsController;
use App\Extensions\AiCaptions\System\Http\Controllers\AiCaptionsSettingController;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AiCaptionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/aicaptions.php', 'aicaptions');
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
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'ai-captions');

        return $this;
    }

    protected function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'ai-captions');

        return $this;
    }

    protected function registerMigrations(): static
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        return $this;
    }

    protected function registerRoutes(): static
    {
        $this->router()->group([
            'middleware' => ['web', 'auth'],
        ], function (Router $router): void {
            $router->prefix('dashboard')->name('dashboard.')->group(function (Router $router): void {
                $router->prefix('user')->name('user.')->group(function (Router $router): void {
                    $router->get('ai-captions', [AiCaptionsController::class, 'index'])
                        ->name('ai-captions.index');
                    $router->get('ai-captions/all', [AiCaptionsController::class, 'all'])
                        ->name('ai-captions.all');
                    $router->post('ai-captions', [AiCaptionsController::class, 'store'])
                        ->name('ai-captions.store');
                    $router->get('ai-captions-check', [AiCaptionsController::class, 'checkStatus'])
                        ->name('ai-captions.check');
                    $router->get('ai-captions-delete/{id}', [AiCaptionsController::class, 'delete'])
                        ->name('ai-captions.delete');
                    $router->post('ai-captions-rename/{id}', [AiCaptionsController::class, 'rename'])
                        ->name('ai-captions.rename');
                    $router->post('ai-captions-bulk-delete', [AiCaptionsController::class, 'bulkDelete'])
                        ->name('ai-captions.bulk-delete');
                });

                $router->prefix('admin/ai-captions')
                    ->name('admin.ai-captions.')
                    ->middleware('admin')
                    ->group(function (Router $router): void {
                        $router->get('settings', [AiCaptionsSettingController::class, 'index'])->name('settings');
                        $router->post('settings', [AiCaptionsSettingController::class, 'update'])->name('update');
                    });
            });
        });

        return $this;
    }

    protected function router(): Router|Route
    {
        return $this->app['router'];
    }
}
