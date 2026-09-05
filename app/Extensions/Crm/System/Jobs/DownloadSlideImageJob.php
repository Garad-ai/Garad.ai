<?php

namespace App\Extensions\Crm\System\Jobs;

use App\Console\Commands\FluxProQueueCheck;
use App\Extensions\Crm\System\Models\CrmPresentationSlide;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DownloadSlideImageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 300;

    public int $tries = 2;

    public function __construct(
        public int $slideId,
        public string $remoteUrl,
    ) {}

    public function handle(): void
    {
        $slide = CrmPresentationSlide::with('presentation')->find($this->slideId);

        if (! $slide) {
            return;
        }

        // Skip if the slide is already pointing at a local URL (job already ran)
        if ($slide->image_url !== $this->remoteUrl) {
            return;
        }

        $localUrl = FluxProQueueCheck::downloadImageToStorage(
            $this->remoteUrl,
            null,
            (int) ($slide->presentation?->user_id ?? 0),
        );

        if (! $localUrl) {
            Log::warning('[Crm] DownloadSlideImageJob failed to download', [
                'slide_id'   => $this->slideId,
                'remote_url' => $this->remoteUrl,
            ]);

            return;
        }

        $slide->update(['image_url' => $localUrl]);
    }
}
