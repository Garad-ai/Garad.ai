<?php

declare(strict_types=1);

namespace App\Extensions\VideoDubbing\System\Jobs;

use App\Extensions\VideoDubbing\System\Models\VideoDubbing;
use App\Extensions\VideoDubbing\System\Services\VideoDubbingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RefreshVideoDubbingStatusJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 120;

    public int $tries = 3;

    public int $uniqueFor = 60;

    public function __construct(public int $videoDubbingId) {}

    public function uniqueId(): string
    {
        return (string) $this->videoDubbingId;
    }

    public function handle(VideoDubbingService $service): void
    {
        $dubbing = VideoDubbing::query()->find($this->videoDubbingId);

        if (! $dubbing || ! $dubbing->isPending() || ! $dubbing->request_id) {
            return;
        }

        $service->checkStatus($dubbing);
    }
}
