<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Connectors;

use App\Extensions\AIAgent\System\Connectors\Contracts\ConnectorInterface;
use App\Extensions\AIAgent\System\Connectors\ValueObjects\OutgoingMessage;
use App\Extensions\AIAgent\System\Models\AIAgentChannel;

class MagicAiConnector implements ConnectorInterface
{
    private AIAgentChannel $channel;

    public function setChannel(AIAgentChannel $channel): static
    {
        $this->channel = $channel;

        return $this;
    }

    public function send(OutgoingMessage $message): void
    {
        // SendMessageAction already persists the outbound message to the DB.
        // No external API to call for the web channel.
    }

    public function registerWebhook(): void
    {
        // Web channel requires no external webhook registration.
    }
}
