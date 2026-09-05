<?php

declare(strict_types=1);

namespace App\Extensions\UGCFactory\System\Actions;

use App\Extensions\UGCFactory\System\Models\UGCFactoryActor;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Downloads a generated actor image (returned by fal.ai) to local public
 * storage and flips the actor to ready. Mirrors FinalizeUGCVideo's role.
 */
class FinalizeUGCActor
{
    public function __invoke(UGCFactoryActor $actor, string $imageUrl): void
    {
        try {
            $download = Http::timeout(120)->get($imageUrl);
        } catch (Exception $e) {
            $actor->update([
                'status' => UGCFactoryActor::STATUS_FAILED,
                'error'  => $e->getMessage(),
            ]);

            return;
        }

        if (! $download->successful()) {
            $actor->update([
                'status' => UGCFactoryActor::STATUS_FAILED,
                'error'  => __('Failed to download generated actor image.'),
            ]);

            return;
        }

        $extension = pathinfo(parse_url($imageUrl, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION) ?: 'png';
        $path = 'ugc-factory/actors/u-' . $actor->user_id . '/' . Str::uuid()->toString() . '.' . $extension;

        Storage::disk('public')->put($path, $download->body());

        $actor->update([
            'status'     => UGCFactoryActor::STATUS_READY,
            'image_path' => $path,
            'error'      => null,
        ]);
    }
}
