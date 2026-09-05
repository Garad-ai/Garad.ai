<?php

declare(strict_types=1);

namespace App\Extensions\UGCFactory\System\Jobs;

use App\Domains\Engine\Services\FalAIService;
use App\Domains\Entity\Enums\EntityEnum;
use App\Extensions\UGCFactory\System\Models\UGCFactoryActor;
use App\Extensions\UGCFactory\System\Services\UGCFactoryRegistry;
use App\Services\Ai\OpenAI\Image\CreateImageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Submits a UGC actor prompt to the configured image model.
 *
 * Branches on provider:
 * - "openai" (gpt-image-*) runs synchronously via CreateImageService and
 *   writes the actor to ready inside this job.
 * - "fal"  (nano-banana, grok-imagine, etc.) submits to fal.ai's queue and
 *   stores the request id. The frontend polls the actor status endpoint
 *   to finalize — see FinalizeUGCActor.
 *
 * Either way the user-supplied prompt is wrapped with the configured
 * UGC actor system prompt before submission.
 */
class GenerateUGCActorJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public int $actorId) {}

    public function handle(): void
    {
        $actor = UGCFactoryActor::find($this->actorId);

        if (! $actor || $actor->status === UGCFactoryActor::STATUS_READY) {
            return;
        }

        $modelKey = UGCFactoryRegistry::getActorImageModel();
        $entity = UGCFactoryRegistry::getActorImageModelEnum();
        $provider = UGCFactoryRegistry::getActorImageProvider();
        $finalPrompt = UGCFactoryRegistry::buildActorPrompt((string) $actor->prompt);

        try {
            if ($provider === 'openai') {
                $this->generateViaOpenAI($actor, $modelKey, $finalPrompt);

                return;
            }

            $this->generateViaFal($actor, $entity, $finalPrompt);
        } catch (Throwable $e) {
            $actor->update([
                'status' => UGCFactoryActor::STATUS_FAILED,
                'error'  => $e->getMessage(),
            ]);
        }
    }

    private function generateViaOpenAI(UGCFactoryActor $actor, string $modelKey, string $prompt): void
    {
        $service = (new CreateImageService)
            ->setModel($modelKey)
            ->setPrompt($prompt)
            ->setSize('1024x1536') // portrait, supported by gpt-image-*
            ->setQuality('high')
            ->setOutputFormat('webp');

        $base64 = $service->generateForAi();

        if (! $base64) {
            $actor->update([
                'status' => UGCFactoryActor::STATUS_FAILED,
                'error'  => __('OpenAI image generation failed.'),
            ]);

            return;
        }

        $path = 'ugc-factory/actors/u-' . $actor->user_id . '/' . Str::uuid()->toString() . '.webp';
        Storage::disk('public')->put($path, base64_decode($base64));

        $actor->update([
            'status'     => UGCFactoryActor::STATUS_READY,
            'image_path' => $path,
            'error'      => null,
        ]);
    }

    private function generateViaFal(UGCFactoryActor $actor, EntityEnum $entity, string $prompt): void
    {
        $requestId = FalAIService::generate(
            $prompt,
            $entity,
            ['aspect_ratio' => 'portrait_16_9'],
        );

        $actor->update([
            'fal_request_id' => $requestId,
            'fal_entity'     => $entity->value,
        ]);
    }
}
