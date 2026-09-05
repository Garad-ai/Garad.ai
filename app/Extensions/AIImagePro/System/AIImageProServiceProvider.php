<?php

declare(strict_types=1);

namespace App\Extensions\AIImagePro\System;

use App\Domains\Marketplace\Contracts\ExtensionRegisterKeyProviderInterface;
use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Extensions\AIImagePro\System\Http\Controllers\AIImageProController;
use App\Extensions\AIImagePro\System\Http\Controllers\AIImageProSettingsController;
use App\Extensions\AIImagePro\System\Http\Middleware\RedirectToAIImageProLogin;
use App\Http\Middleware\CheckTemplateTypeAndPlan;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

/**
 * Author: MagicAI Team <info@liquid-themes.com>
 *
 * @note When you create a new service provider, make sure to add it to the "MarketplaceServiceProvider". Otherwise, your Laravel application won’t recognize this provider, and the related functions won’t work properly.
 * @note If you want to perform a specific action when an extension is uninstalled, you can use the UninstallExtensionServiceProviderInterface. By implementing this interface, you can define custom operations that will be triggered during the uninstallation of the extension.
 * @note The registerKey() method is used to provide a unique identifier for the extension, which is essential for the healthy check and other functionalities.
 */
class AIImageProServiceProvider extends ServiceProvider implements ExtensionRegisterKeyProviderInterface, UninstallExtensionServiceProviderInterface
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
        //        $this->loadViewComponentsAs('ai-image-pro', []);

        return $this;
    }

    public function publishAssets(): static
    {
        $this->publishes([
            //            __DIR__ . '/../resources/assets/js' => public_path('vendor/ai-image-pro/js'),
            __DIR__ . '/../resources/assets' => public_path('vendor/ai-image-pro'),
        ], 'extension');

        return $this;
    }

    public function registerConfig(): static
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/ai-image-pro.php', 'ai-image-pro');

        return $this;
    }

    protected function registerTranslations(): static
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'ai-image-pro');

        return $this;
    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'ai-image-pro');

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
                'middleware' => ['web', 'localeSessionRedirect', 'localizationRedirect', 'localeViewPath'],
                'prefix'     => 'ai-image-pro',
                'as'         => 'ai-image-pro.',
            ], function (Router $router) {
                $router->get('/', AIImageProController::class)->name('index');
                $router->post('/generate', [AIImageProController::class, 'generateImage'])->name('generate');
                $router->post('/tools/generate', [AIImageProController::class, 'generateToolImage'])->name('tools.generate');
                $router->get('/images', [AIImageProController::class, 'getImages'])->name('images');
                $router->get('/stats', [AIImageProController::class, 'getImageStats'])->name('stats');
                $router->get('/completed-images', [AIImageProController::class, 'getCompletedImages'])->name('completed-images');
                $router->get('/community-images', [AIImageProController::class, 'getCommunityImages'])->name('community.images');
                $router->post('/community-images/like', [AIImageProController::class, 'toggleLike'])->name('community.images.like');
                $router->post('/community-images/publish', [AIImageProController::class, 'togglePublish'])->name('community.images.publish');
                $router->post('/share/generate', [AIImageProController::class, 'generateShareLink'])->name('share.generate');
                $router->get('/share/{token?}', [AIImageProController::class, 'viewSharedImage'])->name('share.view');

                $router
                    ->middleware([RedirectToAIImageProLogin::class])
                    ->group(function (Router $router) {
                        // Media library
                        $router->get('/media-library', [AIImageProController::class, 'viewMediaLibrary'])->name('media-library');
                        $router->get('/media-library/images', [AIImageProController::class, 'getMediaLibraryImages'])->name('media-library.images');
                        $router->post('/media-library/delete', [AIImageProController::class, 'deleteMediaLibraryImages'])->name('media-library.delete');

                        // Realtime image generator
                        $router->get('/realtime', [AIImageProController::class, 'realtimeIndex'])->name('realtime');
                        $router->post('/realtime/generate', [AIImageProController::class, 'generateRealtimeImage'])->name('realtime.generate');
                        $router->get('/realtime/images', [AIImageProController::class, 'getRealtimeImages'])->name('realtime.images');
                        // Image editor
                        $router->get('/edit', [AIImageProController::class, 'editIndex'])->name('edit');
                    });
            });

        $this->router()
            ->group([
                'prefix'     => LaravelLocalization::setLocale(),
                'middleware' => ['web', 'localeSessionRedirect', 'localizationRedirect', 'localeViewPath'],
            ], function (Router $router) {
                $router
                    ->prefix('dashboard/user/ai-image-pro')
                    ->name('dashboard.user.ai-image-pro.')
                    ->middleware([RedirectToAIImageProLogin::class])
                    ->group(function (Router $router) {
                        $router->get('/', AIImageProController::class)->name('index')->middleware(CheckTemplateTypeAndPlan::class);
                        $router->match(['get', 'post'], '/images', [AIImageProController::class, 'getUserImages'])->name('images');
                        $router->get('media-library', [AIImageProController::class, 'viewMediaLibrary'])->name('media-library');
                        $router->post('enhance-prompt', [AIImageProController::class, 'enhancePrompt'])->name('enhance-prompt');
                        $router->get('realtime', [AIImageProController::class, 'realtimeIndex'])->name('realtime');
                        $router->get('edit', [AIImageProController::class, 'editIndex'])->name('edit');
                    });

                $router
                    ->middleware(['auth', 'admin'])
                    ->controller(AIImageProSettingsController::class)
                    ->prefix('dashboard/admin/ai-image-pro')
                    ->name('dashboard.admin.ai-image-pro.')
                    ->group(function (Router $router) {

                        $router->get('settings', 'edit')->name('settings');
                        $router->put('settings', 'update')->name('settings.update');

                        $router->prefix('community-images')->as('community-images.')
                            ->group(function (Router $router) {
                                $router->get('/', 'communityReqsIndex')->name('index');
                                $router->get('publish-requests', 'publishReqs')->name('publish-requests');
                                $router->post('publish-requests/{id}/approve', 'approveRequest')->name('publish-requests.approve');
                                $router->post('publish-requests/{id}/reject', 'rejectRequest')->name('publish-requests.reject');
                            });
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
        return 'ai-image-pro';
    }
}
