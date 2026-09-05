<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProGoogleDrive\System\Connectors;

use App\Extensions\AIChatPro\System\Connectors\ConnectorDefinition;
use App\Extensions\AIChatPro\System\Connectors\Models\AIChatProConnector;
use App\Extensions\AIChatProGoogleDrive\System\Actions\Drive\GetFileContentAction;
use App\Extensions\AIChatProGoogleDrive\System\Actions\Drive\SearchFilesAction;
use App\Extensions\AIChatProGoogleDrive\System\Drive\DriveClientFactory;
use App\Extensions\AIChatProGoogleDrive\System\OAuth\GoogleDriveOAuth;
use Illuminate\Support\Facades\Log;
use Throwable;

class GoogleDriveConnectorDefinition implements ConnectorDefinition
{
    public function __construct(
        private readonly SearchFilesAction $searchAction,
        private readonly GetFileContentAction $getAction,
        private readonly DriveClientFactory $clientFactory,
        private readonly GoogleDriveOAuth $oauth,
    ) {}

    public function key(): string
    {
        return 'google_drive';
    }

    public function label(): string
    {
        return 'Google Drive';
    }

    public function description(): string
    {
        return __('Allow AI to search, analyze, and understand your documents, spreadsheets and files for deeper context.');
    }

    public function icon(): string
    {
        return 'ai-chat-pro-google-drive::icon.index';
    }

    public function redirectRoute(): string
    {
        return route('dashboard.user.ai-chat-pro.connectors.google-drive.redirect');
    }

    public function isEnabled(): bool
    {
        return (bool) setting('ai_chat_pro_connector_google_drive_enabled', '1');
    }

    public function tools(AIChatProConnector $connector, string $engine): array
    {
        $searchSchema = [
            'type'       => 'object',
            'properties' => [
                'query'       => [
                    'type'        => 'string',
                    'description' => 'Substring to match against file names in the user\'s Drive.',
                ],
                'max_results' => [
                    'type'        => 'integer',
                    'description' => 'How many files to return (1-20).',
                    'minimum'     => 1,
                    'maximum'     => 20,
                ],
                'mime_type'   => [
                    'type'        => 'string',
                    'description' => 'Optional exact MIME type filter, e.g. application/vnd.google-apps.document.',
                ],
            ],
            'required'   => ['query'],
        ];

        $getSchema = [
            'type'       => 'object',
            'properties' => [
                'file_id' => [
                    'type'        => 'string',
                    'description' => 'The Google Drive file ID returned from connector_google_drive_search_files.',
                ],
            ],
            'required'   => ['file_id'],
        ];

        return match ($engine) {
            'open_ai' => [
                [
                    'type'        => 'function',
                    'name'        => 'connector_google_drive_search_files',
                    'description' => 'Search the user\'s Google Drive by file name. Returns ids, names, mime types, and links.',
                    'parameters'  => $searchSchema,
                ],
                [
                    'type'        => 'function',
                    'name'        => 'connector_google_drive_get_file_content',
                    'description' => 'Fetch the textual content of a Drive file by id (Docs/Sheets/Slides are exported as text).',
                    'parameters'  => $getSchema,
                ],
            ],
            'anthropic' => [
                [
                    'name'         => 'connector_google_drive_search_files',
                    'description'  => 'Search the user\'s Google Drive by file name. Returns ids, names, mime types, and links.',
                    'input_schema' => $searchSchema,
                ],
                [
                    'name'         => 'connector_google_drive_get_file_content',
                    'description'  => 'Fetch the textual content of a Drive file by id (Docs/Sheets/Slides are exported as text).',
                    'input_schema' => $getSchema,
                ],
            ],
            'gemini' => [
                [
                    'name'        => 'connector_google_drive_search_files',
                    'description' => 'Search the user\'s Google Drive by file name. Returns ids, names, mime types, and links.',
                    'parameters'  => $searchSchema,
                ],
                [
                    'name'        => 'connector_google_drive_get_file_content',
                    'description' => 'Fetch the textual content of a Drive file by id (Docs/Sheets/Slides are exported as text).',
                    'parameters'  => $getSchema,
                ],
            ],
            default => [],
        };
    }

    public function handleToolCall(string $functionName, array $arguments, AIChatProConnector $connector): array
    {
        return match ($functionName) {
            'connector_google_drive_search_files'     => $this->searchAction->execute($arguments, $connector),
            'connector_google_drive_get_file_content' => $this->getAction->execute($arguments, $connector),
            default                                   => ['error' => 'Unknown Google Drive tool: ' . $functionName],
        };
    }

    public function systemPromptHint(AIChatProConnector $connector): ?string
    {
        $email = $connector->getCredential('email');

        return 'Google Drive (' . ($email ?: 'connected') . ') — call connector_google_drive_search_files to find files by name, then connector_google_drive_get_file_content for a specific file body.';
    }

    public function meta(AIChatProConnector $connector): array
    {
        return [
            'name' => 'Google Drive',
            'key'  => 'google_drive',
            'icon' => 'tabler-brand-google-drive',
        ];
    }

    public function details(): array
    {
        return [
            'type'    => 'API',
            'author'  => 'Google',
            'uuid'    => 'd2147a91-3f0c-4b54-9a8e-19a3f7e2dc05',
            'website' => 'https://drive.google.com',
        ];
    }

    public function permissions(): array
    {
        return [
            __('Read files and folders in your Google Drive (read-only).'),
            __('Export Google Docs, Sheets, and Slides as text.'),
            __('See your basic Google profile (name, email, picture).'),
        ];
    }

    public function accessOptions(AIChatProConnector $connector): array
    {
        $options = [];

        try {
            $drive = $this->clientFactory->make($connector);
            $listResult = $drive->files->listFiles([
                'q'        => "mimeType = 'application/vnd.google-apps.folder' and trashed = false",
                'pageSize' => 50,
                'fields'   => 'files(id,name,modifiedTime)',
                'orderBy'  => 'name',
            ]);

            foreach ($listResult->getFiles() ?? [] as $folder) {
                $options[] = [
                    'id'   => (string) $folder->getId(),
                    'name' => (string) $folder->getName(),
                    'meta' => '',
                ];
            }
        } catch (Throwable $exception) {
            Log::warning('[connectors] Google Drive accessOptions failed', [
                'connector_id' => $connector->id,
                'message'      => $exception->getMessage(),
            ]);
        }

        return [
            'type'    => 'folders',
            'label'   => __('Folders'),
            'options' => $options,
        ];
    }

    public function revokeToken(AIChatProConnector $connector): void
    {
        $token = $connector->getCredential('refresh_token') ?: $connector->getCredential('access_token');

        if (! $token) {
            return;
        }

        try {
            $this->oauth->revokeToken((string) $token);
        } catch (Throwable $exception) {
            Log::warning('[connectors] Google Drive revokeToken failed', [
                'connector_id' => $connector->id,
                'message'      => $exception->getMessage(),
            ]);
        }
    }
}
