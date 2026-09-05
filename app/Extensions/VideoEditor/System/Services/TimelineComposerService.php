<?php

declare(strict_types=1);

namespace App\Extensions\VideoEditor\System\Services;

use App\Extensions\VideoEditor\System\Models\VideoEditorMedia;
use App\Extensions\VideoEditor\System\Models\VideoEditorProject;
use Illuminate\Support\Facades\Storage;

class TimelineComposerService
{
    /**
     * Build an export plan for the project. The plan describes inputs to
     * download, per-clip intermediate renders (Stage 1), a concat list
     * (Stage 2), and a final overlay/mix invocation (Stage 3). The export
     * service consumes this plan to run small ffmpeg processes in sequence,
     * avoiding the memory blow-up of a single mega-filter-graph.
     *
     * @return array{
     *     sources: list<array{original: string, local: string, needsDownload: bool}>,
     *     visualClips: list<array{cachePath: string, cached: bool, cacheHash: string, sourceIndex: int, mediaType: string, trimStart: float, trimEnd: float, speed: float, volume: float, durationSeconds: float, startTime: float, drawX: int, drawY: int, drawWidth: int, drawHeight: int}>,
     *     audioTracks: list<array{cachePath: string, cached: bool, cacheHash: string, sourceIndex: int, trimStart: float, trimEnd: float, speed: float, volume: float, durationSeconds: float, startTime: float}>,
     *     textOverlayFilter: string,
     *     width: int,
     *     height: int,
     *     fps: int
     * }
     */
    public function plan(VideoEditorProject $project): array
    {
        $timelineData = $project->timeline_data;
        $tracks = $timelineData['tracks'] ?? [];

        $allMediaIds = collect($tracks)
            ->flatMap(fn ($t) => collect($t['clips'] ?? []))
            ->pluck('mediaId')
            ->filter()
            ->unique()
            ->values();

        $mediaMap = VideoEditorMedia::query()
            ->whereIn('id', $allMediaIds)
            ->where('user_id', $project->user_id)
            ->get()
            ->keyBy('id');

        $cacheDir = rtrim(config('video-editor.export_cache_dir'), '/');
        $srcDir = rtrim(config('video-editor.export_source_cache_dir'), '/');
        $cacheVersion = (int) config('video-editor.export_clip_cache_version', 1);

        $sources = [];
        $sourceIndexByPath = [];
        $visualClips = [];
        $audioTracks = [];

        foreach ($tracks as $track) {
            if ($track['locked'] ?? false) {
                continue;
            }
            if (($track['muted'] ?? false) && $track['type'] === 'audio') {
                continue;
            }

            $isVisual = $track['type'] === 'video' || $track['type'] === 'image';
            $isAudioTrack = $track['type'] === 'audio';

            if (! $isVisual && ! $isAudioTrack) {
                continue;
            }

            foreach ($track['clips'] ?? [] as $clip) {
                $media = $mediaMap->get($clip['mediaId'] ?? null);
                if (! $media) {
                    continue;
                }

                $original = $this->resolveOriginal($media);
                if (! $original) {
                    continue;
                }

                $localPath = $this->localPathFor($original, $media, $srcDir);
                if (! isset($sourceIndexByPath[$original])) {
                    $sourceIndexByPath[$original] = count($sources);
                    $sources[] = [
                        'original'      => $original,
                        'local'         => $localPath,
                        'needsDownload' => $this->isRemote($original),
                    ];
                }
                $sourceIndex = $sourceIndexByPath[$original];

                $trimStart = ($clip['trimStart'] ?? 0) / 1000;
                $trimEnd = ($clip['trimEnd'] ?? ($clip['endTime'] - $clip['startTime'])) / 1000;
                if ($trimEnd <= $trimStart) {
                    continue;
                }
                $speed = (float) ($clip['speed'] ?? 1.0);
                if ($speed <= 0) {
                    $speed = 1.0;
                }
                $volume = (float) ($clip['volume'] ?? 1.0);
                $startTime = (int) ($clip['startTime'] ?? 0) / 1000;
                $duration = ($trimEnd - $trimStart) / $speed;

                // Draw rectangle (user-positioned size & location on the canvas).
                // libx264 with yuv420p requires even dimensions, so round down.
                $drawX = (int) ($clip['x'] ?? 0);
                $drawY = (int) ($clip['y'] ?? 0);
                $drawWidth = (int) ($clip['width'] ?? $project->width);
                $drawHeight = (int) ($clip['height'] ?? $project->height);
                $drawWidth = max(2, $drawWidth - ($drawWidth % 2));
                $drawHeight = max(2, $drawHeight - ($drawHeight % 2));

                $hash = $this->hashClip([
                    'v'          => $cacheVersion,
                    'kind'       => $isVisual ? ($media->type === 'image' ? 'image' : 'video') : 'audio',
                    'src'        => $localPath,
                    'srcMtime'   => $this->fileMtime($localPath),
                    'trimStart'  => $trimStart,
                    'trimEnd'    => $trimEnd,
                    'speed'      => $speed,
                    'volume'     => $volume,
                    'drawWidth'  => $isVisual ? $drawWidth : 0,
                    'drawHeight' => $isVisual ? $drawHeight : 0,
                    'fps'        => $project->fps,
                ]);

                $cachePath = $cacheDir . '/' . $hash . ($isVisual ? '.mp4' : '.m4a');

                $entry = [
                    'cachePath'       => $cachePath,
                    'cached'          => is_file($cachePath) && filesize($cachePath) > 0,
                    'cacheHash'       => $hash,
                    'sourceIndex'     => $sourceIndex,
                    'mediaType'       => $media->type,
                    'trimStart'       => $trimStart,
                    'trimEnd'         => $trimEnd,
                    'speed'           => $speed,
                    'volume'          => $volume,
                    'durationSeconds' => $duration,
                    'startTime'       => $startTime,
                    'drawX'           => $drawX,
                    'drawY'           => $drawY,
                    'drawWidth'       => $drawWidth,
                    'drawHeight'      => $drawHeight,
                ];

                if ($isVisual) {
                    $visualClips[] = $entry;
                } else {
                    $audioTracks[] = $entry;
                }
            }
        }

        return [
            'sources'           => $sources,
            'visualClips'       => $visualClips,
            'audioTracks'       => $audioTracks,
            'textOverlayFilter' => $this->buildTextFilters($tracks),
            'width'             => (int) $project->width,
            'height'            => (int) $project->height,
            'fps'               => (int) ($project->fps ?? 30),
        ];
    }

    /**
     * Build the ffmpeg command (as an argv array) that renders a single
     * visual clip to its cache path. Audio is included if the source has it,
     * otherwise a silent stereo AAC track is muxed in so all visual
     * intermediates share the same stream layout (required for concat -c copy).
     *
     * @return list<string>
     */
    public function buildVisualClipCommand(array $clip, array $source, string $ffmpegPath, int $width, int $height, int $fps): array
    {
        $isImage = $clip['mediaType'] === 'image';
        $duration = number_format($clip['durationSeconds'], 3, '.', '');
        $trimStart = number_format($clip['trimStart'], 3, '.', '');
        $trimDuration = number_format($clip['trimEnd'] - $clip['trimStart'], 3, '.', '');

        // Render only at the user-positioned size: the compose pass overlays
        // this small frame at (drawX, drawY) onto the project canvas, so we
        // must NOT pad here — padding would cover the underlying tracks with
        // black during overlay.
        $drawWidth = (int) ($clip['drawWidth'] ?? $width);
        $drawHeight = (int) ($clip['drawHeight'] ?? $height);
        $videoFilter = sprintf(
            'scale=%d:%d:flags=lanczos,setsar=1,fps=%d',
            $drawWidth,
            $drawHeight,
            $fps
        );

        // Speed change is meaningful for video sources only — for a single
        // image frame, `-t duration` already encodes the slowdown.
        if (! $isImage && $clip['speed'] !== 1.0) {
            $videoFilter = sprintf('setpts=%s*PTS,', number_format(1 / $clip['speed'], 6, '.', '')) . $videoFilter;
        }

        $cmd = [$ffmpegPath, '-y', '-hide_banner', '-loglevel', 'error'];

        if ($isImage) {
            // PNG/JPG source: loop the still frame for the requested duration.
            // No audio comes from the image — append anullsrc as a second input.
            $cmd[] = '-loop';
            $cmd[] = '1';
            $cmd[] = '-t';
            $cmd[] = $duration;
            $cmd[] = '-i';
            $cmd[] = $source['local'];

            $cmd[] = '-f';
            $cmd[] = 'lavfi';
            $cmd[] = '-t';
            $cmd[] = $duration;
            $cmd[] = '-i';
            $cmd[] = 'anullsrc=channel_layout=stereo:sample_rate=48000';

            $cmd[] = '-filter:v';
            $cmd[] = $videoFilter;
        } else {
            // Video source: use input-side -ss/-t for fast accurate seek before
            // expensive filters. setpts handles speed change for the video side.
            $cmd[] = '-ss';
            $cmd[] = $trimStart;
            $cmd[] = '-t';
            $cmd[] = $trimDuration;
            $cmd[] = '-i';
            $cmd[] = $source['local'];

            $cmd[] = '-filter:v';
            $cmd[] = $videoFilter;

            $audioFilter = 'aresample=48000,aformat=channel_layouts=stereo';
            if ($clip['speed'] !== 1.0) {
                $audioFilter .= ',' . $this->buildAtempoChain($clip['speed']);
            }
            $audioFilter .= sprintf(',volume=%s', number_format($clip['volume'], 2, '.', ''));

            $cmd[] = '-filter:a';
            $cmd[] = $audioFilter;
        }

        $cmd = array_merge($cmd, [
            '-c:v', 'libx264',
            '-preset', 'veryfast',
            '-crf', '18',
            '-pix_fmt', 'yuv420p',
            '-c:a', 'aac',
            '-ar', '48000',
            '-ac', '2',
            '-b:a', '192k',
            '-r', (string) $fps,
            '-shortest',
            '-movflags', '+faststart',
            $clip['cachePath'],
        ]);

        return $cmd;
    }

    /**
     * Build the ffmpeg command that renders a single audio-track clip to its
     * cache path as a normalized AAC m4a.
     *
     * @return list<string>
     */
    public function buildAudioClipCommand(array $clip, array $source, string $ffmpegPath): array
    {
        $trimStart = number_format($clip['trimStart'], 3, '.', '');
        $trimDuration = number_format($clip['trimEnd'] - $clip['trimStart'], 3, '.', '');

        $audioFilter = 'aresample=48000,aformat=channel_layouts=stereo';
        if ($clip['speed'] !== 1.0) {
            $audioFilter .= ',' . $this->buildAtempoChain($clip['speed']);
        }
        $audioFilter .= sprintf(',volume=%s', number_format($clip['volume'], 2, '.', ''));

        return [
            $ffmpegPath, '-y', '-hide_banner', '-loglevel', 'error',
            '-ss', $trimStart,
            '-t', $trimDuration,
            '-i', $source['local'],
            '-filter:a', $audioFilter,
            '-c:a', 'aac',
            '-ar', '48000',
            '-ac', '2',
            '-b:a', '192k',
            '-vn',
            '-movflags', '+faststart',
            $clip['cachePath'],
        ];
    }

    /**
     * Build the ffmpeg command that composes the entire timeline in a single
     * pass: each visual clip is overlaid onto a black canvas at its absolute
     * timeline `startTime` (with gaps left black, multiple visual tracks
     * compositing on top of each other in track order), every clip's audio is
     * delayed to its timeline position and mixed alongside the audio-track
     * clips, and text overlays are drawn on top.
     *
     * Inputs (positional, used by filter_complex):
     *   [0:v] black canvas (lavfi color, full project duration)
     *   [1:a] silent base track (lavfi anullsrc, full project duration)
     *   [2:v][2:a]..[N+1:*] visual clip intermediates (in $visualClips order)
     *   [N+2:a].. audio-track intermediates (in $audioTracks order)
     *
     * @return list<string>
     */
    public function buildComposeCommand(
        string $ffmpegPath,
        array $visualClips,
        array $audioTracks,
        string $textOverlayFilter,
        int $width,
        int $height,
        int $fps,
        float $totalDurationSeconds,
        string $outputPath,
        string $progressFile
    ): array {
        $duration = number_format(max(0.1, $totalDurationSeconds), 3, '.', '');

        $cmd = [$ffmpegPath, '-y', '-hide_banner', '-progress', $progressFile];

        // Input 0: black video canvas spanning the full project.
        $cmd[] = '-f';
        $cmd[] = 'lavfi';
        $cmd[] = '-t';
        $cmd[] = $duration;
        $cmd[] = '-i';
        $cmd[] = sprintf('color=c=black:s=%dx%d:r=%d', $width, $height, $fps);

        // Input 1: silent audio base so amix always has at least one source.
        $cmd[] = '-f';
        $cmd[] = 'lavfi';
        $cmd[] = '-t';
        $cmd[] = $duration;
        $cmd[] = '-i';
        $cmd[] = 'anullsrc=channel_layout=stereo:sample_rate=48000';

        foreach ($visualClips as $clip) {
            $cmd[] = '-i';
            $cmd[] = $clip['cachePath'];
        }
        foreach ($audioTracks as $track) {
            $cmd[] = '-i';
            $cmd[] = $track['cachePath'];
        }

        $visualInputBase = 2;
        $audioTrackInputBase = $visualInputBase + count($visualClips);

        $parts = [];
        $audioLabels = ['[1:a]'];

        // Visual overlay chain: shift each clip's PTS to its timeline startTime
        // and overlay onto the running canvas, gated to its [start,end] window
        // so frames don't bleed before or after the clip's duration.
        $prevLabel = '[0:v]';
        if (empty($visualClips)) {
            $parts[] = '[0:v]null[vcomp]';
            $prevLabel = '[vcomp]';
        } else {
            $count = count($visualClips);
            foreach ($visualClips as $i => $clip) {
                $start = (float) $clip['startTime'];
                $clipDur = (float) $clip['durationSeconds'];
                $end = $start + $clipDur;
                $inputIdx = $visualInputBase + $i;

                $shiftedLabel = sprintf('[vs%d]', $i);
                if ($start > 0) {
                    $parts[] = sprintf(
                        '[%d:v]tpad=start_duration=%s:color=black,setpts=PTS-STARTPTS%s',
                        $inputIdx,
                        number_format($start, 6, '.', ''),
                        $shiftedLabel
                    );
                } else {
                    $parts[] = sprintf(
                        '[%d:v]setpts=PTS-STARTPTS%s',
                        $inputIdx,
                        $shiftedLabel
                    );
                }

                $outLabel = ($i === $count - 1) ? '[vcomp]' : sprintf('[vov%d]', $i);
                $drawX = (int) ($clip['drawX'] ?? 0);
                $drawY = (int) ($clip['drawY'] ?? 0);
                $parts[] = sprintf(
                    "%s%soverlay=x=%d:y=%d:eof_action=pass:enable='between(t\\,%s\\,%s)'%s",
                    $prevLabel,
                    $shiftedLabel,
                    $drawX,
                    $drawY,
                    number_format($start, 6, '.', ''),
                    number_format($end, 6, '.', ''),
                    $outLabel
                );
                $prevLabel = $outLabel;

                // Bring this clip's audio into the mix, delayed to its
                // timeline startTime.
                $delayMs = (int) round($start * 1000);
                $aLabel = sprintf('[va%d]', $i);
                $f = sprintf('[%d:a]', $inputIdx);
                $f .= $delayMs > 0 ? sprintf('adelay=%d:all=1', $delayMs) : 'anull';
                $f .= $aLabel;
                $parts[] = $f;
                $audioLabels[] = $aLabel;
            }
        }

        if ($textOverlayFilter !== '') {
            $parts[] = $prevLabel . $textOverlayFilter . '[outv]';
        } else {
            $parts[] = $prevLabel . 'null[outv]';
        }

        foreach ($audioTracks as $i => $track) {
            $inputIdx = $audioTrackInputBase + $i;
            $delayMs = (int) round(((float) $track['startTime']) * 1000);
            $aLabel = sprintf('[ta%d]', $i);
            $f = sprintf('[%d:a]', $inputIdx);
            $f .= $delayMs > 0 ? sprintf('adelay=%d:all=1', $delayMs) : 'anull';
            $f .= $aLabel;
            $parts[] = $f;
            $audioLabels[] = $aLabel;
        }

        $parts[] = implode('', $audioLabels) . sprintf(
            'amix=inputs=%d:duration=longest:dropout_transition=0:normalize=0[outa]',
            count($audioLabels)
        );

        $cmd[] = '-filter_complex';
        $cmd[] = implode(';', $parts);

        $cmd = array_merge($cmd, [
            '-map', '[outv]',
            '-map', '[outa]',
            '-t', $duration,
            '-r', (string) $fps,
            '-c:v', 'libx264',
            '-preset', 'veryfast',
            '-crf', '23',
            '-pix_fmt', 'yuv420p',
            '-threads', '2',
            '-x264-params', 'rc-lookahead=10:ref=1:bframes=0',
            '-c:a', 'aac',
            '-b:a', '192k',
            '-movflags', '+faststart',
            $outputPath,
        ]);

        return $cmd;
    }

    private function buildTextFilters(array $tracks): string
    {
        $textOverlays = [];

        foreach ($tracks as $track) {
            if ($track['type'] !== 'text') {
                continue;
            }
            if ($track['muted'] ?? false) {
                continue;
            }

            foreach ($track['clips'] ?? [] as $clip) {
                $text = $this->escapeFFmpegText($clip['text'] ?? '');
                $fontSize = max(8, min(200, (int) ($clip['fontSize'] ?? 48)));
                $color = preg_replace('/[^a-zA-Z0-9#]/', '', $clip['color'] ?? 'white') ?: 'white';
                $x = max(0, (int) ($clip['x'] ?? 10));
                $y = max(0, (int) ($clip['y'] ?? 10));
                $startTime = max(0, ($clip['startTime'] ?? 0)) / 1000;
                $endTime = max(0, ($clip['endTime'] ?? 0)) / 1000;

                $textOverlays[] = sprintf(
                    "drawtext=text='%s':fontsize=%d:fontcolor=%s:x=%d:y=%d:enable='between(t\\,%s\\,%s)'",
                    $text,
                    $fontSize,
                    $color,
                    $x,
                    $y,
                    number_format($startTime, 3, '.', ''),
                    number_format($endTime, 3, '.', '')
                );
            }
        }

        return implode(',', $textOverlays);
    }

    private function escapeFFmpegText(string $text): string
    {
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace("'", "'\\''", $text);
        $text = str_replace(':', '\\:', $text);
        $text = str_replace('[', '\\[', $text);
        $text = str_replace(']', '\\]', $text);
        $text = str_replace(';', '\\;', $text);
        $text = str_replace(',', '\\,', $text);
        $text = preg_replace('/[\x00-\x1F\x7F]/', '', $text);

        return $text;
    }

    /**
     * ffmpeg's atempo accepts 0.5–2.0 per stage. Values outside that range are
     * chained so the product equals the target speed (e.g. 0.25 → 0.5*0.5).
     */
    private function buildAtempoChain(float $speed): string
    {
        $parts = [];
        $remaining = $speed;

        while ($remaining > 2.0) {
            $parts[] = 'atempo=2.0';
            $remaining /= 2.0;
        }
        while ($remaining < 0.5) {
            $parts[] = 'atempo=0.5';
            $remaining /= 0.5;
        }
        $parts[] = 'atempo=' . number_format($remaining, 6, '.', '');

        return implode(',', $parts);
    }

    private function resolveOriginal(VideoEditorMedia $media): ?string
    {
        $path = $media->path;
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        // The "public" disk root is `public_path()/uploads`; some records store
        // paths with the `uploads/` prefix already, others don't. Try both.
        $candidates = [
            Storage::disk('public')->path($path),
            Storage::disk('public')->path(ltrim(preg_replace('#^uploads/#', '', $path), '/')),
            public_path(ltrim($path, '/')),
        ];
        foreach ($candidates as $c) {
            if (is_file($c)) {
                return $c;
            }
        }

        return null;
    }

    /**
     * Where a source should live locally — same path if already local, else
     * a deterministic path under the source cache dir keyed by the URL.
     */
    private function localPathFor(string $original, VideoEditorMedia $media, string $srcDir): string
    {
        if (! $this->isRemote($original)) {
            return $original;
        }

        $ext = $this->extensionFor($original, $media);
        $key = sha1($original);

        return $srcDir . '/' . $key . ($ext ? '.' . $ext : '');
    }

    private function extensionFor(string $url, VideoEditorMedia $media): string
    {
        $fromUrl = pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION);
        if ($fromUrl !== '') {
            return strtolower($fromUrl);
        }

        return match ($media->type) {
            'image' => 'png',
            'audio' => 'mp3',
            default => 'mp4',
        };
    }

    private function isRemote(string $path): bool
    {
        return str_starts_with($path, 'http://') || str_starts_with($path, 'https://');
    }

    private function fileMtime(string $path): int
    {
        if ($this->isRemote($path)) {
            // Can't stat a URL; rely on the URL itself being part of the hash
            // (and the source cache key, which is sha1 of the URL).
            return 0;
        }

        return is_file($path) ? (int) filemtime($path) : 0;
    }

    private function hashClip(array $parts): string
    {
        return sha1(json_encode($parts, JSON_UNESCAPED_SLASHES));
    }
}
