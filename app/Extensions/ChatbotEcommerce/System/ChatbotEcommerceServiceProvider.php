<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System;

use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Extensions\Chatbot\System\Http\Middleware\LanguageMiddleware;
use App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api\ChatbotEcommerceApiController;
use App\Extensions\ChatbotEcommerce\System\Http\Controllers\ChatbotEcommerceController;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ChatbotEcommerceServiceProvider extends ServiceProvider implements UninstallExtensionServiceProviderInterface
{
    public function boot(Kernel $kernel): void
    {
        $this->registerTranslations()
            ->registerViews()
            ->registerRoutes()
            ->registerMigrations()
            ->publishAssets();

    }

    public function publishAssets(): static
    {
        $this->publishes([
        ], 'extension');

        return $this;
    }

    protected function registerTranslations(): static
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'chatbot-ecommerce');

        return $this;
    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'chatbot-ecommerce');

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
                    ->controller(ChatbotEcommerceController::class)
                    ->prefix('dashboard/chatbot-ecommerce')
                    ->name('dashboard.chatbot-ecommerce.')
                    ->group(function (Router $router) {
                        $router->get('', 'index')->name('index');
                    });
            })
            ->group([
                'middleware' => ['api', LanguageMiddleware::class],
                'prefix'     => 'api/v2/chatbot',
                'as'         => 'api.v2.chatbot.',
                'controller' => ChatbotEcommerceApiController::class,
            ], function (Router $router) {
                $router->post('{chatbot:uuid}/session/{sessionId}/productAddToCart', 'productAddToCart')->name('product.addToCart');
                $router->post('{chatbot:uuid}/session/{sessionId}/productUpdateQuantity', 'productUpdateQuantity')->name('product.UpdateQuantity');
                $router->post('{chatbot:uuid}/session/{sessionId}/productCartCheckout', 'productCartCheckout')->name('product.cartCheckout');
                $router->post('{chatbot:uuid}/session/{sessionId}/productGetCart', 'productGetCart')->name('product.getCart');
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
