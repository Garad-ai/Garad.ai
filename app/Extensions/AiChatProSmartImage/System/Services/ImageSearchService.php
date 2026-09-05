<?php

declare(strict_types=1);

namespace App\Extensions\AiChatProSmartImage\System\Services;

use App\Domains\Entity\Enums\EntityEnum;
use App\Helpers\Classes\ApiHelper;
use App\Models\SettingTwo;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use OpenAI\Laravel\Facades\OpenAI;
use Throwable;

class ImageSearchService
{
    /**
     * Search for images using the configured realtime provider.
     *
     * @return array<int, array{title: string, imageUrl: string, thumbnailUrl: string, source: string, domain: string, link: string, imageWidth: int, imageHeight: int}>
     */
    public function search(string $query, int $num = 5): array
    {
        $provider = setting('default_realtime', 'serper');

        return match ($provider) {
            'serper'     => $this->searchSerper($query, $num),
            'perplexity' => $this->searchPerplexity($query, $num),
            'openai'     => $this->searchOpenAI($query, $num),
            default      => [],
        };
    }

    /**
     * @return array<int, array{title: string, imageUrl: string, thumbnailUrl: string, source: string, domain: string, link: string, imageWidth: int, imageHeight: int}>
     */
    private function searchSerper(string $query, int $num): array
    {
        $apiKey = SettingTwo::getCache()->serper_api_key;
        if (! $apiKey) {
            return [];
        }

        try {
            $response = Http::withHeaders([
                'X-API-KEY'    => $apiKey,
                'Content-Type' => 'application/json',
            ])->connectTimeout(300)->timeout(300)->post('https://google.serper.dev/images', [
                'q'   => $query,
                'num' => $num,
            ]);

            if (! $response->successful()) {
                return [];
            }

            return collect($response->json('images', []))
                ->take($num)
                ->map(fn (array $img): array => [
                    'title'        => $img['title'] ?? '',
                    'imageUrl'     => $img['imageUrl'] ?? '',
                    'thumbnailUrl' => $img['thumbnailUrl'] ?? ($img['imageUrl'] ?? ''),
                    'source'       => $img['source'] ?? '',
                    'domain'       => $img['domain'] ?? '',
                    'link'         => $img['link'] ?? '',
                    'imageWidth'   => $img['imageWidth'] ?? 0,
                    'imageHeight'  => $img['imageHeight'] ?? 0,
                ])
                ->filter(fn (array $img): bool => ! empty($img['imageUrl']))
                ->values()
                ->toArray();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, array{title: string, imageUrl: string, thumbnailUrl: string, source: string, domain: string, link: string, imageWidth: int, imageHeight: int}>
     */
    private function searchPerplexity(string $query, int $num): array
    {
        $token = setting('perplexity_key');
        if (! $token) {
            return [];
        }

        try {
            $response = Http::withToken($token)->connectTimeout(300)->timeout(300)->post('https://api.perplexity.ai/chat/completions', [
                'model'    => 'sonar',
                'messages' => [
                    [
                        'role'    => 'user',
                        'content' => "Find {$num} relevant images for: {$query}. Return ONLY a JSON array of objects with keys: title, imageUrl, source, domain, link. No other text.",
                    ],
                ],
            ]);

            if (! $response->successful()) {
                return [];
            }

            $content = $response->json('choices.0.message.content', '');

            // Extract JSON array from response
            if (preg_match('/\[.*\]/s', $content, $matches)) {
                $parsed = json_decode($matches[0], true);
                if (is_array($parsed)) {
                    return collect($parsed)
                        ->take($num)
                        ->map(fn (array $img): array => [
                            'title'        => $img['title'] ?? '',
                            'imageUrl'     => $img['imageUrl'] ?? '',
                            'thumbnailUrl' => $img['imageUrl'] ?? '',
                            'source'       => $img['source'] ?? '',
                            'domain'       => $img['domain'] ?? '',
                            'link'         => $img['link'] ?? '',
                            'imageWidth'   => 0,
                            'imageHeight'  => 0,
                        ])
                        ->filter(fn (array $img): bool => ! empty($img['imageUrl']))
                        ->values()
                        ->toArray();
                }
            }

            return [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, array{title: string, imageUrl: string, thumbnailUrl: string, source: string, domain: string, link: string, imageWidth: int, imageHeight: int}>
     */
    private function searchOpenAI(string $query, int $num): array
    {
        try {
            ApiHelper::setOpenAiKey();

            $model = setting('openai_realtime_model', EntityEnum::GPT_4_O_SEARCH_PREVIEW->value);
            $prompt = "Search the web for: {$query}. "
                . "Find {$num} different web pages with relevant images. "
                . 'For each page you find, tell me the page title, the website name, and include the link to the page. '
                . 'Make sure to cite your sources.';

            // Search preview mini models use chat/completions; full search models use responses API
            $isChatModel = str_contains($model, '-search');

            $annotations = [];

            if ($isChatModel) {
                $response = OpenAI::chat()->create([
                    'model'              => $model,
                    'web_search_options' => ['search_context_size' => 'medium'],
                    'messages'           => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);

                foreach ($response->choices as $choice) {
                    $msg = $choice->message;
                    foreach ($msg->annotations ?? [] as $annotation) {
                        if (($annotation->type ?? '') === 'url_citation') {
                            $annotations[] = [
                                'title' => $annotation->urlCitations->title ?? '',
                                'url'   => $annotation->urlCitations->url ?? '',
                            ];
                        }
                    }
                }
            } else {
                $response = OpenAI::responses()->create([
                    'model' => $model,
                    'tools' => [
                        [
                            'type'                => 'web_search_preview',
                            'search_context_size' => 'medium',
                        ],
                    ],
                    'input' => $prompt,
                ]);

                foreach ($response->output as $item) {
                    if ($item->type === 'message') {
                        foreach ($item->content as $contentBlock) {
                            if ($contentBlock->type === 'output_text') {
                                foreach ($contentBlock->annotations as $annotation) {
                                    if ($annotation->type === 'url_citation') {
                                        $annotations[] = [
                                            'title' => $annotation->title ?? '',
                                            'url'   => $annotation->url,
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Fetch OG images from cited page URLs (take extra to compensate for pages without images)
            $uniqueAnnotations = collect($annotations)->unique('url')->take($num * 3);
            $urlMap = $uniqueAnnotations->values()->all();

            if (empty($urlMap)) {
                return [];
            }

            $pageResponses = Http::pool(fn (Pool $pool) => collect($urlMap)->map(fn ($annotation) => $pool->timeout(5)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; MagicAI/1.0)'])
                ->get($annotation['url'])
            )->all());

            // Extract OG images from fetched pages
            $candidates = [];

            foreach ($pageResponses as $index => $pageResponse) {
                try {
                    if (! $pageResponse instanceof Response || ! $pageResponse->successful()) {
                        continue;
                    }

                    $ogImage = $this->extractOgImage($pageResponse->body(), $urlMap[$index]['url']);

                    if ($ogImage) {
                        $domain = parse_url($urlMap[$index]['url'], PHP_URL_HOST) ?: '';

                        $candidates[] = [
                            'title'        => $urlMap[$index]['title'],
                            'imageUrl'     => $ogImage,
                            'thumbnailUrl' => $ogImage,
                            'source'       => $urlMap[$index]['title'],
                            'domain'       => $domain,
                            'link'         => $urlMap[$index]['url'],
                            'imageWidth'   => 0,
                            'imageHeight'  => 0,
                        ];
                    }
                } catch (Throwable) {
                    continue;
                }
            }

            if (empty($candidates)) {
                return [];
            }

            // Validate image URLs are actually accessible with concurrent HEAD requests
            $headResponses = Http::pool(fn (Pool $pool) => collect($candidates)->map(fn ($candidate) => $pool->timeout(3)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; MagicAI/1.0)'])
                ->head($candidate['imageUrl'])
            )->all());

            $results = [];

            foreach ($headResponses as $index => $headResponse) {
                if (count($results) >= $num) {
                    break;
                }

                try {
                    if (! $headResponse instanceof Response) {
                        continue;
                    }

                    if (! $headResponse->successful()) {
                        continue;
                    }

                    $contentType = $headResponse->header('Content-Type');

                    if ($contentType && ! str_starts_with($contentType, 'image/')) {
                        continue;
                    }

                    $results[] = $candidates[$index];
                } catch (Throwable) {
                    continue;
                }
            }

            return $results;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Extract the Open Graph image URL from raw HTML.
     */
    private function extractOgImage(string $html, string $baseUrl): ?string
    {
        $imageUrl = null;

        // Try og:image (both attribute orders)
        if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
            $imageUrl = $m[1];
        } elseif (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/i', $html, $m)) {
            $imageUrl = $m[1];
        }
        // Try twitter:image
        elseif (preg_match('/<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
            $imageUrl = $m[1];
        } elseif (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']twitter:image["\']/i', $html, $m)) {
            $imageUrl = $m[1];
        }
        // Fallback: find first content image with a likely image extension
        elseif (preg_match_all('/<img[^>]+src=["\']([^"\']+\.(?:jpg|jpeg|png|webp)[^"\']*)["\'][^>]*>/i', $html, $matches)) {
            foreach ($matches[1] as $src) {
                // Skip tiny icons, logos, avatars, tracking pixels
                if (preg_match('/logo|icon|avatar|pixel|badge|button|banner-ad|spinner|loading/i', $src)) {
                    continue;
                }

                $imageUrl = $src;

                break;
            }
        }

        if (! $imageUrl) {
            return null;
        }

        // Resolve relative URLs
        if (! str_starts_with($imageUrl, 'http')) {
            $parsed = parse_url($baseUrl);
            $base = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');

            if (str_starts_with($imageUrl, '//')) {
                $imageUrl = ($parsed['scheme'] ?? 'https') . ':' . $imageUrl;
            } elseif (str_starts_with($imageUrl, '/')) {
                $imageUrl = $base . $imageUrl;
            } else {
                $imageUrl = $base . '/' . $imageUrl;
            }
        }

        return $imageUrl;
    }
}
