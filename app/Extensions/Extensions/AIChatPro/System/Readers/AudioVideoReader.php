<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Readers;

use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity;
use App\Helpers\Classes\ApiHelper;
use App\Models\Usage;
use Illuminate\Support\Facades\Auth;
use OpenAI\Laravel\Facades\OpenAI;
use Symfony\Component\Process\Process;
use Throwable;

class AudioVideoReader
{
    private const VIDEO_EXTENSIONS = ['mp4', 'mov', 'mkv', 'avi', 'm4v', 'webm'];

    private const MAX_TRANSCRIPTION_BYTES = 24 * 1024 * 1024;

    public function __construct(private readonly ImageReader $imageReader = new ImageReader) {}

    public function read(string $path, string $name, string $extension): string
    {
        $extension = strtolower($extension);

        if (in_array($extension, self::VIDEO_EXTENSIONS, true)) {
            return $this->readVideo($path, $name);
        }

        $transcript = $this->transcribe($path);

        return $transcript !== ''
            ? "Asset Type: Audio\nFile: {$name}\n\nTranscript:\n{$transcript}"
            : "Audio file attached: {$name}. Automatic transcription is currently unavailable for this file.";
    }

    private function readVideo(string $path, string $name): string
    {
        $parts = ["Asset Type: Video\nFile: {$name}"];

        $audioPath = $this->extractAudio($path);
        $transcript = $audioPath ? $this->transcribe($audioPath) : $this->transcribe($path);
        if ($audioPath) {
            @unlink($audioPath);
        }

        if ($transcript !== '') {
            $parts[] = "Audio Transcript:\n{$transcript}";
        }

        $frames = $this->extractRepresentativeFrames($path);
        if ($frames !== []) {
            $visual = [];
            foreach ($frames as $index => $frame) {
                try {
                    $visual[] = sprintf(
                        "Frame %d:\n%s",
                        $index + 1,
                        $this->imageReader->read($frame, $name . ' frame ' . ($index + 1)),
                    );
                } catch (Throwable) {
                } finally {
                    @unlink($frame);
                }
            }

            if ($visual !== []) {
                $parts[] = "Representative Visual Frames:\n" . implode("\n\n", $visual);
            }
        }

        if (count($parts) === 1) {
            $parts[] = 'The video was attached, but automatic audio/visual extraction is unavailable on this server.';
        }

        return implode("\n\n---\n\n", $parts);
    }

    private function transcribe(string $path): string
    {
        if (! Auth::check() || ! is_file($path)) {
            return '';
        }

        $preparedPath = $this->prepareForTranscription($path);
        if (! $preparedPath || ! is_file($preparedPath)) {
            return '';
        }

        $temporary = $preparedPath !== $path;

        try {
            ApiHelper::setOpenAiKey();
            $driver = Entity::driver(EntityEnum::WHISPER_1);
            if (! $driver->hasCreditBalance()) {
                return '';
            }

            $response = OpenAI::audio()->transcribe([
                'file'            => fopen($preparedPath, 'rb'),
                'model'           => EntityEnum::WHISPER_1->value,
                'response_format' => 'verbose_json',
            ]);

            $text = trim((string) ($response->text ?? ''));
            if ($text !== '') {
                $driver->input($text)->calculateCredit()->decreaseCredit();
                Usage::getSingle()->updateWordCounts($driver->calculate());
            }

            return $text;
        } catch (Throwable) {
            return '';
        } finally {
            if ($temporary) {
                @unlink($preparedPath);
            }
        }
    }

    private function prepareForTranscription(string $path): ?string
    {
        $size = filesize($path) ?: 0;
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $needsConversion = $size > self::MAX_TRANSCRIPTION_BYTES || in_array($extension, self::VIDEO_EXTENSIONS, true);

        if (! $needsConversion) {
            return $path;
        }

        return $this->extractAudio($path);
    }

    private function extractAudio(string $path): ?string
    {
        if (! $this->ffmpegAvailable()) {
            return null;
        }

        $target = tempnam(sys_get_temp_dir(), 'garad_audio_');
        if (! $target) {
            return null;
        }
        $mp3 = $target . '.mp3';
        @unlink($target);

        try {
            $process = new Process([
                'ffmpeg', '-y', '-hide_banner', '-loglevel', 'error',
                '-i', $path,
                '-vn', '-ac', '1', '-ar', '16000', '-b:a', '48k',
                $mp3,
            ]);
            $process->setTimeout(180);
            $process->run();

            if (! $process->isSuccessful() || ! is_file($mp3) || (filesize($mp3) ?: 0) === 0) {
                @unlink($mp3);

                return null;
            }

            if ((filesize($mp3) ?: 0) > self::MAX_TRANSCRIPTION_BYTES) {
                @unlink($mp3);

                return null;
            }

            return $mp3;
        } catch (Throwable) {
            @unlink($mp3);

            return null;
        }
    }

    /**
     * @return array<int, string>
     */
    private function extractRepresentativeFrames(string $path): array
    {
        if (! $this->ffmpegAvailable()) {
            return [];
        }

        $duration = $this->probeDuration($path);
        if ($duration <= 0) {
            return [];
        }

        $ratios = [0.10, 0.35, 0.65, 0.90];
        $frames = [];

        foreach ($ratios as $index => $ratio) {
            $time = max(0.0, min($duration - 0.1, $duration * $ratio));
            $frame = sys_get_temp_dir() . '/garad_video_frame_' . uniqid('', true) . '_' . $index . '.jpg';

            try {
                $process = new Process([
                    'ffmpeg', '-y', '-hide_banner', '-loglevel', 'error',
                    '-ss', number_format($time, 3, '.', ''),
                    '-i', $path,
                    '-frames:v', '1',
                    '-vf', 'scale=min(1280\\,iw):-2',
                    '-q:v', '3',
                    $frame,
                ]);
                $process->setTimeout(60);
                $process->run();

                if ($process->isSuccessful() && is_file($frame) && (filesize($frame) ?: 0) > 0) {
                    $frames[] = $frame;
                } else {
                    @unlink($frame);
                }
            } catch (Throwable) {
                @unlink($frame);
            }
        }

        return $frames;
    }

    private function probeDuration(string $path): float
    {
        try {
            $process = new Process([
                'ffprobe', '-v', 'error',
                '-show_entries', 'format=duration',
                '-of', 'default=noprint_wrappers=1:nokey=1',
                $path,
            ]);
            $process->setTimeout(30);
            $process->run();

            if (! $process->isSuccessful()) {
                return 0.0;
            }

            return max(0.0, (float) trim($process->getOutput()));
        } catch (Throwable) {
            return 0.0;
        }
    }

    private function ffmpegAvailable(): bool
    {
        static $available;

        if (is_bool($available)) {
            return $available;
        }

        try {
            $process = new Process(['ffmpeg', '-version']);
            $process->setTimeout(10);
            $process->run();
            $available = $process->isSuccessful();
        } catch (Throwable) {
            $available = false;
        }

        return $available;
    }
}
