<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProSkills\System;

use App\Domains\Marketplace\Contracts\ExtensionRegisterKeyProviderInterface;
use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Extensions\AIChatProSkills\System\Http\Controllers\AdminSkillController;
use App\Extensions\AIChatProSkills\System\Http\Controllers\UserSkillController;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AIChatProSkillsServiceProvider extends ServiceProvider implements ExtensionRegisterKeyProviderInterface, UninstallExtensionServiceProviderInterface
{
    public function register(): void
    {
        $this->registerConfig();
    }

    public function boot(): void
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
        return $this;
    }

    public function publishAssets(): static
    {
        $this->publishes([
        ], 'extension');

        return $this;
    }

    public function registerConfig(): static
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/ai-chat-pro-skills.php', 'ai-chat-pro-skills');

        return $this;
    }

    protected function registerTranslations(): static
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'ai-chat-pro-skills');

        return $this;
    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'ai-chat-pro-skills');

        return $this;
    }

    public function registerMigrations(): static
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        return $this;
    }

    private function registerRoutes(): static
    {
        // Guest-accessible routes
        $this->router()
            ->group([
                'middleware' => ['web'],
            ], function (Router $router) {
                $router
                    ->controller(UserSkillController::class)
                    ->prefix('dashboard/user/skills')
                    ->name('dashboard.guest.skills.')
                    ->group(function (Router $router) {
                        $router->get('/public-search', 'publicSearch')->name('public-search');
                        $router->get('/public-list', 'publicList')->name('public-list');
                        $router->get('/public-show/{skill}', 'publicShow')->name('public-show');
                    });
            });

        // Admin routes
        $this->router()
            ->group([
                'middleware' => ['web', 'auth'],
            ], function (Router $router) {
                $router
                    ->controller(AdminSkillController::class)
                    ->prefix('dashboard/admin/skills')
                    ->name('dashboard.admin.skills.')
                    ->middleware(['admin'])
                    ->group(function (Router $router) {
                        $router->get('/', 'index')->name('index');
                        $router->post('/', 'store')->name('store');
                        $router->post('/import-github', 'importFromGithub')->name('import-github');
                        $router->post('/scan-github', 'scanGithub')->name('scan-github');
                        $router->post('/import-github-bulk', 'importGithubBulk')->name('import-github-bulk');
                        $router->get('/{skill}', 'show')->name('show');
                        $router->put('/{skill}', 'update')->name('update');
                        $router->delete('/{skill}', 'destroy')->name('destroy');
                    });

                // User routes
                $router
                    ->controller(UserSkillController::class)
                    ->prefix('dashboard/user/skills')
                    ->name('dashboard.user.skills.')
                    ->group(function (Router $router) {
                        $router->get('/', 'index')->name('index');
                        $router->post('/add/{skill}', 'addSkill')->name('add');
                        $router->post('/remove/{skill}', 'removeSkill')->name('remove');
                        $router->post('/toggle-auto/{skill}', 'toggleAutoUse')->name('toggle-auto');
                        $router->post('/upload', 'upload')->name('upload');
                        $router->post('/import-github', 'importFromGithub')->name('import-github');
                        $router->post('/scan-github', 'scanGithub')->name('scan-github');
                        $router->post('/import-github-bulk', 'importGithubBulk')->name('import-github-bulk');
                        $router->get('/search', 'search')->name('search');
                        $router->post('/create-from-content', 'createFromContent')->name('create-from-content');
                        $router->get('/export/{skill}', 'export')->name('export');
                        $router->get('/file-content/{skill}', 'fileContent')->name('file-content');
                        $router->delete('/delete/{skill}', 'deleteSkill')->name('delete');
                        $router->get('/{skill}', 'show')->name('show');
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
        return 'ai-chat-pro-skills';
    }
}
