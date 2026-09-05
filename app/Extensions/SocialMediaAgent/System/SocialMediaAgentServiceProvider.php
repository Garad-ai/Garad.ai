<?php

declare(strict_types=1);

namespace App\Extensions\SocialMediaAgent\System;

use App\Domains\Marketplace\Contracts\ExtensionRegisterKeyProviderInterface;
use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Extensions\SocialMediaAgent\System\Console\Commands\CheckPendingImagesCommand;
use App\Extensions\SocialMediaAgent\System\Console\Commands\CheckPendingVideosCommand;
use App\Extensions\SocialMediaAgent\System\Console\Commands\GenerateAgentPostsCommand;
use App\Extensions\SocialMediaAgent\System\Console\Commands\PostMetricsAnalyzerCommand;
use App\Extensions\SocialMediaAgent\System\Console\Commands\PostPerformanceAdvisorCommand;
use App\Extensions\SocialMediaAgent\System\Console\Commands\RegeneratePostImagesCommand;
use App\Extensions\SocialMediaAgent\System\Console\Commands\SeedDemoDataCommand;
use App\Extensions\SocialMediaAgent\System\Console\Commands\TrendFinderCommand;
use App\Extensions\SocialMediaAgent\System\Console\Commands\UpdateAverageMetricsCommand;
use App\Extensions\SocialMediaAgent\System\Console\Commands\WeeklySocialTrendsCommand;
use App\Extensions\SocialMediaAgent\System\Http\Controllers\SocialMediaAgentAnalysisController;
use App\Extensions\SocialMediaAgent\System\Http\Controllers\SocialMediaAgentChatController;
use App\Extensions\SocialMediaAgent\System\Http\Controllers\SocialMediaAgentChatSettingsController;
use App\Extensions\SocialMediaAgent\System\Http\Controllers\SocialMediaAgentController;
use App\Extensions\SocialMediaAgent\System\Http\Controllers\SocialMediaAgentPostController;
use App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgent;
use App\Extensions\SocialMediaAgent\System\Policies\SocialMediaAgentPolicy;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class SocialMediaAgentServiceProvider extends ServiceProvider implements ExtensionRegisterKeyProviderInterface, UninstallExtensionServiceProviderInterface
{
    public function register(): void {}

    public function boot(Kernel $kernel): void
    {
        $this->registerTranslations()
            ->registerViews()
            ->registerRoutes()
            ->registerMigrations()
            ->registerPolicies()
            ->registerCommands()
            ->publishAssets();
    }

    protected function registerCommands(): static
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateAgentPostsCommand::class,
                CheckPendingImagesCommand::class,
                CheckPendingVideosCommand::class,
                PostMetricsAnalyzerCommand::class,
                TrendFinderCommand::class,
                PostPerformanceAdvisorCommand::class,
                WeeklySocialTrendsCommand::class,
                SeedDemoDataCommand::class,
                UpdateAverageMetricsCommand::class,
                RegeneratePostImagesCommand::class,
            ]);

            // exec('php artisan social-media-agent:generate-posts 1');
            // Schedule tasks
            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);
                $schedule->command('social-media-agent:generate-posts')->everyTwoMinutes();
                $schedule->command('social-media-agent:check-pending-images')->everyFiveMinutes();
                $schedule->command('social-media-agent:check-pending-videos')->everyFiveMinutes();
                $schedule->command('social-media-agent:post-metrics-analyzer')->cron('10 1 */3 * *');
                $schedule->command('social-media-agent:post-performance-advisor')->cron('25 1 */3 * *');
                $schedule->command('social-media-agent:trend-finder')->cron('40 1 */3 * *');
                $schedule->command('social-media-agent:weekly-social-trends')->cron('0 2 */3 * *');
            });
        }

        return $this;
    }

    protected function registerPolicies(): static
    {
        Gate::policy(SocialMediaAgent::class, SocialMediaAgentPolicy::class);

        return $this;
    }

    public function publishAssets(): static
    {
        $this->publishes([
            __DIR__ . '/../resources/assets/images' => public_path('vendor/social-media-agent/images'),
            __DIR__ . '/../resources/assets/videos' => public_path('vendor/social-media-agent/videos'),
        ], 'extension');

        return $this;
    }

    protected function registerTranslations(): static
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', $this->registerKey());

        return $this;
    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], $this->registerKey());

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
                    ->name('dashboard.user.social-media.agent.')
                    ->prefix('dashboard/user/social-media/agent')
                    ->group(function (Router $router) {

                        Route::get('chat/{id?}', [SocialMediaAgentChatController::class, 'index'])->name('chat.index');
                        // Main CRUD routes
                        Route::get('', [SocialMediaAgentController::class, 'index'])->name('index');
                        Route::get('post-items', [SocialMediaAgentController::class, 'postItems'])->name('post-items');
                        Route::get('create', [SocialMediaAgentController::class, 'create'])->name('create');
                        Route::get('agents', [SocialMediaAgentController::class, 'agents'])->name('agents');
                        Route::get('calendar', [SocialMediaAgentController::class, 'calendar'])->name('calendar');
                        Route::get('posts', [SocialMediaAgentController::class, 'posts'])->name('posts');
                        Route::get('analytics', [SocialMediaAgentController::class, 'analytics'])->name('analytics');
                        Route::get('accounts', [SocialMediaAgentController::class, 'accounts'])->name('accounts');
                        Route::post('', [SocialMediaAgentController::class, 'store'])->name('store');
                        Route::get('{agent}/edit', [SocialMediaAgentController::class, 'edit'])->name('edit');
                        Route::put('{agent}', [SocialMediaAgentController::class, 'update'])->name('update');
                        Route::delete('{agent}', [SocialMediaAgentController::class, 'destroy'])->name('destroy');

                        // Wizard AJAX endpoints
                        Route::post('scrape-website', [SocialMediaAgentController::class, 'scrapeWebsite'])->name('scrape-website');
                        Route::post('generate-targets', [SocialMediaAgentController::class, 'generateTargets'])->name('generate-targets');
                        Route::post('preview-post', [SocialMediaAgentController::class, 'previewPost'])->name('preview-post');

                        // Post management
                        Route::post('{agent}/generate-posts', [SocialMediaAgentController::class, 'generatePosts'])->name('generate-posts');
                        Route::post('posts/{post}/approve', [SocialMediaAgentController::class, 'approvePost'])->name('posts.approve');
                        Route::post('{agent}/approve-bulk', [SocialMediaAgentController::class, 'approveBulk'])->name('approve-bulk');
                        Route::delete('posts/{post}/reject', [SocialMediaAgentController::class, 'rejectPost'])->name('posts.reject');
                        Route::post('posts/{post}/duplicate', [SocialMediaAgentController::class, 'duplicatePost'])->name('posts.duplicate');

                        Route::get('video/status', [SocialMediaAgentController::class, 'getVideoStatus'])->name('video.status');
                        Route::get('image/status', [SocialMediaAgentController::class, 'getImageStatus'])->name('image.status');

                        // Analyses
                        Route::get('analyses', [SocialMediaAgentAnalysisController::class, 'index'])->name('analyses.index');
                        Route::get('analyses/{analysis}', [SocialMediaAgentAnalysisController::class, 'show'])->name('analyses.show');
                        Route::post('analyses/{analysis}/read', [SocialMediaAgentAnalysisController::class, 'markAsRead'])->name('analyses.mark-read');
                        Route::delete('analyses/{analysis}', [SocialMediaAgentAnalysisController::class, 'destroy'])->name('analyses.destroy');
                        Route::delete('analyses', [SocialMediaAgentAnalysisController::class, 'clearAll'])->name('analyses.clear-all');

                        // API endpoints
                        Route::get('api/pending-count', [SocialMediaAgentController::class, 'getPendingCount'])->name('api.pending-count');
                        Route::get('api/posts', [SocialMediaAgentController::class, 'getPosts'])->name('api.posts');
                        Route::post('api/posts', [SocialMediaAgentController::class, 'storePost'])->name('api.posts.store');
                        Route::post('api/upload-image', [SocialMediaAgentController::class, 'uploadImage'])->name('api.upload-image');
                        Route::put('api/posts/{post}', [SocialMediaAgentController::class, 'updatePost'])->name('api.posts.update');
                        Route::post('api/posts/generate-content', [SocialMediaAgentController::class, 'generatePostContent'])->name('api.posts.generate-content');
                        Route::post('api/posts/{post}/regenerate', [SocialMediaAgentPostController::class, 'regenerateContent'])->name('api.posts.regenerate');
                        Route::post('api/posts/generate-image', [SocialMediaAgentController::class, 'generatePostImage'])->name('api.posts.generate-image');
                        Route::get('api/generation-status', [SocialMediaAgentController::class, 'getGenerationStatus'])->name('api.generation-status');
                    });
            });

        $this->router()
            ->group([
                'middleware' => ['web', 'auth'],
            ], function (Router $router) {
                $router
                    ->prefix('dashboard/admin/social-media/agent/chat')
                    ->name('dashboard.admin.social-media.agent.chat.')
                    ->middleware('admin')
                    ->group(function (Router $router) {
                        $router->get('settings', [SocialMediaAgentChatSettingsController::class, 'index'])->name('settings');
                        $router->post('settings', [SocialMediaAgentChatSettingsController::class, 'update'])->name('settings.update');
                    });
            });

        // Fal.ai webhook (no auth required)
        $this->router()
            ->group([
                'middleware' => ['api'],
            ], function (Router $router) {
                Route::post('social-media-agent/fal-webhook', [SocialMediaAgentController::class, 'falWebhook'])->name('dashboard.user.social-media.agent.fal-webhook');
            });

        return $this;
    }

    private function router(): Router|Route
    {
        return $this->app['router'];
    }

    public static function uninstall(): void {}

    public function registerKey(): string
    {
        return 'social-media-agent';
    }
}
