<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Listeners;

use App\Extensions\AIChatPro\System\Events\ConnectorTokenInvalidated;
use App\Extensions\AIChatPro\System\Notifications\ConnectorTokenInvalidatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotifyUserOfConnectorFailure implements ShouldQueue
{
    public function handle(ConnectorTokenInvalidated $event): void
    {
        $connector = $event->connector;
        $user = $connector->user;

        if (! $user) {
            return;
        }

        try {
            $user->notify(new ConnectorTokenInvalidatedNotification($connector));
        } catch (Throwable $exception) {
            Log::warning('[connectors] Failed to notify user about invalidated connector', [
                'connector_id' => $connector->id,
                'user_id'      => $user->id,
                'message'      => $exception->getMessage(),
            ]);
        }
    }
}
