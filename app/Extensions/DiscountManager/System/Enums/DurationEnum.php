<?php

namespace App\Extensions\DiscountManager\System\Enums;

use App\Concerns\HasEnumConvert;

enum DurationEnum: string
{
    use HasEnumConvert;

    case FIRST_MONTH = 'first_month';
    case FIRST_YEAR = 'first_year';
    case ALL_TIME = 'all_time';

    public function label(): string
    {
        return match ($this) {
            self::FIRST_MONTH => 'First Month',
            self::FIRST_YEAR  => 'First Year',
            self::ALL_TIME    => 'All Time'
        };
    }
}
