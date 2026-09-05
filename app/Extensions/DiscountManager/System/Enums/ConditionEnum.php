<?php

namespace App\Extensions\DiscountManager\System\Enums;

use App\Concerns\HasEnumConvert;

enum ConditionEnum: string
{
    use HasEnumConvert;

    case OR = 'or';
    case AND = 'and';

    public function label(): string
    {
        return match ($this) {
            self::OR  => 'OR',
            self::AND => 'AND'
        };
    }
}
