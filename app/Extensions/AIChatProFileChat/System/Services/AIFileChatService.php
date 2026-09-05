<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProFileChat\System\Services;

use App\Models\UserOpenaiChat;
use App\Services\Ai\OpenAI\FileSearchService;
use Illuminate\Support\Facades\Log;
use Throwable;

class AIFileChatService
{
    private array $files;

    private ?string $chatId;

    public function __construct(?string $pdfPaths = '', ?string $chatId = null)
    {
        $this->files = $this->filterValidFiles($pdfPaths);
        $this->chatId = $chatId;
    }

    /**
     * Ensure all provided files exist and are valid.
     */
    private function filterValidFiles(?string $pdfPaths = ''): array
    {
        if (empty($pdfPaths)) {
            return [];
        }

        $paths = array_map('trim', explode(',', $pdfPaths));

        return array_values(array_filter($paths, static function ($path) {
            // If it's a full URL, consider it valid
            if (filter_var($path, FILTER_VALIDATE_URL)) {
                return true;
            }

            // Otherwise, check if the file exists in public path
            return file_exists(public_path($path));
        }));
    }

    /**
     * Validates and decides which analyzer to run.
     */
    public function validateAndAnalyzeFile(): bool
    {
        if (empty($this->files)) {

            try {
                $chat = UserOpenaiChat::findOrFail($this->chatId);

                $messages = $chat->messages()->latest()->take(10)->get();
                $containsPdfData = $messages->contains(function ($message) {
                    return ! empty($message->pdfPath);
                });

                if (! $containsPdfData) {
                    $chat->update([
                        'openai_vector_id' => '',
                        'openai_file_id'   => '',
                        'reference_url'    => '',
                    ]);
                }

                if ($chat->openai_vector_id && $chat->openai_file_id && $chat->reference_url) {
                    return true;
                }
            } catch (Throwable $e) {
                Log::error('AIFileChatService no files error', [
                    'chat_id' => $this->chatId,
                    'error'   => $e->getMessage(),
                ]);

                return false;
            }

            return false;
        }

        if ((int) setting('openai_file_search', 0) === 1
            || (int) setting('chatpro_file_chat_allowed', 1) === 1) {
            return $this->storeDocForChatResponseApi();
        }

        return false;
    }

    /**
     * Store document and attach to chat record.
     */
    private function storeDocForChatResponseApi(): bool
    {
        try {
            if (empty($this->chatId)) {
                return false;
            }

            $chat = UserOpenaiChat::findOrFail($this->chatId);

            $filePath = $this->files[0];

            if (! filter_var($filePath, FILTER_VALIDATE_URL)) {
                $filePath = public_path($filePath);
            }
            $fileSearchService = new FileSearchService;

            $fileId = $fileSearchService->uploadFile($filePath);
            $vectors = $fileSearchService->createVectorStore(basename($filePath), $fileId);

            if (empty($fileId) || empty($vectors?->id)) {
                return false;
            }

            $fileSearchService->waitForVectorStoreFilesReady($vectors->id);

            $chat->update([
                'openai_vector_id' => $vectors->id,
                'openai_file_id'   => $fileId,
                'reference_url'    => $this->files[0],
            ]);

            return true;
        } catch (Throwable $e) {
            Log::error('AIFileChatService error', [
                'chat_id' => $this->chatId,
                'error'   => $e->getMessage(),
            ]);

            return false;
        }
    }
}
