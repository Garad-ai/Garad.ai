<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System;

use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Extensions\AIAgent\System\Actions\AiCallAction;
use App\Extensions\AIAgent\System\Actions\GenerateReportAction;
use App\Extensions\AIAgent\System\Actions\PathAction;
use App\Extensions\AIAgent\System\Actions\SendMessageAction;
use App\Extensions\AIAgent\System\Connectors\ConnectorRegistry;
use App\Extensions\AIAgent\System\Connectors\MagicAiConnector;
use App\Extensions\AIAgent\System\Connectors\TelegramConnector;
use App\Extensions\AIAgent\System\Console\Commands\MigrateActionsToStepsCommand;
use App\Extensions\AIAgent\System\Console\Commands\ProcessWorkflowTriggersCommand;
use App\Extensions\AIAgent\System\Engine\AIAgentActionRegistry;
use App\Extensions\AIAgent\System\Enums\ChannelEnum;
use App\Extensions\AIAgent\System\Formatters\MessageFormatter;
use App\Extensions\AIAgent\System\Formatters\TelegramFormatter;
use App\Extensions\AIAgent\System\Http\Controllers\AIAgentDashboardController;
use App\Extensions\AIAgent\System\Http\Controllers\AIAgentSettingController;
use App\Extensions\AIAgent\System\Http\Controllers\ChannelController;
use App\Extensions\AIAgent\System\Http\Controllers\CopilotController;
use App\Extensions\AIAgent\System\Http\Controllers\KnowledgeSourceController;
use App\Extensions\AIAgent\System\Http\Controllers\MemoryController;
use App\Extensions\AIAgent\System\Http\Controllers\MessageController;
use App\Extensions\AIAgent\System\Http\Controllers\Webhook\GenericWebhookController;
use App\Extensions\AIAgent\System\Http\Controllers\Webhook\TelegramWebhookController;
use App\Extensions\AIAgent\System\Http\Controllers\WorkflowController;
use App\Extensions\AIAgent\System\Memory\MemoryRepository;
use App\Extensions\AIAgent\System\Models\AIAgentChannel;
use App\Extensions\AIAgent\System\Models\AIAgentMemory;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Policies\AIAgentChannelPolicy;
use App\Extensions\AIAgent\System\Policies\AIAgentMemoryPolicy;
use App\Extensions\AIAgent\System\Policies\AIAgentWorkflowPolicy;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AIAgentServiceProvider extends ServiceProvider implements UninstallExtensionServiceProviderInterface
{
    public function register(): void
    {
        $this->registerConfig();
        $this->app->singleton(ConnectorRegistry::class);
        $this->app->singleton(AIAgentActionRegistry::class);
    }

    public function boot(Kernel $kernel): void
    {
        $this->registerTranslations()
            ->registerViews()
            ->registerRoutes()
            ->registerMigrations()
            ->publishAssets()
            ->registerCommand()
            ->registerPolicies()
            ->registerComponents()
            ->registerTelegramConnector()
            ->registerBuiltInActions();
    }

    private function registerBuiltInActions(): static
    {
        $registry = $this->app->make(AIAgentActionRegistry::class);
        $registry->register('ai_call', AiCallAction::class);
        $registry->register('send_message', SendMessageAction::class);
        $registry->register('generate_report', GenerateReportAction::class);
        $registry->register('path', PathAction::class);

        return $this;
    }

    private function registerTelegramConnector(): static
    {
        $registry = $this->app->make(ConnectorRegistry::class);
        $registry->register(ChannelEnum::Telegram, TelegramConnector::class);
        $registry->register(ChannelEnum::MagicAi, MagicAiConnector::class);

        $formatter = $this->app->make(MessageFormatter::class);
        $formatter->registerFormatter(ChannelEnum::Telegram, $this->app->make(TelegramFormatter::class));

        return $this;
    }

    public function registerPolicies(): static
    {
        Gate::policy(AIAgentWorkflow::class, AIAgentWorkflowPolicy::class);
        Gate::policy(AIAgentChannel::class, AIAgentChannelPolicy::class);
        Gate::policy(AIAgentMemory::class, AIAgentMemoryPolicy::class);

        return $this;
    }

    public function registerCommand(): static
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ProcessWorkflowTriggersCommand::class,
                MigrateActionsToStepsCommand::class,
            ]);

            $this->app->booted(function (): void {
                $schedule = $this->app->make(Schedule::class);
                $schedule->command('ai-agent:process-triggers')->everyMinute();
            });
        }

        return $this;
    }

    public function registerComponents(): static
    {
        return $this;
    }

    public function publishAssets(): static
    {
        $this->publishes([
            __DIR__ . '/../resources/assets/images' => public_path('vendor/ai-agent/images'),
        ], 'extension');

        return $this;
    }

    public function registerConfig(): static
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/ai-agent.php', 'ai-agent');

        return $this;
    }

    protected function registerTranslations(): static
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'ai-agent');

        return $this;
    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'ai-agent');

        return $this;
    }

    public function registerMigrations(): static
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        return $this;
    }

    private function registerRoutes(): static
    {
        // ── Webhook routes (no auth) ──────────────────────────────────────────
        $this->router()
            ->group([
                'middleware' => 'api',
                'prefix'     => 'api/ai-agent',
                'as'         => 'api.ai-agent.',
            ], function (Router $router): void {
                $router->post('telegram/{channel}/webhook', [TelegramWebhookController::class, 'handle'])
                    ->name('telegram.webhook');

                $router->post('webhook/{uuid}', [GenericWebhookController::class, 'handle'])
                    ->name('webhook');
            });

        // ── Authenticated dashboard routes ────────────────────────────────────
        $this->router()
            ->group([
                'middleware' => ['web', 'auth'],
                'prefix'     => 'dashboard/user/ai-agent',
                'as'         => 'dashboard.user.ai-agent.',
            ], function (Router $router): void {
                $router->get('', AIAgentDashboardController::class)->name('dashboard');

                // Workflows
                $router->post('workflows/generate-from-prompt', [WorkflowController::class, 'generateFromPrompt'])
                    ->name('workflows.generate-from-prompt');
                $router->get('workflows/available-actions', [WorkflowController::class, 'availableActions'])
                    ->name('workflows.available-actions');
                $router->get('workflows/available-models', [WorkflowController::class, 'availableModels'])
                    ->name('workflows.available-models');
                $router->get('workflows/available-social-media-agents', [WorkflowController::class, 'availableSocialMediaAgents'])
                    ->name('workflows.available-social-media-agents');
                $router->get('workflows/available-social-media-platforms', [WorkflowController::class, 'availableSocialMediaPlatforms'])
                    ->name('workflows.available-social-media-platforms');
                $router->get('workflows/available-crm-records', [WorkflowController::class, 'availableCrmRecords'])
                    ->name('workflows.available-crm-records');
                $router->resource('workflows', WorkflowController::class);
                $router->patch('workflows/{workflow}/toggle-status', [WorkflowController::class, 'toggleStatus'])
                    ->name('workflows.toggle-status');
                $router->post('workflows/{workflow}/avatar', [WorkflowController::class, 'uploadAvatar'])
                    ->name('workflows.upload-avatar');
                $router->get('workflows/{workflow}/runs', [WorkflowController::class, 'runs'])
                    ->name('workflows.runs');

                // Copilot
                $router->post('workflows/{workflow}/copilot/chat', [CopilotController::class, 'chat'])
                    ->name('workflows.copilot.chat');
                $router->get('workflows/{workflow}/copilot/history', [CopilotController::class, 'history'])
                    ->name('workflows.copilot.history');

                // Knowledge Sources
                $router->get('knowledge-sources', [KnowledgeSourceController::class, 'index'])
                    ->name('knowledge-sources.index');
                $router->post('knowledge-sources', [KnowledgeSourceController::class, 'store'])
                    ->name('knowledge-sources.store');
                $router->delete('knowledge-sources/{knowledgeSource}', [KnowledgeSourceController::class, 'destroy'])
                    ->name('knowledge-sources.destroy');

                // Channels
                $router->resource('channels', ChannelController::class)->except(['show', 'edit', 'update']);
                $router->post('channels/{channel}/refresh-webhook', [ChannelController::class, 'refreshWebhook'])
                    ->name('channels.refresh-webhook');
                $router->get('channels/{channel}/check-status', [ChannelController::class, 'checkStatus'])
                    ->name('channels.check-status');

                // Memory
                $router->get('memory', [MemoryController::class, 'index'])->name('memory.index');
                $router->post('memory', [MemoryController::class, 'store'])->name('memory.store');
                $router->get('memory/{memory}/edit', [MemoryController::class, 'edit'])->name('memory.edit');
                $router->put('memory/{memory}', [MemoryController::class, 'update'])->name('memory.update');
                $router->delete('memory/{memory}', [MemoryController::class, 'destroy'])->name('memory.destroy');

                // Messages
                $router->get('messages', [MessageController::class, 'index'])->name('messages.index');
                $router->get('messages/conversations', [MessageController::class, 'conversations'])->name('messages.conversations');
                $router->get('messages/unread-count', [MessageController::class, 'unreadCount'])->name('messages.unread-count');
                $router->post('messages/mark-seen', [MessageController::class, 'markSeen'])->name('messages.mark-seen');
                $router->post('messages/send', [MessageController::class, 'send'])->name('messages.send');
                $router->get('messages/{conversation}/thread', [MessageController::class, 'thread'])->name('messages.thread');
                $router->post('messages/{conversation}/reply', [MessageController::class, 'reply'])->name('messages.reply');
                $router->post('messages/{conversation}/pin', [MessageController::class, 'pin'])->name('messages.pin');
                $router->post('messages/{conversation}/close', [MessageController::class, 'close'])->name('messages.close');
                $router->delete('messages/{conversation}', [MessageController::class, 'destroy'])->name('messages.destroy');
                $router->get('messages/{conversation}/related', [MessageController::class, 'relatedHistory'])->name('messages.related');
                $router->get('messages/{conversation}/export', [MessageController::class, 'export'])->name('messages.export');
            });

        // ── Admin routes ──────────────────────────────────────────────────────
        $this->router()
            ->group([
                'middleware' => ['web', 'auth', 'admin'],
                'prefix'     => 'dashboard/admin/ai-agent',
                'as'         => 'dashboard.admin.ai-agent.',
            ], function (Router $router): void {
                $router->get('/settings', [AIAgentSettingController::class, 'index'])->name('settings');
                $router->post('/settings', [AIAgentSettingController::class, 'update'])->name('settings.update');
            });

        return $this;
    }

    private function router(): Router|Route
    {
        return $this->app['router'];
    }

    public static function uninstall(): void
    {
        app(MemoryRepository::class); // Ensure it's resolved
        // Tables are dropped via migration rollback; no additional cleanup needed here.
    }
}
