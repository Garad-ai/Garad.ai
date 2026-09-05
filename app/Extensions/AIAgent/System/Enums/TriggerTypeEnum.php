<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Enums;

enum TriggerTypeEnum: string
{
    case Schedule       = 'schedule';
    case Webhook        = 'webhook';
    case ChannelMessage = 'channel_message';

    public function label(): string
    {
        return match ($this) {
            self::Schedule       => 'Scheduled (cron)',
            self::Webhook        => 'Incoming Webhook',
            self::ChannelMessage => 'Channel Message',
        };
    }
}
