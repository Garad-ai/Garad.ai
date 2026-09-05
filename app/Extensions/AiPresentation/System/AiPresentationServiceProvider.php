<?php

declare(strict_types=1);

namespace App\Extensions\AiPresentation\System;

use App\Domains\Marketplace\Contracts\ExtensionRegisterKeyProviderInterface;
use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Extensions\AiPresentation\System\Http\Controllers\AiPresentationController;
use App\Extensions\AiPresentation\System\Http\Controllers\GammaAiSettingsController;
use App\Http\Middleware\CheckTemplateTypeAndPlan;
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
class AiPresentationServiceProvider extends ServiceProvider implements ExtensionRegisterKeyProviderInterface, UninstallExtensionServiceProviderInterface
{
    public function register(): void {}

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
        //        $this->loadViewComponentsAs('ai-presentation', []);

        return $this;
    }

    public function publishAssets(): static
    {
        $this->publishes([
            //            __DIR__ . '/../resources/assets/js' => public_path('vendor/ai-presentation/js'),
            __DIR__ . '/../resources/assets' => public_path('vendor/ai-presentation'),
        ], 'extension');

        return $this;
    }

    public function registerConfig(): static
    {
        // $this->mergeConfigFrom(__DIR__ . '/../config/ai-presentation.php', 'ai-presentation');

        return $this;
    }

    protected function registerTranslations(): static
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'ai-presentation');

        return $this;
    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'ai-presentation');

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
                $router->controller(GammaAiSettingsController::class)
                    ->prefix('dashboard/admin/settings/')
                    ->middleware('admin')
                    ->name('dashboard.admin.settings.')->group(function (Router $router) {
                        $router->get('gamma-ai', 'index')->name('gamma-ai');
                        $router->post('gamma-ai', 'update')->name('gamma-ai.update');
                    });

                $router->controller(AiPresentationController::class)
                    ->prefix('dashboard/user/ai-presentation')
                    ->name('dashboard.user.ai-presentation.')
                    ->group(function (Router $router) {
                        $router->get('/', 'index')->name('index')->middleware(CheckTemplateTypeAndPlan::class);
                        $router->post('generate', 'generate')->name('generate');
                        $router->get('status/{generationId}', 'checkStatus')->name('check-status');
                        $router->delete('delete/{generationId}', 'delete')->name('delete');
                        $router->get('gallery', 'gallery')->name('gallery');
                        $router->post('rename-pdf', 'renamePdf')->name('rename-pdf');
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
        return 'ai-presentation';
    }
}
