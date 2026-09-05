<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Connectors\Contracts;

use App\Extensions\AIAgent\System\Connectors\ValueObjects\OutgoingMessage;
use App\Extensions\AIAgent\System\Models\AIAgentChannel;

interface ConnectorInterface
{
    public function setChannel(AIAgentChannel $channel): static;

    public function send(OutgoingMessage $message): void;

    public function registerWebhook(): void;
}
