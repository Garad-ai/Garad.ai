<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Connectors\ValueObjects;

use App\Extensions\AIAgent\System\Enums\ChannelEnum;

final class OutgoingMessage
{
    public function __construct(
        public readonly ChannelEnum $channel,
        public readonly string $recipientId,
        public readonly string $text,
        public readonly array $attachments = [],
    ) {}
}
