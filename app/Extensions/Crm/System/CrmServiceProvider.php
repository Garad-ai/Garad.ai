<?php

declare(strict_types=1);

namespace App\Extensions\Crm\System;

use App\Events\ContactCapturedEvent;
use App\Extensions\Crm\System\Http\Controllers\CrmAiController;
use App\Extensions\Crm\System\Http\Controllers\CrmAssistantChatController;
use App\Extensions\Crm\System\Http\Controllers\CrmAssistantChatSettingsController;
use App\Extensions\Crm\System\Http\Controllers\CrmCalendarController;
use App\Extensions\Crm\System\Http\Controllers\CrmCompanyController;
use App\Extensions\Crm\System\Http\Controllers\CrmContactController;
use App\Extensions\Crm\System\Http\Controllers\CrmContactImportController;
use App\Extensions\Crm\System\Http\Controllers\CrmDashboardController;
use App\Extensions\Crm\System\Http\Controllers\CrmDealController;
use App\Extensions\Crm\System\Http\Controllers\CrmNoteController;
use App\Extensions\Crm\System\Http\Controllers\CrmPresentationController;
use App\Extensions\Crm\System\Http\Controllers\CrmProjectController;
use App\Extensions\Crm\System\Http\Controllers\CrmReportsController;
use App\Extensions\Crm\System\Http\Controllers\CrmSettingsController;
use App\Extensions\Crm\System\Http\Controllers\CrmSyncController;
use App\Extensions\Crm\System\Http\Controllers\CrmTaskApiController;
use App\Extensions\Crm\System\Http\Controllers\CrmTaskController;
use App\Extensions\Crm\System\Http\Controllers\SalesEstimateController;
use App\Extensions\Crm\System\Http\Controllers\SalesInvoiceController;
use App\Extensions\Crm\System\Http\Controllers\SalesPaymentController;
use App\Extensions\Crm\System\Http\Controllers\SalesProposalController;
use App\Extensions\Crm\System\Http\Middleware\EnsureCrmFeatureEnabled;
use App\Extensions\Crm\System\Listeners\SyncCapturedContactToCrm;
use App\Extensions\Crm\System\Models\CrmDeal;
use App\Extensions\Crm\System\Observer\CrmDealObserver;
use App\Http\Middleware\CheckTemplateTypeAndPlan;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CrmServiceProvider extends ServiceProvider
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
            ->registerObservers()
            ->registerListeners()
            ->registerAssets();
    }

    private function registerConfig(): static
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/crm.php', 'crm');

        return $this;
    }

    private function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'crm');

        return $this;
    }

    private function registerMigrations(): static
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        return $this;
    }

    private function registerObservers(): static
    {
        CrmDeal::observe(CrmDealObserver::class);

        return $this;
    }

    private function registerListeners(): static
    {
        Event::listen(ContactCapturedEvent::class, SyncCapturedContactToCrm::class);

        return $this;
    }

    private function registerAssets(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../resources/assets/images' => public_path('vendor/crm/images'),
            ], 'crm-images');
        }
    }

    private function registerRoutes(): static
    {
        $this->router()
            ->group([
                'middleware' => ['web', 'auth', CheckTemplateTypeAndPlan::class, EnsureCrmFeatureEnabled::class],
            ], function (Router $router) {
                $router
                    ->prefix('dashboard/user/crm')
                    ->name('dashboard.user.crm.')
                    ->group(function (Router $router) {
                        // Dashboard
                        $router->get('', [CrmDashboardController::class, 'index'])->name('index');

                        // Reports
                        $router->get('reports', [CrmReportsController::class, 'index'])->name('reports.index');

                        // Toggle Favorite
                        $router->post('toggle-favorite', [CrmDashboardController::class, 'toggleFavorite'])->name('toggleFavorite');

                        // Bulk Delete
                        $router->post('bulk-delete', [CrmDashboardController::class, 'bulkDelete'])->name('bulkDelete');

                        // AI Assistant
                        $router->group([
                            'middleware' => EnsureCrmFeatureEnabled::class . ':assistant',
                        ], function (Router $router) {
                            $router->get('assistant', [CrmAssistantChatController::class, 'index'])->name('ai.index');
                            $router->post('ai-execute', [CrmAiController::class, 'executeActions'])->name('ai.execute');
                        });

                        // Companies
                        $router->group([
                            'prefix'     => 'companies',
                            'as'         => 'companies.',
                            'controller' => CrmCompanyController::class,
                        ], function (Router $router) {
                            $router->get('', 'index')->name('index');
                            $router->post('', 'store')->name('store');
                            $router->get('{company}', 'show')->name('show');
                            $router->put('{company}', 'update')->name('update');
                            $router->get('{company}/delete', 'delete')->name('delete');
                        });

                        // Contacts
                        $router->group([
                            'prefix'     => 'contacts',
                            'as'         => 'contacts.',
                            'controller' => CrmContactController::class,
                        ], function (Router $router) {
                            $router->get('', 'index')->name('index');
                            $router->post('', 'store')->name('store');
                            $router->post('import/headers', [CrmContactImportController::class, 'headers'])->name('import.headers');
                            $router->post('import/preview', [CrmContactImportController::class, 'preview'])->name('import.preview');
                            $router->post('import', [CrmContactImportController::class, 'import'])->name('import');
                            $router->post('sync/{source}', [CrmSyncController::class, 'backfill'])->name('sync');
                            $router->get('{contact}', 'show')->name('show');
                            $router->put('{contact}', 'update')->name('update');
                            $router->get('{contact}/delete', 'delete')->name('delete');
                        });

                        // Deals
                        $router->group([
                            'prefix'     => 'deals',
                            'as'         => 'deals.',
                            'controller' => CrmDealController::class,
                        ], function (Router $router) {
                            $router->get('', 'index')->name('index');
                            $router->get('list', 'list')->name('list');
                            $router->get('create', 'create')->name('create');
                            $router->post('', 'store')->name('store');
                            $router->get('{deal}/edit', 'edit')->name('edit');
                            $router->put('{deal}', 'update')->name('update');
                            $router->get('{deal}/delete', 'delete')->name('delete');
                            $router->post('update-stage', 'updateStage')->name('updateStage');
                        });

                        // Projects
                        $router->group([
                            'prefix'     => 'projects',
                            'as'         => 'projects.',
                            'controller' => CrmProjectController::class,
                        ], function (Router $router) {
                            $router->get('', 'index')->name('index');
                            $router->get('board', 'index')->name('board');
                            $router->post('', 'store')->name('store');
                            $router->post('update-status', 'updateStatus')->name('updateStatus');
                            $router->get('{project}', 'show')->name('show');
                            $router->put('{project}', 'update')->name('update');
                            $router->get('{project}/delete', 'delete')->name('delete');
                            $router->post('{project}/tasks', 'storeTask')->name('storeTask');
                        });

                        // Calendar
                        $router->group([
                            'prefix'     => 'calendar',
                            'as'         => 'calendar.',
                            'controller' => CrmCalendarController::class,
                        ], function (Router $router) {
                            $router->get('', 'index')->name('index');
                            $router->get('events', 'events')->name('events');
                        });

                        // Presentations
                        $router->group([
                            'prefix'     => 'presentations',
                            'as'         => 'presentations.',
                            'controller' => CrmPresentationController::class,
                            'middleware' => EnsureCrmFeatureEnabled::class . ':presentations',
                        ], function (Router $router) {
                            $router->get('', 'index')->name('index');
                            $router->post('', 'store')->name('store');
                            $router->get('slides/{slide}/download', 'downloadSlide')->name('downloadSlide');
                            $router->get('{presentation}', 'show')->name('show');
                            $router->get('{presentation}/status', 'checkStatus')->name('status');
                            $router->get('{presentation}/delete', 'delete')->name('delete');
                            $router->get('{presentation}/download-all', 'downloadAll')->name('downloadAll');
                            $router->get('{presentation}/download-pdf', 'downloadPdf')->name('downloadPdf');
                        });

                        // Notes
                        $router->group([
                            'prefix'     => 'notes',
                            'as'         => 'notes.',
                            'controller' => CrmNoteController::class,
                        ], function (Router $router) {
                            $router->post('', 'store')->name('store');
                            $router->get('{note}/delete', 'delete')->name('delete');
                        });

                        // Tasks
                        $router->group([
                            'prefix'     => 'tasks',
                            'as'         => 'tasks.',
                            'controller' => CrmTaskController::class,
                        ], function (Router $router) {
                            $router->get('', 'board')->name('index');
                            $router->get('list', 'index')->name('list');
                            $router->post('', 'store')->name('store');
                            $router->post('update-status', 'updateStatus')->name('updateStatus');
                            $router->put('{task}', 'update')->name('update');
                            $router->get('{task}/delete', 'delete')->name('delete');
                            $router->post('{task}/toggle-complete', 'toggleComplete')->name('toggleComplete');
                        });

                        // Task API (for calendar modal)
                        $router->get('api/tasks/{task}', [CrmTaskApiController::class, 'show'])->name('api.tasks.show');
                    });

                // Sales routes (registered in CRM extension, URL prefix preserved)
                $router
                    ->prefix('dashboard/user/sales')
                    ->name('dashboard.user.sales.')
                    ->group(function (Router $router) {
                        // Invoices
                        $router->group([
                            'prefix'     => 'invoices',
                            'as'         => 'invoices.',
                            'controller' => SalesInvoiceController::class,
                        ], function (Router $router) {
                            $router->get('', 'index')->name('index');
                            $router->post('', 'store')->name('store');
                            $router->post('update-status', 'updateStatus')->name('updateStatus');
                            $router->get('{invoice}', 'show')->name('show');
                            $router->put('{invoice}', 'update')->name('update');
                            $router->get('{invoice}/delete', 'delete')->name('delete');
                            $router->get('{invoice}/pdf', 'downloadPdf')->name('pdf');
                            $router->post('{invoice}/items', 'saveItems')->name('saveItems');
                            $router->post('{invoice}/payments', 'storePayment')->name('storePayment');
                            $router->delete('{invoice}/payments/{payment}', 'deletePayment')->name('deletePayment');
                        });

                        // Payments
                        $router->group([
                            'prefix'     => 'payments',
                            'as'         => 'payments.',
                            'controller' => SalesPaymentController::class,
                        ], function (Router $router) {
                            $router->get('', 'index')->name('index');
                            $router->post('', 'store')->name('store');
                            $router->put('{payment}', 'update')->name('update');
                            $router->get('{payment}/delete', 'delete')->name('delete');
                        });

                        // Proposals
                        $router->group([
                            'prefix'     => 'proposals',
                            'as'         => 'proposals.',
                            'controller' => SalesProposalController::class,
                        ], function (Router $router) {
                            $router->get('', 'index')->name('index');
                            $router->post('', 'store')->name('store');
                            $router->get('{proposal}', 'show')->name('show');
                            $router->put('{proposal}', 'update')->name('update');
                            $router->get('{proposal}/delete', 'delete')->name('delete');
                            $router->post('{proposal}/items', 'saveItems')->name('saveItems');
                        });

                        // Estimates
                        $router->group([
                            'prefix'     => 'estimates',
                            'as'         => 'estimates.',
                            'controller' => SalesEstimateController::class,
                        ], function (Router $router) {
                            $router->get('', 'index')->name('index');
                            $router->post('', 'store')->name('store');
                            $router->get('{estimate}', 'show')->name('show');
                            $router->put('{estimate}', 'update')->name('update');
                            $router->get('{estimate}/delete', 'delete')->name('delete');
                            $router->post('{estimate}/items', 'saveItems')->name('saveItems');
                        });
                    });
            });

        $this->router()
            ->group([
                'middleware' => ['web', 'auth', 'admin'],
            ], function (Router $router) {
                $router
                    ->prefix('dashboard/admin/crm')
                    ->name('dashboard.admin.crm.')
                    ->group(function (Router $router) {
                        $router->get('settings', [CrmSettingsController::class, 'index'])->name('settings');
                        $router->post('settings', [CrmSettingsController::class, 'update'])->name('update');

                        // CRM Assistant suggested prompts
                        $router->get('assistant/settings', [CrmAssistantChatSettingsController::class, 'index'])->name('assistant.settings');
                        $router->post('assistant/settings', [CrmAssistantChatSettingsController::class, 'update'])->name('assistant.settings.update');
                    });
            });

        return $this;
    }

    private function router(): Router|Route
    {
        return $this->app['router'];
    }
}
