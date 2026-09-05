<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Services;

use App\Extensions\AIChatPro\System\Models\AIChatProDocument;
use App\Models\UserOpenaiChat;
use Illuminate\Http\UploadedFile;

class GaradMultimodalService
{
    public function __construct(
        private readonly DocumentRepository $documents,
        private readonly DocumentChatService $documentChat,
    ) {}

    public function ingestUploadedFile(UserOpenaiChat $chat, UploadedFile $file, ?int $userId = null): AIChatProDocument
    {
        return $this->documents->storeUploadedDocument($chat, $file, $userId);
    }

    public function attachConversationAssets(
        int $chatId,
        array|string|null $documentPaths = null,
        array|string|null $documentNames = null,
        array|string|null $imagePaths = null,
        array|string|null $imageNames = null,
        ?int $userId = null,
    ): bool {
        $attached = false;

        $attached = $this->attachAssetGroup($chatId, $documentPaths, $documentNames, $userId) || $attached;
        $attached = $this->attachAssetGroup($chatId, $imagePaths, $imageNames, $userId) || $attached;

        return $attached;
    }

    public function buildSystemContextMessage(int $chatId, string $prompt, ?string $categorySlug = null): ?string
    {
        $context = $this->documentChat->buildContextForChat($chatId, $prompt);

        if (! $context) {
            return null;
        }

        if ($categorySlug === 'ai_webchat') {
            return "You are a Web Page Analyzer assistant. When referring to content from a specific website or link, please include a brief summary or context of the content. If users inquire about the content or purpose of the website/link, provide assistance professionally without explicitly mentioning the content. Website/link content: \n{$context}";
        }

        return "You are a File Analyzer assistant. Use the provided multimodal context as the primary source of truth for this reply. The context may come from documents, screenshots, charts, tables, scanned files, forms, receipts, CVs, invoices, or other uploaded assets. If the answer is not fully supported by the context, say so clearly. Multimodal context: \n{$context}";
    }

    private function attachAssetGroup(int $chatId, array|string|null $paths, array|string|null $names, ?int $userId): bool
    {
        $normalizedPaths = $this->normalizeList($paths);
        if ($normalizedPaths === []) {
            return false;
        }

        $normalizedNames = $this->normalizeList($names);

        return $this->documents->attachReferencedDocumentsToChat(
            $chatId,
            implode(',', $normalizedPaths),
            $normalizedNames === [] ? null : implode(',', $normalizedNames),
            $userId,
        );
    }

    private function normalizeList(array|string|null $value): array
    {
        if ($value === null) {
            return [];
        }

        $items = is_array($value) ? $value : explode(',', $value);

        return array_values(array_filter(array_map(static function ($item): string {
            return trim((string) $item);
        }, $items), static fn (string $item): bool => $item !== ''));
    }
}