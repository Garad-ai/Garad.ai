<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Services;

use App\Extensions\AIChatPro\System\Models\AIChatProDocument;
use App\Helpers\Classes\ChatAttachmentReferences;
use App\Models\UserOpenaiChat;
use App\Models\UserOpenaiChatMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

final class GaradMultimodalService
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

    public function hasConversationAssets(int $chatId): bool
    {
        return $this->documents->getDocumentsForChat($chatId)->isNotEmpty();
    }

    public function buildSystemContextMessage(int $chatId, string $prompt, ?string $categorySlug = null): ?string
    {
        // Self-heal legacy Chat Pro uploads. MagicAI already stores uploaded paths in
        // user_openai_chat_messages; ingest them into the multimodal repository once so
        // later follow-up messages can still read the user's PDF, Word, Excel, slide deck,
        // screenshot, logo, or image even when the current request no longer sends pdfpath/images.
        $legacyAttachmentAnalysisFailed = $this->hydrateLegacyConversationAssets($chatId);

        $context = $this->documentChat->buildContextForChat($chatId, $prompt);
        if (! $context) {
            return $legacyAttachmentAnalysisFailed
                ? 'You are Garad AI File Analyzer. A previously attached file could not be recovered for analysis. Tell the user that its contents are unavailable and ask them to re-upload it. Do not guess or claim to have inspected it.'
                : null;
        }

        if ($categorySlug === 'ai_webchat') {
            return "You are a Web Page Analyzer assistant. When referring to content from a specific website or link, include a concise summary or context. Website/link content:\n{$context}";
        }

        $failureNotice = $legacyAttachmentAnalysisFailed
            ? "\n\nA previously attached file could not be recovered. Tell the user which available context you can analyze and that the unavailable file must be re-uploaded. Do not guess its contents."
            : '';

        return "You are Garad AI's File Analyzer. Use the uploaded multimodal context as the primary source of truth. The context may come from PDFs, Word files, Excel sheets, presentations, text files, screenshots, charts, scanned documents, logos, images, audio, or video transcripts and frames. Read and analyze the supplied asset rather than ignoring it. If the context says extraction, transcription, or vision is unavailable, clearly tell the user the file could not be fully inspected; do not imply that you watched, heard, or read unavailable content. If a fact is not supported by the uploaded context, say so clearly.\n\nMultimodal context:\n{$context}{$failureNotice}";
    }

    private function hydrateLegacyConversationAssets(int $chatId): bool
    {
        if ($chatId <= 0) {
            return false;
        }

        $analysisFailed = false;

        try {
            $chat = UserOpenaiChat::query()->whereKey($chatId)->first();
            if (! $chat) {
                return false;
            }
            if (Auth::check() && (int) $chat->user_id !== (int) Auth::id()) {
                return false;
            }

            $userId = Auth::id() ?: $chat->user_id;
            $messages = UserOpenaiChatMessage::query()
                ->where('user_openai_chat_id', $chatId)
                ->where(function ($q) {
                    $q->whereNotNull('pdfPath')
                        ->orWhereNotNull('images');
                })
                ->latest('id')
                ->limit(12)
                ->get()
                ->reverse();

            foreach ($messages as $message) {
                $pdfPath = trim((string) ($message->pdfPath ?? ''));
                $pdfName = trim((string) ($message->pdfName ?? ''));
                if ($pdfPath !== '') {
                    try {
                        $this->attachAssetGroup($chatId, $pdfPath, $pdfName !== '' ? $pdfName : null, $userId);
                    } catch (\Throwable $exception) {
                        $analysisFailed = true;
                        Log::warning('A legacy chat document could not be analyzed.', [
                            'chat_id' => $chatId,
                            'user_id' => $userId,
                            'exception_class' => $exception::class,
                        ]);
                    }
                }

                $images = $this->normalizeList($message->images ?? null);
                if ($images !== []) {
                    try {
                        $this->attachAssetGroup($chatId, $images, null, $userId);
                    } catch (\Throwable $exception) {
                        $analysisFailed = true;
                        Log::warning('A legacy chat image could not be analyzed.', [
                            'chat_id' => $chatId,
                            'user_id' => $userId,
                            'exception_class' => $exception::class,
                        ]);
                    }
                }
            }
        } catch (\Throwable $exception) {
            $analysisFailed = true;
            Log::warning('Legacy chat attachments could not be restored for analysis.', [
                'chat_id' => $chatId,
                'user_id' => Auth::id(),
                'exception_class' => $exception::class,
            ]);
        }

        return $analysisFailed;
    }

    private function attachAssetGroup(int $chatId, array|string|null $paths, array|string|null $names, ?int $userId): bool
    {
        $normalizedPaths = ChatAttachmentReferences::parse($paths);
        if ($normalizedPaths === []) {
            return false;
        }

        $normalizedNames = ChatAttachmentReferences::parse($names);

        return $this->documents->attachReferencedDocumentsToChat(
            $chatId,
            $normalizedPaths,
            $normalizedNames === [] ? null : $normalizedNames,
            $userId,
        );
    }

    private function normalizeList(array|string|null $value): array
    {
        return ChatAttachmentReferences::parse($value);
    }
}
