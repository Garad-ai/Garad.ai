<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Services;

use App\Domains\Entity\Enums\EntityEnum;
use App\Extensions\AiChatProImageChat\System\Models\AiChatProImageModel;
use App\Extensions\AiChatProImageChat\System\Services\AIChatImageToolsService;
use App\Helpers\Classes\MarketplaceHelper;
use Illuminate\Support\Facades\Auth;
use JsonException;
use RuntimeException;

class GaradImageToolService
{
    public function edit(array $arguments): string
    {
        $this->ensureAvailable();

        $prompt = trim((string) ($arguments['prompt'] ?? ''));
        if ($prompt === '') {
            throw new RuntimeException('An image edit instruction is required.');
        }

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
                    || in_array($extension, ['png', 'jpg', 'jpeg', 'webp'], true);
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
            '🎨 Image edit started successfully. Image Job ID: <strong>%d</strong>. I can check the image status with this ID.',
            $recordId,
        );
    }

    public function status(array $arguments): string
    {
        $this->ensureAvailable();

        $recordId = (int) ($arguments['record_id'] ?? 0);
        if ($recordId <= 0) {
            throw new RuntimeException('record_id is required.');
        }

        $record = AiChatProImageModel::query()
            ->whereKey($recordId)
            ->where('user_id', Auth::id())
            ->first();

        if (! $record) {
            throw new RuntimeException('Image job was not found or does not belong to your account.');
        }

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

    private function ensureAvailable(): void
    {
        if (! Auth::check()) {
            throw new RuntimeException('Please sign in to edit images from Chat Pro.');
        }

        if (! MarketplaceHelper::isRegistered('ai-chat-pro-image-chat')) {
            throw new RuntimeException('AI image editing is not available on this installation.');
        }
    }
}
