<?php

namespace App\Extensions\DiscountManager\System\Enums;

use App\Concerns\HasEnumConvert;

enum PricingPlansEnum: string
{
    use HasEnumConvert;

    case PREMIUM = 'premium';
    case PRO = 'pro';

    public function label(): string
    {
        return match ($this) {
            self::PREMIUM => 'Premium',
            self::PRO     => 'Pro',
        };
    }
}
