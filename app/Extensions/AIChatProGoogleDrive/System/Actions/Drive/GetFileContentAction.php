<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProGoogleDrive\System\Actions\Drive;

use App\Extensions\AIChatPro\System\Connectors\Models\AIChatProConnector;
use App\Extensions\AIChatProGoogleDrive\System\Drive\DriveClientFactory;
use Google\Service\Drive;
use Throwable;

class GetFileContentAction
{
    private const MAX_CHARS = 12000;

    /**
     * Hard cap on bytes pulled from Drive. We only keep ~12k chars; this leaves
     * headroom for multi-byte encodings while preventing a huge file from
     * exhausting PHP memory.
     */
    private const MAX_BYTES = 524288;

    private const READABLE_PREFIXES = [
        'text/',
        'application/json',
        'application/xml',
        'application/x-yaml',
        'application/yaml',
    ];

    public function __construct(private readonly DriveClientFactory $factory) {}

    /**
     * @param  array{file_id?: string}  $arguments
     */
    public function execute(array $arguments, AIChatProConnector $connector): array
    {
        $fileId = trim((string) ($arguments['file_id'] ?? ''));

        if ($fileId === '') {
            return ['error' => 'file_id is required.'];
        }

        try {
            $drive = $this->factory->make($connector);
            $meta = $drive->files->get($fileId, [
                'fields' => 'id,name,mimeType,modifiedTime,webViewLink,size,parents',
            ]);
        } catch (Throwable $exception) {
            return ['error' => $exception->getMessage()];
        }

        $allowedFolders = $connector->selectedAccessFor('folders');
        if ($allowedFolders !== null) {
            $parents = (array) ($meta->getParents() ?? []);
            $intersection = array_intersect($parents, $allowedFolders);

            if ($intersection === []) {
                return ['error' => 'This file is outside the folders you allowed for AI Chat Pro.'];
            }
        }

        $mimeType = (string) $meta->getMimeType();

        // For binary/text downloads (non-Google-Docs), refuse anything obviously
        // huge up front so we don't even open the body. Google's native Docs
        // formats don't report size — those are handled by the streamed read.
        $reportedSize = (int) ($meta->getSize() ?? 0);
        if ($reportedSize > 0 && $reportedSize > self::MAX_BYTES * 8) {
            return [
                'id'            => $meta->getId(),
                'name'          => $meta->getName(),
                'mime_type'     => $mimeType,
                'web_view_link' => $meta->getWebViewLink(),
                'error'         => 'File is too large to read inline.',
            ];
        }

        try {
            $content = $this->fetchContent($drive, $fileId, $mimeType);
        } catch (Throwable $exception) {
            return ['error' => $exception->getMessage()];
        }

        if ($content === null) {
            return [
                'id'            => $meta->getId(),
                'name'          => $meta->getName(),
                'mime_type'     => $mimeType,
                'web_view_link' => $meta->getWebViewLink(),
                'error'         => 'Unsupported file type for inline reading.',
            ];
        }

        return [
            'id'            => $meta->getId(),
            'name'          => $meta->getName(),
            'mime_type'     => $mimeType,
            'modified_time' => $meta->getModifiedTime(),
            'web_view_link' => $meta->getWebViewLink(),
            'content'       => mb_substr($content, 0, self::MAX_CHARS),
        ];
    }

    private function fetchContent(Drive $drive, string $fileId, string $mimeType): ?string
    {
        $exportMime = match ($mimeType) {
            'application/vnd.google-apps.document'     => 'text/plain',
            'application/vnd.google-apps.spreadsheet'  => 'text/csv',
            'application/vnd.google-apps.presentation' => 'text/plain',
            default                                    => null,
        };

        if ($exportMime !== null) {
            $response = $drive->files->export($fileId, $exportMime, ['alt' => 'media']);

            return $this->readCapped($response->getBody());
        }

        if ($this->isReadableMime($mimeType)) {
            $response = $drive->files->get($fileId, ['alt' => 'media']);

            return $this->readCapped($response->getBody());
        }

        return null;
    }

    private function readCapped(mixed $body): string
    {
        if (! is_object($body) || ! method_exists($body, 'read')) {
            // Fallback for unexpected body shapes — still cap via substr.
            return substr((string) $body, 0, self::MAX_BYTES);
        }

        $buffer = '';
        while (strlen($buffer) < self::MAX_BYTES) {
            if (method_exists($body, 'eof') && $body->eof()) {
                break;
            }
            $chunk = $body->read(min(8192, self::MAX_BYTES - strlen($buffer)));
            if ($chunk === '') {
                break;
            }
            $buffer .= $chunk;
        }

        return $buffer;
    }

    private function isReadableMime(string $mimeType): bool
    {
        foreach (self::READABLE_PREFIXES as $prefix) {
            if (str_starts_with($mimeType, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
