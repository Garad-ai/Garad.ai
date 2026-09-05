<?php

declare(strict_types=1);

namespace App\Extensions\BlogPilot\System;

use App\Domains\Marketplace\Contracts\ExtensionRegisterKeyProviderInterface;
use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Extensions\BlogPilot\System\Console\Commands\GenerateAgentPostsCommand;
use App\Extensions\BlogPilot\System\Console\Commands\PublishAgentPostsCommand;
use App\Extensions\BlogPilot\System\Console\Commands\SeedDemoDataCommand;
use App\Extensions\BlogPilot\System\Http\Controllers\BlogPilotController;
use App\Extensions\BlogPilot\System\Models\BlogPilot;
use App\Extensions\BlogPilot\System\Policies\BlogPilotPolicy;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class BlogPilotServiceProvider extends ServiceProvider implements ExtensionRegisterKeyProviderInterface, UninstallExtensionServiceProviderInterface
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
                PublishAgentPostsCommand::class,
                SeedDemoDataCommand::class,
            ]);

            // exec('php artisan blogpilot:generate-posts 1');
            // Schedule tasks
            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);
                $schedule->command('blogpilot:generate-posts')->everyTwoMinutes();
                $schedule->command('blogpilot:publish-posts')->everyMinute();
            });
        }

        return $this;
    }

    protected function registerPolicies(): static
    {
        Gate::policy(BlogPilot::class, BlogPilotPolicy::class);

        return $this;
    }

    public function publishAssets(): static
    {
        $this->publishes([
            __DIR__ . '/../resources/assets/images' => public_path('vendor/blogpilot/images'),
            __DIR__ . '/../resources/assets/videos' => public_path('vendor/blogpilot/videos'),
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
                    ->name('dashboard.user.blogpilot.agent.')
                    ->prefix('dashboard/user/blogpilot/agent')
                    ->group(function (Router $router) {
                        Route::get('', [BlogPilotController::class, 'index'])->name('index');
                        Route::get('post-items', [BlogPilotController::class, 'postItems'])->name('post-items');
                        Route::get('create', [BlogPilotController::class, 'create'])->name('create');
                        Route::get('agents', [BlogPilotController::class, 'agents'])->name('agents');
                        Route::get('calendar', [BlogPilotController::class, 'calendar'])->name('calendar');
                        Route::get('posts', [BlogPilotController::class, 'posts'])->name('posts');
                        Route::get('analytics', [BlogPilotController::class, 'analytics'])->name('analytics');
                        Route::post('', [BlogPilotController::class, 'store'])->name('store');
                        Route::get('{agent}/edit', [BlogPilotController::class, 'edit'])->name('edit');
                        Route::put('{agent}', [BlogPilotController::class, 'update'])->name('update');
                        Route::delete('{agent}', [BlogPilotController::class, 'destroy'])->name('destroy');

                        // Post management
                        Route::get('posts/{post}/edit', [BlogPilotController::class, 'editPost'])->name('posts.edit');
                        Route::delete('posts/{post}/reject', [BlogPilotController::class, 'rejectPost'])->name('posts.reject');
                        Route::post('posts/{post}/duplicate', [BlogPilotController::class, 'duplicatePost'])->name('posts.duplicate');
                        Route::post('posts/{post}/update', [BlogPilotController::class, 'updatePost'])->name('posts.update');
                        Route::post('posts/{post}/publish', [BlogPilotController::class, 'publishPostAjax'])->name('posts.publish');

                        // Wizard AJAX endpoints
                        Route::post('generate-topics', [BlogPilotController::class, 'generateTopics'])->name('generate-topics');

                        // API endpoints
                        Route::get('api/pending-count', [BlogPilotController::class, 'getPendingCount'])->name('api.pending-count');
                        Route::get('api/posts', [BlogPilotController::class, 'getPosts'])->name('api.posts');
                        Route::get('api/generation-status', [BlogPilotController::class, 'getGenerationStatus'])->name('api.generation-status');
                    });
            });

        // Fal.ai webhook (no auth required)
        $this->router()
            ->group([
                'middleware' => ['api'],
            ], function (Router $router) {
                Route::post('blogpilot/fal-webhook', [BlogPilotController::class, 'falWebhook'])->name('dashboard.user.blogpilot.agent.fal-webhook');
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
        return 'blogpilot';
    }
}
