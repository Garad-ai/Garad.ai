<?php

declare(strict_types=1);

namespace App\Extensions\AiViralClips\System;

use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Author: MagicAI Team <info@liquid-themes.com>
 *
 * @note When you create a new service provider, make sure to add it to the "MarketplaceServiceProvider". Otherwise, your Laravel application won’t recognize this provider, and the related functions won’t work properly.
 * @note If you want to perform a specific action when an extension is uninstalled, you can use the UninstallExtensionServiceProviderInterface. By implementing this interface, you can define custom operations that will be triggered during the uninstallation of the extension.
 */
class AiViralClipsServiceProvider extends ServiceProvider implements UninstallExtensionServiceProviderInterface
{
    public function register(): void
    {
        $this->registerConfig();
    }

    public function boot(Kernel $kernel): void
    {
        $this->registerViews()
            ->registerRoutes();

    }

    public function registerConfig(): static
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/ai-viral-clips.php', 'ai-viral-clips');

        return $this;
    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'ai-viral-clips');

        return $this;
    }

    private function registerRoutes(): static
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/klap.php');
        $this->loadRoutesFrom(__DIR__ . '/../routes/vizard.php');

        Route::group([
            'middleware' => ['web', 'auth'],
        ], function () {
            Route::group([
                'prefix' => 'dashboard/user/viral-clips',
                'as'     => 'dashboard.user.viral-clips.',
            ], function () {
                Route::get('', function () {
                    return view('ai-viral-clips::index');
                })->name('index');
            });
        });

        return $this;
    }

    public static function uninstall(): void
    {
        // TODO: Implement uninstall() method.
    }
}
