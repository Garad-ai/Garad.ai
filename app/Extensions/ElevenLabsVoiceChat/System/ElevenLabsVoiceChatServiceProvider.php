<?php

declare(strict_types=1);

namespace App\Extensions\ElevenLabsVoiceChat\System;

use App\Extensions\ElevenLabsVoiceChat\System\Http\Controllers\ElevenLabsVoiceChatController;
use App\Extensions\ElevenLabsVoiceChat\System\Http\Controllers\ElevenLabsVoiceChatTrainController;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ElevenLabsVoiceChatServiceProvider extends ServiceProvider
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
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'elevenlabs-voice-chat');
    }

    private function registerRoutes(): static
    {
        $this->router()
            ->group([
                'middleware' => ['web', 'auth'],
            ], function (Router $route) {
                $route->group([
                    'prefix' => 'dashboard/admin/voice-chatbot',
                    'as'     => 'dashboard.admin.voice-chatbot.',
                ], function (Router $router) {
                    $router->get('', [ElevenLabsVoiceChatController::class, 'index'])->name('index');
                    $router->put('update', [ElevenLabsVoiceChatController::class, 'update'])->name('update');
                    $router->post('check-balance', [ElevenLabsVoiceChatController::class, 'checkVoiceBalance'])->name('check-Balance');

                    $router->group([
                        'prefix' 		   => 'train',
                        'as' 			      => 'train.',
                        'controller' 	=> ElevenLabsVoiceChatTrainController::class,
                    ], function (Router $router) {
                        $router->get('data', 'trainData')->name('data');
                        $router->post('file', 'trainFile')->name('file');
                        $router->post('text', 'trainText')->name('text');
                        $router->post('url', 'trainUrl')->name('url');

                        $router->post('generate', 'generateEmbedding')->name('generate');
                        $router->delete('delete', 'delete')->name('delete');
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
