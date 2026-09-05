<?php

declare(strict_types=1);

namespace App\Extensions\VideoDubbing\System\Services;

use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity;
use App\Extensions\VideoDubbing\System\Jobs\DownloadDubbedVideoJob;
use App\Extensions\VideoDubbing\System\Models\VideoDubbing;
use getID3;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class VideoDubbingService
{
    private HeygenDubbingService $heygenService;

    private ElevenLabsDubbingService $elevenLabsService;

    public function __construct()
    {
        $this->heygenService = new HeygenDubbingService;
        $this->elevenLabsService = new ElevenLabsDubbingService;
    }

    public function getDefaultProvider(): string
    {
        return setting('video_dubbing_default_provider', 'elevenlabs');
    }

    /**
     * Store an uploaded file once and return the relative path on the uploads disk.
     */
    public function storeUploadedFile(UploadedFile $file): string
    {
        return $this->storeFile($file);
    }

    /**
     * Estimate the duration (in seconds) of an uploaded video file. Returns null when undetectable.
     */
    public function estimateUploadedDuration(?string $storedFilePath): ?int
    {
        if (! $storedFilePath) {
            return null;
        }

        $absolutePath = Storage::disk('uploads')->path($storedFilePath);

        if (! is_file($absolutePath)) {
            return null;
        }

        try {
            $info = (new getID3)->analyze($absolutePath);
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
     * Create a single VideoDubbing record in 'pending' status without submitting to the provider.
     */
    public function createPendingDubbing(array $data, string $targetLanguage, ?string $storedFilePath, ?int $estimatedSeconds, ?string $originalFileName = null, ?string $mimeType = null): VideoDubbing
    {
        $provider = $this->getDefaultProvider();

        $metadata = [];

        if ($storedFilePath && $originalFileName) {
            $metadata['original_file_name'] = $originalFileName;
        }

        if ($storedFilePath && $mimeType) {
            $metadata['mime_type'] = $mimeType;
        }

        return VideoDubbing::create([
            'user_id'               => auth()->id(),
            'provider'              => $provider,
            'source_type'           => $data['source_type'] ?? 'upload',
            'source_url'            => $data['video_url'] ?? null,
            'source_file_path'      => $storedFilePath,
            'target_language'       => $targetLanguage,
            'source_language'       => $data['source_language'] ?? 'auto',
            'speaker_num'           => $data['speaker_num'] ?? null,
            'title'                 => $data['title'] ?? null,
            'mode'                  => $data['mode'] ?? null,
            'translate_audio_only'  => $data['translate_audio_only'] ?? false,
            'highest_resolution'    => $data['highest_resolution'] ?? false,
            'drop_background_audio' => $data['drop_background_audio'] ?? false,
            'use_profanity_filter'  => $data['use_profanity_filter'] ?? false,
            'duration_seconds'      => $estimatedSeconds,
            'status'                => 'pending',
            'metadata'              => $metadata ?: null,
        ]);
    }

    /**
     * Submit a pending dubbing record to its provider.
     *
     * @return array{success: bool, error?: string, dubbing?: VideoDubbing}
     */
    public function submitDubbing(VideoDubbing $dubbing): array
    {
        $sourceUrl = $dubbing->source_url
            ?: ($dubbing->source_file_path ? Storage::disk('uploads')->url($dubbing->source_file_path) : null);

        if ($dubbing->provider === 'heygen') {
            if (! $sourceUrl) {
                $dubbing->update([
                    'status'        => 'error',
                    'error_message' => __('Please provide a video URL to continue.'),
                ]);

                return ['success' => false, 'error' => __('Please provide a video URL to continue.'), 'dubbing' => $dubbing];
            }

            return $this->submitToHeygen($dubbing, $sourceUrl);
        }

        return $this->submitToElevenLabs($dubbing, $sourceUrl);
    }

    /**
     * @return array{success: bool, error?: string, dubbing?: VideoDubbing}
     */
    private function submitToHeygen(VideoDubbing $dubbing, string $sourceUrl): array
    {
        if ($dubbing->request_id) {
            return ['success' => true, 'dubbing' => $dubbing];
        }

        $languageName = config('videodubbing.heygen.languages.' . $dubbing->target_language, $dubbing->target_language);

        $params = [
            'video'            => ['type' => 'url', 'url' => $sourceUrl],
            'output_languages' => [$languageName],
            'mode'             => $this->mapHeygenMode($dubbing->mode),
        ];

        if ($dubbing->title) {
            $params['title'] = $dubbing->title;
        }

        if ($dubbing->speaker_num) {
            $params['speaker_num'] = $dubbing->speaker_num;
        }

        if ($dubbing->translate_audio_only) {
            $params['translate_audio_only'] = true;
        }

        $result = $this->heygenService->createTranslation($params);

        if (isset($result['error'])) {
            VideoDubbing::query()
                ->whereKey($dubbing->id)
                ->whereNull('request_id')
                ->where('status', 'pending')
                ->update([
                    'status'        => 'error',
                    'error_message' => $result['error'],
                ]);

            return ['success' => false, 'error' => $result['error'], 'dubbing' => $dubbing->fresh()];
        }

        $translateId = $result['data']['video_translation_id']
            ?? $result['data']['video_translate_id']
            ?? (is_array($result['data']['video_translation_ids'] ?? null) ? ($result['data']['video_translation_ids'][0] ?? null) : null);

        if (! $translateId) {
            VideoDubbing::query()
                ->whereKey($dubbing->id)
                ->whereNull('request_id')
                ->where('status', 'pending')
                ->update([
                    'status'        => 'error',
                    'error_message' => __('Something went wrong while starting the video translation. Please try again.'),
                ]);

            return ['success' => false, 'error' => __('Something went wrong while starting the video translation. Please try again.'), 'dubbing' => $dubbing->fresh()];
        }

        VideoDubbing::query()
            ->whereKey($dubbing->id)
            ->where('status', 'pending')
            ->update([
                'request_id' => $translateId,
                'status'     => 'running',
            ]);

        return ['success' => true, 'dubbing' => $dubbing->fresh()];
    }

    /**
     * @return array{success: bool, error?: string, dubbing?: VideoDubbing}
     */
    private function submitToElevenLabs(VideoDubbing $dubbing, ?string $sourceUrl): array
    {
        $params = [
            'target_lang' => $dubbing->target_language,
            'source_lang' => $dubbing->source_language ?? 'auto',
            'name'        => $dubbing->title ?? 'Dubbing ' . $dubbing->id,
        ];

        if ($dubbing->speaker_num) {
            $params['num_speakers'] = $dubbing->speaker_num;
        }

        if ($dubbing->highest_resolution) {
            $params['highest_resolution'] = true;
        }

        if ($dubbing->drop_background_audio) {
            $params['drop_background_audio'] = true;
        }

        if ($dubbing->use_profanity_filter) {
            $params['use_profanity_filter'] = true;
        }

        if ($dubbing->source_file_path) {
            $absolutePath = Storage::disk('uploads')->path($dubbing->source_file_path);

            if (! is_file($absolutePath)) {
                $dubbing->update([
                    'status'        => 'error',
                    'error_message' => __('The uploaded video could not be found.'),
                ]);

                return ['success' => false, 'error' => __('The uploaded video could not be found.'), 'dubbing' => $dubbing];
            }

            $metadata = $dubbing->metadata ?? [];
            $params['file_path'] = $absolutePath;
            $params['file_name'] = $metadata['original_file_name'] ?? basename($absolutePath);
            $params['file_mime'] = $metadata['mime_type'] ?? (mime_content_type($absolutePath) ?: 'video/mp4');
        } elseif ($sourceUrl) {
            $params['source_url'] = $sourceUrl;
        } else {
            $dubbing->update([
                'status'        => 'error',
                'error_message' => __('Please provide a video URL or upload a file to continue.'),
            ]);

            return ['success' => false, 'error' => __('Please provide a video URL or upload a file to continue.'), 'dubbing' => $dubbing];
        }

        $result = $this->elevenLabsService->createDubbing($params);

        if (isset($result['error'])) {
            if (! empty($result['retryable'])) {
                // Leave the dubbing in pending state; the job will retry.
                return ['success' => false, 'error' => $result['error'], 'retryable' => true, 'dubbing' => $dubbing];
            }

            $dubbing->update([
                'status'        => 'error',
                'error_message' => $result['error'],
            ]);

            return ['success' => false, 'error' => $result['error'], 'dubbing' => $dubbing];
        }

        $dubbingId = $result['dubbing_id'] ?? null;

        if (! $dubbingId) {
            $dubbing->update([
                'status'        => 'error',
                'error_message' => __('Something went wrong while starting the video dubbing. Please try again.'),
            ]);

            return ['success' => false, 'error' => __('Something went wrong while starting the video dubbing. Please try again.'), 'dubbing' => $dubbing];
        }

        $dubbing->update([
            'request_id' => $dubbingId,
            'status'     => 'running',
        ]);

        return ['success' => true, 'dubbing' => $dubbing];
    }

    /**
     * Check the status of a dubbing job and update the model.
     */
    public function checkStatus(VideoDubbing $dubbing): VideoDubbing
    {
        if (! $dubbing->isPending() || ! $dubbing->request_id) {
            return $dubbing;
        }

        if ($dubbing->provider === 'heygen') {
            return $this->checkHeygenStatus($dubbing);
        }

        return $this->checkElevenLabsStatus($dubbing);
    }

    private function checkHeygenStatus(VideoDubbing $dubbing): VideoDubbing
    {
        $result = $this->heygenService->getTranslationStatus($dubbing->request_id);

        if (isset($result['error'])) {
            VideoDubbing::query()
                ->whereKey($dubbing->id)
                ->whereIn('status', ['pending', 'running'])
                ->update([
                    'status'        => 'error',
                    'error_message' => $result['error'],
                ]);

            return $dubbing->fresh() ?? $dubbing;
        }

        $data = $result['data'] ?? $result;
        $status = $data['status'] ?? null;
        $videoUrl = $data['video_url'] ?? $data['url'] ?? null;
        $captionUrl = $data['caption_url'] ?? null;

        if (in_array($status, ['completed', 'success'], true)) {
            $updated = VideoDubbing::query()
                ->whereKey($dubbing->id)
                ->whereIn('status', ['pending', 'running'])
                ->update([
                    'status'      => 'complete',
                    'output_url'  => $videoUrl,
                    'caption_url' => $captionUrl,
                ]);

            if ($updated) {
                DownloadDubbedVideoJob::dispatch($dubbing->id);
                $this->deductSharedCredits($dubbing->fresh() ?? $dubbing);
            }
        } elseif (in_array($status, ['failed', 'error'], true)) {
            VideoDubbing::query()
                ->whereKey($dubbing->id)
                ->whereIn('status', ['pending', 'running'])
                ->update([
                    'status'        => 'error',
                    'error_message' => $data['error_message'] ?? $data['message'] ?? __('Video translation failed. Please try again with a different video or settings.'),
                ]);
        }

        return $dubbing->fresh();
    }

    private function mapHeygenMode(?string $mode): string
    {
        return match ($mode) {
            'precision', 'precise', 'quality' => 'precision',
            default                           => 'speed',
        };
    }

    private function checkElevenLabsStatus(VideoDubbing $dubbing): VideoDubbing
    {
        $result = $this->elevenLabsService->getDubbingStatus($dubbing->request_id);

        if (isset($result['error'])) {
            $dubbing->update([
                'status'        => 'error',
                'error_message' => $result['error'],
            ]);

            return $dubbing->fresh() ?? $dubbing;
        }

        $status = $result['status'] ?? null;

        if ($status === 'dubbed') {
            $downloadResult = $this->elevenLabsService->downloadDubbedFile(
                $dubbing->request_id,
                $dubbing->target_language
            );

            if (isset($downloadResult['error'])) {
                $dubbing->update([
                    'status'        => 'error',
                    'error_message' => $downloadResult['error'],
                ]);

                return $dubbing->fresh() ?? $dubbing;
            }

            $extension = str_contains($downloadResult['content_type'] ?? '', 'video') ? 'mp4' : 'mp3';
            $relativePath = "media/videos/u-{$dubbing->user_id}/dubbed_" . Str::random(40) . '.' . $extension;
            $absolutePath = Storage::disk('uploads')->path($relativePath);

            if (! is_dir(dirname($absolutePath))) {
                mkdir(dirname($absolutePath), 0755, true);
            }

            file_put_contents($absolutePath, $downloadResult['content']);

            $dubbing->update([
                'status'           => 'complete',
                'output_url'       => Storage::disk('uploads')->url($relativePath),
                'duration_seconds' => $result['media_metadata']['duration'] ?? $dubbing->duration_seconds,
            ]);

            $this->deductSharedCredits($dubbing->fresh() ?? $dubbing);
        } elseif ($status === 'failed') {
            $dubbing->update([
                'status'        => 'error',
                'error_message' => __('Video dubbing failed. Please try again with a different video or settings.'),
            ]);
        }

        return $dubbing->fresh();
    }

    private function storeFile(UploadedFile $file): string
    {
        $userId = auth()->id();
        $relativePath = "media/videos/u-{$userId}/" . Str::random(40) . '.' . ($file->guessExtension() ?? $file->getClientOriginalExtension());
        $absolutePath = Storage::disk('uploads')->path($relativePath);

        if (! is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0755, true);
        }

        $file->move(dirname($absolutePath), basename($absolutePath));

        return $relativePath;
    }

    /**
     * @return array<string, string>
     */
    public function getLanguagesForProvider(?string $provider = null): array
    {
        $provider = $provider ?? $this->getDefaultProvider();

        return config("videodubbing.{$provider}.languages", []);
    }

    private function deductSharedCredits(VideoDubbing $dubbing): void
    {
        try {
            Entity::driver(EntityEnum::VIDEO_DUBBING)
                ->forUser($dubbing->user_id)
                ->inputSecond($dubbing->duration_seconds ?? 1)
                ->calculateCredit()
                ->decreaseCredit(1.0);
        } catch (Throwable $e) {
            Log::warning('VideoDubbing: SharedCredit deduction failed', [
                'id'    => $dubbing->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
