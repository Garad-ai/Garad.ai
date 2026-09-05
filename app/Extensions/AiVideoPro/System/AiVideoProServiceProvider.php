<?php

declare(strict_types=1);

namespace App\Extensions\AiVideoPro\System;

use App\Extensions\AiVideoPro\System\Http\Controllers\AiVideoProController;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AiVideoProServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(Kernel $kernel): void
    {
        $this->registerTranslations()
            ->registerViews()
            ->registerRoutes()
            ->registerMigrations();
    }

    protected function registerTranslations(): static
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'ai-video-pro');

        return $this;
    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'ai-video-pro');

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
                                $router->resource('ai-video-pro', AiVideoProController::class)->except('destroy', 'show');
                                $router->get('ai-video-pro-delete/{id}', [AiVideoProController::class, 'delete'])->name('ai-video-pro.delete');
                                $router->post('ai-video-pro-bulk-delete', [AiVideoProController::class, 'bulkDelete'])->name('ai-video-pro.bulk-delete');
                                $router->get('ai-video-pro-check', [AiVideoProController::class, 'checkVideoStatus'])->name('ai-video-pro.check');
                                $router->post('ai-video-pro-enhance-prompt', [AiVideoProController::class, 'enhancePrompt'])->name('ai-video-pro.enhance-prompt');
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
