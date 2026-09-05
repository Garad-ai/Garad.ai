<?php

namespace App\Extensions\PhoneCallAgent\System\Enums;

use App\Enums\Traits\EnumTo;

enum RoleEnum: string
{
    use EnumTo;

    case user = 'user';
    case agent = 'agent';
}
