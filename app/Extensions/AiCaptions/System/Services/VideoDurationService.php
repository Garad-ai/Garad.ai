<?php

declare(strict_types=1);

namespace App\Extensions\AiCaptions\System\Services;

use getID3;
use Throwable;

class VideoDurationService
{
    /**
     * Resolve the duration (in whole seconds) of a video at an absolute path.
     * Returns null if the duration cannot be determined.
     */
    public static function fromPath(string $absolutePath): ?int
    {
        if (! is_file($absolutePath)) {
            return null;
        }

        try {
            $analyzer = new getID3;
            $info = $analyzer->analyze($absolutePath);
            $seconds = $info['playtime_seconds'] ?? null;

            if (! is_numeric($seconds)) {
                return null;
            }

            return max(0, (int) round((float) $seconds));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{width: int, height: int}|null
     */
    public static function dimensionsFromPath(string $absolutePath): ?array
    {
        if (! is_file($absolutePath)) {
            return null;
        }

        try {
            $analyzer = new getID3;
            $info = $analyzer->analyze($absolutePath);
            $width = $info['video']['resolution_x'] ?? null;
            $height = $info['video']['resolution_y'] ?? null;

            if (! is_numeric($width) || ! is_numeric($height)) {
                return null;
            }

            return ['width' => (int) $width, 'height' => (int) $height];
        } catch (Throwable) {
            return null;
        }
    }
}
