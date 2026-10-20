<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System;

use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Extensions\AIAgent\System\Engine\AIAgentActionRegistry;
use App\Extensions\AIChatPro\System\Actions\NotifyUserAction;
use App\Extensions\AIChatPro\System\Connectors\ConnectorRegistry;
use App\Extensions\AIChatPro\System\Connectors\Models\AIChatProConnector;
use App\Extensions\AIChatPro\System\Connectors\Policies\AIChatProConnectorPolicy;
use App\Extensions\AIChatPro\System\Console\Commands\ProcessGaradScheduledTasksCommand;
use App\Extensions\AIChatPro\System\Events\ConnectorTokenInvalidated;
use App\Extensions\AIChatPro\System\Http\Controllers\AgentWorkspaceController;
use App\Extensions\AIChatPro\System\Http\Controllers\AIChatProAttachmentController;
use App\Extensions\AIChatPro\System\Http\Controllers\AIChatProController;
use App\Extensions\AIChatPro\System\Http\Controllers\AIChatProSettingsController;
use App\Extensions\AIChatPro\System\Http\Controllers\ArtifactController;
use App\Extensions\AIChatPro\System\Http\Controllers\ConnectorController;
use App\Extensions\AIChatPro\System\Http\Controllers\DocumentExportController;
use App\Extensions\AIChatPro\System\Http\Controllers\EditImageStreamController;
use App\Extensions\AIChatPro\System\Http\Controllers\GuestChatStatusController;
use App\Extensions\AIChatPro\System\Http\Controllers\PlansController;
use App\Extensions\AIChatPro\System\Http\Controllers\ProjectController;
use App\Extensions\AIChatPro\System\Http\Controllers\ReactChatController;
use App\Extensions\AIChatPro\System\Http\Controllers\ReactSocialController;
use App\Extensions\AIChatPro\System\Http\Controllers\SalesLeadController;
use App\Extensions\AIChatPro\System\Http\Controllers\ScheduledTaskController;
use App\Extensions\AIChatPro\System\Http\Controllers\WorkspaceController;
use App\Extensions\AIChatPro\System\Http\Middleware\GuestChatGenerationMiddleware;
use App\Extensions\AIChatPro\System\Listeners\NotifyUserOfConnectorFailure;
use App\Helpers\Classes\MarketplaceHelper;
use App\Http\Controllers\AIChatController;
use App\Http\Controllers\OpenAi\GeneratorController;
use App\Http\Middleware\RedirectLegacyChatProForUsers;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

/**
 * Author: MagicAI Team <info@liquid-themes.com>
 *
 * @note When you create a new service provider, make sure to add it to the "MarketplaceServiceProvider". Otherwise, your Laravel application won’t recognize this provider, and the related functions won’t work properly.
 * @note If you want to perform a specific action when an extension is uninstalled, you can use the UninstallExtensionServiceProviderInterface. By implementing this interface, you can define custom operations that will be triggered during the uninstallation of the extension.
 */
class AIChatProServiceProvider extends ServiceProvider implements UninstallExtensionServiceProviderInterface
{
    public function register(): void
    {
        $this->app->singleton(ConnectorRegistry::class);

        if ($this->app->runningInConsole()) {
            $this->commands([ProcessGaradScheduledTasksCommand::class]);
        }
    }

    public function boot(Kernel $kernel): void
    {
        $this->registerTranslations()
            ->registerViews();

        $this->registerRoutes();

        $this->registerMigrations()
            ->registerConnectorPolicies()
            ->registerConnectorEvents()
            ->publishAssets()
            ->registerComponents();

        if (class_exists(AIAgentActionRegistry::class)) {
            $this->app->make(AIAgentActionRegistry::class)
                ->register('notify_user', NotifyUserAction::class);
        }

    }

    private function registerConnectorEvents(): static
    {
        Event::listen(ConnectorTokenInvalidated::class, NotifyUserOfConnectorFailure::class);

        return $this;
    }

    private function registerConnectorPolicies(): static
    {
        Gate::policy(AIChatProConnector::class, AIChatProConnectorPolicy::class);

        return $this;
    }

    public function registerComponents(): static
    {
        //        $this->loadViewComponentsAs('example', []);

        return $this;
    }

    public function publishAssets(): static
    {
        $this->publishes([
            //            __DIR__ . '/../resources/assets/js' => public_path('vendor/example/js'),
            //            __DIR__ . '/../resources/assets/images' => public_path('vendor/example/images'),
        ], 'extension');

        return $this;
    }

    protected function registerTranslations(): static
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'ai-chat-pro');

        return $this;
    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'ai-chat-pro');

        return $this;
    }

    public function registerMigrations(): static
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        return $this;
    }

    private function registerRoutes(): static
    {
        $firstRouteLocale = LaravelLocalization::setLocale();

        $this->router()
            ->group([
                'prefix'     => $firstRouteLocale,
                'middleware' => ['web', 'auth', 'localeSessionRedirect', 'localizationRedirect', 'localeViewPath'],
            ], function (Router $router) {
                $router
                    ->controller(AIChatProSettingsController::class)
                    ->prefix('dashboard/admin/openai/chat/pro')
                    ->name('dashboard.admin.openai.chat.pro.')
                    ->middleware('admin')
                    ->group(function (Router $router) {
                        $router->get('settings', 'index')->name('settings');
                        $router->post('settings', 'update')->name('update');
                    });

                $router
                    ->controller(SalesLeadController::class)
                    ->prefix('dashboard/admin/openai/chat/pro/sales-leads')
                    ->name('dashboard.admin.openai.chat.pro.sales-leads.')
                    ->middleware('admin')
                    ->group(function (Router $router) {
                        $router->get('/', 'index')->name('index');
                        $router->patch('{id}', 'update')->whereNumber('id')->name('update');
                        $router->delete('{id}', 'destroy')->whereNumber('id')->name('destroy');
                    });

                $router
                    ->controller(AIChatProController::class)
                    ->prefix('dashboard/user/openai/chat/pro')
                    ->name('dashboard.user.openai.chat.pro.')
                    ->group(function (Router $router) {
                        $router->get('chat/{slug?}', 'index')
                            ->middleware(RedirectLegacyChatProForUsers::class)
                            ->name('index');
                        $router->get('message-suggestions/{messageId}', 'getMessageSuggestions')->name('message-suggestions');
                        // GARAD_V63_STATUS_ROUTES_START
                        $router->get('garad-video-status/{videoId}', 'garadVideoStatus')->name('garad-video-status');
                        $router->get('garad-presentation-status/{generationId}', 'garadPresentationStatus')->name('garad-presentation-status');
                        // GARAD_V63_STATUS_ROUTES_END
                    });

                $router->get('dashboard/user/openai/chat/pro/artifacts/{artifact}/download', [ArtifactController::class, 'download'])
                    ->name('dashboard.user.openai.chat.pro.artifacts.download');

                $router->prefix('dashboard/user/openai/chat/pro/scheduled')
                    ->name('dashboard.user.openai.chat.pro.scheduled.')
                    ->controller(ScheduledTaskController::class)
                    ->group(function (Router $router) {
                        $router->get('/', 'index')->name('index');
                        $router->post('/', 'store')->name('store');
                        $router->post('tick', 'tick')->name('tick')->middleware('throttle:30,1');
                        $router->patch('{taskId}/status', 'status')->name('status');
                        $router->patch('{taskId}', 'update')->name('update');
                        $router->post('{taskId}/run', 'run')->name('run');
                        $router->delete('{taskId}', 'destroy')->name('destroy');
                    });

                $router->prefix('dashboard/user/openai/chat/pro/projects')
                    ->name('dashboard.user.openai.chat.pro.projects.')
                    ->controller(ProjectController::class)
                    ->group(function (Router $router) {
                        $router->get('/', 'index')->name('index');
                        $router->post('/', 'store')->name('store');
                        $router->put('{projectId}', 'update')->name('update');
                        $router->delete('{projectId}', 'destroy')->name('destroy');
                    });

                $router->prefix('dashboard/user/openai/chat/pro/workspace')
                    ->name('dashboard.user.openai.chat.pro.workspace.')
                    ->controller(WorkspaceController::class)
                    ->group(function (Router $router) {
                        $router->get('agents', 'agents')->name('agents');
                        $router->get('library', 'library')->name('library');
                        $router->get('code', 'code')->name('code');
                    });

                $router
                    ->prefix('dashboard/user/ai-chat-pro/connectors')
                    ->name('dashboard.user.ai-chat-pro.connectors.')
                    ->group(function (Router $router) {
                        $router->get('/', [ConnectorController::class, 'index'])->name('index');
                        $router->delete('{connector}', [ConnectorController::class, 'destroy'])->name('destroy');
                        $router->get('pre-consent/{key}', [ConnectorController::class, 'preConsent'])->name('pre-consent');
                        $router->get('{connector}/access', [ConnectorController::class, 'accessShow'])->name('access.show');
                        $router->put('{connector}/access', [ConnectorController::class, 'accessUpdate'])->name('access.update');
                        $router->post('{connector}/toggle-pause', [ConnectorController::class, 'togglePause'])->name('toggle-pause');
                    });

                $router
                    ->controller(ReactSocialController::class)
                    ->prefix('/dashboard/user/ai-chat-pro/social')
                    ->group(function (Router $router) {
                        $router->get('/overview', 'overview');
                        $router->post('/posts', 'store');
                        $router->put('/posts/{id}', 'update')->whereNumber('id');
                        $router->delete('/posts/{id}', 'destroy')->whereNumber('id');
                        $router->post('/posts/{id}/duplicate', 'duplicate')->whereNumber('id');
                        $router->post('/generate', 'generate');
                        $router->post('/assist', 'assist');
                        $router->post('/campaigns', 'storeCampaign');
                        $router->delete('/campaigns/{id}', 'destroyCampaign')->whereNumber('id');
                        $router->delete('/accounts/{id}', 'disconnect')->whereNumber('id');
                    });

                $router
                    ->controller(ReactChatController::class)
                    ->group(function (Router $router) {
                        $router->get('/chat/{slug?}', 'index')->name('chat.pro')->withoutMiddleware('auth');
                        $router->get('/agents', 'index')
                            ->name('agents')
                            ->withoutMiddleware('auth');
                        $router->get('/@{username}', 'index')
                            ->where('username', '[A-Za-z0-9._-]+')
                            ->name('profile.public')
                            ->withoutMiddleware('auth');
                        $router->get('/app/{slug}', 'index')
                            ->where('slug', 'ai-image-generator|ai-video-generator|audio|social-media')
                            ->name('creative.tool')
                            ->withoutMiddleware('auth');

                        $router->get('/api/account', 'account');
                        $router->patch('/api/account/preferences', 'updatePreferences');
                        $router->patch('/api/account/profile', 'updateProfile');
                        $router->get('/api/profile/{username}', 'showPublicProfile')
                            ->withoutMiddleware('auth');
                        $router->post('/api/profile/{userId}/follow', 'toggleFollow');
                        $router->delete('/api/profile/{userId}/follow', 'toggleFollow');
                        $router->get('/api/mentions/search', 'searchMentionUsers')
                            ->middleware('throttle:30,1');
                        $router->post('/api/agent-mentions', 'storeAgentMentions')
                            ->middleware('throttle:10,1');
                        $router->get('/api/agent-mentions/messages', 'agentMessageMentions');
                        $router->get('/api/agent-shares/recipients', 'searchAgentShareRecipients')
                            ->middleware('throttle:30,1');
                        $router->post('/api/agent-shares', 'storeAgentShare')
                            ->middleware('throttle:10,1');
                        $router->get('/api/agent-shares', 'listAgentShares');
                        $router->delete('/api/agent-shares/{shareId}', 'revokeAgentShare')
                            ->whereUuid('shareId');
                        $router->get('/api/agent-shares/{token}', 'showAgentShare')
                            ->whereAlphaNumeric('token');
                        $router->post('/api/profile/{userId}/collaboration-requests', 'storeCollaborationRequest')
                            ->whereNumber('userId')
                            ->middleware('throttle:5,1');
                        $router->post('/api/mentions', 'storeMention')
                            ->middleware('throttle:10,1');
                        $router->get('/api/notifications', 'notifications');
                        $router->patch('/api/notifications/{notificationId}', 'updateNotification');
                        $router->post('/api/notifications/read-all', 'markAllNotificationsRead');
                        $router->delete('/api/notifications/{notificationId}', 'deleteNotification');
                        $router->post('/api/collaboration-requests/{requestId}/decision', 'decideCollaborationRequest')
                            ->whereNumber('requestId')
                            ->middleware('throttle:10,1');
                        $router->get('/api/workspaces/memberships', 'workspaceMemberships');
                        $router->controller(AgentWorkspaceController::class)
                            ->prefix('/api/workspaces/teams')
                            ->group(function (Router $router): void {
                                $router->get('/', 'index');
                                $router->post('/', 'store')
                                    ->middleware('throttle:5,1');
                                $router->post('/{team}/invitations', 'invite')
                                    ->whereNumber('team')
                                    ->middleware('throttle:5,1');
                                $router->post('/invitations/{member}/decision', 'decideInvitation')
                                    ->whereNumber('member')
                                    ->middleware('throttle:10,1');
                                $router->patch('/{team}/members/{member}', 'updateMember')
                                    ->whereNumber(['team', 'member']);
                                $router->delete('/{team}/members/{member}', 'destroyMember')
                                    ->whereNumber(['team', 'member']);
                            });
                        $router->get('/api/usage', 'usage');

                        $router->get('/api/subscription', 'subscription');
                        $router->get('/api/subscriptions/current', 'subscription');

                        $router->get('/api/models', 'models')
                            ->withoutMiddleware('auth');
                        $router->get('/dashboard/user/ai-chat-pro/image-models', 'imageModels')
                            ->withoutMiddleware('auth');
                        $router->get('/dashboard/user/ai-chat-pro/image-proxy', 'imageProxy');
                        $router->get('/dashboard/user/ai-chat-pro/video-models', 'videoModels')
                            ->withoutMiddleware('auth');
                        $router->get('/dashboard/user/ai-chat-pro/video-history', 'videoHistory');
                        $router->get('/dashboard/user/ai-chat-pro/video-status/{id}', 'videoStatus')->whereNumber('id');
                        $router->get('/dashboard/user/ai-chat-pro/video-file', 'videoProxy');

                        $router->get('/api/composer/capabilities', 'composerCapabilities');
                        $router->get('/api/composer/prompts', 'composerPrompts');
                        $router->get('/api/composer/brand-products/{companyId}', 'composerBrandProducts');
                    });

                $router->get('/plans', [ReactChatController::class, 'index'])
                    ->name('plans.pro')
                    ->withoutMiddleware('auth');
                $router->get('/scheduled', [ReactChatController::class, 'index'])
                    ->name('scheduled.pro');
                $router->get('/contact-sales', [ReactChatController::class, 'index'])
                    ->name('contact-sales.pro')
                    ->withoutMiddleware('auth');
                $router->post('/api/contact-sales', [SalesLeadController::class, 'submit'])
                    ->withoutMiddleware('auth')
                    ->middleware('throttle:5,10');
                $router->get('/api/subscriptions/plans', [PlansController::class, 'plans'])->withoutMiddleware('auth');
                $router->get('/api/plans', [PlansController::class, 'plans'])->withoutMiddleware('auth');
                $router->get('/api/payments/methods', [PlansController::class, 'methods'])->withoutMiddleware('auth');
                $router->post('/api/payments/checkout', [PlansController::class, 'checkout'])
                    ->middleware('throttle:10,1');

                $router->get('/api/guest/chat-status', [GuestChatStatusController::class, 'show'])
                    ->withoutMiddleware('auth')
                    ->middleware('throttle:60,1');
                $router->post('/api/guest/chat-stream', [GeneratorController::class, 'buildStreamedOutput'])
                    ->withoutMiddleware('auth')
                    ->middleware([
                        'throttle:10,1',
                        GuestChatGenerationMiddleware::class,
                        'surveyMiddleware',
                    ]);

                $router
                    ->controller(DocumentExportController::class)
                    ->group(function (Router $router) {
                        $router->post('/api/presentations', 'createPresentation')->middleware('throttle:6,1');
                        $router->get('/api/presentations/{generationId}', 'presentationStatus')->middleware('throttle:120,1')->where('generationId', '[A-Za-z0-9_\-]+');
                        $router->post('/api/documents/{format}', 'export')->middleware('throttle:20,1');
                        $router->get('/api/library', 'library');
                        $router->delete('/api/library/{id}', 'destroy')->whereNumber('id');
                    });
            });

        $secondRouteLocale = LaravelLocalization::setLocale();

        $this->router()
            ->group([
                'prefix'     => $secondRouteLocale,
                'middleware' => [
                    'web',
                    'auth',
                    'localeSessionRedirect',
                    'localizationRedirect',
                    'localeViewPath',
                ],
            ], function (Router $router) {
                $router
                    ->controller(AIChatController::class)
                    ->group(function (Router $router) {
                        $router->post('/dashboard/user/openai/chat/start-new-chat', 'startNewChat')
                            ->withoutMiddleware(ShareErrorsFromSession::class);
                    });
                $router
                    ->controller(AIChatController::class)
                    ->group(function (Router $router) {
                        $router->post('dashboard/user/generator/generate-stream', [GeneratorController::class, 'buildStreamedOutput'])
                            ->name('stream.generate')
                            ->middleware('surveyMiddleware')
                            ->withoutMiddleware(ShareErrorsFromSession::class);
                    });
                if (MarketplaceHelper::isRegistered('ai-chat-pro-image-chat')) {
                    $router
                        ->controller(EditImageStreamController::class)
                        ->group(function (Router $router) {
                            $router->post('dashboard/user/generator/generate-stream-edit-image', 'buildStreamedOutput')->name('stream.generate.edit-image');
                        });
                }
            });

        $this->router()
            ->middleware(['web', 'auth', 'throttle:20,1'])
            ->prefix('dashboard/user/ai-chat-pro/attachments')
            ->name('dashboard.user.ai-chat-pro.attachments.')
            ->group(function (Router $router): void {
                $router->post('/', [AIChatProAttachmentController::class, 'store'])->name('store');
                $router->get('{attachment}', [AIChatProAttachmentController::class, 'show'])
                    ->whereUuid('attachment')
                    ->name('show');
                $router->delete('{attachment}', [AIChatProAttachmentController::class, 'destroy'])
                    ->whereUuid('attachment')
                    ->name('destroy');
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
