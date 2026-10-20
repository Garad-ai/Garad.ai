<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Services;

use App\Extensions\AIChatPro\System\Models\AIChatProAttachment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class AIChatProAttachmentService
{
    private const REFERENCE_PATTERN = '#^/dashboard/user/ai-chat-pro/attachments/([0-9a-f-]{36})$#iD';

    public function isPrivateReference(string $reference): bool
    {
        return $this->uuidFromReference($reference) !== null;
    }

    public function findOwnedReference(string $reference, ?int $userId = null): ?AIChatProAttachment
    {
        $uuid = $this->uuidFromReference($reference);
        $ownerId = $userId ?? (Auth::id() !== null ? (int) Auth::id() : null);

        if ($uuid === null || $ownerId === null) {
            return null;
        }

        return AIChatProAttachment::query()
            ->where('uuid', strtolower($uuid))
            ->where('user_id', $ownerId)
            ->first();
    }

    public function readOwnedReference(string $reference, ?int $userId = null): ?string
    {
        $attachment = $this->findOwnedReference($reference, $userId);
        if ($attachment === null) {
            return null;
        }

        $disk = Storage::disk($attachment->storage_disk);
        if (! $disk->exists($attachment->storage_path)) {
            throw new RuntimeException('Private attachment storage object is missing.');
        }

        $contents = $disk->get($attachment->storage_path);
        if (strlen($contents) !== (int) $attachment->file_size
            || ! hash_equals($attachment->checksum, hash('sha256', $contents))) {
            throw new RuntimeException('Private attachment integrity verification failed.');
        }

        return $contents;
    }

    public function mimeTypeForOwnedReference(string $reference, ?int $userId = null): ?string
    {
        return $this->findOwnedReference($reference, $userId)?->mime_type;
    }

    private function uuidFromReference(string $reference): ?string
    {
        $path = parse_url($reference, PHP_URL_PATH);
        if (! is_string($path) || ! preg_match(self::REFERENCE_PATTERN, $path, $matches)) {
            return null;
        }

        return $matches[1];
    }
}
