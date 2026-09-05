<?php

namespace App\Extensions\Migration\System\Enums;

use App\Extensions\Migration\System\Drivers\DavinciDriver;

enum MigrationDriverEnum: string
{
    case DAVINCI = 'davinci';

    public function label(): string
    {
        return match ($this) {
            self::DAVINCI => 'Davinci AI',
        };
    }

    public function driver(): string
    {
        return match ($this) {
            self::DAVINCI => DavinciDriver::class,
        };
    }
}
