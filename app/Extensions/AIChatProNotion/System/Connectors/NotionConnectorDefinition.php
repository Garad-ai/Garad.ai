<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProNotion\System\Connectors;

use App\Extensions\AIChatPro\System\Connectors\ConnectorDefinition;
use App\Extensions\AIChatPro\System\Connectors\Models\AIChatProConnector;
use App\Extensions\AIChatProNotion\System\Actions\Notion\GetPageAction;
use App\Extensions\AIChatProNotion\System\Actions\Notion\SearchPagesAction;
use App\Extensions\AIChatProNotion\System\OAuth\NotionOAuth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotionConnectorDefinition implements ConnectorDefinition
{
    private const NOTION_VERSION = '2022-06-28';

    public function __construct(
        private readonly SearchPagesAction $searchAction,
        private readonly GetPageAction $getAction,
        private readonly NotionOAuth $oauth,
    ) {}

    public function key(): string
    {
        return 'notion';
    }

    public function label(): string
    {
        return 'Notion';
    }

    public function description(): string
    {
        return __('Turn your workspace into an AI-powered knowledge hub by connecting notes, projects and more.');
    }

    public function icon(): string
    {
        return 'ai-chat-pro-notion::icon.index';
    }

    public function redirectRoute(): string
    {
        return route('dashboard.user.ai-chat-pro.connectors.notion.redirect');
    }

    public function isEnabled(): bool
    {
        return (bool) setting('ai_chat_pro_connector_notion_enabled', '1');
    }

    public function tools(AIChatProConnector $connector, string $engine): array
    {
        $searchSchema = [
            'type'       => 'object',
            'properties' => [
                'query'       => [
                    'type'        => 'string',
                    'description' => 'Text to search across the user\'s Notion pages and databases. Use natural keywords.',
                ],
                'max_results' => [
                    'type'        => 'integer',
                    'description' => 'How many results to return (1-20).',
                    'minimum'     => 1,
                    'maximum'     => 20,
                ],
            ],
            'required'   => ['query'],
        ];

        $getSchema = [
            'type'       => 'object',
            'properties' => [
                'page_id' => [
                    'type'        => 'string',
                    'description' => 'The Notion page ID returned from connector_notion_search_pages.',
                ],
            ],
            'required'   => ['page_id'],
        ];

        return match ($engine) {
            'open_ai' => [
                [
                    'type'        => 'function',
                    'name'        => 'connector_notion_search_pages',
                    'description' => 'Search the user\'s Notion workspace. Returns page titles, types, urls, and IDs.',
                    'parameters'  => $searchSchema,
                ],
                [
                    'type'        => 'function',
                    'name'        => 'connector_notion_get_page',
                    'description' => 'Fetch a single Notion page\'s content (title and body text) by id.',
                    'parameters'  => $getSchema,
                ],
            ],
            'anthropic' => [
                [
                    'name'         => 'connector_notion_search_pages',
                    'description'  => 'Search the user\'s Notion workspace. Returns page titles, types, urls, and IDs.',
                    'input_schema' => $searchSchema,
                ],
                [
                    'name'         => 'connector_notion_get_page',
                    'description'  => 'Fetch a single Notion page\'s content (title and body text) by id.',
                    'input_schema' => $getSchema,
                ],
            ],
            'gemini' => [
                [
                    'name'        => 'connector_notion_search_pages',
                    'description' => 'Search the user\'s Notion workspace. Returns page titles, types, urls, and IDs.',
                    'parameters'  => $searchSchema,
                ],
                [
                    'name'        => 'connector_notion_get_page',
                    'description' => 'Fetch a single Notion page\'s content (title and body text) by id.',
                    'parameters'  => $getSchema,
                ],
            ],
            default => [],
        };
    }

    public function handleToolCall(string $functionName, array $arguments, AIChatProConnector $connector): array
    {
        return match ($functionName) {
            'connector_notion_search_pages' => $this->searchAction->execute($arguments, $connector),
            'connector_notion_get_page'     => $this->getAction->execute($arguments, $connector),
            default                         => ['error' => 'Unknown Notion tool: ' . $functionName],
        };
    }

    public function systemPromptHint(AIChatProConnector $connector): ?string
    {
        $workspace = $connector->getCredential('workspace_name');

        return 'Notion (' . ($workspace ?: 'connected') . ') — call connector_notion_search_pages to find pages, then connector_notion_get_page for a specific page body.';
    }

    public function meta(AIChatProConnector $connector): array
    {
        return [
            'name' => 'Notion',
            'key'  => 'notion',
            'icon' => 'tabler-brand-notion',
        ];
    }

    public function details(): array
    {
        return [
            'type'    => 'API',
            'author'  => 'Notion Labs, Inc.',
            'uuid'    => 'c3e29f0b-1b6d-4d23-9c1d-7a8e9c0e4b12',
            'website' => 'https://www.notion.so',
        ];
    }

    public function permissions(): array
    {
        return [
            __('Read the Notion pages and databases you grant access to (read-only).'),
            __('See the basic workspace info you are authorising.'),
        ];
    }

    public function accessOptions(AIChatProConnector $connector): array
    {
        $accessToken = (string) $connector->getCredential('access_token');

        if ($accessToken === '') {
            return ['type' => 'pages', 'label' => __('Pages'), 'options' => []];
        }

        $options = [];

        try {
            $response = Http::withToken($accessToken)
                ->withHeaders([
                    'Notion-Version' => self::NOTION_VERSION,
                    'Accept'         => 'application/json',
                ])
                ->asJson()
                ->post('https://api.notion.com/v1/search', [
                    'page_size' => 50,
                    'filter'    => ['property' => 'object', 'value' => 'page'],
                ])
                ->throw();

            foreach ((array) data_get($response->json(), 'results', []) as $item) {
                $id = (string) data_get($item, 'id', '');
                if ($id === '') {
                    continue;
                }

                $options[] = [
                    'id'   => $id,
                    'name' => $this->extractTitle($item),
                    'meta' => (string) data_get($item, 'last_edited_time', ''),
                ];
            }
        } catch (Throwable $exception) {
            Log::warning('[connectors] Notion accessOptions failed', [
                'connector_id' => $connector->id,
                'message'      => $exception->getMessage(),
            ]);
        }

        return [
            'type'    => 'pages',
            'label'   => __('Pages'),
            'options' => $options,
        ];
    }

    public function revokeToken(AIChatProConnector $connector): void
    {
        $accessToken = $connector->getCredential('access_token');

        if (! $accessToken) {
            return;
        }

        try {
            $this->oauth->revokeToken((string) $accessToken);
        } catch (Throwable $exception) {
            Log::warning('[connectors] Notion revokeToken failed', [
                'connector_id' => $connector->id,
                'message'      => $exception->getMessage(),
            ]);
        }
    }

    private function extractTitle(array $item): string
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
}
