<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Enums;

enum WorkflowStatusEnum: string
{
    case Active   = 'active';
    case Paused   = 'paused';
    case Archived = 'archived';
}
