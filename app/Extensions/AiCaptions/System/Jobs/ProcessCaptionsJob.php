<?php

declare(strict_types=1);

namespace App\Extensions\AiCaptions\System\Jobs;

use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity;
use App\Extensions\AiCaptions\System\Models\AiCaptionVideo;
use App\Extensions\AiCaptions\System\Services\CaptionsApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Polls the Captions API for a submitted video and updates its status.
 * On completion, dispatches DownloadCaptionedVideoJob to fetch the result locally.
 */
class ProcessCaptionsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 300;

    public int $tries = 3;

    public function __construct(public int $entryId) {}

    public function handle(CaptionsApiService $api): void
    {
        $entry = AiCaptionVideo::query()->find($this->entryId);

        if (! $entry || $entry->isFailed() || $entry->isComplete()) {
            return;
        }

        if (! $entry->captions_video_id) {
            $entry->update([
                'status'        => 'failed',
                'error_message' => __('No captions video id stored for this entry.'),
            ]);

            return;
        }

        $result = $api->poll($entry->captions_video_id);

        match ($result['status']) {
            CaptionsApiService::STATUS_COMPLETED => $this->handleCompleted($entry, $result),
            CaptionsApiService::STATUS_FAILED    => $this->handleFailed($entry, $result),
            default                              => $this->handlePending($entry, $result['status']),
        };
    }

    /**
     * @param  array{output_url?: ?string}  $result
     */
    private function handleCompleted(AiCaptionVideo $entry, array $result): void
    {
        $outputUrl = $result['output_url'] ?? null;

        if (! $outputUrl) {
            $entry->update([
                'status'        => 'failed',
                'error_message' => __('Captions completed but no output URL was returned.'),
            ]);

            return;
        }

        $entry->update([
            'status'         => 'completed',
            'output_url'     => $outputUrl,
            'usage_deducted' => true,
        ]);

        $this->deductSharedCredits($entry);

        DownloadCaptionedVideoJob::dispatch($entry->id, $outputUrl);
    }

    /**
     * @param  array{error?: ?string}  $result
     */
    private function handleFailed(AiCaptionVideo $entry, array $result): void
    {
        $entry->update([
            'status'        => 'failed',
            'error_message' => Str::limit((string) ($result['error'] ?? __('Captions generation failed.')), 1000),
        ]);

        Log::warning('AiCaptions: generation failed', [
            'entry_id' => $entry->id,
            'error'    => $result['error'] ?? null,
        ]);
    }

    private function handlePending(AiCaptionVideo $entry, string $status): void
    {
        $entry->update(['status' => $status]);

        // Re-dispatch after a delay so the entry keeps moving toward completion.
        static::dispatch($entry->id)->delay(now()->addSeconds(15));
    }

    private function deductSharedCredits(AiCaptionVideo $entry): void
    {
        try {
            $minutes = max(1, (int) ceil(($entry->duration_seconds ?? 60) / 60));
            Entity::driver(EntityEnum::AI_CAPTIONS)
                ->forUser($entry->user_id)
                ->inputMinute($minutes)
                ->calculateCredit()
                ->decreaseCredit(1.0);
        } catch (Throwable $e) {
            Log::warning('AiCaptions: SharedCredit deduction failed', [
                'id'    => $entry->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
