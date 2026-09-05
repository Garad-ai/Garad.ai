<?php

declare(strict_types=1);

namespace App\Extensions\FashionStudio\System\Jobs;

use App\Domains\Entity\Enums\EntityEnum;
use App\Extensions\FashionStudio\System\Enums\ImageStatusEnum;
use App\Extensions\FashionStudio\System\Models\Background;
use App\Extensions\FashionStudio\System\Models\FashionModel;
use App\Extensions\FashionStudio\System\Models\Pose;
use App\Extensions\FashionStudio\System\Models\Wardrobe;
use App\Extensions\FashionStudio\System\Services\FashionStudioFalAIService;
use App\Extensions\FashionStudio\System\Services\FashionStudioImageModelRegistry;
use App\Models\UserOpenai;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class CheckFalAIGenerationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 60;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 600;

    /**
     * Delete the job if its models no longer exist.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Supported model types and their configurations
     */
    private const MODEL_CONFIGS = [
        'user_openai' => [
            'class'      => UserOpenai::class,
            'uuid_field' => 'payload',
            'uuid_key'   => 'uuid',
            'output'     => 'output',
        ],
        'pose' => [
            'class'      => Pose::class,
            'uuid_field' => 'generation_uuid',
            'uuid_key'   => null,
            'output'     => 'image_url',
        ],
        'wardrobe' => [
            'class'      => Wardrobe::class,
            'uuid_field' => 'generation_uuid',
            'uuid_key'   => null,
            'output'     => 'image_url',
        ],
        'fashion_model' => [
            'class'      => FashionModel::class,
            'uuid_field' => 'generation_uuid',
            'uuid_key'   => null,
            'output'     => 'image_url',
        ],
        'background' => [
            'class'      => Background::class,
            'uuid_field' => 'generation_uuid',
            'uuid_key'   => null,
            'output'     => 'image_url',
        ],
    ];

    public function __construct(
        public int $recordId,
        public string $type = 'image',
        public string $modelType = 'user_openai'
    ) {}

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [5, 5, 5, 10, 10, 10, 15, 15, 15, 20];
    }

    public function handle(): void
    {
        $record = $this->getRecord();

        if (! $record) {
            Log::warning('CheckFalAIGenerationJob: Record not found', [
                'record_id'  => $this->recordId,
                'model_type' => $this->modelType,
            ]);

            return;
        }

        // Skip if already completed or failed
        if (in_array($record->status, [ImageStatusEnum::completed->value, ImageStatusEnum::failed->value], true)) {
            return;
        }

        try {
            $uuid = $this->getUuid($record);

            if (! $uuid) {
                Log::error('CheckFalAIGenerationJob: No UUID found', [
                    'record_id'  => $this->recordId,
                    'model_type' => $this->modelType,
                ]);
                $record->update(['status' => ImageStatusEnum::failed->value]);

                return;
            }

            if ($this->type === 'video') {
                $this->checkVideoGeneration($record, $uuid);
            } else {
                $this->checkImageGeneration($record, $uuid);
            }
        } catch (RuntimeException $e) {
            // Stop immediately and mark as failed - no retries
            Log::error('CheckFalAIGenerationJob: Fal AI error - marking as failed', [
                'record_id'  => $this->recordId,
                'model_type' => $this->modelType,
                'error'      => $e->getMessage(),
            ]);

            $record->update(['status' => ImageStatusEnum::failed->value]);
            $this->delete(); // Stop the job completely
        } catch (Exception $e) {
            // Other exceptions (network issues, etc.) - may retry
            Log::error('CheckFalAIGenerationJob: Error checking status', [
                'record_id'  => $this->recordId,
                'model_type' => $this->modelType,
                'error'      => $e->getMessage(),
            ]);

            if ($this->attempts() >= $this->tries) {
                $record->update(['status' => ImageStatusEnum::failed->value]);
            } else {
                throw $e;
            }
        }
    }

    /**
     * Get the record based on model type
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
     * Get UUID from record based on model configuration
     */
    protected function getUuid(Model $record): ?string
    {
        $config = self::MODEL_CONFIGS[$this->modelType] ?? null;

        if (! $config) {
            return null;
        }

        $uuidField = $config['uuid_field'];
        $uuidKey = $config['uuid_key'];

        if ($uuidKey) {
            // UUID is inside JSON payload - handle both array cast and raw JSON string
            $fieldValue = $record->{$uuidField};
            $payload = is_array($fieldValue) ? $fieldValue : json_decode((string) $fieldValue, true, 512, JSON_THROW_ON_ERROR);

            return $payload[$uuidKey] ?? null;
        }

        // UUID is a direct field
        return $record->{$uuidField};
    }

    /**
     * Resolve which FAL image model the record was generated with.
     *
     * UserOpenai records persist the EntityEnum value in `model`; other model
     * types (Pose/Wardrobe/FashionModel/Background) don't track this column,
     * so we fall back to the admin-configured default.
     */
    protected function resolveImageModel(Model $record): EntityEnum
    {
        $modelValue = $record->getAttribute('model');

        if (is_string($modelValue) && $modelValue !== '') {
            $enum = EntityEnum::tryFrom($modelValue);
            if ($enum !== null) {
                return $enum;
            }
        }

        return FashionStudioImageModelRegistry::getDefaultModel();
    }

    /**
     * Get the output field name for the model type
     */
    protected function getOutputField(): string
    {
        $config = self::MODEL_CONFIGS[$this->modelType] ?? null;

        return $config['output'] ?? 'output';
    }

    /**
     * Check video generation status
     */
    protected function checkVideoGeneration(Model $record, string $uuid): void
    {
        $response = FashionStudioFalAIService::checkVideo($uuid);

        if ($response && isset($response['video_url'])) {
            $localPath = $this->downloadAndSaveFile($response['video_url'], 'mp4', 'video');

            if ($localPath) {
                $outputField = $this->getOutputField();
                $record->update([
                    'status'     => ImageStatusEnum::completed->value,
                    $outputField => $localPath,
                ]);

                return;
            }
        }

        $this->releaseOrFail($record);
    }

    /**
     * Check image generation status
     */
    protected function checkImageGeneration(Model $record, string $uuid): void
    {
        $response = FashionStudioFalAIService::check($uuid, $this->resolveImageModel($record));

        if ($response) {
            $images = data_get($response, 'images', []);

            if (empty($images)) {
                $image = data_get($response, 'image.url');
                $images = $image ? [$image] : [];
            }

            if (! empty($images)) {
                $localPath = $this->downloadAndSaveFile($images[0], null, 'image');

                if ($localPath) {
                    $outputField = $this->getOutputField();
                    $record->update([
                        'status'     => ImageStatusEnum::completed->value,
                        $outputField => $localPath,
                    ]);

                    // Create separate records for additional images (UserOpenai model only)
                    if ($this->modelType === 'user_openai' && count($images) > 1) {
                        $this->createAdditionalImageRecords($record, array_slice($images, 1));
                    }

                    return;
                }
            }
        }

        $this->releaseOrFail($record);
    }

    /**
     * Download file from URL and save it locally
     */
    protected function downloadAndSaveFile(string $url, ?string $defaultExtension = null, string $fileType = 'image'): ?string
    {
        try {
            $response = Http::timeout(120)->get($url);

            if ($response->successful()) {
                $extension = $defaultExtension ?? pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'png';
                $fileName = Str::uuid() . '.' . $extension;

                $folder = $fileType === 'video' ? 'videos' : 'images';
                $path = 'media/' . $folder . '/u-' . $this->getUserId() . '/' . $fileName;

                Storage::disk('public')->put($path, $response->body());

                return '/uploads/' . $path;
            }
        } catch (Exception $e) {
            Log::error('CheckFalAIGenerationJob: Error downloading file', [
                'url'        => $url,
                'error'      => $e->getMessage(),
                'record_id'  => $this->recordId,
                'model_type' => $this->modelType,
            ]);
        }

        return null;
    }

    /**
     * Get the user ID from the record
     */
    protected function getUserId(): ?int
    {
        $record = $this->getRecord();

        return $record?->user_id;
    }

    /**
     * Create separate UserOpenai records for additional images
     */
    protected function createAdditionalImageRecords(Model $record, array $additionalImages): void
    {
        foreach ($additionalImages as $index => $imageUrl) {
            $localPath = $this->downloadAndSaveFile($imageUrl);

            if (! $localPath) {
                continue;
            }

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

    /**
     * Release job back to queue or mark as failed
     */
    protected function releaseOrFail(Model $record): void
    {
        if ($this->attempts() < $this->tries) {
            $this->release($this->getBackoffDelay());

            return;
        }

        $record->update(['status' => ImageStatusEnum::failed->value]);
        Log::warning('CheckFalAIGenerationJob: Generation timed out', [
            'record_id'  => $this->recordId,
            'model_type' => $this->modelType,
            'type'       => $this->type,
        ]);
    }

    /**
     * Get the backoff delay based on attempt number
     */
    protected function getBackoffDelay(): int
    {
        $attempt = $this->attempts();

        if ($attempt <= 10) {
            return 5;
        }

        if ($attempt <= 20) {
            return 10;
        }

        if ($attempt <= 40) {
            return 15;
        }

        return 20;
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Exception $exception): void
    {
        $record = $this->getRecord();

        if ($record && $record->status !== ImageStatusEnum::completed->value) {
            $record->update(['status' => ImageStatusEnum::failed->value]);
        }

        Log::error('CheckFalAIGenerationJob: Job failed permanently', [
            'record_id'  => $this->recordId,
            'model_type' => $this->modelType,
            'error'      => $exception?->getMessage(),
        ]);
    }
}
