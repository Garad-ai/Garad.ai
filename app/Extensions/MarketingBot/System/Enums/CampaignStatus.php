<?php

namespace App\Extensions\MarketingBot\System\Enums;

enum CampaignStatus: string
{
    case pending = 'pending';
    case scheduled = 'scheduled';
    case in_progress = 'in_progress';
    case published = 'published';
    case failed = 'failed';
    case running = 'running';
}
