<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentSlackChannel\System;

use App\Extensions\AIAgent\System\AIAgentServiceProvider;
use App\Extensions\AIAgent\System\Connectors\ConnectorRegistry;
use App\Extensions\AIAgent\System\Enums\ChannelEnum;
use App\Extensions\AIAgent\System\Formatters\MessageFormatter;
use App\Extensions\AIAgentSlackChannel\System\Connectors\SlackConnector;
use App\Extensions\AIAgentSlackChannel\System\Formatters\SlackFormatter;
use App\Extensions\AIAgentSlackChannel\System\Http\Controllers\Webhook\SlackWebhookController;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class AIAgentSlackChannelServiceProvider extends ServiceProvider
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
        $registry->register(ChannelEnum::Slack, SlackConnector::class);

        return $this;
    }

    private function registerFormatter(): static
    {
        $formatter = $this->app->make(MessageFormatter::class);
        $formatter->registerFormatter(ChannelEnum::Slack, $this->app->make(SlackFormatter::class));

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
            $router->post('slack/{channel}/webhook', [SlackWebhookController::class, 'handle'])
                ->name('slack.webhook');
        });

        return $this;
    }
}
