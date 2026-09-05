<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Events;

use App\Extensions\AIChatPro\System\Connectors\Models\AIChatProConnector;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConnectorTokenInvalidated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly AIChatProConnector $connector) {}
}
