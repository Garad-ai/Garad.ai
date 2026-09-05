<?php

declare(strict_types=1);

namespace App\Extensions\PhoneCallAgent\System\Enums;

enum BookingProviderEnum: string
{
    case CalCom = 'cal_com';
    case Calendly = 'calendly';
}
