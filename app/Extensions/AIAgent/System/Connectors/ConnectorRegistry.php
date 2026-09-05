<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Connectors;

use App\Extensions\AIAgent\System\Connectors\Contracts\ConnectorInterface;
use App\Extensions\AIAgent\System\Enums\ChannelEnum;
use App\Extensions\AIAgent\System\Models\AIAgentChannel;
use Exception;

class ConnectorRegistry
{
    /** @var array<string, class-string<ConnectorInterface>> */
    private array $connectors = [];

    public function register(ChannelEnum $channel, string $connectorClass): void
    {
        $this->connectors[$channel->value] = $connectorClass;
    }

    public function has(ChannelEnum $channel): bool
    {
        return isset($this->connectors[$channel->value]);
    }

    /**
     * @throws Exception
     */
    public function resolve(ChannelEnum $channel, AIAgentChannel $model): ConnectorInterface
    {
        if (! $this->has($channel)) {
            throw new Exception("No connector registered for channel: {$channel->value}");
        }

        /** @var ConnectorInterface $connector */
        $connector = app($this->connectors[$channel->value]);

        return $connector->setChannel($model);
    }
}
