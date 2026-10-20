<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Services;

use App\Domains\Engine\Enums\EngineEnum;
use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity;
use App\Enums\AiImageStatusEnum;
use App\Extensions\AIImagePro\System\Models\AiImageProModel;
use App\Extensions\AIImagePro\System\Services\AIImageProService;
use App\Extensions\AiChatProImageChat\System\Models\AiChatProImageModel;
use App\Extensions\AiChatProImageChat\System\Services\AIChatImageToolsService;
use App\Helpers\Classes\MarketplaceHelper;
use App\Http\Controllers\AIController;
use App\Models\Usage;
use App\Services\Ai\AIImageClient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Throwable;

class GaradImageToolService
{
    /**
     * Generate a new image from normal Chat Pro while reusing AI Image Pro's
     * configured models, credit system, history table and image backend.
     */
    public function generate(array $arguments): string
    {
        if (! Auth::check()) {
            throw new RuntimeException('Please sign in to generate images from Chat Pro.');
        }

        $prompt = trim((string) ($arguments['prompt'] ?? ''));
        if ($prompt === '') {
            throw new RuntimeException('An image prompt is required.');
        }

        // Apply only the brand selected by the authenticated user. The brand
        // context service ownership-scopes Company/Product lookups.
        $prompt = app(GaradBrandContextService::class)->augmentPrompt($prompt, 'image');

        if (! MarketplaceHelper::isRegistered('ai-image-pro')) {
            // Backward-compatible fallback for installations that only have the
            // original Chat Pro image-generation feature.
            if (! (bool) setting('ai_chat_pro_image_generation_feature', '0')) {
                throw new RuntimeException('AI image generation is not available on this installation.');
            }

            return (string) app(AIController::class)->generateImageWithOpenAI($prompt);
        }

        $model = $this->resolveGenerationModel();
        $driver = Entity::driver($model)->inputImageCount(1)->calculateCredit();
        if (! $driver->hasCreditBalanceForInput()) {
            throw new RuntimeException('You have no image credits left. Please consider upgrading your plan.');
        }

        $aspectRatio = $this->normalizeOpenAiSize((string) ($arguments['aspect_ratio'] ?? '1024x1024'));
        $quality = strtolower((string) ($arguments['quality'] ?? 'auto'));
        $quality = in_array($quality, ['auto', 'low', 'medium', 'high', 'standard', 'hd'], true) ? $quality : 'auto';

        $record = AiImageProModel::create([
            'user_id'          => Auth::id(),
            'guest_ip'         => null,
            'model'            => $model->value,
            'engine'           => $model->engine()->value,
            'prompt'           => mb_substr($prompt, 0, 32000),
            'params'           => [
                'style'        => $arguments['style'] ?? null,
                'aspect_ratio' => $aspectRatio,
                'image_count'  => 1,
            ],
            'status' => AiImageStatusEnum::PENDING,
            'metadata' => [
                'source'  => 'chat_pro',
                'chat_id' => (int) request()?->input('chat_id'),
            ],
        ]);

        $driver->decreaseCredit();

        try {
            $record->markAsStarted();

            $result = AIImageClient::generate([
                'model'        => $model->value,
                'engine'       => $model->engine()->value,
                'prompt'       => mb_substr($prompt, 0, 32000),
                'aspect_ratio' => $aspectRatio,
                'quality'      => $quality,
                'background'   => 'auto',
                'image_count'  => 1,
                'style'        => $arguments['style'] ?? null,
            ]);

            if (! isset($result[0]) || ! is_string($result[0]) || $result[0] === '') {
                throw new RuntimeException('The image provider did not return an image.');
            }

            $directory = 'media/images/u-' . Auth::id();
            $filename = $directory . '/' . Str::uuid() . '-garad-chat-pro.webp';
            if (! Storage::disk('public')->put($filename, $result[0])) {
                throw new RuntimeException('The generated image could not be stored.');
            }

            $url = '/uploads/' . $filename;
            $record->markAsCompleted([$url], [
                'source'  => 'chat_pro',
                'chat_id' => (int) request()?->input('chat_id'),
                'model'   => $model->value,
            ]);
            $record->saveDimensions($filename);

            Usage::getSingle()->updateImageCounts(1);

            $safeUrl = e($url);

            // Keep tool output Markdown-only. Raw HTML cards are intentionally
            // avoided because the Chat Pro Markdown/stream renderer may escape
            // partial tags and expose code-like fragments to the user.
            return sprintf(
                "![Generated image](%s)\n\nImage created successfully with **%s**.\n\n[Open / Download image](%s)",
                $safeUrl,
                e($model->label()),
                $safeUrl,
            );
        } catch (Throwable $e) {
            $record->markAsFailed($e->getMessage());
            $driver->increaseCredit($driver->calculate());
            throw $e;
        }
    }

    public function edit(array $arguments): string
    {
        $this->ensureEditAvailable();

        $prompt = trim((string) ($arguments['prompt'] ?? ''));
        if ($prompt === '') {
            throw new RuntimeException('An image edit instruction is required.');
        }

        $prompt = app(GaradBrandContextService::class)->augmentPrompt($prompt, 'image edit');

        $chatId = (int) request()?->input('chat_id');
        if ($chatId <= 0) {
            throw new RuntimeException('The current Chat Pro conversation could not be identified.');
        }

        $image = app(DocumentRepository::class)
            ->getDocumentsForChat($chatId)
            ->filter(static function ($document): bool {
                $mime = strtolower((string) $document->mime_type);
                $extension = strtolower((string) $document->file_extension);

                return str_starts_with($mime, 'image/')
                    || in_array($extension, ['png', 'jpg', 'jpeg', 'webp', 'gif', 'bmp'], true);
            })
            ->last();

        if (! $image || blank($image->reference_url)) {
            throw new RuntimeException('Please attach an image to this Chat Pro conversation before asking me to edit it.');
        }

        $model = $this->resolveEditModel();
        $payload = [
            'model'               => $model->value,
            'prompt'              => $prompt,
            'n'                   => 1,
            'ratio'               => null,
            'style'               => null,
            'reference_image_url' => (string) $image->reference_url,
        ];

        $chatParams = [
            'user_id'         => Auth::id(),
            'message_id'      => null,
            'guest_ip'        => request()?->header('cf-connecting-ip') ?? request()?->ip(),
            'image_reference' => (string) $image->reference_url,
        ];

        try {
            $response = AIChatImageToolsService::generateOrEditImage(
                json_encode($payload, JSON_THROW_ON_ERROR),
                $chatParams,
                null,
            );
        } catch (JsonException $e) {
            throw new RuntimeException('Unable to prepare the image edit request.', previous: $e);
        }

        $decoded = is_string($response) ? json_decode($response, true) : null;
        $recordId = (int) ($decoded['record_id'] ?? 0);

        if ($recordId <= 0) {
            throw new RuntimeException('Image editing could not be started.');
        }

        return sprintf(
            "Image edit started successfully.\n\nImage Job ID: **%d**\n\nAsk me to check the image status with this ID.",
            $recordId,
        );
    }

    public function status(array $arguments): string
    {
        if (! Auth::check()) {
            throw new RuntimeException('Please sign in to check image status.');
        }

        $recordId = (int) ($arguments['record_id'] ?? 0);
        if ($recordId <= 0) {
            throw new RuntimeException('record_id is required.');
        }

        // First support AI Image Pro jobs (including images generated by Chat Pro).
        if (MarketplaceHelper::isRegistered('ai-image-pro')) {
            $proRecord = AiImageProModel::query()
                ->whereKey($recordId)
                ->where('user_id', Auth::id())
                ->first();

            if ($proRecord) {
                $status = (string) ($proRecord->status?->value ?? $proRecord->status ?? 'pending');
                $images = is_array($proRecord->generated_images) ? $proRecord->generated_images : [];

                if ($status === AiImageStatusEnum::COMPLETED->value && $images !== []) {
                    $markdown = array_map(
                        static fn ($path): string => sprintf('![Generated image](%s)', e((string) $path)),
                        $images,
                    );

                    return "✅ Image completed.\n\n" . implode("\n\n", $markdown);
                }

                if ($status === AiImageStatusEnum::FAILED->value) {
                    $metadata = is_array($proRecord->metadata) ? $proRecord->metadata : [];

                    return '❌ Image generation failed: ' . e((string) ($metadata['error'] ?? 'Unknown error'));
                }

                return sprintf('⏳ Image Job #%d is still processing.', $recordId);
            }
        }

        // Then support the dedicated Chat Pro image-edit extension jobs.
        if (MarketplaceHelper::isRegistered('ai-chat-pro-image-chat')) {
            $record = AiChatProImageModel::query()
                ->whereKey($recordId)
                ->where('user_id', Auth::id())
                ->first();

            if ($record) {
                $status = (string) ($record->status?->value ?? $record->status ?? 'pending');
                $images = is_array($record->generated_images) ? $record->generated_images : [];

                if (in_array($status, ['completed', 'complete'], true) && $images !== []) {
                    $markdown = array_map(
                        static fn ($path): string => sprintf('![Edited image](%s)', e((string) $path)),
                        $images,
                    );

                    return "✅ Image edit completed.\n\n" . implode("\n\n", $markdown);
                }

                if (in_array($status, ['failed', 'error'], true)) {
                    $metadata = is_array($record->metadata) ? $record->metadata : [];

                    return '❌ Image edit failed: ' . e((string) ($metadata['error'] ?? 'Unknown error'));
                }

                return sprintf('⏳ Image Job #%d is still processing.', $recordId);
            }
        }

        throw new RuntimeException('Image job was not found or does not belong to your account.');
    }

    private function resolveGenerationModel(): EntityEnum
    {
        $activeModels = AIImageProService::getActiveImageModels();
        $activeSlugs = [];

        foreach ($activeModels as $config) {
            $slug = (string) ($config['slug'] ?? '');
            if ($slug !== '') {
                $activeSlugs[] = $slug;
            }
        }

        // Prefer the newest synchronous OpenAI image model, while respecting the
        // admin's selected AI Image Pro model list.
        $preferred = [
            EntityEnum::GPT_IMAGE_2,
            EntityEnum::GPT_IMAGE_1_5,
            EntityEnum::GPT_IMAGE_1,
            EntityEnum::DALL_E_3,
            EntityEnum::DALL_E_2,
        ];

        foreach ($preferred as $candidate) {
            if (in_array($candidate->slug(), $activeSlugs, true)
                && $candidate->engine() === EngineEnum::OPEN_AI) {
                return $candidate;
            }
        }

        throw new RuntimeException('Enable at least one OpenAI image model in AI Image Pro settings for instant Chat Pro image generation.');
    }

    private function normalizeOpenAiSize(string $value): string
    {
        $value = strtolower(trim($value));
        $map = [
            '1:1'  => '1024x1024',
            '16:9' => '1536x1024',
            '9:16' => '1024x1536',
        ];

        $value = $map[$value] ?? $value;

        return in_array($value, [
            'auto', '1024x1024', '1536x1024', '1024x1536',
            '2048x2048', '2048x1152', '3840x2160', '2160x3840',
        ], true) ? $value : '1024x1024';
    }

    private function resolveEditModel(): EntityEnum
    {
        $configured = (string) setting('ai_image_pro_edit_model', EntityEnum::GPT_IMAGE_1_5->value);

        $aliases = [
            'nano-banana'     => EntityEnum::NANO_BANANA,
            'nano-banana-pro' => EntityEnum::NANO_BANANA_PRO,
            'nano-banana-2'   => EntityEnum::NANO_BANANA_2,
        ];

        if (isset($aliases[$configured])) {
            return $aliases[$configured];
        }

        return EntityEnum::fromSlug($configured) ?? EntityEnum::GPT_IMAGE_1_5;
    }

    private function ensureEditAvailable(): void
    {
        if (! Auth::check()) {
            throw new RuntimeException('Please sign in to edit images from Chat Pro.');
        }

        if (! MarketplaceHelper::isRegistered('ai-chat-pro-image-chat')) {
            throw new RuntimeException('AI image editing is not available on this installation.');
        }
    }
}
