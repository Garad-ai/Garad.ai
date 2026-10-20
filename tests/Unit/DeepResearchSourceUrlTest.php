<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Extensions\AIChatProDeepResearch\System\Services\GeminiDeepResearchDriver;
use App\Extensions\AIChatProDeepResearch\System\Services\OpenAIDeepResearchDriver;
use PHPUnit\Framework\TestCase;

final class DeepResearchSourceUrlTest extends TestCase
{
    public function test_openai_sources_only_include_unique_http_urls(): void
    {
        $driver = new OpenAIDeepResearchDriver;

        $sources = $driver->extractSources([
            [
                'type'    => 'message',
                'content' => [
                    [
                        'type'        => 'output_text',
                        'annotations' => [
                            ['title' => 'Safe source', 'url' => 'https://example.org/source'],
                            ['title' => 'Unsafe source', 'url' => 'javascript:alert(1)'],
                            ['title' => 'Duplicate source', 'url' => 'https://example.org/source'],
                        ],
                    ],
                ],
            ],
        ]);

        self::assertSame([
            ['title' => 'Safe source', 'url' => 'https://example.org/source'],
        ], $sources);
    }

    public function test_gemini_sources_reject_non_http_urls(): void
    {
        $driver = new GeminiDeepResearchDriver;

        $sources = $driver->extractSources([
            [
                'annotations' => [
                    ['title' => 'Safe source', 'source' => 'https://example.org/source'],
                    ['title' => 'Unsafe source', 'source' => 'javascript:alert(1)'],
                ],
                'groundingMetadata' => [
                    'groundingChunks' => [
                        ['web' => ['title' => 'Grounded source', 'uri' => 'https://example.net/report']],
                    ],
                ],
                'citations' => [
                    ['title' => 'Unsafe source', 'uri' => 'ftp://example.com/file'],
                ],
            ],
        ]);

        self::assertSame([
            ['title' => 'Safe source', 'url' => 'https://example.org/source'],
            ['title' => 'Grounded source', 'url' => 'https://example.net/report'],
        ], $sources);
    }
}
