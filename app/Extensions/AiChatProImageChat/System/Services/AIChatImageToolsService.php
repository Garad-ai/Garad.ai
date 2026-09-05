<?php

declare(strict_types=1);

namespace App\Extensions\AiChatProImageChat\System\Services;

use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity;
use App\Enums\AiImageStatusEnum;
use App\Extensions\AiChatProImageChat\System\Jobs\GenerateAIChatProImageJob;
use App\Extensions\AiChatProImageChat\System\Models\AiChatProImageModel;
use App\Models\Usage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

class AIChatImageToolsService
{
    private static function availableTools(): array
    {
        return [
            [
                'type'        => 'function',
                'name'        => 'generate_edit_image',
                'description' => 'Generate a new image or edit an existing image based on the given prompt. When the user asks to edit, modify, change, or transform an image that was uploaded in the conversation, you MUST provide the image URL in the reference_image_url parameter.',
                'strict'      => true,
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'model' => [
                            'type'        => 'string',
                            'description' => 'The image generation or editing model to use.',
                        ],
                        'prompt' => [
                            'type'        => 'string',
                            'description' => 'The text prompt describing what to generate or how to edit the image.',
                        ],
                        'n' => [
                            'type'        => ['integer', 'null'],
                            'description' => 'The number of images to generate (default is 1).',
                        ],
                        'ratio' => [
                            'type'        => ['string', 'null'],
                            'description' => 'The ratio of the generated image.',
                        ],
                        'style' => [
                            'type'        => ['string', 'null'],
                            'description' => 'The style of the generated image.',
                        ],
                        'reference_image_url' => [
                            'type'        => ['string', 'null'],
                            'description' => 'The URL of the image to edit. REQUIRED when the user wants to edit/modify an existing image from the conversation. Use the image URL provided in the system message.',
                        ],
                    ],
                    'required'             => ['model', 'prompt', 'n', 'ratio', 'style', 'reference_image_url'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    public static function tools(): array
    {
        return self::availableTools();
    }

    public static function callFunction(?string $functionName, ?string $argsString, $chatParams, $driver): ?string
    {
        return match ($functionName) {
            'generate_edit_image' => self::generateOrEditImage($argsString, $chatParams, $driver),
            default               => null,
        };
    }

    public static function generateOrEditImage(?string $argsString, $chatParams, $driver): ?string
    {
        try {
            $args = json_decode($argsString, true, 512, JSON_THROW_ON_ERROR);

            if (! isset($args['prompt'])) {
                throw new JsonException('Invalid arguments: prompt is required');
            }

            // Map the arguments to match the expected format
            // Prioritize uploaded images from chatParams over LLM-provided reference_image_url
            $imageReference = $chatParams['image_reference'] ?? $args['reference_image_url'] ?? null;

            // Deduplicate image references if array
            if (is_array($imageReference)) {
                $imageReference = array_values(array_unique($imageReference));
                // Return single value if only one image
                if (count($imageReference) === 1) {
                    $imageReference = $imageReference[0];
                }
            }

            // Safely get the engine value
            $modelSlug = $args['model'] ?? '';
            $engine = '';

            try {
                $entityEnum = EntityEnum::fromSlug($modelSlug);
                $engine = $entityEnum->engine()->value ?? '';
            } catch (Throwable) {
                // Invalid model slug, continue with empty engine
            }

            $validated = [
                'model'           => $modelSlug,
                'engine'          => $engine,
                'prompt'          => $args['prompt'],
                'image_count'     => $args['n'] ?? 1,
                'aspect_ratio'    => $args['ratio'] ?? null,
                'style'           => $args['style'] ?? null,
                'image_reference' => $imageReference,
            ];

            // Dispatch the job and get the record ID immediately
            $recordId = self::dispatchImageGenerationJob($validated, $chatParams, $driver);

            // Return the record ID immediately so frontend can start polling
            return json_encode(['record_id' => $recordId]);
        } catch (JsonException|Throwable) {
            // Create a failed record so frontend gets feedback
            try {
                $failedRecord = AiChatProImageModel::create([
                    'user_id'    => $chatParams['user_id'] ?? null,
                    'message_id' => $chatParams['message_id'] ?? null,
                    'guest_ip'   => $chatParams['guest_ip'] ?? null,
                    'model'      => null,
                    'engine'     => null,
                    'prompt'     => $args['prompt'] ?? '',
                    'params'     => [],
                    'status'     => AiImageStatusEnum::FAILED,
                    'metadata'   => ['error' => __('Image generation failed. Please try again.')],
                ]);

                return json_encode(['record_id' => $failedRecord->id]);
            } catch (Throwable) {
                return null;
            }
        }
    }

    private static function dispatchImageGenerationJob(array $validated, $chatParams, $chatDriver): ?int
    {
        $record = null;

        try {
            // Create driver for the IMAGE model (not the chat model)
            $imageModel = EntityEnum::fromSlug($validated['model'] ?? '');
            $imageDriver = null;

            if (auth()->check() && $imageModel !== null) {
                try {
                    $imageDriver = Entity::driver($imageModel)
                        ->inputImageCount($validated['image_count'] ?? 1)
                        ->calculateCredit();
                } catch (Throwable) {
                    // Driver creation failed, continue without credit check
                }
            }

            // Check IMAGE model credit balance BEFORE creating the record
            // Use hasCreditBalanceForInput to check against calculated credit (includes image count)
            if ($imageDriver !== null && ! $imageDriver->hasCreditBalanceForInput()) {
                $record = AiChatProImageModel::create([
                    'user_id'    => $chatParams['user_id'] ?? null,
                    'message_id' => $chatParams['message_id'] ?? null,
                    'guest_ip'   => $chatParams['guest_ip'] ?? null,
                    'model'      => $validated['model'] ?? null,
                    'engine'     => $validated['engine'] ?? null,
                    'prompt'     => $validated['prompt'] ?? '',
                    'params'     => [
                        'style'           => $validated['style'] ?? null,
                        'aspect_ratio'    => $validated['aspect_ratio'] ?? null,
                        'image_count'     => $validated['image_count'] ?? 1,
                        'style_reference' => $validated['style_reference'] ?? null,
                        'image_reference' => $validated['image_reference'] ?? null,
                    ],
                    'status'     => AiImageStatusEnum::FAILED,
                    'metadata'   => ['error' => __('You have no credits left. Please consider upgrading your plan.')],
                ]);

                return $record->id;
            }

            // Create database record for tracking
            $record = AiChatProImageModel::create([
                'user_id'    => $chatParams['user_id'] ?? null,
                'message_id' => $chatParams['message_id'] ?? null,
                'guest_ip'   => $chatParams['guest_ip'] ?? null,
                'model'      => $validated['model'] ?? null,
                'engine'     => $validated['engine'] ?? null,
                'prompt'     => $validated['prompt'] ?? '',
                'params'     => [
                    'style'           => $validated['style'] ?? null,
                    'aspect_ratio'    => $validated['aspect_ratio'] ?? null,
                    'image_count'     => $validated['image_count'] ?? 1,
                    'style_reference' => $validated['style_reference'] ?? null,
                    'image_reference' => $validated['image_reference'] ?? null,
                ],
                'status'     => AiImageStatusEnum::PENDING,
            ]);

            // Attach record ID to job payload
            $payload = array_merge($validated, ['record_id' => $record->id]);

            // Decrease credit upfront using IMAGE driver
            $imageDriver?->decreaseCredit();
            Usage::getSingle()->updateImageCounts($validated['image_count'] ?? 1);

            // Dispatch image generation job with image driver
            dispatch(new GenerateAIChatProImageJob($payload, $imageDriver))->delay(1);

            return $record->id;
        } catch (Throwable $e) {
            // If record was created, mark it as failed
            if ($record !== null) {
                $record->markAsFailed($e->getMessage());

                return $record->id;
            }

            // Create a failed record so frontend gets feedback
            try {
                $failedRecord = AiChatProImageModel::create([
                    'user_id'    => $chatParams['user_id'] ?? null,
                    'message_id' => $chatParams['message_id'] ?? null,
                    'guest_ip'   => $chatParams['guest_ip'] ?? null,
                    'model'      => $validated['model'] ?? null,
                    'engine'     => $validated['engine'] ?? null,
                    'prompt'     => $validated['prompt'] ?? '',
                    'params'     => [],
                    'status'     => AiImageStatusEnum::FAILED,
                    'metadata'   => ['error' => __('Image generation failed. Please try again.')],
                ]);

                return $failedRecord->id;
            } catch (Throwable) {
                return null;
            }
        }
    }

    /**
     * Check the status of an image generation job
     */
    public static function checkImageStatus(int $recordId): ?array
    {
        try {
            $record = AiChatProImageModel::find($recordId);

            if (! $record) {
                return null;
            }

            $metadata = $record->metadata ?? [];

            return [
                'status'               => $record->status->value ?? AiImageStatusEnum::PENDING->value,
                'original_prompt'      => $record->prompt,
                'paths'                => $record->generated_images ?? [],
                'metadata'             => $metadata,
                'error'                => $metadata['error'] ?? null,
                'suggestions_response' => $record->suggestions_response ?? [],
                'record_id'            => $record->id,
            ];
        } catch (Throwable $e) {
            Log::error(__('Failed to check image status'), [
                'record_id' => $recordId,
                'error'     => $e->getMessage(),
            ]);

            return null;
        }
    }

    private static function downloadAndSaveImage(string $remoteUrl): ?string
    {
        try {
            $contents = file_get_contents($remoteUrl);

            if ($contents === false) {
                return null;
            }

            $extension = pathinfo(parse_url($remoteUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'png';
            $basePath = auth()->check()
                ? 'media/images/u-' . auth()->id()
                : 'media/images/guest';

            $fileName = $basePath . '/' . Str::uuid() . '.' . $extension;

            Storage::disk('public')->put($fileName, $contents);

            return '/uploads/' . $fileName;
        } catch (Throwable $e) {
            Log::error('Failed to download and save fal.ai image', [
                'url'   => $remoteUrl,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
