<?php

declare(strict_types=1);

namespace App\Extensions\UGCFactory\System;

use App\Domains\Marketplace\Contracts\ExtensionRegisterKeyProviderInterface;
use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Extensions\UGCFactory\System\Http\Controllers\UGCFactoryActorController;
use App\Extensions\UGCFactory\System\Http\Controllers\UGCFactoryController;
use App\Extensions\UGCFactory\System\Http\Controllers\UGCFactorySettingController;
use App\Extensions\UGCFactory\System\Http\Controllers\UGCFactoryVideoController;
use App\Extensions\UGCFactory\System\Http\Controllers\UGCFactoryVoiceController;
use App\Extensions\UGCFactory\System\Models\UGCFactoryVideo;
use App\Http\Middleware\CheckTemplateTypeAndPlan;
use App\Models\User;
use App\Services\UGCStudio\UGCSource;
use App\Services\UGCStudio\UGCSourceRegistry;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Author: MagicAI Team <info@liquid-themes.com>
 *
 * @note When you create a new service provider, make sure to add it to the "MarketplaceServiceProvider". Otherwise, your Laravel application won't recognize this provider, and the related functions won't work properly.
 * @note If you want to perform a specific action when an extension is uninstalled, you can use the UninstallExtensionServiceProviderInterface. By implementing this interface, you can define custom operations that will be triggered during the uninstallation of the extension.
 * @note The registerKey() method is used to provide a unique identifier for the extension, which is essential for the healthy check and other functionalities.
 */
class UGCFactoryServiceProvider extends ServiceProvider implements ExtensionRegisterKeyProviderInterface, UninstallExtensionServiceProviderInterface
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
            ->registerComponents()
            ->registerUGCStudioSource();
    }

    private function registerUGCStudioSource(): static
    {
        $this->app->make(UGCSourceRegistry::class)->register(new UGCSource(
            key: 'ugc-factory',
            label: __('UGC Factory'),
            icon: 'tabler-wand',
            entryRoute: 'dashboard.user.ugc-factory.index',
            description: __('Create realistic UGC content for social media and marketing.'),
            pagedOutputsResolver: function (User $user, string $bucket, int $page, int $perPage): array {
                $query = UGCFactoryVideo::query()
                    ->forUser($user->id)
                    ->when(
                        $bucket === 'today',
                        fn ($q) => $q->whereDate('created_at', '>=', today()),
                        fn ($q) => $q->whereDate('created_at', '<', today()),
                    )
                    ->latest();

                $total = (clone $query)->count();
                $videos = $query->forPage($page, $perPage)->get();

                return [
                    'entries'  => $videos->map(fn (UGCFactoryVideo $video) => [
                        'source'           => 'ugc-factory',
                        'id'               => $video->id,
                        'title'            => $video->title,
                        'thumb_url'        => $video->thumb_url,
                        'video_url'        => $video->video_url,
                        'status'           => $video->status,
                        'duration_seconds' => $video->duration_seconds,
                        'created_at'       => $video->created_at,
                        'status_url'       => route('dashboard.user.ugc-factory.videos.status', ['video' => $video->id]),
                        'rename_url'       => route('dashboard.user.ugc-factory.videos.update', ['video' => $video->id]),
                        'destroy_url'      => route('dashboard.user.ugc-factory.videos.destroy', ['video' => $video->id]),
                    ])->all(),
                    'has_more' => ($page * $perPage) < $total,
                ];
            },
        ));

        return $this;
    }

    public function registerComponents(): static
    {
        return $this;
    }

    public function publishAssets(): static
    {
        $this->publishes([
            __DIR__ . '/../resources/assets/images' => public_path('vendor/ugc-factory/images'),
        ], 'extension');

        return $this;
    }

    public function registerConfig(): static
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/ugc-factory.php', 'ugc-factory');

        return $this;
    }

    protected function registerTranslations(): static
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'ugc-factory');

        return $this;
    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'ugc-factory');

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
                'prefix'     => 'dashboard/user/ugc-factory',
                'as'         => 'dashboard.user.ugc-factory.',
            ], function (Router $router) {
                $router->get('/', UGCFactoryController::class)
                    ->name('index')
                    ->middleware(CheckTemplateTypeAndPlan::class);

                $router->middleware(CheckTemplateTypeAndPlan::class)->group(function (Router $router) {
                    $router->get('/actors', [UGCFactoryActorController::class, 'index'])->name('actors.index');
                    $router->post('/actors/upload', [UGCFactoryActorController::class, 'uploadStore'])->name('actors.upload');
                    $router->post('/actors/generate', [UGCFactoryActorController::class, 'generateStore'])->name('actors.generate');
                    $router->get('/actors/{actor}/status', [UGCFactoryActorController::class, 'status'])->name('actors.status');
                    $router->delete('/actors/{actor}', [UGCFactoryActorController::class, 'destroy'])->name('actors.destroy');

                    $router->get('/voices', [UGCFactoryVoiceController::class, 'index'])->name('voices.index');

                    $router->post('/videos', [UGCFactoryVideoController::class, 'store'])->name('videos.store');
                    $router->get('/videos/{video}', [UGCFactoryVideoController::class, 'show'])->name('videos.show');
                    $router->get('/videos/{video}/status', [UGCFactoryVideoController::class, 'status'])->name('videos.status');
                    $router->patch('/videos/{video}', [UGCFactoryVideoController::class, 'update'])->name('videos.update');
                    $router->delete('/videos/{video}', [UGCFactoryVideoController::class, 'destroy'])->name('videos.destroy');
                });
            });

        $this->router()
            ->group([
                'middleware' => ['web', 'auth', 'admin'],
                'prefix'     => 'dashboard/admin/ugc-factory',
                'as'         => 'dashboard.admin.ugc-factory.',
            ], function (Router $router) {
                $router->get('/settings', [UGCFactorySettingController::class, 'index'])->name('settings');
                $router->post('/settings', [UGCFactorySettingController::class, 'update'])->name('settings.update');
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
        return 'ugc-factory';
    }
}
