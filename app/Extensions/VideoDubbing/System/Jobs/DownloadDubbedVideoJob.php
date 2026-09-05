<?php

declare(strict_types=1);

namespace App\Extensions\VideoDubbing\System\Jobs;

use App\Extensions\VideoDubbing\System\Models\VideoDubbing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DownloadDubbedVideoJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;

    public int $tries = 3;

    public int $backoff = 30;

    public int $uniqueFor = 600;

    public function __construct(public int $videoDubbingId) {}

    public function uniqueId(): string
    {
        return (string) $this->videoDubbingId;
    }

    public function handle(): void
    {
        $dubbing = VideoDubbing::query()->find($this->videoDubbingId);

        if (! $dubbing || $dubbing->status !== 'complete' || ! $dubbing->output_url) {
            return;
        }

        if ($dubbing->local_output_path && Storage::disk('uploads')->exists($dubbing->local_output_path)) {
            return;
        }

        $remoteUrl = $dubbing->output_url;
        $relativePath = sprintf('video-dubbing/%d-%s.mp4', $dubbing->id, $dubbing->target_language);
        $absolutePath = Storage::disk('uploads')->path($relativePath);

        if (! is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0775, true);
        }

        $source = @fopen($remoteUrl, 'rb');

        if (! $source) {
            Log::warning('DownloadDubbedVideoJob: unable to open remote URL', [
                'dubbing_id' => $dubbing->id,
                'url'        => $remoteUrl,
            ]);

            return;
        }

        $dest = fopen($absolutePath, 'wb');
        stream_copy_to_stream($source, $dest);
        fclose($source);
        fclose($dest);

        $localUrl = Storage::disk('uploads')->url($relativePath);

        VideoDubbing::query()
            ->whereKey($dubbing->id)
            ->update([
                'output_url'        => $localUrl,
                'local_output_path' => $relativePath,
            ]);
    }
}
