<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Enums;

enum MemoryTypeEnum: string
{
    case Profile    = 'profile';
    case Preference = 'preference';
    case Task       = 'task';
    case Account    = 'account';

    public function label(): string
    {
        return match ($this) {
            self::Profile    => 'Profile',
            self::Preference => 'Preference',
            self::Task       => 'Task',
            self::Account    => 'Account',
        };
    }
}
