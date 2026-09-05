<?php

declare(strict_types=1);

namespace App\Extensions\VideoEditor\System\Services;

use App\Extensions\VideoEditor\System\Models\VideoEditorExportJob;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class FfmpegExportService
{
    private TimelineComposerService $composer;

    public function __construct()
    {
        $this->composer = new TimelineComposerService;
    }

    public function export(VideoEditorExportJob $exportJob): void
    {
        $project = $exportJob->project;
        if (! $project) {
            throw new RuntimeException('Project not found for export job');
        }

        $ffmpegPath = config('video-editor.ffmpeg_path', '/usr/bin/ffmpeg');
        if (! file_exists($ffmpegPath)) {
            throw new RuntimeException('FFmpeg not found at: ' . $ffmpegPath);
        }

        // Resolve writable cache locations BEFORE planning, since the composer
        // bakes these paths into each clip's local source/intermediate path.
        // If the configured dir (e.g. /tmp/ve-src) is owned by another user
        // and not writable, transparently fall back to a Laravel-managed path.
        $cacheDir = $this->resolveWritableDir(config('video-editor.export_cache_dir'), 've-clip-cache');
        $srcDir = $this->resolveWritableDir(config('video-editor.export_source_cache_dir'), 've-src');
        $workRoot = $this->resolveWritableDir(config('video-editor.export_work_dir'), 've-export');

        config([
            'video-editor.export_cache_dir'        => $cacheDir,
            'video-editor.export_source_cache_dir' => $srcDir,
            'video-editor.export_work_dir'         => $workRoot,
        ]);

        $plan = $this->composer->plan($project);
        if (empty($plan['visualClips']) && empty($plan['audioTracks'])) {
            throw new RuntimeException('Timeline has no renderable clips');
        }

        $workDir = $workRoot . '/job-' . $exportJob->id;
        $this->ensureDir($workDir);

        $outputDir = 'video-editor/exports/' . $exportJob->user_id;
        Storage::disk('public')->makeDirectory($outputDir);
        $outputFilename = Str::slug($project->name) . '_' . $exportJob->resolution . '_' . time() . '.' . $exportJob->format;
        $outputRelPath = $outputDir . '/' . $outputFilename;
        $outputAbsPath = Storage::disk('public')->path($outputRelPath);

        $exportJob->update([
            'status'     => 'processing',
            'started_at' => now(),
            'progress'   => 0,
        ]);

        $deadline = time() + (int) config('video-editor.export_timeout_seconds', 3600);

        try {
            // Stage 0: download remote sources
            $this->downloadSources($plan['sources'], $deadline, function (int $done, int $total) use ($exportJob) {
                $this->updateProgress($exportJob, $total > 0 ? (int) round(15 * $done / $total) : 15);
            });

            // Stage 1: per-clip intermediate renders
            $allClips = array_merge($plan['visualClips'], $plan['audioTracks']);
            $totalClips = count($allClips);
            $renderedCount = 0;

            foreach ($plan['visualClips'] as $clip) {
                if (! $clip['cached']) {
                    $source = $plan['sources'][$clip['sourceIndex']];
                    $cmd = $this->composer->buildVisualClipCommand($clip, $source, $ffmpegPath, $plan['width'], $plan['height'], $plan['fps']);
                    $this->runProcess($cmd, $deadline, 'render visual clip');
                }
                $renderedCount++;
                $this->updateProgress($exportJob, 15 + (int) round(60 * $renderedCount / max(1, $totalClips)));
            }

            foreach ($plan['audioTracks'] as $clip) {
                if (! $clip['cached']) {
                    $source = $plan['sources'][$clip['sourceIndex']];
                    $cmd = $this->composer->buildAudioClipCommand($clip, $source, $ffmpegPath);
                    $this->runProcess($cmd, $deadline, 'render audio clip');
                }
                $renderedCount++;
                $this->updateProgress($exportJob, 15 + (int) round(60 * $renderedCount / max(1, $totalClips)));
            }

            // Stage 2: single-pass timeline compose. Each visual clip is
            // overlaid onto a black canvas at its absolute startTime (so gaps
            // stay black and layered tracks composite), every clip's audio is
            // delayed to its timeline position, text overlays are drawn, and
            // the result is written directly to the output file.
            $progressFile = $workDir . '/progress.txt';
            $totalDurationSeconds = $this->computeTimelineDuration($plan, (int) $project->duration_ms);
            $composeCmd = $this->composer->buildComposeCommand(
                $ffmpegPath,
                $plan['visualClips'],
                $plan['audioTracks'],
                $plan['textOverlayFilter'],
                $plan['width'],
                $plan['height'],
                $plan['fps'],
                $totalDurationSeconds,
                $outputAbsPath,
                $progressFile
            );
            $totalMs = max(1, (int) round($totalDurationSeconds * 1000));
            $this->runProcessWithProgress($composeCmd, $deadline, $progressFile, $totalMs, function (int $stageProgress) use ($exportJob) {
                // Map 0..100 of the compose ffmpeg run to overall 75..99
                $this->updateProgress($exportJob, 75 + (int) round(24 * $stageProgress / 100));
            }, 'timeline compose');

            if (! file_exists($outputAbsPath)) {
                throw new RuntimeException('FFmpeg final stage produced no output');
            }

            $exportJob->update([
                'status'       => 'completed',
                'progress'     => 100,
                'output_path'  => $outputRelPath,
                'completed_at' => now(),
            ]);
        } finally {
            $this->removeDir($workDir);
            $this->pruneCache($cacheDir);
            $this->pruneCache($srcDir);
        }
    }

    /**
     * @param  list<array{original: string, local: string, needsDownload: bool}>  $sources
     */
    private function downloadSources(array $sources, int $deadline, callable $onProgress): void
    {
        $total = count($sources);
        $done = 0;
        foreach ($sources as $src) {
            if (! $src['needsDownload']) {
                $done++;
                $onProgress($done, $total);

                continue;
            }
            if (is_file($src['local']) && filesize($src['local']) > 0) {
                $done++;
                $onProgress($done, $total);

                continue;
            }
            $this->ensureDir(dirname($src['local']));
            $this->downloadTo($src['original'], $src['local'], $deadline);
            $done++;
            $onProgress($done, $total);
        }
    }

    private function downloadTo(string $url, string $dest, int $deadline): void
    {
        $remaining = max(5, $deadline - time());
        $destDir = dirname($dest);

        $this->ensureDir($destDir);

        $tmp = $this->openWritableTempFile($destDir, basename($dest), $fh);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fh,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => $remaining,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_USERAGENT      => 'VideoEditor/1.0',
        ]);
        $ok = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        fclose($fh);

        if (! $ok || $code >= 400) {
            @unlink($tmp);

            throw new RuntimeException(sprintf('Source download failed (%s): %s — %s', $code, $url, $err));
        }

        if (! @rename($tmp, $dest)) {
            // Fallback for cross-device or permission-locked targets: copy + unlink.
            if (! @copy($tmp, $dest)) {
                $err = error_get_last()['message'] ?? 'unknown error';
                @unlink($tmp);

                throw new RuntimeException("Cannot move downloaded file into place: {$dest} ({$err})");
            }
            @unlink($tmp);
        }
    }

    /**
     * Open a uniquely-named writable temp file inside $dir. Stale partials from
     * crashed runs (possibly owned by a different OS user under Octane vs queue
     * workers) are removed first; if removal fails, fall back to a unique name
     * so the export can still proceed.
     *
     * @param  resource|null  $fh  Out-param: open file handle on success
     */
    private function openWritableTempFile(string $dir, string $baseName, &$fh): string
    {
        $primary = $dir . '/' . $baseName . '.partial';

        if (is_file($primary)) {
            @unlink($primary);
        }

        $candidates = [$primary];
        if (is_file($primary)) {
            // Could not remove stale partial — try a unique-suffixed name.
            $candidates[] = $dir . '/' . $baseName . '.' . bin2hex(random_bytes(4)) . '.partial';
        }

        foreach ($candidates as $tmp) {
            $handle = @fopen($tmp, 'wb');
            if ($handle) {
                $fh = $handle;

                return $tmp;
            }
        }

        $diag = sprintf(
            'dir=%s exists=%s writable=%s owner=%s perms=%o free=%s err=%s',
            $dir,
            is_dir($dir) ? 'yes' : 'no',
            is_writable($dir) ? 'yes' : 'no',
            (string) (function_exists('fileowner') ? @fileowner($dir) : '?'),
            @fileperms($dir) & 0777,
            (string) @disk_free_space($dir),
            error_get_last()['message'] ?? 'unknown',
        );

        throw new RuntimeException('Cannot open download temp file: ' . $primary . ' [' . $diag . ']');
    }

    /**
     * Resolve the final timeline length. The project stores duration_ms but
     * we also derive from clip end-times as a safety net so the canvas is
     * always long enough to contain every clip even if the stored duration
     * is stale.
     *
     * @param  array<string, mixed>  $plan
     */
    private function computeTimelineDuration(array $plan, int $projectDurationMs): float
    {
        $maxEnd = 0.0;
        foreach ($plan['visualClips'] as $c) {
            $maxEnd = max($maxEnd, (float) $c['startTime'] + (float) $c['durationSeconds']);
        }
        foreach ($plan['audioTracks'] as $a) {
            $maxEnd = max($maxEnd, (float) $a['startTime'] + (float) $a['durationSeconds']);
        }

        $fromProject = max(0.0, $projectDurationMs / 1000);

        return max(0.1, $maxEnd, $fromProject);
    }

    /**
     * @param  list<string>  $cmd
     */
    private function runProcess(array $cmd, int $deadline, string $stage): void
    {
        $command = $this->shellJoin($cmd);

        $process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes
        );

        if (! is_resource($process)) {
            throw new RuntimeException("Failed to start ffmpeg ({$stage})");
        }
        fclose($pipes[0]);

        $stderr = '';
        $exitCode = -1;
        $timedOut = false;

        while (true) {
            $status = proc_get_status($process);
            $stderr .= (string) stream_get_contents($pipes[2]);

            if (! $status['running']) {
                $exitCode = (int) $status['exitcode'];

                break;
            }
            if (time() > $deadline) {
                proc_terminate($process, 9);
                $timedOut = true;

                break;
            }
            usleep(200000);
        }

        $stderr .= (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        if ($timedOut) {
            throw new RuntimeException("FFmpeg timed out during {$stage}");
        }
        if ($exitCode !== 0) {
            throw new RuntimeException("FFmpeg failed during {$stage}: " . trim($stderr));
        }
    }

    /**
     * Like runProcess() but reads `-progress` output and reports a 0..100
     * stage progress via the callback while ffmpeg runs.
     *
     * @param  list<string>  $cmd
     */
    private function runProcessWithProgress(array $cmd, int $deadline, string $progressFile, int $totalMs, callable $onProgress, string $stage): void
    {
        $command = $this->shellJoin($cmd);

        $process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes
        );

        if (! is_resource($process)) {
            throw new RuntimeException("Failed to start ffmpeg ({$stage})");
        }
        fclose($pipes[0]);

        $stderr = '';
        $exitCode = -1;
        $timedOut = false;
        $lastReported = -1;

        while (true) {
            $status = proc_get_status($process);
            $stderr .= (string) stream_get_contents($pipes[2]);

            if (! $status['running']) {
                $exitCode = (int) $status['exitcode'];

                break;
            }
            if (time() > $deadline) {
                proc_terminate($process, 9);
                $timedOut = true;

                break;
            }

            if (is_file($progressFile)) {
                $p = $this->parseProgress($progressFile, $totalMs);
                if ($p !== $lastReported) {
                    $onProgress($p);
                    $lastReported = $p;
                }
            }
            usleep(400000);
        }

        $stderr .= (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        if (is_file($progressFile)) {
            @unlink($progressFile);
        }

        if ($timedOut) {
            throw new RuntimeException("FFmpeg timed out during {$stage}");
        }
        if ($exitCode !== 0) {
            throw new RuntimeException("FFmpeg failed during {$stage}: " . trim($stderr));
        }
    }

    private function parseProgress(string $file, int $totalMs): int
    {
        $content = @file_get_contents($file);
        if (! $content) {
            return 0;
        }
        preg_match_all('/out_time_ms=(\d+)/', $content, $m);
        if (empty($m[1])) {
            return 0;
        }
        $currentMs = (int) end($m[1]) / 1000;
        if ($totalMs <= 0) {
            return 0;
        }

        return min(99, (int) round(($currentMs / $totalMs) * 100));
    }

    private function updateProgress(VideoEditorExportJob $exportJob, int $progress): void
    {
        $progress = max(0, min(99, $progress));
        if ((int) $exportJob->progress === $progress) {
            return;
        }
        $exportJob->update(['progress' => $progress]);
    }

    /**
     * @param  list<string>  $cmd
     */
    private function shellJoin(array $cmd): string
    {
        return implode(' ', array_map(fn ($a) => escapeshellarg((string) $a), $cmd));
    }

    private function ensureDir(string $dir): void
    {
        if (! $this->tryEnsureDir($dir)) {
            $err = error_get_last()['message'] ?? 'unknown error';

            throw new RuntimeException(sprintf(
                'Cannot prepare writable directory: %s (owner=%s perms=%o err=%s)',
                $dir,
                is_dir($dir) ? (string) @fileowner($dir) : '?',
                is_dir($dir) ? (@fileperms($dir) & 0777) : 0,
                $err,
            ));
        }
    }

    /**
     * Attempt to create $dir if missing and make it writable by the current process.
     * Returns true on success, false otherwise (no exception, no logging).
     */
    private function tryEnsureDir(string $dir): bool
    {
        if ($dir === '') {
            return false;
        }

        if (! is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        if (! is_dir($dir)) {
            return false;
        }

        if (is_writable($dir)) {
            return true;
        }

        // Try to relax perms (only succeeds if we own the dir).
        @chmod($dir, 0777);
        clearstatcache(true, $dir);

        return is_writable($dir);
    }

    /**
     * Resolve a writable cache dir, preferring the configured path. If that
     * path can't be made writable (e.g. /tmp/ve-src is owned by another OS
     * user under Octane vs queue worker), transparently fall back to a
     * per-user subdir under storage/app, then under sys_get_temp_dir().
     */
    private function resolveWritableDir(?string $configured, string $name): string
    {
        $candidates = [];
        if (is_string($configured) && $configured !== '') {
            $candidates[] = rtrim($configured, '/');
        }
        // Per-OS-user fallback inside Laravel's storage (always writable by us).
        $candidates[] = storage_path('app/video-editor-cache/' . $name);
        // Per-OS-user fallback inside system temp (avoids the shared /tmp/ve-* path).
        $uid = function_exists('posix_geteuid') ? @posix_geteuid() : (function_exists('getmyuid') ? getmyuid() : 0);
        $candidates[] = rtrim(sys_get_temp_dir(), '/') . '/' . $name . '-' . $uid;

        foreach ($candidates as $candidate) {
            if ($this->tryEnsureDir($candidate)) {
                return $candidate;
            }
        }

        // Last resort: throw with diagnostics, since nothing usable exists.
        throw new RuntimeException('No writable cache directory available. Tried: ' . implode(', ', $candidates));
    }

    private function removeDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $items = @scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    /**
     * Age- and size-based pruning. Files older than the max age are deleted
     * outright; if the directory is still over the size cap, oldest-atime
     * files are deleted until it fits.
     */
    private function pruneCache(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $maxAge = (int) config('video-editor.export_cache_max_age_seconds', 7 * 86400);
        $maxBytes = (int) config('video-editor.export_cache_max_bytes', 5 * 1024 * 1024 * 1024);
        $now = time();

        $entries = [];
        $items = @scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (! is_file($path)) {
                continue;
            }
            $mtime = @filemtime($path) ?: $now;

            // Stale .partial files from crashed runs are never reusable —
            // remove them eagerly (older than 5 minutes to avoid racing
            // a concurrent download).
            if (str_ends_with($item, '.partial') && ($now - $mtime) > 300) {
                @unlink($path);

                continue;
            }

            $atime = @fileatime($path) ?: $mtime;
            $size = @filesize($path) ?: 0;

            if ($maxAge > 0 && ($now - $atime) > $maxAge) {
                @unlink($path);

                continue;
            }
            $entries[] = ['path' => $path, 'atime' => $atime, 'size' => $size];
        }

        $totalBytes = array_sum(array_column($entries, 'size'));
        if ($totalBytes <= $maxBytes) {
            return;
        }

        usort($entries, fn ($a, $b) => $a['atime'] <=> $b['atime']);
        foreach ($entries as $e) {
            if ($totalBytes <= $maxBytes) {
                break;
            }
            if (@unlink($e['path'])) {
                $totalBytes -= $e['size'];
            }
        }
    }
}
