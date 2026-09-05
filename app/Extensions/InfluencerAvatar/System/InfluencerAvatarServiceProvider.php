<?php

declare(strict_types=1);

namespace App\Extensions\InfluencerAvatar\System;

use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Extensions\InfluencerAvatar\System\Http\Controllers\InfluencerAvatarController;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Author: MagicAI Team <info@liquid-themes.com>
 *
 * @note When you create a new service provider, make sure to add it to the "MarketplaceServiceProvider". Otherwise, your Laravel application won’t recognize this provider, and the related functions won’t work properly.
 * @note If you want to perform a specific action when an extension is uninstalled, you can use the UninstallExtensionServiceProviderInterface. By implementing this interface, you can define custom operations that will be triggered during the uninstallation of the extension.
 */
class InfluencerAvatarServiceProvider extends ServiceProvider implements UninstallExtensionServiceProviderInterface
{
    public function boot(Kernel $kernel): void
    {
        $this->registerViews()
            ->registerRoutes();

    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'influencer-avatar');

        return $this;
    }

    private function registerRoutes(): static
    {
        Route::group([
            'middleware' => ['web', 'auth'],
        ], function () {
            Route::group([
                'prefix' => 'dashboard/user/influencer-avatar',
                'as'     => 'dashboard.user.influencer-avatar.',
            ], function () {
                Route::get('', [InfluencerAvatarController::class, 'index'])->name('index');

                Route::post('generate-short-video', [InfluencerAvatarController::class, 'generateShortVideo'])->name('generate-short-video');
                Route::get('check-video-status/{request_id}', [InfluencerAvatarController::class, 'checkStatus'])->name('check-video-status');
                Route::get('get-final-result/{request_id}', [InfluencerAvatarController::class, 'getFinalVideo'])->name('get-final-result');
            });
        });

        return $this;
    }

    public static function uninstall(): void
    {
        // TODO: Implement uninstall() method.
    }
}
