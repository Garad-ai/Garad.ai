<?php

declare(strict_types=1);

namespace App\Extensions\VideoEditor\System\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaProcessingService
{
    public static function extractMetadata(string $filePath, string $type): array
    {
        $metadata = [];

        if (in_array($type, ['video', 'audio'])) {
            $metadata = self::extractMediaMetadata($filePath);
        }

        if ($type === 'image') {
            $imageSize = @getimagesize($filePath);
            if ($imageSize) {
                $metadata['width'] = $imageSize[0];
                $metadata['height'] = $imageSize[1];
            }
        }

        return $metadata;
    }

    public static function generateThumbnail(string $filePath, string $type, int $userId): ?string
    {
        $ffmpegPath = config('video-editor.ffmpeg_path', '/usr/bin/ffmpeg');

        if (! file_exists($ffmpegPath)) {
            return null;
        }

        $thumbnailFilename = Str::random(20) . '.jpg';
        $thumbnailDir = 'video-editor/thumbnails/' . $userId;
        $thumbnailRelPath = $thumbnailDir . '/' . $thumbnailFilename;
        $thumbnailAbsPath = Storage::disk('public')->path($thumbnailRelPath);

        Storage::disk('public')->makeDirectory($thumbnailDir);

        if ($type === 'video') {
            $command = sprintf(
                '%s -i %s -ss 00:00:01 -vframes 1 -vf "scale=320:-1" -y %s 2>&1',
                escapeshellarg($ffmpegPath),
                escapeshellarg($filePath),
                escapeshellarg($thumbnailAbsPath)
            );

            exec($command, $output, $returnCode);

            if ($returnCode === 0 && file_exists($thumbnailAbsPath)) {
                return $thumbnailRelPath;
            }
        }

        if ($type === 'image') {
            $command = sprintf(
                '%s -i %s -vf "scale=320:-1" -y %s 2>&1',
                escapeshellarg($ffmpegPath),
                escapeshellarg($filePath),
                escapeshellarg($thumbnailAbsPath)
            );

            exec($command, $output, $returnCode);

            if ($returnCode === 0 && file_exists($thumbnailAbsPath)) {
                return $thumbnailRelPath;
            }
        }

        return null;
    }

    private static function extractMediaMetadata(string $filePath): array
    {
        $ffprobePath = config('video-editor.ffprobe_path', '/usr/bin/ffprobe');

        if (! file_exists($ffprobePath)) {
            return [];
        }

        $command = sprintf(
            '%s -v quiet -print_format json -show_format -show_streams %s 2>&1',
            escapeshellarg($ffprobePath),
            escapeshellarg($filePath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            return [];
        }

        $json = json_decode(implode("\n", $output), true);

        if (! $json) {
            return [];
        }

        $metadata = [];

        if (isset($json['format']['duration'])) {
            $metadata['duration_ms'] = (int) round((float) $json['format']['duration'] * 1000);
        }

        foreach ($json['streams'] ?? [] as $stream) {
            if ($stream['codec_type'] === 'video') {
                $metadata['width'] = (int) ($stream['width'] ?? 0);
                $metadata['height'] = (int) ($stream['height'] ?? 0);

                break;
            }
        }

        return $metadata;
    }
}
