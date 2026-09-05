<?php

declare(strict_types=1);

namespace App\Extensions\VideoEditor\System;

use App\Extensions\VideoEditor\System\Http\Controllers\VideoEditorAiController;
use App\Extensions\VideoEditor\System\Http\Controllers\VideoEditorController;
use App\Extensions\VideoEditor\System\Http\Controllers\VideoEditorExportController;
use App\Extensions\VideoEditor\System\Http\Controllers\VideoEditorMediaController;
use App\Extensions\VideoEditor\System\Http\Controllers\VideoEditorProjectController;
use App\Http\Middleware\CheckTemplateTypeAndPlan;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class VideoEditorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerConfig();
    }

    public function boot(Kernel $kernel): void
    {
        $this->registerViews()
            ->registerRoutes()
            ->registerMigrations()
            ->publishAssets();
    }

    private function registerConfig(): static
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/video-editor.php', 'video-editor');

        return $this;
    }

    private function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'video-editor');

        return $this;
    }

    private function registerMigrations(): static
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        return $this;
    }

    private function publishAssets(): static
    {
        $this->publishes([
            __DIR__ . '/../resources/assets' => public_path('vendor/video-editor'),
        ], 'extension');

        return $this;
    }

    private function registerRoutes(): static
    {
        $this->router()
            ->group([
                'middleware' => ['web', 'auth', CheckTemplateTypeAndPlan::class],
            ], function (Router $router) {
                $router
                    ->prefix('dashboard/user/video-editor')
                    ->name('dashboard.user.video-editor.')
                    ->group(function (Router $router) {
                        // Page routes
                        $router->get('', [VideoEditorController::class, 'index'])->name('index');
                        $router->get('editor/{project?}', [VideoEditorController::class, 'editor'])->name('editor');

                        // Project API
                        $router->group([
                            'prefix'     => 'projects',
                            'as'         => 'projects.',
                            'controller' => VideoEditorProjectController::class,
                        ], function (Router $router) {
                            $router->post('', 'store')->name('store');
                            $router->post('from-url', 'fromUrl')->name('fromUrl')->middleware('throttle:20,1');
                            $router->get('{project}', 'show')->name('show');
                            $router->put('{project}', 'update')->name('update');
                            $router->delete('{project}', 'destroy')->name('destroy');
                            $router->post('{project}/duplicate', 'duplicate')->name('duplicate');
                        });

                        // Media API
                        $router->group([
                            'prefix'     => 'media',
                            'as'         => 'media.',
                            'controller' => VideoEditorMediaController::class,
                        ], function (Router $router) {
                            $router->get('', 'index')->name('index');
                            $router->post('upload', 'upload')->name('upload')->middleware('throttle:10,1');
                            $router->delete('{media}', 'destroy')->name('destroy');
                            $router->post('import-from-library', 'importFromLibrary')->name('importFromLibrary');
                            $router->post('import-from-content-manager', 'importFromContentManager')->name('importFromContentManager')->middleware('throttle:10,1');
                        });

                        // AI Generation API (rate-limited — expensive external API calls)
                        $router->group([
                            'prefix'     => 'ai',
                            'as'         => 'ai.',
                            'controller' => VideoEditorAiController::class,
                        ], function (Router $router) {
                            $router->get('models', 'models')->name('models');
                            $router->post('generate', 'generate')->name('generate')->middleware('throttle:5,1');
                            $router->post('status', 'checkStatus')->name('status')->middleware('throttle:60,1');
                            $router->post('import', 'importToEditor')->name('import');
                            $router->get('credits', 'checkCredits')->name('credits');

                            // Voice (TTS) — rate-limited
                            $router->get('voice-models', 'voiceModels')->name('voiceModels');
                            $router->post('generate-voice', 'generateVoice')->name('generateVoice')->middleware('throttle:5,1');

                            // Music — rate-limited
                            $router->post('generate-music', 'generateMusic')->name('generateMusic')->middleware('throttle:5,1');
                        });

                        // Export API (rate-limited — CPU-intensive FFmpeg jobs)
                        $router->group([
                            'prefix'     => 'export',
                            'as'         => 'export.',
                            'controller' => VideoEditorExportController::class,
                        ], function (Router $router) {
                            $router->post('', 'start')->name('start')->middleware('throttle:3,1');
                            $router->get('{exportJob}/status', 'status')->name('status')->middleware('throttle:60,1');
                            $router->get('{exportJob}/download', 'download')->name('download');
                            $router->get('{exportJob}/delete', 'destroy')->name('delete');
                            $router->post('{exportJob}/rename', 'rename')->name('rename');
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
