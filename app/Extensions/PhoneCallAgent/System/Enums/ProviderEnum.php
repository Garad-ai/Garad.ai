<?php

namespace App\Extensions\PhoneCallAgent\System\Enums;

use App\Enums\Traits\EnumTo;

enum ProviderEnum: string
{
    use EnumTo;

    case Twilio = 'twilio';
    case Elevenlabs = 'elevenlabs';
}
