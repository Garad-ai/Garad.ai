<?php

declare(strict_types=1);

namespace App\Extensions\FashionStudio\System\Jobs;

use App\Domains\Entity\Enums\EntityEnum;
use App\Extensions\FashionStudio\System\Enums\ImageStatusEnum;
use App\Extensions\FashionStudio\System\Models\Background;
use App\Extensions\FashionStudio\System\Models\FashionModel;
use App\Extensions\FashionStudio\System\Models\Pose;
use App\Extensions\FashionStudio\System\Models\Wardrobe;
use App\Extensions\FashionStudio\System\Services\FashionStudioImageModelRegistry;
use App\Models\UserOpenai;
use App\Services\Ai\Images\OpenAIImageService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Asynchronous OpenAI image generation for Fashion Studio.
 *
 * Moves the long-running synchronous OpenAIImageService::generate() call
 * out of the HTTP request to avoid Cloudflare's 100s edge timeout.
 *
 * One job dispatch per generation request. Loops $numImages times to
 * mirror the FAL multi-image behavior (gpt-image returns one image
 * per call). For UserOpenai records with numImages > 1, additional
 * images create replicated UserOpenai rows just like CheckFalAIGenerationJob.
 */
class GenerateOpenAIImageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Single attempt — OpenAI calls are cost-sensitive and either succeed
     * or fail. We do not want to silently double-charge on transient errors.
     */
    public int $tries = 1;

    /**
     * Long enough for slow gpt-image responses on multi-image generations.
     */
    public int $timeout = 600;

    /**
     * Delete the job if its models no longer exist.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Supported model types and their configurations.
     *
     * Mirrors CheckFalAIGenerationJob::MODEL_CONFIGS — keep in sync if either
     * job grows new model types. Duplicated rather than extracted because
     * the only shared fields here are `class` and `output`.
     */
    private const MODEL_CONFIGS = [
        'user_openai' => [
            'class'  => UserOpenai::class,
            'output' => 'output',
        ],
        'pose' => [
            'class'  => Pose::class,
            'output' => 'image_url',
        ],
        'wardrobe' => [
            'class'  => Wardrobe::class,
            'output' => 'image_url',
        ],
        'fashion_model' => [
            'class'  => FashionModel::class,
            'output' => 'image_url',
        ],
        'background' => [
            'class'  => Background::class,
            'output' => 'image_url',
        ],
    ];

    /**
     * @param  int  $recordId  Database id of the record to update on completion
     * @param  string  $modelType  One of MODEL_CONFIGS keys
     * @param  string  $modelValue  EntityEnum value (e.g. 'gpt-image-1')
     * @param  string  $prompt  Prompt to send to OpenAI
     * @param  array  $imageUrls  Optional reference images for image-edit calls
     * @param  int  $numImages  How many images to generate (loops the API call)
     * @param  ?string  $sizeOverride  Optional user-derived OpenAI size (e.g. '1024x1536').
     *                                 When present, this is used instead of the admin
     *                                 default from FashionStudioImageModelRegistry.
     */
    public function __construct(
        public int $recordId,
        public string $modelType,
        public string $modelValue,
        public string $prompt,
        public array $imageUrls = [],
        public int $numImages = 1,
        public ?string $sizeOverride = null
    ) {}

    public function handle(): void
    {
        $record = $this->getRecord();

        if (! $record) {
            Log::warning('GenerateOpenAIImageJob: Record not found', [
                'record_id'  => $this->recordId,
                'model_type' => $this->modelType,
            ]);

            return;
        }

        // Idempotency: skip if a previous run already finished this record.
        if (in_array($record->status, [ImageStatusEnum::completed->value, ImageStatusEnum::failed->value], true)) {
            return;
        }

        $model = EntityEnum::tryFrom($this->modelValue);

        if ($model === null) {
            Log::error('GenerateOpenAIImageJob: Invalid model value', [
                'record_id'   => $this->recordId,
                'model_type'  => $this->modelType,
                'model_value' => $this->modelValue,
            ]);
            $record->update(['status' => ImageStatusEnum::failed->value]);

            return;
        }

        try {
            $service = app(OpenAIImageService::class);

            $options = [
                'model'        => $this->modelValue,
                'prompt'       => $this->prompt,
                'quality'      => FashionStudioImageModelRegistry::getOpenAIQuality(),
                'aspect_ratio' => $this->sizeOverride ?? FashionStudioImageModelRegistry::getOpenAISize(),
            ];

            if (! empty($this->imageUrls)) {
                $options['image_reference'] = $this->imageUrls;
            }

            $numImages = max(1, $this->numImages);

            // First image is written into the original record.
            $firstPath = $this->generateSingleImage($service, $options, $record->user_id);

            if (! $firstPath) {
                $record->update(['status' => ImageStatusEnum::failed->value]);

                return;
            }

            $outputField = $this->getOutputField();

            // Generate any remaining images BEFORE marking the primary record
            // completed — otherwise the frontend's status polling sees
            // `completed` after image #1 and stops polling before the
            // additional records are created.
            $extraPaths = [];

            if ($this->modelType === 'user_openai' && $numImages > 1) {
                for ($i = 1; $i < $numImages; $i++) {
                    $extraPath = $this->generateSingleImage($service, $options, $record->user_id);

                    if ($extraPath) {
                        $extraPaths[] = $extraPath;
                    }
                }

                if (! empty($extraPaths)) {
                    $this->createAdditionalImageRecords($record, $extraPaths);
                }
            }

            // Mark the primary record completed last so polling picks up all
            // sibling records in a single status response.
            $record->update([
                'status'     => ImageStatusEnum::completed->value,
                $outputField => $firstPath,
            ]);
        } catch (Throwable $e) {
            Log::error('GenerateOpenAIImageJob: Generation failed', [
                'record_id'  => $this->recordId,
                'model_type' => $this->modelType,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);

            $record->update(['status' => ImageStatusEnum::failed->value]);
            // Do not rethrow: tries=1 and we've already marked the record failed.
        }
    }

    /**
     * Safety net — if the worker kills the job (e.g. timeout, OOM) the
     * exception path above won't run, so ensure the record is marked failed.
     */
    public function failed(?Throwable $exception): void
    {
        $record = $this->getRecord();

        if ($record && $record->status !== ImageStatusEnum::completed->value) {
            $record->update(['status' => ImageStatusEnum::failed->value]);
        }

        Log::error('GenerateOpenAIImageJob: Job failed permanently', [
            'record_id'  => $this->recordId,
            'model_type' => $this->modelType,
            'error'      => $exception?->getMessage(),
        ]);
    }

    /**
     * Resolve the record by configured model class.
     */
    protected function getRecord(): ?Model
    {
        $config = self::MODEL_CONFIGS[$this->modelType] ?? null;

        if (! $config) {
            return null;
        }

        return $config['class']::find($this->recordId);
    }

    /**
     * Output field name for the configured model type.
     */
    protected function getOutputField(): string
    {
        $config = self::MODEL_CONFIGS[$this->modelType] ?? null;

        return $config['output'] ?? 'output';
    }

    /**
     * Run a single OpenAIImageService call and persist the binary output.
     * Returns the public-uploads relative path or null on failure.
     */
    protected function generateSingleImage(OpenAIImageService $service, array $options, ?int $userId): ?string
    {
        try {
            $images = $service->generate($options);

            if (empty($images) || ! is_string($images[0])) {
                return null;
            }

            $fileName = Str::uuid()->toString() . '.png';
            $path = 'media/images/u-' . ($userId ?? 0) . '/' . $fileName;

            Storage::disk('public')->put($path, $images[0]);

            return '/uploads/' . $path;
        } catch (Exception $e) {
            Log::error('GenerateOpenAIImageJob: OpenAI image generation failed', [
                'record_id'  => $this->recordId,
                'model_type' => $this->modelType,
                'error'      => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Replicate the original UserOpenai record once per additional image.
     * Mirrors CheckFalAIGenerationJob::createAdditionalImageRecords exactly.
     */
    protected function createAdditionalImageRecords(Model $record, array $additionalPaths): void
    {
        foreach ($additionalPaths as $index => $localPath) {
            $imageNumber = $index + 2;

            $newRecord = $record->replicate();
            $newRecord->title = $record->title . " #{$imageNumber}";
            $newRecord->slug = str()->random(7) . '-' . $record->slug . "-{$imageNumber}";
            $newRecord->hash = str()->random(256);
            $newRecord->status = ImageStatusEnum::completed->value;
            $newRecord->output = $localPath;
            $newRecord->created_at = now();
            $newRecord->updated_at = now();
            $newRecord->save();
        }
    }
}
