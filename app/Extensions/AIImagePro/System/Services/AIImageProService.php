<?php

namespace App\Extensions\AIImagePro\System\Services;

use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity;
use App\Enums\AiImageStatusEnum;
use App\Extensions\AIImagePro\System\Jobs\GenerateAIImageJob;
use App\Extensions\AIImagePro\System\Models\AiImageProModel;
use App\Helpers\Classes\MarketplaceHelper;
use App\Models\Setting;
use App\Models\Usage;
use App\Services\Ai\AiCompletionService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class AIImageProService
{
    /**
     * Get all available AI image models with their configuration.
     */
    public static function getAllAvailableModels(): array
    {
        $models = [];

        // --- OpenAI Models DALL-E 2, DALL-E 3, GPT-IMAGE-1 ---
        $models[EntityEnum::DALL_E_2->value] = [
            'slug'   => EntityEnum::DALL_E_2->slug(),
            'label'  => __('DALL-E 2'),
            'engine' => EntityEnum::DALL_E_2->engine()->slug(),
            'inputs' => [
                self::promptInput(),
                self::referenceImageInput(),
                self::imageCountSelect([1, 2, 3, 4]),
                self::styleSelect(),
                self::aspectRatioSelect(['256x256', '512x512', '1024x1024']),
            ],
        ];
        $models[EntityEnum::DALL_E_3->value] = [
            'slug'   => EntityEnum::DALL_E_3->slug(),
            'label'  => __('DALL-E 3'),
            'engine' => EntityEnum::DALL_E_3->engine()->slug(),
            'inputs' => [
                self::promptInput(),
                self::imageCountSelect([1, 2, 3, 4]),
                self::styleSelect(false),
                self::aspectRatioSelect(['1024x1024', '1792x1024', '1024x1792']),
            ],
        ];
        $models[EntityEnum::GPT_IMAGE_1->value] = [
            'slug'   => EntityEnum::GPT_IMAGE_1->slug(),
            'label'  => __('GPT-IMAGE-1'),
            'engine' => EntityEnum::GPT_IMAGE_1->engine()->slug(),
            'inputs' => [
                self::promptInput(),
                self::referenceImageInput(),
                self::background(['auto', 'transparent', 'opaque']),
                self::imageCountSelect([1, 2, 3, 4]),
                self::styleSelect(),
                self::aspectRatioSelect(['auto', '1024x1024', '1536x1024', '1024x1536']),
            ],
        ];
        $models[EntityEnum::GPT_IMAGE_1_5->value] = [
            'slug'   => EntityEnum::GPT_IMAGE_1_5->slug(),
            'label'  => __('GPT-IMAGE-1.5'),
            'engine' => EntityEnum::GPT_IMAGE_1_5->engine()->slug(),
            'inputs' => [
                self::promptInput(),
                self::referenceImageInput(),
                self::background(['auto', 'transparent', 'opaque']),
                self::imageCountSelect([1, 2, 3, 4]),
                self::styleSelect(),
                self::aspectRatioSelect(['auto', '1024x1024', '1536x1024', '1024x1536']),
            ],
        ];
        $models[EntityEnum::GPT_IMAGE_2->value] = [
            'slug'   => EntityEnum::GPT_IMAGE_2->slug(),
            'label'  => __('GPT-IMAGE-2'),
            'engine' => EntityEnum::GPT_IMAGE_2->engine()->slug(),
            'inputs' => [
                self::promptInput(),
                self::referenceImageInput(),
                self::background(['auto', 'opaque']),
                self::imageCountSelect([1, 2, 3, 4]),
                self::styleSelect(),
                self::aspectRatioSelect(['auto', '1024x1024', '1536x1024', '1024x1536', '2048x2048', '2048x1152', '3840x2160', '2160x3840']),
            ],
        ];

        // --- Stable Diffusion ---
        $models[EntityEnum::ULTRA->value] = [
            'slug'   => EntityEnum::ULTRA->slug(),
            'label'  => __('Ultra-Diffusion'),
            'engine' => EntityEnum::ULTRA->engine()->slug(),
            'inputs' => [
                self::promptInput(),
                self::referenceImageInput(),
                self::imageCountSelect([1, 2, 3, 4]),
                self::styleSelect(),
                self::aspectRatioSelect(['1:1', '16:9', '21:9', '2:3', '3:2', '4:5', '5:4', '9:16', '9:21']),
            ],
        ];
        $models[EntityEnum::CORE->value] = [
            'slug'   => EntityEnum::CORE->slug(),
            'label'  => __('Core-Diffusion'),
            'engine' => EntityEnum::CORE->engine()->slug(),
            'inputs' => [
                self::promptInput(),
                self::imageCountSelect([1, 2, 3, 4]),
                self::styleSelect(false),
                self::aspectRatioSelect(['1:1', '16:9', '21:9', '2:3', '3:2', '4:5', '5:4', '9:16', '9:21']),
            ],
        ];
        $models[EntityEnum::SD_3_5_LARGE->value] = [
            'slug'   => EntityEnum::SD_3_5_LARGE->slug(),
            'label'  => __('SD 3.5 Large'),
            'engine' => EntityEnum::SD_3_5_LARGE->engine()->slug(),
            'inputs' => [
                self::promptInput(),
                self::referenceImageInput(),
                self::imageCountSelect([1, 2, 3, 4]),
                self::styleSelect(),
                self::aspectRatioSelect(['1:1', '16:9', '21:9', '2:3', '3:2', '4:5', '5:4', '9:16', '9:21']),
            ],
        ];
        $models[EntityEnum::SD_3_5_LARGE_TURBO->value] = [
            'slug'   => EntityEnum::SD_3_5_LARGE_TURBO->slug(),
            'label'  => __('SD 3.5 Large Turbo'),
            'engine' => EntityEnum::SD_3_5_LARGE_TURBO->engine()->slug(),
            'inputs' => [
                self::promptInput(),
                self::referenceImageInput(),
                self::imageCountSelect([1, 2, 3, 4]),
                self::styleSelect(),
                self::aspectRatioSelect(['1:1', '16:9', '21:9', '2:3', '3:2', '4:5', '5:4', '9:16', '9:21']),
            ],
        ];
        $models[EntityEnum::SD_3_5_MEDIUM->value] = [
            'slug'   => EntityEnum::SD_3_5_MEDIUM->slug(),
            'label'  => __('SD 3.5 Medium'),
            'engine' => EntityEnum::SD_3_5_MEDIUM->engine()->slug(),
            'inputs' => [
                self::promptInput(),
                self::referenceImageInput(),
                self::imageCountSelect([1, 2, 3, 4]),
                self::styleSelect(),
                self::aspectRatioSelect(['1:1', '16:9', '21:9', '2:3', '3:2', '4:5', '5:4', '9:16', '9:21']),
            ],
        ];

        // --- Optional marketplace-based models ---
        if (MarketplaceHelper::isRegistered('flux-pro')) {
            $models[EntityEnum::FLUX_PRO->value] = [
                'slug'   => EntityEnum::FLUX_PRO->slug(),
                'label'  => __('Flux Pro'),
                'engine' => EntityEnum::FLUX_PRO->engine()->slug(),
                'inputs' => [
                    self::promptInput(),
                    self::imageCountSelect([1, 2, 3, 4]),
                    self::styleSelect(false),
                    self::aspectRatioSelect(['square_hd', 'square', 'portrait_4_3', 'portrait_16_9', 'landscape_4_3', 'landscape_16_9']),
                ],
            ];

            // --- Flux Realism ---
            $models[EntityEnum::FLUX_REALISM->value] = [
                'slug'   => EntityEnum::FLUX_REALISM->slug(),
                'label'  => __('Flux Realism'),
                'engine' => EntityEnum::FLUX_REALISM->engine()->slug(),
                'inputs' => [
                    self::promptInput(),
                    self::imageCountSelect([1, 2, 3, 4]),
                    self::styleSelect(false),
                    self::aspectRatioSelect(['square_hd', 'square', 'portrait_4_3', 'portrait_16_9', 'landscape_4_3', 'landscape_16_9']),
                ],
            ];
        }
        if (MarketplaceHelper::isRegistered('ideogram')) {
            $models[EntityEnum::IDEOGRAM->value] = [
                'slug'   => EntityEnum::IDEOGRAM->slug(),
                'label'  => __('Ideogram'),
                'engine' => EntityEnum::IDEOGRAM->engine()->slug(),
                'inputs' => [
                    self::promptInput(),
                    // self::negativePromptInput(),
                    self::imageCountSelect([1, 2, 3, 4]),
                    self::styleSelect(false),
                    self::aspectRatioSelect(['1:1', '10:16', '16:10', '9:16', '16:9', '4:3', '3:4', '1:3', '3:1', '3:2', '2:3']),
                ],
            ];
        }
        if (MarketplaceHelper::isRegistered('nano-banana')) {
            $models[EntityEnum::NANO_BANANA->value] = [
                'slug'   => EntityEnum::NANO_BANANA->slug(),
                'label'  => __('Nano Banana'),
                'engine' => EntityEnum::NANO_BANANA->engine()->slug(),
                'inputs' => [
                    self::promptInput(),
                    self::referenceImageInput(true),
                    self::imageCountSelect([1, 2, 3, 4]),
                    self::styleSelect(),
                    self::aspectRatioSelect(['1:1', '21:9', '16:9', '3:2', '4:3', '5:4', '4:5', '3:4', '2:3', '9:16']),
                ],
            ];
            $models[EntityEnum::NANO_BANANA_PRO->value] = [
                'slug'   => EntityEnum::NANO_BANANA_PRO->slug(),
                'label'  => __('Nano Banana Pro'),
                'engine' => EntityEnum::NANO_BANANA_PRO->engine()->slug(),
                'inputs' => [
                    self::promptInput(),
                    self::referenceImageInput(true),
                    self::imageCountSelect([1, 2, 3, 4]),
                    self::styleSelect(),
                    self::aspectRatioSelect(['1:1', '21:9', '16:9', '3:2', '4:3', '5:4', '4:5', '3:4', '2:3', '9:16']),
                ],
            ];
            $models[EntityEnum::NANO_BANANA_2->value] = [
                'slug'   => EntityEnum::NANO_BANANA_2->slug(),
                'label'  => __('Nano Banana 2'),
                'engine' => EntityEnum::NANO_BANANA_2->engine()->slug(),
                'inputs' => [
                    self::promptInput(),
                    self::referenceImageInput(true),
                    self::imageCountSelect([1, 2, 3, 4]),
                    self::styleSelect(),
                    self::aspectRatioSelect(['1:1', '21:9', '16:9', '3:2', '4:3', '5:4', '4:5', '3:4', '2:3', '9:16']),
                ],
            ];
        }
        if (MarketplaceHelper::isRegistered('see-dream-v4')) {
            $models[EntityEnum::SEEDREAM_4->value] = [
                'slug'   => EntityEnum::SEEDREAM_4->slug(),
                'label'  => __('SeeDream v4'),
                'engine' => EntityEnum::SEEDREAM_4->engine()->slug(),
                'inputs' => [
                    self::promptInput(),
                    self::referenceImageInput(),
                    self::imageCountSelect([1, 2, 3, 4]),
                    self::styleSelect(),
                    self::aspectRatioSelect(['square_hd', 'square', 'portrait_4_3', 'portrait_16_9', 'landscape_4_3', 'landscape_16_9', 'auto', 'auto_2K', 'auto_4K']),
                ],
            ];
        }
        $models[EntityEnum::GROK_IMAGINE_IMAGE->value] = [
            'slug'   => EntityEnum::GROK_IMAGINE_IMAGE->slug(),
            'label'  => __('Grok Imagine Image'),
            'engine' => EntityEnum::GROK_IMAGINE_IMAGE->engine()->slug(),
            'inputs' => [
                self::promptInput(),
                self::referenceImageInput(true),
                self::imageCountSelect([1, 2, 3, 4]),
                self::styleSelect(),
                self::aspectRatioSelect(['1:1', '21:9', '16:9', '3:2', '4:3', '5:4', '4:5', '3:4', '2:3', '9:16']),
            ],
        ];

        return $models;
    }

    /**
     * Generate Laravel validation rules dynamically based on model definition.
     */
    public static function getValidationRulesFor(string $modelKey): array
    {
        $models = self::getAllAvailableModels();
        if (! isset($models[$modelKey])) {
            return [
                'model' => ['required', 'string', 'in:' . implode(',', array_keys($models))],
            ];
        }

        $rules = [
            'model' => ['required', 'string', 'in:' . implode(',', array_keys($models))],
        ];

        $inputs = $models[$modelKey]['inputs'] ?? [];

        foreach ($inputs as $input) {
            $name = $input['name'];
            $inputRules = [];
            $inputRules[] = ($input['required'] ?? false) ? 'required' : 'nullable';

            switch ($input['type']) {
                case 'text':
                case 'textarea':
                    $inputRules[] = 'string';
                    if (! empty($input['max'])) {
                        $inputRules[] = 'max:' . $input['max'];
                    }

                    break;

                case 'number':
                    $inputRules[] = 'numeric';
                    if (isset($input['min'])) {
                        $inputRules[] = 'min:' . $input['min'];
                    }
                    if (isset($input['max'])) {
                        $inputRules[] = 'max:' . $input['max'];
                    }

                    break;

                case 'select':
                    $values = array_column($input['options'] ?? [], 'value');
                    if ($values) {
                        $inputRules[] = 'in:' . implode(',', $values);
                    }

                    break;

                case 'modal':
                    $inputRules[] = 'string';

                    break;

                case 'file':
                    $inputRules[] = 'file';
                    if (isset($input['accept']) && str_contains($input['accept'], 'image')) {
                        $inputRules[] = 'image';
                        $inputRules[] = 'mimes:jpeg,jpg,png,webp';
                        $inputRules[] = 'max:25600'; // 25MB
                    }

                    break;
            }

            $rules[$name] = $inputRules;
        }

        return $rules;
    }

    public static function getSelectedModelSlugs(): array
    {
        $selectedModelSlugs = setting('ai_image_selected_models', null);

        if (empty($selectedModelSlugs)) {
            return self::getDefaultSelectedModels();
        }

        if (is_string($selectedModelSlugs)) {
            $slugs = json_decode($selectedModelSlugs, true) ?? explode(',', $selectedModelSlugs);
        } else {
            $slugs = (array) $selectedModelSlugs;
        }

        return array_values($slugs);
    }

    public static function getActiveImageModels(): array
    {
        $allModels = self::getAllAvailableModels();
        $selectedSlugs = self::getSelectedModelSlugs();

        if (empty($selectedSlugs)) {
            return $allModels;
        }

        $orderedModels = [];
        foreach ($selectedSlugs as $slug) {
            foreach ($allModels as $key => $model) {
                if ($model['slug'] === $slug) {
                    $orderedModels[$key] = $model;

                    break;
                }
            }
        }

        return $orderedModels;
    }

    public static function getDefaultSelectedModels(): array
    {
        return array_map(
            static fn ($model) => $model['slug'],
            self::getAllAvailableModels()
        );
    }

    public static function getModelsForTagInput(): array
    {
        $allModels = self::getAllAvailableModels();

        return array_map(static function ($model) {
            return [
                'value' => $model['slug'],
                'label' => $model['label'],
            ];
        }, $allModels);
    }

    // INPUT BUILDERS
    private static function promptInput(): array
    {
        return [
            'type'        => 'textarea',
            'name'        => 'prompt',
            'label'       => __('Image Description'),
            'placeholder' => __('Describe the image you want to generate...'),
            'required'    => true,
            'rows'        => 4,
            'max'         => 5000,
        ];
    }

    private static function imageCountSelect(array $availableCounts): array
    {
        $options = array_map(static fn ($count) => [
            'value' => (string) $count,
            'label' => (string) $count,
        ], $availableCounts);

        return [
            'type'      => 'select',
            'name'      => 'image_count',
            'label'     => __('Number of Images'),
            'required'  => true,
            'options'   => $options,
            'default'   => (string) $availableCounts[0],
        ];
    }

    private static function aspectRatioSelect(array $ratios): array
    {
        $ratioDescriptions = [
            '1:1'            => __('Square'),
            '256x256'        => __('Square'),
            '512x512'        => __('Square'),
            '1024x1024'      => __('Square'),
            'square'         => __('Square'),
            'square_hd'      => __('Square HD'),
            'auto'           => __('Auto'),
            'auto_2K'        => __('Auto 2K'),
            'auto_4K'        => __('Auto 4K'),
            '16:9'           => __('Landscape'),
            '21:9'           => __('Ultra Wide'),
            '4:3'            => __('Landscape'),
            '3:2'            => __('Landscape'),
            '5:4'            => __('Landscape'),
            '16:10'          => __('Landscape'),
            '1792x1024'      => __('Landscape'),
            '1536x1024'      => __('Landscape'),
            'landscape_16_9' => __('Landscape'),
            'landscape_4_3'  => __('Landscape'),
            '9:16'           => __('Portrait'),
            '9:21'           => __('Portrait'),
            '3:4'            => __('Portrait'),
            '2:3'            => __('Portrait'),
            '4:5'            => __('Portrait'),
            '10:16'          => __('Portrait'),
            '1:3'            => __('Portrait'),
            '3:1'            => __('Panorama'),
            '1024x1792'      => __('Portrait'),
            '1024x1536'      => __('Portrait'),
            'portrait_16_9'  => __('Portrait'),
            'portrait_4_3'   => __('Portrait'),
        ];

        $options = array_map(static function ($ratio) use ($ratioDescriptions) {
            $displayRatio = Str::title(str_replace(['_', 'x'], [':', ':'], $ratio));
            $description = $ratioDescriptions[$ratio] ?? '';

            return [
                'value' => $ratio,
                'label' => $description ? "{$displayRatio} {$description}" : $displayRatio,
            ];
        }, $ratios);

        return [
            'type'      => 'select',
            'name'      => 'aspect_ratio',
            'label'     => __('Aspect Ratio'),
            'required'  => true,
            'options'   => $options,
            'default'   => $ratios[0],
        ];
    }

    private static function background(array $bgOptions): array
    {
        $options = array_map(static fn ($option) => [
            'value' => $option,
            'label' => Str::title($option),
        ], $bgOptions);

        return [
            'type'      => 'select',
            'name'      => 'background',
            'label'     => __('Background'),
            'required'  => false,
            'options'   => $options,
            'default'   => $bgOptions[0],
        ];
    }

    private static function quality(array $qOptions): array
    {
        $options = array_map(static fn ($option) => [
            'value' => $option,
            'label' => Str::title($option),
        ], $qOptions);

        return [
            'type'      => 'select',
            'name'      => 'quality',
            'label'     => __('Quality'),
            'required'  => false,
            'options'   => $options,
            'default'   => $qOptions[0],
        ];
    }

    private static function styleSelect(bool $refImageAllowed = true): array
    {
        return [
            'type'      => 'modal',
            'name'      => 'style',
            'label'     => __('Style'),
            'required'  => false,
            'default'   => '',
            'component' => 'image-style-modal',
            'props'     => [
                'refImageAllowed' => $refImageAllowed,
            ],
        ];
    }

    private static function referenceImageInput(bool $multiple = false): array
    {
        return [
            'type'        => 'image',
            'name'        => 'image_reference',
            'label'       => null,
            'multiple'    => $multiple,
            'placeholder' => __('Upload an image to guide...'),
            'required'    => false,
            'accept'      => 'image/*',
        ];
    }

    private static function negativePromptInput(): array
    {
        return [
            'type'        => 'textarea',
            'name'        => 'negative_prompt',
            'label'       => __('Negative Prompt'),
            'placeholder' => __('What to avoid in the image...'),
            'required'    => false,
            'rows'        => 2,
            'max'         => 5000,
        ];
    }

    private static function seedInput(): array
    {
        return [
            'type'        => 'number',
            'name'        => 'seed',
            'label'       => __('Seed'),
            'placeholder' => __('Leave empty for random'),
            'tooltip'     => __('Optional: numeric seed for deterministic results'),
            'required'    => false,
            'min'         => 0,
        ];
    }

    private static function tablerIcon(string $iconName): string
    {
        return Blade::render("<x-tabler-{$iconName} class=\"size-4\" />");
    }

    public static function dispatchImageGenerationJob(array $validated, ?int $userId, $driver): int
    {
        try {
            // Create database record for tracking
            $record = AiImageProModel::create([
                'user_id'  => $userId,
                'guest_ip' => auth()->check() ? null : (request()?->header('cf-connecting-ip') ?? request()?->ip()),
                'model'    => $validated['model'] ?? null,
                'engine'   => $validated['engine'] ?? null,
                'prompt'   => $validated['prompt'] ?? '',
                'params'   => [
                    'style'           => $validated['style'] ?? null,
                    'aspect_ratio'    => $validated['aspect_ratio'] ?? null,
                    'image_count'     => $validated['image_count'] ?? 1,
                    'style_reference' => $validated['style_reference'] ?? null,
                    'image_reference' => $validated['image_reference'] ?? null,
                ],
                'status' => AiImageStatusEnum::PENDING,
            ]);

            // Attach record ID to job payload
            $payload = array_merge($validated, ['record_id' => $record->id]);

            // Decrease credit upfront to avoid race conditions, if any error occurs, refund will be handled in the job
            $driver?->decreaseCredit();
            Usage::getSingle()->updateImageCounts($validated['image_count'] ?? 1);

            // Dispatch image generation job
            dispatch(new GenerateAIImageJob($payload, $userId, $driver))->delay(1);

            return $record->id;
        } catch (Throwable $e) {
            Log::error('Failed to dispatch AI image generation job', [
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
                'user_id' => $userId,
                'model'   => $validated['model'] ?? null,
            ]);

            return 0;
        }
    }

    public static function enhancePrompt(?string $prompt, string $toolType): string
    {
        // tool type could be "product placement" or "social media post" or "Product Photography" or etc
        $model = Setting::getCache()->openai_default_model;
        $driver = Entity::driver(EntityEnum::fromSlug($model) ?? EntityEnum::GPT_5_MINI);

        $driver->redirectIfNoCreditBalance();

        if (empty($prompt)) {
            $prompt = 'Generate a short creative prompt for an AI image generation tool that focuses on ' . $toolType;
        } else {
            $prompt = 'Enhance the following prompt to make it more detailed and vivid for an AI image generation tool that focuses on ' . $toolType . ':\n\n"' . $prompt . '"\n\nThe enhanced prompt should inspire unique and visually striking images that effectively represent the essence of ' . $toolType . '.';
        }
        $prompt .= ' Return the prompt as a single string ready to use. Don\'t wrap the result in any additional text or quotes or brackets.';

        $responsedText = app(AiCompletionService::class)->completeUserOnly($prompt);
        $driver->input($responsedText)->calculateCredit()->decreaseCredit();
        Usage::getSingle()->updateWordCounts($driver->calculate());

        return $responsedText;
    }

    /**
     * Get tools configuration from JSON file.
     */
    public static function getToolsConfiguration(): array
    {
        $toolsFile = public_path('vendor/ai-image-pro/templates/tools.json');

        if (! file_exists($toolsFile)) {
            Log::warning('Tools configuration file not found', [
                'path' => $toolsFile,
            ]);

            return [];
        }

        try {
            $content = file_get_contents($toolsFile);
            $tools = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Failed to parse tools configuration', [
                    'error' => json_last_error_msg(),
                ]);

                return [];
            }

            return $tools ?? [];
        } catch (Throwable $e) {
            Log::error('Failed to load tools configuration', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get tools grouped by category.
     */
    public static function getToolsByCategory(): array
    {
        $tools = self::getToolsConfiguration();
        $grouped = [];

        foreach ($tools as $tool) {
            $category = $tool['category'] ?? 'Uncategorized';
            if (! isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][] = $tool;
        }

        return $grouped;
    }

    /**
     * Get a specific tool by ID.
     */
    public static function getToolById(int $toolId): ?array
    {
        $tools = self::getToolsConfiguration();

        return collect($tools)->firstWhere('id', $toolId);
    }

    /**
     * Validate tool inputs against configuration.
     */
    public static function validateToolInputs(array $inputs, array $toolConfig): array
    {
        $errors = [];

        foreach ($toolConfig['data']['inputs'] as $inputConfig) {
            $key = $inputConfig['key'];
            $isRequired = $inputConfig['required'] ?? false;

            if ($isRequired && empty($inputs[$key])) {
                $errors[$key] = __('The :field field is required.', [
                    'field' => $inputConfig['label'] ?? $key,
                ]);
            }
        }

        return $errors;
    }
}
