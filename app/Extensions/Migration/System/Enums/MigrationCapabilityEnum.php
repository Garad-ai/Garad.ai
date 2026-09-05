<?php

namespace App\Extensions\Migration\System\Enums;

enum MigrationCapabilityEnum: string
{
    /**
     * here we define the capabilities of the migration system (the tables that could be migrated)
     */
    case USERS = 'users';
    case SUBSCRIPTIONS = 'subscriptions';
    case PLANS = 'plans';
    case SETTINGS = 'settings';
    case PAYMENT_GATEWAYS = 'payment_gateways';
    case CHATS = 'chats';
    case DOCUMENTS = 'documents';

    public function label(): string
    {
        return match ($this) {
            self::USERS            => 'Users',
            self::SUBSCRIPTIONS    => 'Subscriptions',
            self::PLANS            => 'Plans',
            self::SETTINGS         => 'Settings',
            self::PAYMENT_GATEWAYS => 'Payment Gateways',
            self::CHATS            => 'Chats',
            self::DOCUMENTS        => 'Documents',
        };
    }
}
