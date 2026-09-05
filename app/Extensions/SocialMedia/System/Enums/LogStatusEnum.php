<?php

namespace App\Extensions\SocialMedia\System\Enums;

enum LogStatusEnum: string
{
    case success = 'success';

    case expired = 'expired';

    case failed = 'failed';
}
