<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Http\Controllers;

use App\Extensions\AIChatPro\System\Models\AIChatProAttachment;
use App\Extensions\AIChatPro\System\Models\AIChatProDocument;
use App\Extensions\AIChatPro\System\Services\AIChatProStorageQuotaService;
use App\Http\Controllers\Controller;
use finfo;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use ZipArchive;

class AIChatProAttachmentController extends Controller
{
    private const STORAGE_DISK = 'ai_chat_pro_attachments';

    private const MAX_FILE_SIZE = 15728640;

    public function store(Request $request, AIChatProStorageQuotaService $storageQuota): JsonResponse
    {
        $validated = $request->validate([
            'attachment_id' => ['required', 'uuid'],
            'files'         => ['required', 'array', 'size:1'],
            'files.0.name'  => ['required', 'string', 'max:255'],
            'files.0.data'  => ['required', 'string', 'max:20971600'],
        ]);

        $uuid = strtolower($validated['attachment_id']);
        $file = $validated['files'][0];
        [$name, $extension] = $this->normalizeFilename($file['name']);
        [$binary, $mimeType] = $this->decodeAndValidate($file['data'], $extension);
        $checksum = hash('sha256', $binary);
        $size = strlen($binary);
        $ownerId = (int) $request->user()->getAuthIdentifier();

        return $storageQuota->withUserLock($ownerId, function () use (
            $request,
            $storageQuota,
            $uuid,
            $name,
            $extension,
            $binary,
            $mimeType,
            $size,
            $checksum,
            $ownerId
        ): JsonResponse {
            $existing = AIChatProAttachment::query()->where('uuid', $uuid)->first();
            if ($existing !== null) {
                return $this->respondForExisting($existing, $ownerId, $name, $mimeType, $size, $checksum);
            }

            $storageQuota->assertCanStore($ownerId, $size, $request->user()->activePlan());

            $storagePath = $ownerId . '/' . $uuid . '/' . $checksum . '.' . $extension;
            $disk = Storage::disk(self::STORAGE_DISK);

            try {
                if (! $disk->exists($storagePath)) {
                    $storedPath = $disk->put($storagePath, $binary);
                    if ($storedPath === false) {
                        throw new RuntimeException('Private attachment storage failed.');
                    }
                }

                $storedSize = $disk->size($storagePath);
                $storedChecksum = hash('sha256', $disk->get($storagePath));
                if ($storedSize < 1 || $storedSize !== $size || ! hash_equals($checksum, $storedChecksum)) {
                    throw new RuntimeException('Private attachment storage verification failed.');
                }
            } catch (Throwable $exception) {
                $disk->delete($storagePath);
                report($exception);

                return response()->json(['message' => 'The attachment could not be stored.'], 500);
            }

            try {
                $attachment = AIChatProAttachment::query()->create([
                    'uuid'              => $uuid,
                    'user_id'           => $ownerId,
                    'original_name'     => $name,
                    'mime_type'         => $mimeType,
                    'file_size'         => $size,
                    'storage_disk'      => self::STORAGE_DISK,
                    'storage_path'      => $storagePath,
                    'checksum'          => $checksum,
                    'upload_status'     => 'uploaded',
                    'processing_status' => 'pending',
                    'extraction_status' => 'pending',
                ]);
            } catch (QueryException $exception) {
                $existing = AIChatProAttachment::query()->where('uuid', $uuid)->first();
                if ($existing !== null) {
                    if ($existing->storage_path !== $storagePath) {
                        $disk->delete($storagePath);
                    }

                    return $this->respondForExisting($existing, $ownerId, $name, $mimeType, $size, $checksum);
                }

                if (! AIChatProAttachment::query()->where('storage_path', $storagePath)->exists()) {
                    $disk->delete($storagePath);
                }
                report($exception);

                return response()->json(['message' => 'The attachment could not be recorded.'], 500);
            } catch (Throwable $exception) {
                if (! AIChatProAttachment::query()->where('storage_path', $storagePath)->exists()) {
                    $disk->delete($storagePath);
                }
                report($exception);

                return response()->json(['message' => 'The attachment could not be recorded.'], 500);
            }

            return $this->attachmentResponse($attachment, false, 201);
        });
    }

    public function destroy(
        Request $request,
        string $attachment,
        AIChatProStorageQuotaService $storageQuota
    ): JsonResponse {
        $ownerId = (int) $request->user()->getAuthIdentifier();

        try {
            return $storageQuota->withUserLock($ownerId, function () use ($attachment, $ownerId): JsonResponse {
                $record = AIChatProAttachment::query()
                    ->where('uuid', strtolower($attachment))
                    ->where('user_id', $ownerId)
                    ->firstOrFail();
                $disk = Storage::disk($record->storage_disk);

                if ($disk->exists($record->storage_path) && ! $disk->delete($record->storage_path)) {
                    throw new RuntimeException('Private attachment storage deletion failed.');
                }

                AIChatProDocument::query()
                    ->where('user_id', $ownerId)
                    ->where('reference_url', '/dashboard/user/ai-chat-pro/attachments/' . $record->uuid)
                    ->delete();

                if (! $record->delete()) {
                    throw new RuntimeException('Private attachment record deletion failed.');
                }

                return response()->json(['ok' => true]);
            });
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'The attachment could not be deleted.'], 500);
        }
    }

    public function show(Request $request, string $attachment): StreamedResponse
    {
        $record = AIChatProAttachment::query()
            ->where('uuid', $attachment)
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->firstOrFail();

        abort_unless($record->upload_status === 'uploaded', 404);

        $disk = Storage::disk($record->storage_disk);
        abort_unless(
            $disk->exists($record->storage_path)
                && $disk->size($record->storage_path) === (int) $record->file_size,
            404
        );

        $inlineTypes = [
            'application/pdf',
            'text/plain',
            'text/csv',
            'image/png',
            'image/jpeg',
            'image/webp',
            'image/gif',
            'image/bmp',
            'audio/mpeg',
            'audio/mp4',
            'audio/x-m4a',
            'audio/wav',
            'audio/x-wav',
            'audio/vnd.wave',
            'audio/ogg',
            'application/ogg',
            'audio/flac',
            'audio/x-flac',
            'audio/aac',
            'audio/vnd.dlna.adts',
            'audio/x-aac',
            'video/mp4',
            'application/mp4',
            'video/quicktime',
            'video/x-matroska',
            'application/x-matroska',
            'video/x-msvideo',
            'video/avi',
            'video/x-m4v',
            'video/webm',
        ];
        $inline = in_array(strtolower($record->mime_type), $inlineTypes, true)
            && ! $request->boolean('download');
        $stream = $disk->readStream($record->storage_path);
        abort_if($stream === false, 404);

        $hashContext = hash_init('sha256');
        $verifiedSize = hash_update_stream($hashContext, $stream);
        $verifiedChecksum = hash_final($hashContext);
        if ($verifiedSize !== (int) $record->file_size
            || ! hash_equals($record->checksum, $verifiedChecksum)
            || ! rewind($stream)) {
            fclose($stream);
            abort(500, 'The stored attachment failed integrity verification.');
        }

        $disposition = HeaderUtils::makeDisposition(
            $inline ? 'inline' : 'attachment',
            $record->original_name,
            'attachment'
        );

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type'            => $record->mime_type,
            'Content-Length'          => (string) $record->file_size,
            'Content-Disposition'     => $disposition,
            'Cache-Control'           => 'private, no-store',
            'X-Content-Type-Options'  => 'nosniff',
            'Content-Security-Policy' => "sandbox; default-src 'none'",
        ]);
    }

    private function normalizeFilename(string $filename): array
    {
        $filename = preg_replace('/[\x00-\x1F\x7F]/u', '', str_replace('\\', '/', trim($filename))) ?? '';
        $filename = basename($filename);
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $supportedExtensions = [
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'txt', 'csv', 'json', 'xml', 'html', 'htm', 'md', 'markdown',
            'png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp',
            'mp3', 'mpeg', 'mpga', 'm4a', 'wav', 'ogg', 'flac', 'aac',
            'mp4', 'mov', 'mkv', 'avi', 'm4v', 'webm',
        ];

        if ($filename === '' || strlen($filename) > 255 || ! in_array($extension, $supportedExtensions, true)) {
            throw ValidationException::withMessages(['files.0.name' => 'This attachment type is not supported.']);
        }

        return [$filename, $extension];
    }

    private function decodeAndValidate(string $dataUri, string $extension): array
    {
        if (! preg_match('/\Adata:[^;,]{1,120};base64,([A-Za-z0-9+\/]*={0,2})\z/D', $dataUri, $matches)) {
            throw ValidationException::withMessages(['files.0.data' => 'The attachment must be a base64 data URI.']);
        }

        $binary = base64_decode($matches[1], true);
        if ($binary === false || $binary === '' || strlen($binary) > self::MAX_FILE_SIZE) {
            throw ValidationException::withMessages(['files.0.data' => 'The attachment is empty or exceeds the 15 MB limit.']);
        }

        $detectedMime = (new finfo(FILEINFO_MIME_TYPE))->buffer($binary);
        $mimeType = match ($extension) {
            'pdf'  => $this->validPdf($binary, $detectedMime) ? 'application/pdf' : null,
            'doc', 'xls', 'ppt' => $this->validLegacyOfficeDocument($binary)
                ? $this->legacyOfficeMime($extension)
                : null,
            'txt', 'md', 'markdown' => $this->validText($binary, [
                'text/plain', 'text/markdown', 'application/octet-stream',
            ]) ? 'text/plain' : null,
            'csv'  => $this->validText($binary, [
                'text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel', 'application/octet-stream',
            ]) ? 'text/csv' : null,
            'json' => $this->validText($binary, ['application/json', 'text/plain', 'application/octet-stream'])
                ? 'application/json'
                : null,
            'xml' => $this->validText($binary, ['application/xml', 'text/xml', 'text/plain', 'application/octet-stream'])
                ? 'application/xml'
                : null,
            'html', 'htm' => $this->validText($binary, ['text/html', 'application/xhtml+xml', 'text/plain', 'application/octet-stream'])
                ? 'text/html'
                : null,
            'docx', 'xlsx', 'pptx' => $this->validOfficeDocument($binary, $extension)
                ? match ($extension) {
                    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                }
            : null,
            'png' => $detectedMime === 'image/png' && str_starts_with($binary, "\x89PNG\r\n\x1a\n") ? 'image/png' : null,
            'jpg', 'jpeg' => $detectedMime === 'image/jpeg' && str_starts_with($binary, "\xff\xd8\xff") ? 'image/jpeg' : null,
            'webp'  => $detectedMime === 'image/webp' && substr($binary, 0, 4) === 'RIFF' && substr($binary, 8, 4) === 'WEBP' ? 'image/webp' : null,
            'gif'   => in_array($detectedMime, ['image/gif', 'application/octet-stream'], true)
                && preg_match('/\AGIF8[79]a/', $binary) === 1
                    ? 'image/gif'
                    : null,
            'bmp' => in_array($detectedMime, ['image/bmp', 'image/x-ms-bmp', 'application/octet-stream'], true)
                && str_starts_with($binary, 'BM')
                    ? 'image/bmp'
                    : null,
            'mp3', 'mpeg', 'mpga', 'm4a', 'wav', 'ogg', 'flac', 'aac',
            'mp4', 'mov', 'mkv', 'avi', 'm4v', 'webm' => $this->mediaMime($detectedMime, $extension),
            default => null,
        };

        if ($mimeType === null) {
            throw ValidationException::withMessages(['files.0.data' => 'The file contents do not match a supported attachment format.']);
        }

        return [$binary, $mimeType];
    }

    private function validPdf(string $binary, string|false $detectedMime): bool
    {
        return $detectedMime === 'application/pdf' && str_starts_with($binary, '%PDF-');
    }

    private function validOfficeDocument(string $binary, string $extension): bool
    {
        if (! str_starts_with($binary, "PK\x03\x04") || ! class_exists(ZipArchive::class)) {
            return false;
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'aicp_upload_');
        if ($temporaryPath === false) {
            return false;
        }

        try {
            if (file_put_contents($temporaryPath, $binary, LOCK_EX) !== strlen($binary)) {
                return false;
            }

            $archive = new ZipArchive;
            if ($archive->open($temporaryPath) !== true) {
                return false;
            }

            try {
                if ($archive->numFiles > 4096) {
                    return false;
                }

                $totalUncompressedSize = 0;
                for ($index = 0; $index < $archive->numFiles; $index++) {
                    $entry = $archive->statIndex($index);
                    if (! is_array($entry)) {
                        return false;
                    }

                    $totalUncompressedSize += (int) ($entry['size'] ?? 0);
                    if ($totalUncompressedSize > 52428800) {
                        return false;
                    }
                }

                $contentTypesStat = $archive->statName('[Content_Types].xml');
                if (! is_array($contentTypesStat) || (int) ($contentTypesStat['size'] ?? 0) > 1048576) {
                    return false;
                }

                $contentTypes = $archive->getFromName('[Content_Types].xml');
                [$mainPart, $mainContentType] = match ($extension) {
                    'docx' => [
                        'word/document.xml',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml',
                    ],
                    'xlsx' => [
                        'xl/workbook.xml',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml',
                    ],
                    'pptx' => [
                        'ppt/presentation.xml',
                        'application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml',
                    ],
                    default => ['', ''],
                };

                return $mainPart !== ''
                    && is_string($contentTypes)
                    && str_contains($contentTypes, $mainContentType)
                    && $archive->locateName($mainPart) !== false;
            } finally {
                $archive->close();
            }
        } finally {
            @unlink($temporaryPath);
        }
    }

    private function validText(string $binary, array $allowedMimes): bool
    {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($binary);

        return ! str_contains($binary, "\0") && preg_match('//u', $binary) === 1 && in_array($mime, $allowedMimes, true);
    }

    private function validLegacyOfficeDocument(string $binary): bool
    {
        return str_starts_with($binary, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1");
    }

    private function legacyOfficeMime(string $extension): string
    {
        return match ($extension) {
            'doc' => 'application/msword',
            'xls' => 'application/vnd.ms-excel',
            'ppt' => 'application/vnd.ms-powerpoint',
        };
    }

    private function mediaMime(string|false $detectedMime, string $extension): ?string
    {
        $supportedMime = match ($extension) {
            'mp3', 'mpeg', 'mpga' => ['audio/mpeg'],
            'm4a'  => ['audio/mp4', 'audio/x-m4a', 'video/mp4'],
            'wav'  => ['audio/wav', 'audio/x-wav', 'audio/vnd.wave'],
            'ogg'  => ['audio/ogg', 'application/ogg'],
            'flac' => ['audio/flac', 'audio/x-flac'],
            'aac'  => ['audio/aac', 'audio/vnd.dlna.adts', 'audio/x-aac'],
            'mp4'  => ['video/mp4', 'application/mp4'],
            'mov'  => ['video/quicktime'],
            'mkv'  => ['video/x-matroska', 'application/x-matroska'],
            'avi'  => ['video/x-msvideo', 'video/avi'],
            'm4v'  => ['video/x-m4v', 'video/mp4'],
            'webm' => ['video/webm'],
        };

        if (! in_array($detectedMime, $supportedMime, true)) {
            return null;
        }

        return $supportedMime[0];
    }

    private function respondForExisting(
        AIChatProAttachment $attachment,
        int $ownerId,
        string $name,
        string $mimeType,
        int $size,
        string $checksum
    ): JsonResponse {
        if ((int) $attachment->user_id !== $ownerId) {
            return response()->json(['message' => 'This attachment ID is already in use.'], 409);
        }

        if ($attachment->original_name !== $name
            || $attachment->mime_type !== $mimeType
            || (int) $attachment->file_size !== $size
            || ! hash_equals($attachment->checksum, $checksum)) {
            return response()->json(['message' => 'This attachment ID already refers to different content.'], 409);
        }

        $disk = Storage::disk($attachment->storage_disk);
        if (! $disk->exists($attachment->storage_path)
            || $disk->size($attachment->storage_path) !== $size
            || ! hash_equals($checksum, hash('sha256', $disk->get($attachment->storage_path)))) {
            return response()->json(['message' => 'The stored attachment failed integrity verification.'], 500);
        }

        return $this->attachmentResponse($attachment, true, 200);
    }

    private function attachmentResponse(AIChatProAttachment $attachment, bool $idempotent, int $status): JsonResponse
    {
        $url = '/dashboard/user/ai-chat-pro/attachments/' . $attachment->uuid;

        return response()->json([
            'attachment_id' => $attachment->uuid,
            'idempotent'    => $idempotent,
            'files'         => [[
                'name'              => $attachment->original_name,
                'mime_type'         => $attachment->mime_type,
                'size'              => (int) $attachment->file_size,
                'checksum'          => $attachment->checksum,
                'upload_status'     => $attachment->upload_status,
                'processing_status' => $attachment->processing_status,
                'extraction_status' => $attachment->extraction_status,
                'storage'           => [
                    'disk' => $attachment->storage_disk,
                    'path' => $attachment->storage_path,
                ],
                'path' => $url,
                'url'  => $url,
            ]],
        ], $status);
    }
}
