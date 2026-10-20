<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Enums;

enum WorkflowRunStatusEnum: string
{
    case Running = 'running';
    case Cancelling = 'cancelling';
    case Cancelled = 'cancelled';
    case PendingApproval = 'pending_approval';
    case Completed = 'completed';
    case Failed = 'failed';
}
