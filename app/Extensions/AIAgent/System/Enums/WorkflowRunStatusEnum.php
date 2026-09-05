<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Enums;

enum WorkflowRunStatusEnum: string
{
    case Running   = 'running';
    case Completed = 'completed';
    case Failed    = 'failed';
}
