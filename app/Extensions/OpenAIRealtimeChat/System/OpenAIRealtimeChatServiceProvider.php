<?php

declare(strict_types=1);

namespace App\Extensions\OpenAIRealtimeChat\System;

use App\Extensions\OpenAIRealtimeChat\System\Http\Controllers\RealtimeVoiceChatController;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class OpenAIRealtimeChatServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(Kernel $kernel): void
    {
        $this->registerMigrations()
            ->registerRoutes()
            ->registerViews();
    }

    public function registerMigrations(): static
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        return $this;
    }

    public function registerViews(): void
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'openai-realtime-chat');
    }

    private function registerRoutes(): static
    {
        $this->router()
            ->group([
                'middleware' => ['web', 'auth'],
            ], function (Router $route) {
                $route->group([
                    'prefix' => 'dashboard/user/',
                    'as'     => 'dashboard.user.',
                ], function (Router $router) {
                    $router->post('realtime/chat/checkBalance', [RealtimeVoiceChatController::class, 'checkBalance']);
                    $router->post('realtime/chat/session', [RealtimeVoiceChatController::class, 'session']);
                });
            });

        return $this;
    }

    private function router(): Router|Route
    {
        return $this->app['router'];
    }
}
