<?php

declare(strict_types=1);

namespace App\Extensions\UGCFactory\System\Jobs;

use App\Extensions\UGCFactory\System\Actions\GenerateVoiceover;
use App\Extensions\UGCFactory\System\Models\UGCFactoryActor;
use App\Extensions\UGCFactory\System\Models\UGCFactoryVideo;
use App\Extensions\UGCFactory\System\Services\UGCFactoryRegistry;
use App\Extensions\UGCFactory\System\Services\VeedFabricService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Throwable;

/**
 * Submits a UGCFactoryVideo to fal.ai's VEED Fabric endpoint.
 *
 * 1. Generates a voiceover via GenerateVoiceover when audio_source = 'tts'.
 * 2. Resolves public URLs for image + audio (must be reachable by fal.ai —
 *    APP_URL has to be a real URL in production).
 * 3. Submits the request and stores fal_request_id. The frontend polls the
 *    video status endpoint to finalize — see FinalizeUGCVideo. This mirrors
 *    the CreativeSuiteAITemplate pattern, which is queue-driver agnostic
 *    (works under sync, database, or redis without changes).
 */
class ProcessUGCVideoJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public int $videoId) {}

    public function handle(GenerateVoiceover $generator): void
    {
        $video = UGCFactoryVideo::find($this->videoId);

        if (! $video || $video->status === UGCFactoryVideo::STATUS_COMPLETED) {
            return;
        }

        try {
            $video->update(['status' => UGCFactoryVideo::STATUS_PROCESSING]);

            if ($video->audio_source === UGCFactoryVideo::SOURCE_TTS && ! $video->audio_path) {
                $result = ($generator)(
                    (string) $video->voice_provider,
                    (string) $video->voice_id,
                    (string) $video->script,
                    $video->voice_language,
                );
                $video->update(['audio_path' => $result['path']]);
            }

            $imageUrl = $this->resolveImageUrl($video);
            $audioUrl = $this->resolveAudioUrl($video);

            if (! $imageUrl || ! $audioUrl) {
                throw new RuntimeException(__('Could not resolve actor image or audio URL.'));
            }

            $requestId = VeedFabricService::submit($imageUrl, $audioUrl, $video->resolution ?: UGCFactoryRegistry::getResolution());

            $video->update([
                'fal_request_id'     => $requestId,
                'image_url_resolved' => $imageUrl,
                'audio_url_resolved' => $audioUrl,
            ]);
        } catch (Throwable $e) {
            $video->update([
                'status' => UGCFactoryVideo::STATUS_FAILED,
                'error'  => $e->getMessage(),
            ]);
        }
    }

    private function resolveImageUrl(UGCFactoryVideo $video): ?string
    {
        $snapshot = $video->actor_snapshot ?? [];
        $type = $snapshot['type'] ?? null;
        $presetKey = $snapshot['preset_key'] ?? null;
        $path = $snapshot['image_path'] ?? null;

        if ($type === UGCFactoryActor::TYPE_PRESET && $presetKey) {
            return URL::to('/vendor/ugc-factory/images/actors/' . $presetKey . '.webp');
        }

        if ($path) {
            return URL::to('/uploads/' . ltrim($path, '/'));
        }

        return null;
    }

    private function resolveAudioUrl(UGCFactoryVideo $video): ?string
    {
        if (! $video->audio_path) {
            return null;
        }

        return URL::to('/uploads/' . ltrim($video->audio_path, '/'));
    }
}
