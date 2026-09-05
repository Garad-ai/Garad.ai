<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Enums;

enum ChannelEnum: string
{
    case Telegram = 'telegram';
    case Email = 'email';
    case Whatsapp = 'whatsapp';
    case Slack = 'slack';
    case Discord = 'discord';
    case Sms = 'sms';
    case MagicAi = 'magicai';

    public function label(): string
    {
        return match ($this) {
            self::Telegram => 'Telegram',
            self::Email    => 'Email',
            self::Whatsapp => 'WhatsApp',
            self::Slack    => 'Slack',
            self::Discord  => 'Discord',
            self::Sms      => 'SMS',
            self::MagicAi  => 'MagicAI (Web)',
        };
    }

    public function isActive(): bool
    {
        return match ($this) {
            self::Telegram => true,
            self::Whatsapp => true,
            self::MagicAi  => false,
            self::Email    => false,
            self::Slack    => true,
            self::Discord  => false,
            self::Sms      => false,
        };
    }
}
