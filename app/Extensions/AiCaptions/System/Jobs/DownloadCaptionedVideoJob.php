<?php

declare(strict_types=1);

namespace App\Extensions\AiCaptions\System\Jobs;

use App\Extensions\AiCaptions\System\Models\AiCaptionVideo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Downloads the remote captioned-video URL to local storage and updates the
 * entry's output_url to the local URL.
 */
class DownloadCaptionedVideoJob implements ShouldQueue
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
    ) {}

    public function handle(): void
    {
        $entry = AiCaptionVideo::query()->find($this->entryId);

        if (! $entry) {
            return;
        }

        // Already downloaded — nothing to do.
        if ($entry->local_path && $entry->output_url !== $this->remoteUrl) {
            return;
        }

        $fileName = "captions_{$entry->id}.mp4";
        $relativePath = "media/videos/u-{$entry->user_id}/captions/{$fileName}";
        $absolutePath = Storage::disk('uploads')->path($relativePath);

        try {
            if (! is_dir(dirname($absolutePath))
                && ! mkdir($dir = dirname($absolutePath), 0755, true)
                && ! is_dir($dir)
            ) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
            }

            $response = Http::timeout(300)->sink($absolutePath)->get($this->remoteUrl);

            if (! $response->successful()) {
                Log::warning('AiCaptions: failed to download captioned video', [
                    'entry_id' => $entry->id,
                    'status'   => $response->status(),
                ]);

                return;
            }

            $entry->update([
                'output_url' => Storage::disk('uploads')->url($relativePath),
                'local_path' => $relativePath,
            ]);
        } catch (Throwable $e) {
            Log::warning('AiCaptions: download exception', [
                'entry_id' => $entry->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }
}
