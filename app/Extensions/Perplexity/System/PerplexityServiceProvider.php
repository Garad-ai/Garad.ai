<?php

declare(strict_types=1);

namespace App\Extensions\Perplexity\System;

use App\Extensions\Perplexity\System\Http\Controllers\PerplexitySettingController;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PerplexityServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(Kernel $kernel): void
    {
        $this->registerViews()
            ->registerRoutes();

    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'perplexity');

        return $this;
    }

    private function registerRoutes(): void
    {
        $this->router()
            ->group([
                'middleware' => ['web', 'auth'],
            ], function (Router $router) {
                Route::controller(PerplexitySettingController::class)
                    ->prefix('dashboard/admin/settings')
                    ->name('dashboard.admin.settings.')
                    ->middleware('admin')
                    ->group(function (Router $router) {
                        $router->get('perplexity', 'index')->name('perplexity');
                        $router->post('perplexity', 'update')->name('perplexity.save');
                    });
            });

    }

    private function router(): Router|Route
    {
        return $this->app['router'];
    }
}
