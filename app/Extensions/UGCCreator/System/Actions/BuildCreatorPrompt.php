<?php

declare(strict_types=1);

namespace App\Extensions\UGCCreator\System\Actions;

use App\Extensions\UGCCreator\System\Models\UGCCreatorVideo;
use App\Extensions\UGCCreator\System\Services\UGCCreatorRegistry;

/**
 * Merges the user's Video Details text with the selected camera-style preset
 * into the final prompt sent to the AI video model.
 *
 * Camera-style preset modifiers are injected silently — the user picks a
 * visual preset, and this action prepends the matching prompt fragment.
 */
class BuildCreatorPrompt
{
    public function __invoke(UGCCreatorVideo $video): string
    {
        $sections = [];

        if ($video->camera_preset && $video->camera_preset !== 'auto') {
            $injection = trim(UGCCreatorRegistry::getCameraPresetInjection($video->camera_preset));

            if ($injection !== '') {
                $sections[] = $injection;
            }
        }

        $details = trim((string) $video->video_details);
        if ($details !== '') {
            $sections[] = $details;
        }

        if (! empty($video->product_asset_ids) || ! empty($video->character_asset_ids)) {
            $hints = [];

            if (! empty($video->product_asset_ids)) {
                $hints[] = 'Featured product references are shown in the supplied product image; preserve appearance accurately.';
            }

            if (! empty($video->character_asset_ids)) {
                $hints[] = 'Use the supplied character reference image for the on-screen person — match their look and identity.';
            }

            $sections[] = implode(' ', $hints);
        }

        return trim(implode("\n\n", $sections));
    }
}
