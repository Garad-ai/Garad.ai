<?php

declare(strict_types=1);

namespace App\Extensions\UGCCreator\System\Actions;

use App\Domains\Entity\Facades\Entity;
use App\Extensions\UGCCreator\System\Models\UGCCreatorVideo;
use App\Extensions\UGCCreator\System\Services\UGCCreatorRegistry;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Downloads a finished fal.ai video to local storage, deducts credits against
 * the model's EntityEnum unit price, and flips the record to completed.
 */
class FinalizeUGCCreatorVideo
{
    public function __invoke(UGCCreatorVideo $video, string $videoUrl, ?int $duration): void
    {
        $extension = pathinfo(parse_url($videoUrl, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION) ?: 'mp4';
        $path = 'ugc-creator/videos/u-' . $video->user_id . '/' . Str::uuid()->toString() . '.' . $extension;

        try {
            $download = Http::timeout(180)->get($videoUrl);
        } catch (Exception $e) {
            $video->update([
                'status' => UGCCreatorVideo::STATUS_FAILED,
                'error'  => $e->getMessage(),
            ]);

            return;
        }

        if (! $download->successful()) {
            $video->update([
                'status' => UGCCreatorVideo::STATUS_FAILED,
                'error'  => __('Failed to download generated video.'),
            ]);

            return;
        }

        Storage::disk('public')->put($path, $download->body());

        $credits = $this->deductCredits($video);

        $video->update([
            'status'           => UGCCreatorVideo::STATUS_COMPLETED,
            'video_path'       => $path,
            'video_url'        => '/uploads/' . $path,
            'duration_seconds' => $duration,
            'credits_used'     => $credits,
        ]);
    }

    private function deductCredits(UGCCreatorVideo $video): float
    {
        $entity = UGCCreatorRegistry::getModelEntity((string) $video->model);

        if (! $entity) {
            return 0.0;
        }

        try {
            $driver = Entity::driver($entity)->forUser($video->user_id);
            $unitPrice = $entity->unitPrice();
            $driver->decreaseCredit(1.0);

            return (float) $unitPrice;
        } catch (Throwable $e) {
            Log::warning('FinalizeUGCCreatorVideo: credit deduction failed', [
                'video_id' => $video->id,
                'error'    => $e->getMessage(),
            ]);

            return 0.0;
        }
    }
}
