<?php

namespace App\Extensions\DiscountManager\System\Enums;

use App\Concerns\HasEnumConvert;
use Illuminate\Support\Facades\Blade;

enum DiscountTypeEnum: string
{
    use HasEnumConvert;

    case PERCENTAGE = 'percentage';
    // case FIXED = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::PERCENTAGE => 'Percentage',
            // self::FIXED      => 'Fixed'
        };
    }

    public function symbolIcon(): string
    {
        return match ($this) {
            self::PERCENTAGE => Blade::render('<x-tabler-percentage :class="$class" />', ['class' => 'pointer-events-none size-4']),
            // self::FIXED      => Blade::render('<x-tabler-currency-dollar :class="$class" />', ['class' => 'pointer-events-none size-4']),
        };
    }
}
