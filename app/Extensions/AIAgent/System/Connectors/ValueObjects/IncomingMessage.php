<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Connectors\ValueObjects;

use App\Extensions\AIAgent\System\Enums\ChannelEnum;

final class IncomingMessage
{
    public function __construct(
        public readonly ChannelEnum $channel,
        public readonly string $senderId,
        public readonly string $text,
        public readonly array $attachments = [],
        public readonly array $rawPayload = [],
        public readonly ?int $conversationId = null,
    ) {}
}
