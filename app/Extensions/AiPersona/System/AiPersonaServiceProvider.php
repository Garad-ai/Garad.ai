<?php

declare(strict_types=1);

namespace App\Extensions\AiPersona\System;

use App\Extensions\AiPersona\System\Http\Controllers\AiPersonaController;
use App\Extensions\AiPersona\System\Http\Controllers\HeygenSettingController;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AiPersonaServiceProvider extends ServiceProvider
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
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'ai-persona');

        return $this;
    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'ai-persona');

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
                                $router->resource('ai-persona', AiPersonaController::class)->except('destroy', 'show');
                                $router->get('ai-persona-delete/{id}', [AiPersonaController::class, 'delete'])->name('ai-persona.delete');
                                $router->post('ai-persona-bulk-delete', [AiPersonaController::class, 'bulkDelete'])->name('ai-persona.bulk-delete');
                                $router->get('ai-persona-check', [AiPersonaController::class, 'checkVideoStatus'])->name('ai-persona.check');
                                $router->get('ai-persona-videos', [AiPersonaController::class, 'videos'])->name('ai-persona.videos');
                                $router->post('ai-persona-enhance-script', [AiPersonaController::class, 'enhanceScript'])->name('ai-persona.enhance-script');
                                $router->post('ai-persona-avatars', [AiPersonaController::class, 'storeAvatar'])->name('ai-persona.avatars.store');
                                $router->get('ai-persona-avatars', [AiPersonaController::class, 'listUserAvatars'])->name('ai-persona.avatars.index');
                                $router->get('ai-persona-avatars-check', [AiPersonaController::class, 'checkAvatars'])->name('ai-persona.avatars.check');
                                $router->delete('ai-persona-avatars/{id}', [AiPersonaController::class, 'deleteAvatar'])->whereNumber('id')->name('ai-persona.avatars.delete');
                            });
                        $router
                            ->controller(HeygenSettingController::class)
                            ->prefix('admin/settings')
                            ->name('admin.settings.')
                            ->group(function (Router $router) {
                                $router->get('heygen', 'index')->name('heygen');
                                $router->post('heygen', 'update')->name('heygen.update');
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
