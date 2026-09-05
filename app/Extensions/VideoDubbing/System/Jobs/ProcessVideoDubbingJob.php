<?php

declare(strict_types=1);

namespace App\Extensions\VideoDubbing\System\Jobs;

use App\Extensions\VideoDubbing\System\Models\VideoDubbing;
use App\Extensions\VideoDubbing\System\Services\VideoDubbingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessVideoDubbingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;

    public int $tries = 6;

    public int $backoff = 30;

    public function __construct(public int $videoDubbingId) {}

    public function handle(VideoDubbingService $service): void
    {
        $dubbing = VideoDubbing::query()->find($this->videoDubbingId);

        if (! $dubbing || $dubbing->status !== 'pending') {
            return;
        }

        $result = $service->submitDubbing($dubbing);

        if (! ($result['success'] ?? false) && ! empty($result['retryable'])) {
            $this->release(min(300, $this->backoff * ($this->attempts() + 1)));
        }
    }

    public function failed(Throwable $exception): void
    {
        VideoDubbing::query()
            ->whereKey($this->videoDubbingId)
            ->update([
                'status'        => 'error',
                'error_message' => $exception->getMessage(),
            ]);
    }
}
