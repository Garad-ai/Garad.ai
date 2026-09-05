<?php

declare(strict_types=1);

namespace App\Extensions\Crm\System\Jobs;

use App\Extensions\Crm\System\Services\CrmContactSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BackfillCrmContactsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public int $userId, public string $source) {}

    public function handle(CrmContactSyncService $syncService): void
    {
        if (! $syncService->isEnabled($this->source)) {
            return;
        }

        $modelClass = $syncService->modelClassFor($this->source);

        if ($modelClass === null) {
            return;
        }

        $modelClass::query()
            ->where('user_id', $this->userId)
            ->chunkById(200, function ($records) use ($syncService): void {
                foreach ($records as $record) {
                    $syncService->syncRecord($record, $this->source);
                }
            });
    }
}
