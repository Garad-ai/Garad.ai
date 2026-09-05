<?php

namespace App\Extensions\DiscountManager\System\Enums;

use App\Concerns\HasEnumConvert;

enum UserTypeEnum: string
{
    use HasEnumConvert;

    case NEW = 'new';

    case INACTIVE = 'inactive';
    // case ALL = 'all';

    public function label(): string
    {
        return match ($this) {
            self::NEW      => 'New',
            self::INACTIVE => 'Inactive',
            // self::ALL      => 'All',
        };
    }
}
