<?php

declare(strict_types=1);

namespace App\Extensions\UGCCreator\System\Jobs;

use App\Extensions\UGCCreator\System\Models\UGCCreatorVideo;
use App\Extensions\UGCCreator\System\Services\FalVideoService;
use App\Extensions\UGCCreator\System\Services\UGCCreatorRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Throwable;

/**
 * Submits a UGCCreatorVideo to the selected fal.ai image-to-video endpoint.
 *
 * Every UGC Creator model is image-to-video — we resolve the user's product
 * or character reference URL, build a payload, and queue a fal.ai request.
 * The frontend polls the video status endpoint to finalize the result via
 * FinalizeUGCCreatorVideo.
 */
class ProcessUGCCreatorVideoJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public int $videoId) {}

    public function handle(): void
    {
        $video = UGCCreatorVideo::find($this->videoId);

        if (! $video || $video->status === UGCCreatorVideo::STATUS_COMPLETED) {
            return;
        }

        try {
            $video->update(['status' => UGCCreatorVideo::STATUS_PROCESSING]);

            $endpoint = UGCCreatorRegistry::getModelEndpoint((string) $video->model);

            if (! $endpoint) {
                throw new RuntimeException(__('Unsupported video model.'));
            }

            $snapshot = $this->resolveSnapshot($video);
            $video->update(['asset_snapshot' => $snapshot]);

            $payload = $this->buildPayload($video, $snapshot);
            $submission = FalVideoService::submit($endpoint, $payload);

            $video->update([
                'fal_request_id'   => $submission['request_id'],
                'fal_response_url' => $submission['response_url'],
                'fal_status_url'   => $submission['status_url'],
            ]);
        } catch (Throwable $e) {
            $video->update([
                'status' => UGCCreatorVideo::STATUS_FAILED,
                'error'  => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<int, array{kind: string, asset_id: int, image_path: string, image_url_resolved: string}>
     */
    private function resolveSnapshot(UGCCreatorVideo $video): array
    {
        $snapshot = is_array($video->asset_snapshot) ? $video->asset_snapshot : [];
        $out = [];

        foreach ($snapshot as $entry) {
            $path = $entry['image_path'] ?? null;
            if (! is_string($path) || $path === '') {
                continue;
            }
            $out[] = [
                'kind'               => (string) ($entry['kind'] ?? ''),
                'asset_id'           => (int) ($entry['asset_id'] ?? 0),
                'image_path'         => $path,
                'image_url_resolved' => URL::to('/uploads/' . ltrim($path, '/')),
            ];
        }

        return $out;
    }

    /**
     * @param  array<int, array{kind: string, asset_id: int, image_path: string, image_url_resolved: string}>  $snapshot
     *
     * @return array<string, mixed>
     */
    private function buildPayload(UGCCreatorVideo $video, array $snapshot): array
    {
        $references = $this->collectReferences($snapshot);
        if ($references === []) {
            $fallback = $this->cameraPresetFallback($video);
            if ($fallback) {
                $references = [$fallback];
            }
        }

        if ($references === []) {
            throw new RuntimeException(__('Could not resolve a reference image for video generation.'));
        }

        $maxRefs = UGCCreatorRegistry::getModelMaxRefs((string) $video->model);
        if ($maxRefs > 0) {
            $references = array_slice($references, 0, $maxRefs);
        }

        $shape = UGCCreatorRegistry::getModelPayloadShape((string) $video->model);

        $payload = [
            'prompt'       => (string) $video->final_prompt,
            'aspect_ratio' => '9:16',
        ];

        $payload += $this->mapReferencesToPayload($shape, $references);

        // Explicitly send true/false for audio-capable models. Veo defaults
        // audio to ON, so just omitting the field doesn't disable it.
        if (UGCCreatorRegistry::modelSupportsAudio((string) $video->model)) {
            $payload['generate_audio'] = (bool) $video->audio_enabled;
        }

        return $payload;
    }

    /**
     * Map collected reference URLs into the field names the chosen fal endpoint expects.
     *
     * @param  array<int, string>  $references
     *
     * @return array<string, mixed>
     */
    private function mapReferencesToPayload(string $shape, array $references): array
    {
        if ($references === []) {
            return [];
        }

        // end_image_url is optional on Kling v3 / Seedance ITV — array_filter
        // drops the slot when only one reference is available.
        return match ($shape) {
            'image_only' => ['image_url' => $references[0]],
            'start_end'  => array_filter([
                'start_image_url' => $references[0] ?? null,
                'end_image_url'   => $references[1] ?? null,
            ]),
            'image_end' => array_filter([
                'image_url'     => $references[0] ?? null,
                'end_image_url' => $references[1] ?? null,
            ]),
            default => ['image_urls' => $references],
        };
    }

    /**
     * @param  array<int, array{kind: string, asset_id: int, image_path: string, image_url_resolved: string}>  $snapshot
     *
     * @return array<int, string>
     */
    private function collectReferences(array $snapshot): array
    {
        $products = [];
        $characters = [];

        foreach ($snapshot as $entry) {
            $url = $entry['image_url_resolved'] ?? null;
            if (! is_string($url) || $url === '') {
                continue;
            }
            if (($entry['kind'] ?? '') === 'product') {
                $products[] = $url;
            } else {
                $characters[] = $url;
            }
        }

        return array_values(array_merge($characters, $products));
    }

    /**
     * Fall back to the selected camera preset's image when no user reference
     * was attached. Both product and character images are optional in the UI;
     * fal.ai image-to-video models still need *some* image_url, so the preset
     * thumbnail acts as a safe default.
     */
    private function cameraPresetFallback(UGCCreatorVideo $video): ?string
    {
        $presetKey = trim((string) $video->camera_preset);

        // 'auto' is the "no specific style" choice — its thumbnail is a generic
        // placeholder that fal.ai can't meaningfully use as an anchor frame.
        if ($presetKey === '' || $presetKey === 'auto') {
            return null;
        }

        $path = UGCCreatorRegistry::getCameraPresetImagePath($presetKey);

        if (! $path) {
            return null;
        }

        return URL::to(ltrim($path, '/'));
    }
}
