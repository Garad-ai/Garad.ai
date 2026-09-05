<?php

declare(strict_types=1);

namespace App\Extensions\ModelCouncil\System;

use App\Domains\Marketplace\Contracts\ExtensionRegisterKeyProviderInterface;
use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Extensions\ModelCouncil\System\Http\Controllers\ModelCouncilController;
use App\Helpers\Classes\MarketplaceHelper;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ModelCouncilServiceProvider extends ServiceProvider implements ExtensionRegisterKeyProviderInterface, UninstallExtensionServiceProviderInterface
{
    public function register(): void
    {
        $this->registerConfig();
    }

    public function boot(Kernel $kernel): void
    {
        if (! MarketplaceHelper::isRegistered('ai-chat-pro')) {
            return;
        }

        $this->registerTranslations()
            ->registerViews()
            ->registerRoutes()
            ->registerMigrations()
            ->publishAssets();
    }

    public function publishAssets(): static
    {
        $this->publishes([], 'extension');

        return $this;
    }

    public function registerConfig(): static
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/model-council.php', 'model-council');

        return $this;
    }

    protected function registerTranslations(): static
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'model-council');

        return $this;
    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'model-council');

        return $this;
    }

    public function registerMigrations(): static
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        return $this;
    }

    private function registerRoutes(): static
    {
        if (! MarketplaceHelper::isRegistered('multi-model')) {
            $this->router()->group([
                'middleware' => ['web', 'auth'],
                'prefix'     => 'dashboard/user/multimodel',
                'controller' => ModelCouncilController::class,
            ], function (Router $router) {
                $router->post('/accept-response', 'acceptResponse')->name('prefer');
            });
        }

        return $this;
    }

    private function router(): Router|Route
    {
        return $this->app['router'];
    }

    public static function uninstall(): void
    {
        //
    }

    public function registerKey(): string
    {
        return 'model-council';
    }
}
