<?php

declare(strict_types=1);

namespace App\Extensions\SocialMedia\System;

use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Extensions\SocialMedia\System\Http\Controllers\Common\DemoDataController;
use App\Extensions\SocialMedia\System\Http\Controllers\Common\SocialMediaCampaignCommonController;
use App\Extensions\SocialMedia\System\Http\Controllers\Common\SocialMediaCompanyCommonController;
use App\Extensions\SocialMedia\System\Http\Controllers\ImageStatusController;
use App\Extensions\SocialMedia\System\Http\Controllers\Oauth\FacebookController;
use App\Extensions\SocialMedia\System\Http\Controllers\Oauth\InstagramController;
use App\Extensions\SocialMedia\System\Http\Controllers\Oauth\LinkedinController;
use App\Extensions\SocialMedia\System\Http\Controllers\Oauth\TiktokController;
use App\Extensions\SocialMedia\System\Http\Controllers\Oauth\XController;
use App\Extensions\SocialMedia\System\Http\Controllers\Oauth\YoutubeController;
use App\Extensions\SocialMedia\System\Http\Controllers\SocialMediaCalendarController;
use App\Extensions\SocialMedia\System\Http\Controllers\SocialMediaCampaignController;
use App\Extensions\SocialMedia\System\Http\Controllers\SocialMediaController;
use App\Extensions\SocialMedia\System\Http\Controllers\SocialMediaPlatformController;
use App\Extensions\SocialMedia\System\Http\Controllers\SocialMediaPostController;
use App\Extensions\SocialMedia\System\Http\Controllers\SocialMediaSettingController;
use App\Extensions\SocialMedia\System\Http\Controllers\SocialMediaUploadController;
use App\Extensions\SocialMedia\System\Http\Controllers\SocialMediaVideoController;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class SocialMediaServiceProvider extends ServiceProvider implements UninstallExtensionServiceProviderInterface
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
            ->publishAssets()
            ->registerComponents()
            ->registerCommand();

    }

    public function registerCommand(): static
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\Commands\PublishedCommand::class,
                Console\Commands\XRefreshTokenCommand::class,
                Console\Commands\XPostMetricsCommand::class,
                Console\Commands\FacebookPostMetricsCommand::class,
                Console\Commands\InstagramPostMetricsCommand::class,
                Console\Commands\SocialMediaDailyMetricsCommand::class,
                Console\Commands\SocialMediaFollowersSyncCommand::class,
            ]);

            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);
                $schedule->command('app:social-media-published-command')->everyTwoMinutes();
                $schedule->command('app:social-media-x-refresh')->everyThreeMinutes();
                $schedule->command('app:social-media-facebook-post-metrics')->everyThreeMinutes();
                $schedule->command('app:social-media-instagram-post-metrics')->everyThreeMinutes();
                $schedule->command('app:social-media-daily-metrics')->hourly();
                $schedule->command('php artisan app:social-media-sync-followers')->hourly();
            });
        }

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
            __DIR__ . '/../resources/assets' => public_path('vendor/social-media'),
        ], 'extension');

        return $this;
    }

    public function registerConfig(): static
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/social-media.php', 'social-media');

        return $this;
    }

    protected function registerTranslations(): static
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'social-media');

        return $this;
    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'social-media');

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

                $router->get('tiktok/verify', [TiktokController::class, 'verify'])->name('tiktok.verify');

                $router->get('social-media-demo-data', DemoDataController::class)->name('demo-data');

                $router->any('social-media/webhook/instagram', [InstagramController::class, 'webhook'])->name('social-media.oauth.webhook.facebook')->withoutMiddleware('auth');
                $router->any('social-media/webhook/facebook', [FacebookController::class, 'webhook'])->name('social-media.oauth.webhook.facebook')->withoutMiddleware('auth');

                $router->group([
                    'prefix'     => 'social-media/oauth',
                ], function (Router $router) {
                    $router->get('redirect/tiktok', [TiktokController::class, 'redirect'])->name('social-media.oauth.connect.tiktok');
                    $router->get('callback/tiktok', [TiktokController::class, 'callback'])->name('social-media.oauth.callback.tiktok');

                    $router->get('redirect/instagram', [InstagramController::class, 'redirect'])->name('social-media.oauth.connect.instagram');
                    $router->get('callback/instagram', [InstagramController::class, 'callback'])->name('social-media.oauth.callback.instagram');

                    $router->get('redirect/x', [XController::class, 'redirect'])->name('social-media.oauth.connect.x');
                    $router->get('callback/x', [XController::class, 'callback'])->name('social-media.oauth.callback.x');

                    $router->get('redirect/facebook', [FacebookController::class, 'redirect'])->name('social-media.oauth.connect.facebook');
                    $router->get('callback/facebook', [FacebookController::class, 'callback'])->name('social-media.oauth.callback.facebook');

                    $router->get('redirect/linkedin', [LinkedinController::class, 'redirect'])->name('social-media.oauth.connect.linkedin');
                    $router->get('callback/linkedin', [LinkedinController::class, 'callback'])->name('social-media.oauth.callback.linkedin');

                    $router->get('redirect/youtube', [YoutubeController::class, 'redirectYoutube'])->name('social-media.oauth.connect.youtube');
                    $router->get('callback/youtube', [YoutubeController::class, 'callbackYoutube'])->name('social-media.oauth.callback.youtube');

                    $router->get('redirect/youtube-shorts', [YoutubeController::class, 'redirectYoutubeShorts'])->name('social-media.oauth.connect.youtube-shorts');
                    $router->get('callback/youtube-shorts', [YoutubeController::class, 'callbackYoutubeShorts'])->name('social-media.oauth.callback.youtube-shorts');
                });

                $router
                    ->name('dashboard.user.social-media.')
                    ->prefix('dashboard/user/social-media')
                    ->group(function (Router $router) {

                        $router->get('post', [SocialMediaPostController::class, 'index'])->name('post.index');
                        $router->get('post/create', [SocialMediaPostController::class, 'create'])->name('post.create');
                        $router->get('post/{post}/edit', [SocialMediaPostController::class, 'edit'])->name('post.edit');
                        $router->post('post/{post}/update', [SocialMediaPostController::class, 'update'])->name('post.update');
                        $router->get('post/{id}', [SocialMediaPostController::class, 'show'])->name('post.show');
                        $router->post('post', [SocialMediaPostController::class, 'store'])->name('post.store');
                        $router->post('post/{post}/duplicate', [SocialMediaPostController::class, 'duplicate'])->name('post.duplicate');
                        $router->get('post/{post}/delete', [SocialMediaPostController::class, 'destroy'])->name('post.delete');
                        $router->post('upload/image', [SocialMediaUploadController::class, 'image'])->name('upload.image');
                        $router->post('upload/video', [SocialMediaUploadController::class, 'video'])->name('upload.video');

                        $router->get('', SocialMediaController::class)->name('index');
                        $router->get('platforms', SocialMediaPlatformController::class)->name('platforms');
                        $router->get('platforms/{platform}/disconnect', [SocialMediaPlatformController::class, 'disconnect'])->name('platforms.disconnect');
                        $router->post('campaign/generate', [SocialMediaCampaignController::class, 'generate'])->name('campaign.generate');
                        $router->any('image/get-status', ImageStatusController::class)->name('image.get.status');

                        $router->get('campaign/{campaign}/delete', [SocialMediaCampaignController::class, 'destroy'])->name('campaign.destroy');
                        $router->resource('campaign', SocialMediaCampaignController::class)->only('index', 'store');

                        $router->get('calendar', SocialMediaCalendarController::class)->name('calendar');

                        $router->post('video/generate', SocialMediaVideoController::class)->name('video.generate');

                        $router->get('video/status', [SocialMediaVideoController::class, 'status'])->name('video.status');
                    });

                $router
                    ->name('dashboard.user.social-media.common.')
                    ->prefix('dashboard/user/social-media/common')
                    ->group(function (Router $router) {
                        $router->get('companies', SocialMediaCompanyCommonController::class)->name('companies');
                        $router->post('campaigns', SocialMediaCampaignCommonController::class)->name('campaigns');
                        $router->get('generate-content', [SocialMediaCampaignCommonController::class, 'generate'])->name('campaigns.generate.content');
                    });

                $router->post(
                    'genContent', [SocialMediaCampaignCommonController::class, 'generate']
                )
                    ->name('dashboard.user.automation.campaign.genContent')
                    ->prefix('dashboard/user/automation/campaign');

                $router
                    ->middleware('admin')
                    ->prefix('dashboard/admin/social-media/setting')
                    ->name('dashboard.admin.social-media.setting.')
                    ->controller(SocialMediaSettingController::class)
                    ->group(function () {
                        Route::get('', 'index')->name('index');
                        Route::post('{platform}/update', 'update')->name('update');
                    });

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
