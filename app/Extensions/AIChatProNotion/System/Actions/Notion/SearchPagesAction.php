<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProNotion\System\Actions\Notion;

use App\Extensions\AIChatPro\System\Connectors\Models\AIChatProConnector;
use Illuminate\Support\Facades\Http;
use Throwable;

class SearchPagesAction
{
    private const API_BASE = 'https://api.notion.com/v1';

    private const NOTION_VERSION = '2022-06-28';

    /**
     * @param  array{query?: string, max_results?: int}  $arguments
     */
    public function execute(array $arguments, AIChatProConnector $connector): array
    {
        $query = (string) ($arguments['query'] ?? '');
        $pageSize = max(1, min((int) ($arguments['max_results'] ?? 10), 20));

        $accessToken = (string) $connector->getCredential('access_token');

        if ($accessToken === '') {
            return ['error' => 'Notion access token is missing.'];
        }

        try {
            $response = Http::withToken($accessToken)
                ->withHeaders([
                    'Notion-Version' => self::NOTION_VERSION,
                    'Accept'         => 'application/json',
                ])
                ->asJson()
                ->post(self::API_BASE . '/search', array_filter([
                    'query'     => $query !== '' ? $query : null,
                    'page_size' => $pageSize,
                ], fn ($value) => $value !== null))
                ->throw();
        } catch (Throwable $exception) {
            return ['error' => $exception->getMessage()];
        }

        $workspaceName = (string) ($connector->getCredential('workspace_name') ?? '');
        $items = (array) data_get($response->json(), 'results', []);
        $allowedPages = $connector->selectedAccessFor('pages');
        $results = [];

        foreach ($items as $item) {
            $itemId = (string) data_get($item, 'id', '');
            if ($allowedPages !== null && $itemId !== '' && ! in_array($itemId, $allowedPages, true)) {
                continue;
            }

            $type = (string) data_get($item, 'object', 'page');
            $title = $type === 'database'
                ? $this->extractDatabaseTitle($item)
                : $this->extractPageTitle($item);

            $results[] = [
                'id'                => (string) data_get($item, 'id', ''),
                'title'             => $title,
                'type'              => $type,
                'url'               => (string) data_get($item, 'url', ''),
                'last_edited_time'  => (string) data_get($item, 'last_edited_time', ''),
                'parent_workspace'  => $workspaceName,
            ];
        }

        return [
            'query'   => $query,
            'count'   => count($results),
            'results' => $results,
        ];
    }

    private function extractPageTitle(array $item): string
    {
        $properties = (array) data_get($item, 'properties', []);

        foreach ($properties as $property) {
            if (data_get($property, 'type') === 'title') {
                $segments = (array) data_get($property, 'title', []);
                $text = collect($segments)->pluck('plain_text')->filter()->implode('');
                if ($text !== '') {
                    return $text;
                }
            }
        }

        return '(untitled)';
    }

    private function extractDatabaseTitle(array $item): string
    {
        $segments = (array) data_get($item, 'title', []);
        $text = collect($segments)->pluck('plain_text')->filter()->implode('');

        return $text !== '' ? $text : '(untitled database)';
    }
}
