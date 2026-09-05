<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentWhatsappChannel\System;

use App\Extensions\AIAgent\System\AIAgentServiceProvider;
use App\Extensions\AIAgent\System\Connectors\ConnectorRegistry;
use App\Extensions\AIAgent\System\Enums\ChannelEnum;
use App\Extensions\AIAgent\System\Formatters\MessageFormatter;
use App\Extensions\AIAgentWhatsappChannel\System\Connectors\WhatsappConnector;
use App\Extensions\AIAgentWhatsappChannel\System\Formatters\WhatsappFormatter;
use App\Extensions\AIAgentWhatsappChannel\System\Http\Controllers\Webhook\WhatsappWebhookController;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class AIAgentWhatsappChannelServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! class_exists(AIAgentServiceProvider::class)) {
            return;
        }

        $this->registerConnector()
            ->registerFormatter()
            ->registerRoutes();
    }

    private function registerConnector(): static
    {
        $registry = $this->app->make(ConnectorRegistry::class);
        $registry->register(ChannelEnum::Whatsapp, WhatsappConnector::class);

        return $this;
    }

    private function registerFormatter(): static
    {
        $formatter = $this->app->make(MessageFormatter::class);
        $formatter->registerFormatter(ChannelEnum::Whatsapp, $this->app->make(WhatsappFormatter::class));

        return $this;
    }

    private function registerRoutes(): static
    {
        /** @var Router $router */
        $router = $this->app['router'];

        $router->group([
            'middleware' => 'api',
            'prefix'     => 'api/ai-agent',
            'as'         => 'api.ai-agent.',
        ], function (Router $router): void {
            $router->get('whatsapp/{channel}/webhook', [WhatsappWebhookController::class, 'verify'])
                ->name('whatsapp.webhook.verify');

            $router->post('whatsapp/{channel}/webhook', [WhatsappWebhookController::class, 'handle'])
                ->name('whatsapp.webhook');
        });

        return $this;
    }
}
