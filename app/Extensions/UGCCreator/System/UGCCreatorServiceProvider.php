<?php

declare(strict_types=1);

namespace App\Extensions\UGCCreator\System;

use App\Domains\Marketplace\Contracts\ExtensionRegisterKeyProviderInterface;
use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Extensions\UGCCreator\System\Http\Controllers\UGCCreatorAssetController;
use App\Extensions\UGCCreator\System\Http\Controllers\UGCCreatorController;
use App\Extensions\UGCCreator\System\Http\Controllers\UGCCreatorSettingController;
use App\Extensions\UGCCreator\System\Http\Controllers\UGCCreatorVideoController;
use App\Extensions\UGCCreator\System\Http\Controllers\UGCCreatorVoiceController;
use App\Extensions\UGCCreator\System\Models\UGCCreatorVideo;
use App\Http\Middleware\CheckTemplateTypeAndPlan;
use App\Models\User;
use App\Services\UGCStudio\UGCSource;
use App\Services\UGCStudio\UGCSourceRegistry;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * UGC Creator extension service provider.
 *
 * @note Registered in MarketplaceServiceProvider under the 'ugc-creator' key so
 *       Laravel discovers it on boot.
 */
class UGCCreatorServiceProvider extends ServiceProvider implements ExtensionRegisterKeyProviderInterface, UninstallExtensionServiceProviderInterface
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
            key: 'ugc-creator',
            label: __('UGC Creator'),
            icon: 'tabler-movie',
            entryRoute: 'dashboard.user.ugc-creator.index',
            description: __('Turn your ideas into realistic UGC videos with optional product shots and avatar images.'),
            cardImage: custom_theme_url('/assets/img/ugc-studio/ugc-creator-card.webp'),
            pagedOutputsResolver: function (User $user, string $bucket, int $page, int $perPage): array {
                $query = UGCCreatorVideo::query()
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
                    'entries' => $videos->map(fn (UGCCreatorVideo $video) => [
                        'source'             => 'ugc-creator',
                        'source_label'       => __('UGC Creator'),
                        'id'                 => $video->id,
                        'title'              => $video->title,
                        'thumb_url'          => $video->thumb_url,
                        'video_url'          => $video->video_url,
                        'status'             => $video->status,
                        'error'              => $video->error,
                        'duration_seconds'   => $video->duration_seconds,
                        'formatted_duration' => $video->duration_seconds ? gmdate(($video->duration_seconds >= 3600 ? 'H:i:s' : 'i:s'), (int) $video->duration_seconds) : null,
                        'prompt'             => $video->video_details ?: $video->prompt,
                        'model'              => $video->model,
                        'created_at'         => $video->created_at,
                        'status_url'         => route('dashboard.user.ugc-creator.videos.status', ['video' => $video->id]),
                        'rename_url'         => route('dashboard.user.ugc-creator.videos.update', ['video' => $video->id]),
                        'destroy_url'        => route('dashboard.user.ugc-creator.videos.destroy', ['video' => $video->id]),
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
            __DIR__ . '/../resources/assets/images' => public_path('vendor/ugc-creator/images'),
        ], 'extension');

        return $this;
    }

    public function registerConfig(): static
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/ugc-creator.php', 'ugc-creator');

        return $this;
    }

    protected function registerTranslations(): static
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'ugc-creator');

        return $this;
    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'ugc-creator');

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
                'prefix'     => 'dashboard/user/ugc-creator',
                'as'         => 'dashboard.user.ugc-creator.',
            ], function (Router $router) {
                $router->get('/', UGCCreatorController::class)
                    ->name('index')
                    ->middleware(CheckTemplateTypeAndPlan::class);

                $router->middleware(CheckTemplateTypeAndPlan::class)->group(function (Router $router) {
                    $router->get('/assets', [UGCCreatorAssetController::class, 'index'])->name('assets.index');
                    $router->post('/assets', [UGCCreatorAssetController::class, 'store'])->name('assets.store');
                    $router->delete('/assets/{asset}', [UGCCreatorAssetController::class, 'destroy'])->name('assets.destroy');

                    $router->get('/voices', [UGCCreatorVoiceController::class, 'index'])->name('voices.index');

                    $router->post('/videos', [UGCCreatorVideoController::class, 'store'])->name('videos.store');
                    $router->get('/videos/{video}', [UGCCreatorVideoController::class, 'show'])->name('videos.show');
                    $router->get('/videos/{video}/status', [UGCCreatorVideoController::class, 'status'])->name('videos.status');
                    $router->patch('/videos/{video}', [UGCCreatorVideoController::class, 'update'])->name('videos.update');
                    $router->delete('/videos/{video}', [UGCCreatorVideoController::class, 'destroy'])->name('videos.destroy');
                });
            });

        $this->router()
            ->group([
                'middleware' => ['web', 'auth', 'admin'],
                'prefix'     => 'dashboard/admin/ugc-creator',
                'as'         => 'dashboard.admin.ugc-creator.',
            ], function (Router $router) {
                $router->get('/settings', [UGCCreatorSettingController::class, 'index'])->name('settings');
                $router->post('/settings', [UGCCreatorSettingController::class, 'update'])->name('settings.update');
            });

        return $this;
    }

    private function router(): Router|Route
    {
        return $this->app['router'];
    }

    public static function uninstall(): void
    {
        // No-op: video records and uploaded assets are preserved on uninstall.
    }

    public function registerKey(): string
    {
        return 'ugc-creator';
    }
}
