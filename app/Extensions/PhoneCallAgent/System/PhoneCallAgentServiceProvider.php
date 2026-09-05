<?php

declare(strict_types=1);

namespace App\Extensions\PhoneCallAgent\System;

use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Extensions\PhoneCallAgent\System\Console\Commands\TwilioConversationRelayServer;
use App\Extensions\PhoneCallAgent\System\Console\Commands\TwilioRelayTestCall;
use App\Extensions\PhoneCallAgent\System\Http\Controllers\PhoneCallAgentCallTagController;
use App\Extensions\PhoneCallAgent\System\Http\Controllers\PhoneCallAgentController;
use App\Extensions\PhoneCallAgent\System\Http\Controllers\PhoneCallAgentHistoryController;
use App\Extensions\PhoneCallAgent\System\Http\Controllers\PhoneCallAgentSettingController;
use App\Extensions\PhoneCallAgent\System\Http\Controllers\PhoneCallAgentTrainController;
use App\Extensions\PhoneCallAgent\System\Http\Controllers\PhoneCallAgentWebhookController;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PhoneCallAgentServiceProvider extends ServiceProvider implements UninstallExtensionServiceProviderInterface
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
            ->registerAssets();

        if ($this->app->runningInConsole()) {
            $this->commands([
                TwilioConversationRelayServer::class,
                TwilioRelayTestCall::class,
            ]);
        }
    }

    public function registerConfig(): static
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/phone-call-agent.php', 'phone-call-agent');

        return $this;
    }

    protected function registerTranslations(): static
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'phone-call-agent');

        return $this;
    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'phone-call-agent');

        return $this;
    }

    public function registerMigrations(): static
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        return $this;
    }

    public function registerAssets(): static
    {
        $this->publishes([
            __DIR__ . '/../resources/assets/images' => public_path('vendor/phone-call-agent/images'),
        ], 'phone-call-agent-assets');

        return $this;
    }

    private function registerRoutes(): static
    {
        $this->router()
            ->group([
                'middleware' => ['web', 'auth'],
                'prefix'     => 'dashboard/phone-call-agent',
                'as'         => 'dashboard.phone-call-agent.',
                'controller' => PhoneCallAgentController::class,
            ], function (Router $router) {
                $router->get('', 'index')->name('index');
                $router->get('history', 'index')->name('history');
                $router->get('new', 'index')->name('new');
                $router->post('store', 'store')->name('store');
                $router->put('update', 'update')->name('update');
                $router->delete('delete', 'delete')->name('delete');
                $router->post('{agent}/simulate', 'simulate')->name('simulate');
                $router->post('booking/calendly-event-types', 'fetchCalendlyEventTypes')->name('booking.calendly-event-types');

                $router->group([
                    'prefix' => 'phone-numbers',
                    'as'     => 'phone-numbers.',
                ], function (Router $router) {
                    $router->post('import', 'importPhoneNumber')->name('import');
                    $router->post('assign', 'assignPhoneNumber')->name('assign');
                    $router->get('{agent}', 'phoneNumbers')->name('index');
                    $router->delete('{agent}', 'releasePhoneNumber')->name('release');
                });

                $router->group([
                    'prefix'     => 'train',
                    'as'         => 'train.',
                    'controller' => PhoneCallAgentTrainController::class,
                ], function (Router $router) {
                    $router->get('data', 'trainData')->name('data');
                    $router->delete('delete', 'delete')->name('delete');
                    $router->post('generate', 'generateEmbedding')->name('generate');
                    $router->post('file', 'trainFile')->name('file');
                    $router->post('text', 'trainText')->name('text');
                    $router->post('url', 'trainUrl')->name('url');
                });

                $router->get('calls', [PhoneCallAgentHistoryController::class, 'index'])->name('calls');
                $router->get('calls/export', [PhoneCallAgentHistoryController::class, 'export'])->name('calls.export');
                $router->post('calls/pin', [PhoneCallAgentHistoryController::class, 'pin'])->name('calls.pin');
                $router->delete('calls/{call}', [PhoneCallAgentHistoryController::class, 'destroy'])->name('calls.destroy');

                $router->group([
                    'prefix'     => 'call-tags',
                    'as'         => 'call-tags.',
                    'controller' => PhoneCallAgentCallTagController::class,
                ], function (Router $router) {
                    $router->get('', 'index')->name('index');
                    $router->post('', 'store')->name('store');
                    $router->post('sync', 'sync')->name('sync');
                });

                $router->group([
                    'prefix' => 'admin',
                    'as'     => 'admin.',
                ], function (Router $router) {
                    $router->get('settings', [PhoneCallAgentSettingController::class, 'index'])->name('settings');
                    $router->post('settings', [PhoneCallAgentSettingController::class, 'update'])->name('settings.update');
                });
            })
            ->group([
                'prefix' => 'api/phone-call-agent/webhook',
                'as'     => 'api.phone-call-agent.webhook.',
            ], function (Router $router) {
                $router->post('twilio/inbound', [PhoneCallAgentWebhookController::class, 'twilioInbound'])->name('twilio.inbound');
                $router->post('twilio/status', [PhoneCallAgentWebhookController::class, 'twilioStatus'])->name('twilio.status');
                $router->post('elevenlabs/inbound', [PhoneCallAgentWebhookController::class, 'elevenLabsInbound'])->name('elevenlabs.inbound');
                $router->post('elevenlabs/tool/{agentUuid}/{toolName}', [PhoneCallAgentWebhookController::class, 'elevenLabsTool'])->name('elevenlabs.tool');
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
}
