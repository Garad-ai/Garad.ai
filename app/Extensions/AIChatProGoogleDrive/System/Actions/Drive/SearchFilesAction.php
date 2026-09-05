<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProGoogleDrive\System\Actions\Drive;

use App\Extensions\AIChatPro\System\Connectors\Models\AIChatProConnector;
use App\Extensions\AIChatProGoogleDrive\System\Drive\DriveClientFactory;
use Throwable;

class SearchFilesAction
{
    public function __construct(private readonly DriveClientFactory $factory) {}

    /**
     * @param  array{query?: string, max_results?: int, mime_type?: string}  $arguments
     */
    public function execute(array $arguments, AIChatProConnector $connector): array
    {
        $query = trim((string) ($arguments['query'] ?? ''));
        $maxResults = max(1, min((int) ($arguments['max_results'] ?? 10), 20));
        $mimeType = isset($arguments['mime_type']) ? trim((string) $arguments['mime_type']) : null;

        if ($query === '') {
            return ['error' => 'query is required.'];
        }

        $escapedQuery = str_replace("'", "\\'", $query);
        $qParts = ["name contains '{$escapedQuery}'", 'trashed = false'];

        if ($mimeType) {
            $escapedMime = str_replace("'", "\\'", $mimeType);
            $qParts[] = "mimeType = '{$escapedMime}'";
        }

        $allowedFolders = $connector->selectedAccessFor('folders');
        if ($allowedFolders !== null) {
            $folderClauses = array_map(
                fn (string $folderId): string => "'" . str_replace("'", "\\'", $folderId) . "' in parents",
                $allowedFolders
            );
            $qParts[] = '(' . implode(' or ', $folderClauses) . ')';
        }

        if ($connector->selectedRecentOnly()) {
            $qParts[] = "modifiedTime > '" . now()->subDays(30)->toRfc3339String() . "'";
        }

        try {
            $drive = $this->factory->make($connector);
            $listResult = $drive->files->listFiles([
                'q'        => implode(' and ', $qParts),
                'pageSize' => $maxResults,
                'fields'   => 'files(id,name,mimeType,modifiedTime,webViewLink,size,owners)',
            ]);
        } catch (Throwable $exception) {
            return ['error' => $exception->getMessage()];
        }

        $files = [];

        foreach ($listResult->getFiles() ?? [] as $file) {
            $files[] = [
                'id'            => $file->getId(),
                'name'          => $file->getName(),
                'mime_type'     => $file->getMimeType(),
                'modified_time' => $file->getModifiedTime(),
                'web_view_link' => $file->getWebViewLink(),
                'size'          => $file->getSize(),
            ];
        }

        return [
            'query' => $query,
            'count' => count($files),
            'files' => $files,
        ];
    }
}
