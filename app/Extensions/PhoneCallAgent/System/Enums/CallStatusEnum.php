<?php

namespace App\Extensions\PhoneCallAgent\System\Enums;

use App\Enums\Traits\EnumTo;

enum CallStatusEnum: string
{
    use EnumTo;

    case Ringing = 'ringing';
    case Answered = 'answered';
    case Completed = 'completed';
    case Failed = 'failed';
    case Missed = 'missed';
}
