<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Services;

use App\Extensions\AIChatPro\System\Models\AIChatProDocument;
use App\Helpers\Classes\ChatAttachmentReferences;
use App\Models\UserOpenaiChat;
use App\Models\SettingTwo;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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

    public function readBinaryForOwnedDocument(AIChatProDocument $document, int $userId): string
    {
        if ((int) $document->user_id !== $userId || $document->status !== 'ready') {
            throw new RuntimeException('The requested document is not available to this user.');
        }

        if (! $this->findAccessibleChat((int) $document->user_openai_chat_id, $userId)) {
            throw new RuntimeException('The requested document is not available to this user.');
        }

        $storagePath = trim((string) $document->storage_path);
        if ($storagePath !== '' && $document->storage_disk !== 'remote') {
            $disk = Storage::disk((string) $document->storage_disk);
            if (! $disk->exists($storagePath)) {
                throw new RuntimeException('The stored document is missing.');
            }

            $contents = $disk->get($storagePath);
        } else {
            $reference = trim((string) $document->reference_url);
            if ($reference === '') {
                throw new RuntimeException('The stored document has no readable source.');
            }

            $isRemote = $this->isRemoteUrl($reference);
            $localPath = $this->resolveReadablePath($reference, (string) $document->original_name);
            try {
                $contents = file_get_contents($localPath);
            } finally {
                if ($isRemote) {
                    @unlink($localPath);
                }
            }
        }

        if (! is_string($contents) || $contents === '') {
            throw new RuntimeException('The stored document could not be read.');
        }

        if (strlen($contents) > 50 * 1024 * 1024) {
            throw new RuntimeException('The stored document exceeds the 50 MB safety limit.');
        }

        return $contents;
    }

    public function attachReferencedDocumentsToChat(int $chatId, array|string|null $paths, array|string|null $names = null, ?int $userId = null): bool
    {
        $pathList = ChatAttachmentReferences::parse($paths);
        if ($pathList === []) {
            return false;
        }

        $chat = $this->findAccessibleChat($chatId, $userId);
        if (! $chat) {
            return false;
        }

        $nameList = ChatAttachmentReferences::parse($names);
        if (count($nameList) !== count($pathList)) {
            $nameList = [];
        }
        $attached = false;

        foreach ($pathList as $index => $path) {
            $attachmentService = app(AIChatProAttachmentService::class);
            $privateReference = $attachmentService->isPrivateReference($path);
            $privateAttachment = $privateReference
                ? $attachmentService->findOwnedReference($path, $userId)
                : null;
            $originalName = $nameList[$index]
                ?? $privateAttachment?->original_name
                ?? basename(parse_url($path, PHP_URL_PATH) ?: $path);

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
            $stage = 'resolve_attachment';
            if ($privateReference && $privateAttachment === null) {
                throw new RuntimeException('The private attachment is not available to this user.');
            }

            try {
                if ($privateAttachment !== null) {
                    $contents = $attachmentService->readOwnedReference($path, $userId);
                    if (! is_string($contents)) {
                        throw new RuntimeException('The private attachment could not be read.');
                    }

                    $localPath = $this->writeTemporaryDocument($contents, $originalName);
                } else {
                    $localPath = $this->resolveReadablePath($path, $originalName);
                }
                $stage = 'extract_document';
                $payload = $this->reader->readFile($localPath, $originalName, mime_content_type($localPath) ?: null);

                $stage = 'persist_document';
                $document = AIChatProDocument::create([
                    'user_openai_chat_id' => $chatId,
                    'user_id' => $userId,
                    'status' => 'ready',
                    'original_name' => $payload['original_name'],
                    'file_name' => basename($localPath),
                    'file_extension' => $payload['extension'],
                    'mime_type' => $payload['mime_type'],
                    'file_size' => is_file($localPath) ? filesize($localPath) ?: 0 : 0,
                    'storage_disk' => $privateAttachment?->storage_disk
                        ?? ($this->isRemoteUrl($path) ? 'remote' : 'public'),
                    'storage_path' => $privateAttachment?->storage_path
                        ?? ($this->isRemoteUrl($path) ? null : ltrim($path, '/')),
                    'reference_url' => $path,
                    'content_hash' => is_file($localPath) ? sha1_file($localPath) ?: null : null,
                    'extracted_text' => $payload['extracted_text'],
                    'chunks' => $this->chunkText($payload['extracted_text']),
                    'metadata' => $payload['metadata'],
                    'processed_at' => now(),
                ]);

                $stage = 'sync_chat_reference';
                $this->syncChatReference($chat, $document);
                if ($privateAttachment !== null) {
                    $stage = 'update_attachment_status';
                    $privateAttachment->forceFill([
                        'processing_status' => 'completed',
                        'extraction_status' => 'completed',
                    ])->save();
                }
                $attached = true;
            } catch (\Throwable $exception) {
                Log::warning('A referenced chat document could not be attached.', [
                    'chat_id' => $chatId,
                    'user_id' => $userId,
                    'file_extension' => strtolower(pathinfo($originalName, PATHINFO_EXTENSION) ?: 'unknown'),
                    'stage' => $stage,
                    'exception_class' => $exception::class,
                    'sql_state' => $exception instanceof QueryException ? ($exception->errorInfo[0] ?? null) : null,
                    'driver_code' => $exception instanceof QueryException ? ($exception->errorInfo[1] ?? null) : null,
                ]);
                if ($privateAttachment !== null) {
                    $privateAttachment->forceFill([
                        'processing_status' => 'failed',
                        'extraction_status' => 'failed',
                    ])->save();
                }

                throw $exception;
            } finally {
                if ($localPath && str_starts_with($localPath, sys_get_temp_dir()) && is_file($localPath)) {
                    @unlink($localPath);
                }
            }
        }

        return $attached;
    }

    private function writeTemporaryDocument(string $contents, string $originalName): string
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'aicp_doc_');
        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to allocate temporary document storage.');
        }

        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $targetPath = $extension !== '' ? $temporaryPath.'.'.$extension : $temporaryPath;
        if ($targetPath !== $temporaryPath && ! rename($temporaryPath, $targetPath)) {
            @unlink($temporaryPath);
            throw new RuntimeException('Unable to prepare the temporary document.');
        }

        if (file_put_contents($targetPath, $contents, LOCK_EX) !== strlen($contents)) {
            @unlink($targetPath);
            throw new RuntimeException('Unable to write the temporary document.');
        }

        return $targetPath;
    }

    public function getDocumentsForChat(int $chatId): Collection
    {
        $chat = $this->findAccessibleChat($chatId, Auth::id());
        if (! $chat) {
            return collect();
        }

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

            $effectiveOverlap = $cut <= $overlap
                ? 0
                : min($overlap, intdiv($cut, 2));
            $offset += max(1, $cut - $effectiveOverlap);
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
        if ($this->isRemoteUrl($path)) {
            $remote = $this->assertSafeRemoteUrl($path);
            $options = ['allow_redirects' => false];

            // Pin the connection to the IP address that passed the public-IP check.
            // This closes the DNS-rebinding gap between validation and the HTTP request.
            if (defined('CURLOPT_RESOLVE')) {
                $resolvedIp = str_contains($remote['ip'], ':') ? '[' . $remote['ip'] . ']' : $remote['ip'];
                $options['curl'] = [
                    CURLOPT_RESOLVE => [sprintf('%s:%d:%s', $remote['host'], $remote['port'], $resolvedIp)],
                ];
            }

            $response = Http::timeout(60)
                ->withOptions($options)
                ->get($path);

            if (! $response->successful()) {
                throw new RuntimeException('Unable to download document from remote storage.');
            }

            $body = $response->body();
            if (strlen($body) > 50 * 1024 * 1024) {
                throw new RuntimeException('The remote document exceeds the 50 MB safety limit.');
            }

            $extension = pathinfo($originalName, PATHINFO_EXTENSION) ?: pathinfo(parse_url($path, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION);
            $tempPath = tempnam(sys_get_temp_dir(), 'aicp_doc_');
            $targetPath = $tempPath . ($extension ? '.' . $extension : '');
            file_put_contents($targetPath, $body);
            @unlink($tempPath);

            return $targetPath;
        }

        $normalized = ltrim(str_replace('\\', '/', $path), '/');
        if ($normalized === '' || str_contains($normalized, '../') || str_starts_with($normalized, '..')) {
            throw new RuntimeException('Invalid local document path.');
        }

        $publicRoot = realpath(public_path());
        if (! $publicRoot) {
            throw new RuntimeException('Public storage root is unavailable.');
        }

        $candidates = [
            public_path($normalized),
            public_path('uploads/' . basename($normalized)),
        ];

        foreach ($candidates as $candidate) {
            $realPath = realpath($candidate);
            if (! $realPath || ! is_file($realPath)) {
                continue;
            }

            if ($realPath === $publicRoot || str_starts_with($realPath, $publicRoot . DIRECTORY_SEPARATOR)) {
                return $realPath;
            }
        }

        throw new RuntimeException('Unable to locate the attached document on disk.');
    }

    private function isRemoteUrl(string $path): bool
    {
        $parts = parse_url($path);

        return is_array($parts)
            && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            && ! empty($parts['host']);
    }

    private function findAccessibleChat(int $chatId, ?int $userId): ?UserOpenaiChat
    {
        if ($chatId <= 0) {
            return null;
        }

        $query = UserOpenaiChat::query()->whereKey($chatId);

        if ($userId !== null) {
            $user = Auth::user();
            $sharedTeamId = $user?->id === $userId && $user?->team?->is_shared ? $user?->team?->id : null;
            $createdTeamId = $user?->id === $userId ? $user?->myCreatedTeam?->id : null;

            return $query
                ->where(function ($builder) use ($userId, $sharedTeamId, $createdTeamId): void {
                    $builder->where('user_id', $userId);

                    if ($sharedTeamId) {
                        $builder->orWhere('team_id', $sharedTeamId);
                    }

                    if ($createdTeamId) {
                        $builder->orWhere('team_id', $createdTeamId);
                    }
                })
                ->first();
        }

        $guestChatIds = array_map('intval', (array) session('ai_chat_pro_guest_chat_ids', []));
        if (! in_array($chatId, $guestChatIds, true)) {
            return null;
        }

        return $query
            ->whereNull('user_id')
            ->where('is_guest', true)
            ->first();
    }

    /**
     * @return array{host: string, port: int, ip: string}
     */
    private function assertSafeRemoteUrl(string $url): array
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $port = isset($parts['port']) ? (int) $parts['port'] : null;

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new RuntimeException('Unsupported remote document URL.');
        }

        if (isset($parts['user']) || isset($parts['pass']) || ($port !== null && ! in_array($port, [80, 443], true))) {
            throw new RuntimeException('Unsafe remote document URL.');
        }

        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            throw new RuntimeException('Local network URLs are not allowed.');
        }

        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        } else {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
            foreach ($records as $record) {
                if (! empty($record['ip'])) {
                    $ips[] = $record['ip'];
                }
                if (! empty($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        if ($ips === []) {
            throw new RuntimeException('Unable to resolve remote document host.');
        }

        $publicIps = [];
        foreach (array_unique($ips) as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException('Private or reserved network URLs are not allowed.');
            }

            $publicIps[] = $ip;
        }

        // Prefer IPv4 for broader cURL resolver compatibility, then fall back to IPv6.
        usort($publicIps, static fn (string $left, string $right): int =>
            (int) str_contains($left, ':') <=> (int) str_contains($right, ':')
        );

        return [
            'host' => $host,
            'port' => $port ?? ($scheme === 'https' ? 443 : 80),
            'ip'   => $publicIps[0],
        ];
    }

}