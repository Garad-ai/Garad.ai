<?php

declare(strict_types=1);

namespace App\Extensions\Crm\System\Listeners;

use App\Events\ContactCapturedEvent;
use App\Extensions\Crm\System\Services\CrmContactSyncService;

/**
 * Runs synchronously so a single captured contact is mirrored into the CRM
 * immediately, without depending on a running queue worker. Each handle()
 * performs at most one lightweight upsert.
 */
class SyncCapturedContactToCrm
{
    public function __construct(private readonly CrmContactSyncService $syncService) {}

    public function handle(ContactCapturedEvent $event): void
    {
        $this->syncService->sync(
            $event->userId,
            $event->name,
            $event->email,
            $event->phone,
            $event->countryCode,
            $event->source,
        );
    }
}
