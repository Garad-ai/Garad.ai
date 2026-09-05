<?php

namespace App\Extensions\AiChatProImageChat\System\Services;

use App\Domains\Entity\Enums\EntityEnum;
use App\Enums\AiImageStatusEnum;
use App\Extensions\AiChatProImageChat\System\Models\AiChatProImageModel;
use App\Helpers\Classes\ApiHelper;
use App\Helpers\Classes\Helper;
use App\Helpers\Classes\MarketplaceHelper;
use App\Models\UserOpenaiChat;
use App\Services\Stream\StreamService;
use Exception;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Responses\Output\OutputFunctionToolCall;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AIChatImageService
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
                case 'image':
                    // Handle both single file and array of files
                    if ($input['multiple'] ?? false) {
                        $inputRules = ['nullable', 'array'];
                        $rules[$name . '.*'] = ['file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:25600'];
                    } else {
                        $inputRules[] = 'file';
                        if (isset($input['accept']) && str_contains($input['accept'], 'image')) {
                            $inputRules[] = 'image';
                            $inputRules[] = 'mimes:jpeg,jpg,png,webp';
                            $inputRules[] = 'max:25600'; // 25MB
                        }
                    }

                    break;
            }

            $rules[$name] = $inputRules;
        }

        return $rules;
    }

    public static function getSelectedModelSlugs(): array
    {
        $selectedModelSlugs = setting('ai_chat_pro_image_chat_selected_models', null);

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

    public static function extractImageChatParameters(Request $request): array
    {
        $imageReference = self::processImageReferenceUpload($request);

        return [
            'style'               => $request->input('style'),
            'style_id'            => $request->input('style_id'),
            'image_reference'     => $imageReference,
            'image_count'         => $request->input('image_count'),
            'aspect_ratio'        => $request->input('aspect_ratio'),
            'model'               => $request->input('model'),
            'engine'              => $request->input('engine'),
            'slug'                => $request->input('slug'),
            'user_id'             => auth()?->id(),
            'guest_ip'            => auth()->check() ? null : (request()?->header('cf-connecting-ip') ?? request()?->ip()),
            'reimagine_image_url' => $request->input('reimagine_image_url'),
            'reimagine_prompt'    => $request->input('reimagine_prompt'),
            'edit_tab'            => $request->input('edit_tab'),
            'edit_mode'           => $request->input('edit_mode'),
            'edit_has_highlights' => (bool) $request->input('edit_has_highlights', false),
        ];
    }

    /**
     * Build a contextual prompt for image editing based on the selected tab and mode.
     *
     * @param  string  $rawPrompt  The user's raw prompt text
     * @param  string|null  $tab  The selected editing tab (smart_edit, restyle, remove_background, replace_background, reimagine)
     * @param  string  $mode  The editing mode (visual or text)
     * @param  bool  $hasHighlights  Whether the user has painted highlight areas on the image
     */
    public static function buildEditPrompt(string $rawPrompt, ?string $tab, string $mode = 'text', bool $hasHighlights = false): string
    {
        $userPrompt = trim($rawPrompt);

        return match ($tab) {
            'smart_edit' => $mode === 'visual' && $hasHighlights
                ? ($userPrompt !== ''
                    ? "Keep the original image exactly as it is. Only fill the transparent masked area with: {$userPrompt}. Make it blend naturally with the rest of the image, matching the lighting, perspective, and style."
                    : '')
                : $userPrompt,

            'restyle' => $userPrompt !== ''
                ? "Restyle this image with the following style: {$userPrompt}"
                : '',

            'remove_background' => 'Remove the background from this image and make it transparent or white.',

            'replace_background' => $userPrompt !== ''
                ? "Replace the background of this image with: {$userPrompt}"
                : '',

            'reimagine' => $userPrompt !== ''
                ? "Reimagine this image: {$userPrompt}"
                : '',

            default => $userPrompt,
        };
    }

    /**
     * Process uploaded image reference files and return URL(s).
     */
    protected static function processImageReferenceUpload(Request $request): string|array|null
    {
        $urls = [];
        $processedFiles = []; // Track processed files by name+size to avoid duplicates

        // Check for file uploads - Laravel handles both 'image_reference' and 'image_reference[]'
        $files = $request->file('image_reference');

        if ($files) {
            // Normalize to array
            $files = is_array($files) ? $files : [$files];

            foreach ($files as $file) {
                if ($file && $file->isValid()) {
                    // Create unique key based on file name and size to detect duplicates
                    $fileKey = $file->getClientOriginalName() . '-' . $file->getSize();

                    if (in_array($fileKey, $processedFiles, true)) {
                        continue; // Skip duplicate
                    }
                    $processedFiles[] = $fileKey;

                    $url = self::saveUploadedImage($file);
                    if ($url) {
                        $urls[] = $url;
                    }
                }
            }
        }

        // If we have uploaded files, return them
        if (! empty($urls)) {
            return count($urls) === 1 ? $urls[0] : $urls;
        }

        // Fall back to input value (could be a URL or path string)
        return $request->input('image_reference');
    }

    /**
     * Save an uploaded image file and return its URL.
     */
    protected static function saveUploadedImage($file): ?string
    {
        try {
            $basePath = auth()->check() ? 'media/images/u-' . auth()->id() : 'media/images/guest-' . (request()?->header('cf-connecting-ip') ?? request()?->ip());
            $path = processSecureFileUpload($file, $basePath);

            if (! $path) {
                return null;
            }

            // Return full URL for the AI to use
            return url($path);
        } catch (Exception $e) {
            Log::error('Failed to save chat image reference', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public static function chatImageStream($chat_bot, $history, $main_message, $chatParams): ?StreamedResponse
    {
        $mainStreamService = app(StreamService::class);
        ApiHelper::setOpenAiKey();
        $tools = AIChatImageToolsService::tools();
        $mainStreamService->prepareStreamEnvironment();

        $model = EntityEnum::fromSlug($chat_bot) ?? EntityEnum::fromSlug(Helper::setting('openai_default_model'));
        $driver = auth()->check() ? $mainStreamService->createDriver($model) : null;
        $imageModel = EntityEnum::fromSlug($chatParams['model'] ?? '');
        $availableModels = self::getAllAvailableModels();

        if (! isset($availableModels[$imageModel->value])) {
            throw new RuntimeException(__('The selected image generation model is not available.'));
        }

        // Extract image URLs from the conversation history for editing
        $conversationImages = self::extractImagesFromHistory($history, $chatParams);

        // Build extra prompt with parameters
        $extraPrompt = 'You are an AI assistant that can generate and edit images. {only and only if needed use the following parameters to generate the image otherwise ignore this part: | Model: ' . $imageModel->value;
        foreach ($availableModels[$imageModel->value]['inputs'] ?? [] as $input) {
            $inputName = $input['name'];
            if ($inputName === 'prompt') {
                continue;
            }
            if (isset($chatParams[$inputName])) {
                $value = $chatParams[$inputName];
                // Skip arrays (like image_reference) - they're handled separately
                if (is_array($value)) {
                    continue;
                }
                $extraPrompt .= " | {$inputName}: " . $value;
            }
        }
        $extraPrompt .= '. Do not use any other parameters not listed here}';

        // Check for reimagine_image_url (silently passed from frontend for reimagine action)
        $reimagineImageUrl = $chatParams['reimagine_image_url'] ?? null;
        if (! empty($reimagineImageUrl)) {
            // When reimagining, the specific image URL takes priority
            $extraPrompt .= "\n\nIMAGE EDITING CONTEXT:\n";
            $extraPrompt .= "- IMPORTANT: The user wants to create a variation of this specific image: {$reimagineImageUrl}\n";
            $extraPrompt .= "- You MUST use this URL as the reference_image_url parameter when calling the generate_edit_image function.\n";
            $extraPrompt .= '- This is a reimagine/variation request - generate a creative variation of the referenced image.';
        } elseif (! empty($conversationImages)) {
            // Only include the most recent image (last in array) unless user specifies a URL
            $mostRecentImage = end($conversationImages);
            $extraPrompt .= "\n\nIMAGE EDITING CONTEXT:\n";
            $extraPrompt .= "- Most recent image in conversation: {$mostRecentImage}\n";
            $extraPrompt .= "- When the user asks to edit, modify, vary, or change 'the image' or 'this image', use the most recent image URL above.\n";
            $extraPrompt .= "- If the user provides a specific image URL in their message (e.g., 'edit this: http://...'), use THAT exact URL as reference_image_url.\n";
            $extraPrompt .= "- For 'reimagine' or 'create a variation' requests, use the image URL mentioned in the user's message if provided, otherwise use the most recent image.";
        }

        array_unshift($history, [
            'role'    => 'system',
            'content' => $extraPrompt,
        ]);

        $total_used_tokens = 0;
        $output = '';
        $responsedText = '';
        $chat_id = $main_message->user_openai_chat_id;
        $chat = UserOpenaiChat::whereId($chat_id)->first();

        return response()->stream(static function () use ($chatParams, $model, $chat, &$total_used_tokens, &$output, &$responsedText, $driver, $mainStreamService, $tools, $history, $main_message) {
            // Add message_id to chatParams for linking image records
            $chatParams['message_id'] = $main_message->id;

            echo "event: message\n";
            echo 'data: ' . $main_message->id . "\n\n";
            $mainStreamService->safeFlush();
            $signalSent = false;
            $recordIdSent = false;

            if ($driver && ! $driver->hasCreditBalance()) {
                echo PHP_EOL;
                echo "event: data\n";
                echo 'data: ' . __('You have no credits left. Please buy more credits to continue.');
                echo "\n\n";
                $mainStreamService->safeFlush();
                echo "event: stop\n";
                echo 'data: [DONE]';
                echo "\n\n";
                $mainStreamService->safeFlush();

                return null;
            }

            $currentHistory = $history;

            // Initial API call
            $options = [
                'model'  => $model->value,
                'stream' => true,
                'tools'  => $tools,
                'input'  => $currentHistory,
            ];

            if ($model->isReasoningModel()) {
                $options['reasoning']['effort'] = $model === EntityEnum::GPT_5_PRO
                    ? 'high'
                    : $model->resolveReasoningEffort(setting('openai_reasoning_models_effort', 'low'));
            } else {
                $options['temperature'] = 1.0;
            }

            try {
                $stream = OpenAI::responses()->createStreamed($options);
            } catch (Exception $e) {
                $msg = str_starts_with($e->getMessage(), 'Incorrect API key provided')
                    ? __('The AI service API key is invalid. Please contact the administrator.')
                    : $e->getMessage();

                echo PHP_EOL;
                echo "event: data\n";
                echo 'data: ' . __('An error occurred while processing your request: ') . $msg;
                echo "\n\n";
                $mainStreamService->safeFlush();
                echo "event: stop\n";
                echo 'data: [DONE]';
                echo "\n\n";
                $mainStreamService->safeFlush();

                return null;
            }

            foreach ($stream as $response) {
                if (! isset($response->event)) {
                    continue;
                }

                if (connection_aborted()) {
                    break;
                }

                if (! empty($tools) && $response->event === 'response.completed' && isset($response->response->output)) {
                    $calls = $response->response->output;
                    foreach ($calls ?? [] as $call) {
                        if ($call instanceof OutputFunctionToolCall) {
                            // Check daily limit for guests
                            if (Helper::appIsDemo() || ! auth()->check()) {
                                $limitCheck = self::checkGuestDailyLimit(request(), $chatParams['image_count'] ?? 1);

                                if (! $limitCheck['allowed']) {
                                    echo PHP_EOL;
                                    echo "event: data\n";
                                    echo 'data: ' . $limitCheck['message'];
                                    echo "\n\n";
                                    $mainStreamService->safeFlush();
                                    echo "event: stop\n";
                                    echo 'data: [DONE]';
                                    echo "\n\n";
                                    $mainStreamService->safeFlush();

                                    return null;
                                }
                            }

                            $functionName = $call?->name;
                            $argumentsString = $call?->arguments;

                            // Send function_call event first
                            if (! empty($functionName) && ! $signalSent) {
                                echo PHP_EOL;
                                echo "event: function_call\n";
                                echo 'data: ' . $functionName . "\n\n";
                                $mainStreamService->safeFlush();
                                $signalSent = true;
                            }

                            $result = AIChatImageToolsService::callFunction(
                                $functionName,
                                $argumentsString,
                                $chatParams,
                                $driver
                            );

                            if (! $recordIdSent && $result) {
                                $resultData = json_decode($result, true);
                                if (isset($resultData['record_id'])) {
                                    echo PHP_EOL;
                                    echo "event: image_record\n";
                                    echo 'data: ' . $resultData['record_id'] . "\n\n";
                                    $mainStreamService->safeFlush();
                                    $recordIdSent = true;
                                }
                            }
                        }
                    }
                }

                if ((isset($response->response->delta) && $response->event === 'response.output_text.delta')) {
                    $text = $response->response->delta;
                    $messageFix = str_replace(["\r\n", "\r", "\n"], '<br/>', $text);
                    $output .= $messageFix;
                    $responsedText .= $text;
                    $total_used_tokens += countWords($text);
                    echo PHP_EOL;
                    echo "event: data\n";
                    echo 'data: ' . $messageFix;
                    echo "\n\n";
                    $mainStreamService->safeFlush();
                }
            }

            echo "event: stop\n";
            echo 'data: [DONE]';
            echo "\n\n";

            $mainStreamService->safeFlush();
            $mainStreamService->saveStreamResponse($main_message, $chat, $responsedText, $output, $total_used_tokens, $driver);
        }, 200, [
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
            'Content-Type'      => 'text/event-stream',
        ]);
    }

    /**
     * Check if guest user can generate the requested number of images based on daily limit.
     * Uses cache locking to prevent race conditions.
     */
    protected static function checkGuestDailyLimit(Request $request, int $requestedImageCount): array
    {
        $dailyGuestLimit = (int) setting('ai_chat_pro_image_chat:guest_daily_limit', 2);

        // If limit is negative, no restrictions
        if ($dailyGuestLimit < 0) {
            return ['allowed' => true];
        }

        // If limit is zero, generation is not allowed for guests
        if ($dailyGuestLimit === 0) {
            return [
                'allowed' => false,
                'message' => __('Image generation is not allowed for guest users. Please create an account to continue.'),
            ];
        }

        $userIp = $request->header('cf-connecting-ip') ?? $request->ip();
        $lockKey = "_guest_limit_check:{$userIp}";
        $cacheKey = "_guest_daily_count:{$userIp}:" . now()->toDateString();

        // Acquire lock with 10 second timeout
        $lock = Cache::lock($lockKey, 10);

        try {
            // Wait up to 10 seconds to acquire the lock
            $lock->block(10);

            // Get or calculate today's generated image count
            $todayGeneratedCount = Cache::remember($cacheKey, 3600, function () use ($userIp) {
                $todayRecords = AiChatProImageModel::where('guest_ip', $userIp)
                    ->whereDate('created_at', now()->toDateString())
                    ->get(['generated_images', 'params']);

                return $todayRecords->sum(function ($record) {
                    // Count actual generated images (completed)
                    $generatedCount = count($record->generated_images ?? []);

                    // If no generated images yet, count the requested amount
                    if ($generatedCount === 0) {
                        $generatedCount = (int) ($record->params['image_count'] ?? 1);
                    }

                    return $generatedCount;
                });
            });

            // Check if user can generate the requested images
            $totalAfterRequest = $todayGeneratedCount + $requestedImageCount;

            if ($totalAfterRequest > $dailyGuestLimit) {
                $remaining = max(0, $dailyGuestLimit - $todayGeneratedCount);

                return [
                    'allowed' => false,
                    'message' => __('Daily limit exceeded. You have generated :count out of :limit images today. You can generate :remaining more image(s). Please create an account to continue.', [
                        'count'     => $todayGeneratedCount,
                        'limit'     => $dailyGuestLimit,
                        'remaining' => $remaining,
                    ]),
                    'current_count' => $todayGeneratedCount,
                    'limit'         => $dailyGuestLimit,
                    'remaining'     => $remaining,
                ];
            }

            // Update cache with new count (optimistic update)
            Cache::put($cacheKey, $totalAfterRequest, 3600);

            return [
                'allowed'       => true,
                'current_count' => $todayGeneratedCount,
                'limit'         => $dailyGuestLimit,
                'remaining'     => $dailyGuestLimit - $totalAfterRequest,
            ];

        } catch (LockTimeoutException $e) {
            Log::warning('Failed to acquire lock for guest limit check', [
                'ip'    => $userIp,
                'error' => $e->getMessage(),
            ]);

            return [
                'allowed' => false,
                'message' => __('Too many requests. Please try again in a moment.'),
            ];
        } finally {
            // Always release the lock
            optional($lock)->release();
        }
    }

    /**
     * Extract image URLs from the chat history and current message for editing purposes.
     *
     * @param  array  $history  The chat history array
     * @param  array  $chatParams  The chat parameters including current images
     *
     * @return array Array of image URLs available for editing
     */
    protected static function extractImagesFromHistory(array $history, array $chatParams): array
    {
        $images = [];

        // Extract generated images from ai_chat_pro_image table for this chat
        if (! empty($chatParams['chat_id'])) {
            $generatedImages = self::getGeneratedImagesForChat($chatParams['chat_id']);
            foreach ($generatedImages as $imagePath) {
                if (! Str::startsWith($imagePath, ['http://', 'https://'])) {
                    $imagePath = ltrim($imagePath, '/');
                    $imagePath = url($imagePath);
                }
                $images[] = $imagePath;
            }
        }

        // Extract images from history messages
        foreach ($history as $message) {
            if (! isset($message['content']) || ! is_array($message['content'])) {
                continue;
            }

            foreach ($message['content'] as $content) {
                if (! is_array($content)) {
                    continue;
                }

                // Check for input_image type (OpenAI vision format)
                if (isset($content['type']) && $content['type'] === 'input_image' && isset($content['image_url'])) {
                    $imageUrl = $content['image_url'];

                    // Skip base64 images - we need actual URLs for the image editing API
                    if (Str::startsWith($imageUrl, 'data:')) {
                        continue;
                    }

                    $images[] = $imageUrl;
                }
            }
        }

        // Add current message images if they exist (from chatParams)
        if (! empty($chatParams['images'])) {
            $currentImages = is_array($chatParams['images'])
                ? $chatParams['images']
                : explode(',', $chatParams['images']);

            foreach ($currentImages as $image) {
                $image = trim($image);
                if (empty($image)) {
                    continue;
                }

                // Convert relative paths to absolute URLs
                if (! Str::startsWith($image, ['http://', 'https://'])) {
                    $image = ltrim($image, '/');
                    $image = url($image);
                }

                // Skip base64 images
                if (Str::startsWith($image, 'data:')) {
                    continue;
                }

                $images[] = $image;
            }
        }

        // Add image_reference from form if provided
        if (! empty($chatParams['image_reference'])) {
            $refImages = $chatParams['image_reference'];
            $refImages = is_array($refImages) ? $refImages : [$refImages];

            foreach ($refImages as $refImage) {
                if (Str::startsWith($refImage, 'data:')) {
                    continue;
                }
                if (! Str::startsWith($refImage, ['http://', 'https://'])) {
                    $refImage = ltrim($refImage, '/');
                    $refImage = url($refImage);
                }
                $images[] = $refImage;
            }
        }

        // Return unique images, most recent last
        return array_values(array_unique($images));
    }

    /**
     * Get generated images for a chat from the ai_chat_pro_image table.
     * Only returns the most recent image to avoid confusing the AI with too many images.
     *
     * @param  int  $chatId  The chat ID
     *
     * @return array Array of image paths (most recent only)
     */
    protected static function getGeneratedImagesForChat(int $chatId): array
    {
        // Get only the most recent completed image record
        $imageRecord = AiChatProImageModel::query()
            ->whereHas('message', function ($query) use ($chatId) {
                $query->where('user_openai_chat_id', $chatId);
            })
            ->where('status', AiImageStatusEnum::COMPLETED)
            ->orderBy('created_at', 'desc')
            ->first();

        $generatedImages = $imageRecord->generated_images ?? [];

        if (empty($generatedImages)) {
            return [];
        }

        // Return only the first image from the most recent generation
        $firstImage = reset($generatedImages);

        return $firstImage ? [$firstImage] : [];
    }
}
