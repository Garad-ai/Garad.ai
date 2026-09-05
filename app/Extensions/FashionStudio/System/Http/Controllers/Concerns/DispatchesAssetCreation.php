<?php

declare(strict_types=1);

namespace App\Extensions\FashionStudio\System\Http\Controllers\Concerns;

use App\Domains\Entity\Enums\EntityEnum;
use App\Extensions\FashionStudio\System\Enums\ImageStatusEnum;
use App\Extensions\FashionStudio\System\Jobs\CheckFalAIGenerationJob;
use App\Extensions\FashionStudio\System\Jobs\GenerateOpenAIImageJob;
use App\Extensions\FashionStudio\System\Services\FashionStudioFalAIService;
use App\Extensions\FashionStudio\System\Services\FashionStudioImageModelRegistry;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Shared logic for the four Fashion Studio asset-creator flows
 * (PoseController, WardrobeController, FashionModelController, BackgroundController).
 *
 * Resolves the admin-configured image model and routes generation to either
 * FAL (async, polled by CheckFalAIGenerationJob) or OpenAI (synchronous).
 */
trait DispatchesAssetCreation
{
    /**
     * Resolve the runtime image model for an asset-creator flow.
     *
     * Asset creators always start from text (no reference images), so the
     * /edit-variant swap is a no-op here, but we still go through
     * resolveForRequest() for consistency.
     */
    protected function resolveAssetCreationModel(): EntityEnum
    {
        $base = FashionStudioImageModelRegistry::getDefaultModel();

        return FashionStudioImageModelRegistry::resolveForRequest($base, false);
    }

    /**
     * Run generation against the resolved provider and update the asset record.
     *
     * For FAL: fires async request, stores request_id in `generation_uuid`,
     * sets status to processing, dispatches CheckFalAIGenerationJob.
     *
     * For OpenAI: calls the service synchronously, persists the returned
     * binary to public storage, sets status to completed and image_url.
     *
     * On failure the record is marked failed and the exception is re-thrown
     * so the calling controller can return its existing JSON error shape.
     *
     * @param  Model  $record  Asset model (Pose, Wardrobe, FashionModel, Background)
     * @param  string  $modelType  CheckFalAIGenerationJob model type key
     */
    protected function dispatchAssetGeneration(
        Model $record,
        string $prompt,
        string $modelType,
        EntityEnum $model
    ): void {
        try {
            $provider = FashionStudioImageModelRegistry::getProviderFor($model);

            if ($provider === FashionStudioImageModelRegistry::PROVIDER_OPENAI) {
                $this->runOpenAIAssetGeneration($record, $prompt, $modelType, $model);

                return;
            }

            $this->runFalAssetGeneration($record, $prompt, $modelType, $model);
        } catch (Exception $e) {
            Log::error('Fashion Studio asset generation failed', [
                'record_id'  => $record->id,
                'model_type' => $modelType,
                'error'      => $e->getMessage(),
            ]);

            $record->update(['status' => ImageStatusEnum::failed->value]);

            throw $e;
        }
    }

    /**
     * Async FAL flow: enqueue request, set processing, dispatch polling job.
     */
    protected function runFalAssetGeneration(
        Model $record,
        string $prompt,
        string $modelType,
        EntityEnum $model
    ): void {
        $requestId = FashionStudioFalAIService::generateFromText($prompt, [], $model);

        $record->update([
            'status'          => ImageStatusEnum::processing->value,
            'generation_uuid' => $requestId,
        ]);

        CheckFalAIGenerationJob::dispatch($record->id, 'image', $modelType)->delay(now()->addSeconds(5));
    }

    /**
     * Async OpenAI flow: enqueue the generation job and return immediately.
     *
     * Avoids Cloudflare's 100s edge timeout for slow gpt-image generations.
     * Asset creators are text-only, so no image references are passed.
     */
    protected function runOpenAIAssetGeneration(
        Model $record,
        string $prompt,
        string $modelType,
        EntityEnum $model
    ): void {
        $record->update(['status' => ImageStatusEnum::processing->value]);

        GenerateOpenAIImageJob::dispatch(
            $record->id,
            $modelType,
            $model->value,
            $prompt,
            [],
            1
        )->delay(now()->addSeconds(2));
    }
}
