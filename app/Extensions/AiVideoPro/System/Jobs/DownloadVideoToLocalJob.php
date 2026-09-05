<?php

namespace App\Extensions\AiVideoPro\System\Jobs;

use App\Extensions\AiVideoPro\System\Models\UserFall;
use App\Extensions\AiVideoPro\System\Services\SoraService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DownloadVideoToLocalJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;

    public int $tries = 2;

    public function __construct(
        public int $entryId,
        public string $remoteUrl,
        public string $prefix,
    ) {}

    public function handle(): void
    {
        $entry = UserFall::find($this->entryId);

        if (! $entry) {
            return;
        }

        // Skip if already pointing at a local URL (job already ran)
        if ($entry->video_url !== $this->remoteUrl) {
            return;
        }

        $localUrl = SoraService::downloadFromUrl(
            $this->remoteUrl,
            $entry->user_id,
            $entry->request_id ?? (string) $entry->id,
            $this->prefix,
        );

        if (! $localUrl) {
            Log::warning('[AiVideoPro] DownloadVideoToLocalJob failed to download', [
                'entry_id'   => $this->entryId,
                'remote_url' => $this->remoteUrl,
            ]);

            return;
        }

        $entry->update(['video_url' => $localUrl]);
    }
}
