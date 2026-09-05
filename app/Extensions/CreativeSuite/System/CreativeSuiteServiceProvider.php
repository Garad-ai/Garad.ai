<?php

declare(strict_types=1);

namespace App\Extensions\CreativeSuite\System;

use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Extensions\CreativeSuite\System\Http\Controllers\Admin\CreativeSuiteSettingsController;
use App\Extensions\CreativeSuite\System\Http\Controllers\CreativeSuiteAIController;
use App\Extensions\CreativeSuite\System\Http\Controllers\CreativeSuiteController;
use App\Extensions\CreativeSuite\System\Http\Controllers\CreativeSuiteDocumentController;
use App\Extensions\CreativeSuite\System\Http\Controllers\ImageUploadController;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CreativeSuiteServiceProvider extends ServiceProvider implements UninstallExtensionServiceProviderInterface
{
    public function register(): void
    {
        $this->registerConfig();
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
        //        $this->loadViewComponentsAs('example', []);

        return $this;
    }

    public function publishAssets(): static
    {
        $this->publishes([
            __DIR__ . '/../resources/assets/img'       => public_path('vendor/creative-suite/img'),
            __DIR__ . '/../resources/assets/templates' => public_path('vendor/creative-suite/templates'),
        ], 'extension');

        return $this;
    }

    public function registerConfig(): static
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/creative-suite.php', 'creative-suite');

        return $this;
    }

    protected function registerTranslations(): static
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'creative-suite');

        return $this;
    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'creative-suite');

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
                'prefix'     => 'dashboard/user/creative-suite',
                'as'         => 'dashboard.user.creative-suite.',
            ], function (Router $router) {
                $router->get('', CreativeSuiteController::class)->name('index');

                $router->post('image/upload', ImageUploadController::class)->name('image.upload');
                $router->post('document', [CreativeSuiteDocumentController::class, 'updateOrCreate'])->name('document.update-or-create');
                $router->post('document/duplicate', [CreativeSuiteDocumentController::class, 'duplicate'])->name('document.duplicate');
                $router->post('document/name', [CreativeSuiteDocumentController::class, 'name'])->name('document.name');
                $router->post('document/delete', [CreativeSuiteDocumentController::class, 'destroy'])->name('document.delete');
                $router->get('document/{document}', [CreativeSuiteDocumentController::class, 'show'])->name('document.update-or-create');

                $router->post('ai/editor', [CreativeSuiteAIController::class, 'editor'])->name('ai.editor');
                $router->get('ai/editor/{task}/status', [CreativeSuiteAIController::class, 'status'])->name('ai.editor.status');
            });

        $this->router()
            ->group([
                'middleware' => ['web', 'auth'],
                'prefix'     => 'dashboard/admin/creative-suite',
                'as'         => 'dashboard.admin.creative-suite.',
            ], function (Router $router) {
                $router->get('settings', [CreativeSuiteSettingsController::class, 'index'])->name('settings');
                $router->post('settings', [CreativeSuiteSettingsController::class, 'update'])->name('settings.update');
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
