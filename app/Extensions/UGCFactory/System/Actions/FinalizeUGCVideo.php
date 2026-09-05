<?php

declare(strict_types=1);

namespace App\Extensions\UGCFactory\System\Actions;

use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity;
use App\Extensions\UGCFactory\System\Models\UGCFactoryVideo;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Downloads a finished VEED Fabric video to local storage, deducts credits,
 * and flips the video record to completed. Pulled out of the original
 * polling job so the user-facing status endpoint can drive the same logic.
 */
class FinalizeUGCVideo
{
    public function __invoke(UGCFactoryVideo $video, string $videoUrl, ?int $duration): void
    {
        $extension = pathinfo(parse_url($videoUrl, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION) ?: 'mp4';
        $path = 'ugc-factory/videos/u-' . $video->user_id . '/' . Str::uuid()->toString() . '.' . $extension;

        try {
            $download = Http::timeout(180)->get($videoUrl);
        } catch (Exception $e) {
            $video->update([
                'status' => UGCFactoryVideo::STATUS_FAILED,
                'error'  => $e->getMessage(),
            ]);

            return;
        }

        if (! $download->successful()) {
            $video->update([
                'status' => UGCFactoryVideo::STATUS_FAILED,
                'error'  => __('Failed to download generated video.'),
            ]);

            return;
        }

        Storage::disk('public')->put($path, $download->body());

        $credits = $this->deductCredits($video);

        $video->update([
            'status'           => UGCFactoryVideo::STATUS_COMPLETED,
            'video_path'       => $path,
            'video_url'        => '/uploads/' . $path,
            'duration_seconds' => $duration,
            'credits_used'     => $credits,
        ]);
    }

    private function deductCredits(UGCFactoryVideo $video): float
    {
        try {
            $driver = Entity::driver(EntityEnum::VEED_FABRIC)->forUser($video->user_id);
            $unitPrice = EntityEnum::VEED_FABRIC->unitPrice();
            $driver->decreaseCredit(1.0);

            return (float) $unitPrice;
        } catch (Throwable $e) {
            Log::warning('FinalizeUGCVideo: credit deduction failed', [
                'video_id' => $video->id,
                'error'    => $e->getMessage(),
            ]);

            return 0.0;
        }
    }
}
