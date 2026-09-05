<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Services;

use App\Extensions\AIChatPro\System\Models\AIChatProDocument;
use App\Models\UserOpenaiChat;
use App\Models\SettingTwo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class DocumentRepository
{
    private const DEFAULT_CHUNK_SIZE = 1800;

    private const DEFAULT_CHUNK_OVERLAP = 250;

    public function __construct(private readonly DocumentReaderService $reader) {}

    public function storeUploadedDocument(UserOpenaiChat $chat, UploadedFile $file, ?int $userId = null): AIChatProDocument
    {
        $disk = 'public';
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'txt');
        $fileName = Str::uuid()->toString() . '.' . $extension;
        $storagePath = 'ai-chat-pro-documents/' . $fileName;

        Storage::disk($disk)->put($storagePath, fopen($file->getRealPath(), 'rb'));

        $referenceUrl = '/uploads/' . $storagePath;

        if (SettingTwo::getCache()?->ai_image_storage === 's3') {
            $s3Path = Storage::disk('s3')->putFileAs('ai-chat-pro-documents', $file, $fileName);
            if (! is_string($s3Path) || $s3Path === '') {
                throw new RuntimeException('Unable to store document on S3.');
            }

            Storage::disk($disk)->delete($storagePath);
            $referenceUrl = Storage::disk('s3')->url($s3Path);
            $storagePath = $s3Path;
            $disk = 's3';
        }

        $payload = $this->reader->readUploadedFile($file);

        $document = AIChatProDocument::create([
            'user_openai_chat_id' => $chat->id,
            'user_id' => $userId,
            'status' => 'ready',
            'original_name' => $payload['original_name'],
            'file_name' => $fileName,
            'file_extension' => $payload['extension'],
            'mime_type' => $payload['mime_type'],
            'file_size' => $file->getSize() ?: 0,
            'storage_disk' => $disk,
            'storage_path' => $storagePath,
            'reference_url' => $referenceUrl,
            'content_hash' => sha1_file($file->getRealPath()) ?: null,
            'extracted_text' => $payload['extracted_text'],
            'chunks' => $this->chunkText($payload['extracted_text']),
            'metadata' => $payload['metadata'],
            'processed_at' => now(),
        ]);

        $this->syncChatReference($chat, $document);

        return $document;
    }

    public function attachReferencedDocumentsToChat(int $chatId, ?string $paths, ?string $names = null, ?int $userId = null): bool
    {
        if (blank($paths)) {
            return false;
        }

        $chat = UserOpenaiChat::find($chatId);
        if (! $chat) {
            return false;
        }

        $pathList = array_values(array_filter(array_map('trim', explode(',', (string) $paths))));
        $nameList = array_values(array_map('trim', explode(',', (string) $names)));
        $attached = false;

        foreach ($pathList as $index => $path) {
            $originalName = $nameList[$index] ?? basename(parse_url($path, PHP_URL_PATH) ?: $path);

            if ($originalName === '') {
                $originalName = 'document';
            }

            if (AIChatProDocument::query()
                ->where('user_openai_chat_id', $chatId)
                ->where('reference_url', $path)
                ->exists()) {
                continue;
            }

            $localPath = null;
            try {
                $localPath = $this->resolveReadablePath($path, $originalName);
                $payload = $this->reader->readFile($localPath, $originalName, mime_content_type($localPath) ?: null);

                $document = AIChatProDocument::create([
                    'user_openai_chat_id' => $chatId,
                    'user_id' => $userId,
                    'status' => 'ready',
                    'original_name' => $payload['original_name'],
                    'file_name' => basename($localPath),
                    'file_extension' => $payload['extension'],
                    'mime_type' => $payload['mime_type'],
                    'file_size' => is_file($localPath) ? filesize($localPath) ?: 0 : 0,
                    'storage_disk' => filter_var($path, FILTER_VALIDATE_URL) ? 'remote' : 'public',
                    'storage_path' => filter_var($path, FILTER_VALIDATE_URL) ? null : ltrim($path, '/'),
                    'reference_url' => $path,
                    'content_hash' => is_file($localPath) ? sha1_file($localPath) ?: null : null,
                    'extracted_text' => $payload['extracted_text'],
                    'chunks' => $this->chunkText($payload['extracted_text']),
                    'metadata' => $payload['metadata'],
                    'processed_at' => now(),
                ]);

                $this->syncChatReference($chat, $document);
                $attached = true;
            } finally {
                if ($localPath && str_starts_with($localPath, sys_get_temp_dir()) && is_file($localPath)) {
                    @unlink($localPath);
                }
            }
        }

        return $attached;
    }

    public function getDocumentsForChat(int $chatId): Collection
    {
        return AIChatProDocument::query()
            ->where('user_openai_chat_id', $chatId)
            ->where('status', 'ready')
            ->orderBy('id')
            ->get();
    }

    public function chunkText(string $text, int $chunkSize = self::DEFAULT_CHUNK_SIZE, int $overlap = self::DEFAULT_CHUNK_OVERLAP): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $chunks = [];
        $length = mb_strlen($text);
        $offset = 0;

        while ($offset < $length) {
            $slice = mb_substr($text, $offset, $chunkSize + 300);
            $cut = min($chunkSize, mb_strlen($slice));
            $candidate = mb_substr($slice, 0, $cut);

            foreach (["\n\n", "\n", '. ', '! ', '? ', ' '] as $delimiter) {
                $position = mb_strrpos($candidate, $delimiter);
                if ($position !== false && $position > (int) ($chunkSize * 0.6)) {
                    $cut = $position + mb_strlen($delimiter);
                    break;
                }
            }

            $chunk = trim(mb_substr($slice, 0, $cut));
            if ($chunk !== '') {
                $chunks[] = [
                    'index' => count($chunks),
                    'content' => $chunk,
                    'length' => mb_strlen($chunk),
                ];
            }

            $offset += max(1, $cut - $overlap);
        }

        return $chunks;
    }

    private function syncChatReference(UserOpenaiChat $chat, AIChatProDocument $document): void
    {
        $chat->reference_url = $document->reference_url;
        $chat->doc_name = $document->original_name;
        $chat->openai_vector_id = null;
        $chat->openai_file_id = null;
        $chat->save();
    }

    private function resolveReadablePath(string $path, string $originalName): string
    {
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            $response = Http::timeout(60)->get($path);
            if (! $response->successful()) {
                throw new RuntimeException('Unable to download document from remote storage.');
            }

            $extension = pathinfo($originalName, PATHINFO_EXTENSION) ?: pathinfo(parse_url($path, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION);
            $tempPath = tempnam(sys_get_temp_dir(), 'aicp_doc_');
            $targetPath = $tempPath . ($extension ? '.' . $extension : '');
            file_put_contents($targetPath, $response->body());
            @unlink($tempPath);

            return $targetPath;
        }

        $normalized = ltrim($path, '/\\');
        $candidates = [
            public_path($normalized),
            public_path('/' . $normalized),
            public_path('/uploads/' . basename($normalized)),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Unable to locate the attached document on disk.');
    }
}