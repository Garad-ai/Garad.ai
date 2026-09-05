<?php

declare(strict_types=1);

namespace App\Extensions\FluxPro\System;

use App\Extensions\FluxPro\System\Http\Controllers\FalAIWebhookController;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class FluxProServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(Kernel $kernel): void
    {
        $this->registerTranslations()
            ->registerViews()
            ->registerRoutes();

    }

    protected function registerTranslations(): static
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'flux-pro');

        return $this;
    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'flux-pro');

        return $this;
    }

    private function registerRoutes(): void
    {
        $this->router()
            ->group([
                'middleware' => ['web', 'auth'],
            ], function (Router $router) {
                $router->any('generator/webhook/fal-ai', FalAIWebhookController::class)
                    ->name('generator.webhook.fal-ai')
                    ->withoutMiddleware(['web', 'auth']);

            });
    }

    private function router(): Router|Route
    {
        return $this->app['router'];
    }
}
