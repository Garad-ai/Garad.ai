<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProNotion\System\Actions\Notion;

use App\Extensions\AIChatPro\System\Connectors\Models\AIChatProConnector;
use Illuminate\Support\Facades\Http;
use Throwable;

class GetPageAction
{
    private const API_BASE = 'https://api.notion.com/v1';

    private const NOTION_VERSION = '2022-06-28';

    private const TEXTUAL_BLOCK_TYPES = [
        'paragraph',
        'heading_1',
        'heading_2',
        'heading_3',
        'bulleted_list_item',
        'numbered_list_item',
        'to_do',
        'quote',
        'code',
    ];

    /**
     * @param  array{page_id?: string}  $arguments
     */
    public function execute(array $arguments, AIChatProConnector $connector): array
    {
        $pageId = (string) ($arguments['page_id'] ?? '');

        if ($pageId === '') {
            return ['error' => 'page_id is required.'];
        }

        $allowedPages = $connector->selectedAccessFor('pages');
        if ($allowedPages !== null && ! in_array($pageId, $allowedPages, true)) {
            return ['error' => 'This page is outside the pages you allowed for AI Chat Pro.'];
        }

        $accessToken = (string) $connector->getCredential('access_token');

        if ($accessToken === '') {
            return ['error' => 'Notion access token is missing.'];
        }

        $headers = [
            'Notion-Version' => self::NOTION_VERSION,
            'Accept'         => 'application/json',
        ];

        try {
            $pageResponse = Http::withToken($accessToken)
                ->withHeaders($headers)
                ->get(self::API_BASE . '/pages/' . $pageId)
                ->throw();

            $blocksResponse = Http::withToken($accessToken)
                ->withHeaders($headers)
                ->get(self::API_BASE . '/blocks/' . $pageId . '/children', ['page_size' => 50])
                ->throw();
        } catch (Throwable $exception) {
            return ['error' => $exception->getMessage()];
        }

        $page = (array) $pageResponse->json();
        $blocks = (array) data_get($blocksResponse->json(), 'results', []);

        $body = $this->blocksToText($blocks);

        return [
            'id'               => (string) data_get($page, 'id', $pageId),
            'title'            => $this->extractPageTitle($page),
            'url'              => (string) data_get($page, 'url', ''),
            'last_edited_time' => (string) data_get($page, 'last_edited_time', ''),
            'created_time'     => (string) data_get($page, 'created_time', ''),
            'body'             => mb_substr($body, 0, 12000),
        ];
    }

    private function blocksToText(array $blocks): string
    {
        $lines = [];

        foreach ($blocks as $block) {
            $type = (string) data_get($block, 'type', '');

            if (! in_array($type, self::TEXTUAL_BLOCK_TYPES, true)) {
                continue;
            }

            $segments = (array) data_get($block, $type . '.rich_text', []);
            $text = collect($segments)->pluck('plain_text')->filter()->implode('');

            if ($text === '') {
                continue;
            }

            $lines[] = match ($type) {
                'heading_1'           => '# ' . $text,
                'heading_2'           => '## ' . $text,
                'heading_3'           => '### ' . $text,
                'bulleted_list_item'  => '- ' . $text,
                'numbered_list_item'  => '1. ' . $text,
                'to_do'               => (data_get($block, 'to_do.checked') ? '[x] ' : '[ ] ') . $text,
                'quote'               => '> ' . $text,
                'code'                => "```\n" . $text . "\n```",
                default               => $text,
            };
        }

        return implode("\n", $lines);
    }

    private function extractPageTitle(array $page): string
    {
        $properties = (array) data_get($page, 'properties', []);

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
