<?php

declare(strict_types=1);

namespace App\Extensions\VideoEditor\System\Jobs;

use App\Extensions\VideoEditor\System\Models\VideoEditorExportJob;
use App\Extensions\VideoEditor\System\Services\FfmpegExportService;
use DateTime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessVideoExportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public int $exportJobId
    ) {}

    public function timeout(): int
    {
        // Queue worker timeout must outlive the internal FFmpeg loop timeout so
        // the catch block has time to mark the export as failed.
        return (int) config('video-editor.export_timeout_seconds', 3600) + 120;
    }

    /**
     * Absolute deadline for the job. Without this, Laravel uses
     * `attempts > tries` to decide failure — and `retry_after` (default 90s)
     * lets a second worker re-reserve a still-running export, immediately
     * tripping "attempted too many times" because $tries = 1.
     */
    public function retryUntil(): DateTime
    {
        return now()->addSeconds($this->timeout() + 60)->toDateTime();
    }

    /**
     * Prevent two workers from processing the same export concurrently if the
     * queue's retry_after fires before FFmpeg finishes.
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping((string) $this->exportJobId))
                ->expireAfter($this->timeout() + 60)
                ->dontRelease(),
        ];
    }

    public function handle(FfmpegExportService $exportService): void
    {
        $exportJob = VideoEditorExportJob::find($this->exportJobId);

        if (! $exportJob) {
            return;
        }

        try {
            $exportService->export($exportJob);
        } catch (Throwable $e) {
            Log::error('Video export failed', [
                'export_job_id' => $this->exportJobId,
                'error'         => $e->getMessage(),
            ]);

            $exportJob->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $exportJob = VideoEditorExportJob::find($this->exportJobId);

        if (! $exportJob || $exportJob->status === 'completed') {
            return;
        }

        $exportJob->update([
            'status'        => 'failed',
            'error_message' => $exception?->getMessage() ?? __('Export job was terminated'),
        ]);
    }
}
