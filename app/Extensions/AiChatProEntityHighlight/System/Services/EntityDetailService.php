<?php

declare(strict_types=1);

namespace App\Extensions\AiChatProEntityHighlight\System\Services;

use App\Domains\Entity\Enums\EntityEnum;
use App\Helpers\Classes\ApiHelper;
use App\Models\Setting;
use App\Models\SettingTwo;
use Illuminate\Support\Facades\Http;
use OpenAI\Laravel\Facades\OpenAI;
use Throwable;

class EntityDetailService
{
    /**
     * Fetch structured details for an entity.
     * Uses a non-streaming AI call with JSON response format.
     * Image is deferred — returns image_search_query for frontend lazy loading.
     */
    public static function fetchDetails(
        string $entityText,
        string $entityType,
        ?string $originalContext = null
    ): array {
        $prompt = self::buildDetailPrompt($entityText, $entityType, $originalContext);
        $response = self::callAI($prompt);

        return self::parseDetailResponse($response);
    }

    /**
     * Build the prompt requesting structured entity details in JSON format.
     */
    private static function buildDetailPrompt(
        string $entityText,
        string $entityType,
        ?string $context
    ): string {
        $contextLine = $context
            ? "IMPORTANT — Use the following conversation context to correctly identify what \"{$entityText}\" refers to:\n---\n{$context}\n---"
            : '';

        return <<<PROMPT
Provide structured information about the following {$entityType}: "{$entityText}"
{$contextLine}
Use the conversation context above to disambiguate the entity. For example, if the context discusses a musician and the entity is a number, it likely refers to an album or song title, not the number itself.

Respond in this exact JSON format and nothing else:
{
  "title": "Full name or title",
  "subtitle": "Brief one-line descriptor (e.g. 'American Film Director' or 'Sci-Fi Film, 1999')",
  "description": "2-3 sentence overview paragraph",
  "image_search_query": "best concise search query to find a representative image of this entity",
  "key_facts": [
    "Born: date, location",
    "Notable for: ...",
    "Awards: ...",
    "max 6 facts, each under 80 characters"
  ],
  "sections": [
    {
      "title": "Section heading (e.g. 'Early life and education')",
      "content": "1-2 paragraph section content"
    }
  ],
  "related_entities": [
    {"text": "Related Entity Name", "type": "entity_type"}
  ]
}

Keep it concise but informative. Max 2 sections. Max 3 related entities.
IMPORTANT: All text values (title, subtitle, description, key_facts, sections, image_search_query) MUST be written in the same language as the conversation context above. If the user's question is in French, respond in French. If in Arabic, respond in Arabic. Only the JSON keys should remain in English.
Respond with ONLY the JSON object, no markdown fences or extra text.
PROMPT;
    }

    /**
     * Make a non-streaming AI call to OpenAI for entity details.
     *
     * Uses gpt-4o-mini for cost efficiency — this is a short, structured response
     * that doesn't need the full model.
     */
    private static function callAI(string $prompt): string
    {
        $settings = Setting::query()->first();

        $apiKey = $settings->openai_api_secret ?? config('services.openai.key');
        $baseUrl = 'https://api.openai.com/v1';
        $model = setting('openai_realtime_model', EntityEnum::GPT_4_O_SEARCH_PREVIEW->value);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type'  => 'application/json',
        ])->connectTimeout(300)->timeout(300)->post($baseUrl . '/chat/completions', [
            'model'           => $model,
            'messages'        => [
                ['role' => 'system', 'content' => 'You are a helpful knowledge assistant. Respond only with valid JSON.'],
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        if ($response->failed()) {
            return '{}';
        }

        return $response->json('choices.0.message.content', '{}');
    }

    /**
     * Parse the AI response into a structured detail array with fallback.
     * Image is NOT fetched here — image_search_query is returned for frontend lazy loading.
     */
    private static function parseDetailResponse(string $response): array
    {
        $data = json_decode($response, true);

        if (! $data || ! isset($data['title'])) {
            return [
                'title'              => 'Details unavailable',
                'subtitle'           => '',
                'description'        => 'Could not load entity details at this time.',
                'image_url'          => null,
                'image_search_query' => null,
                'key_facts'          => [],
                'sections'           => [],
                'related_entities'   => [],
            ];
        }

        return [
            'title'              => $data['title'] ?? '',
            'subtitle'           => $data['subtitle'] ?? '',
            'description'        => $data['description'] ?? '',
            'image_url'          => null,
            'image_search_query' => $data['image_search_query'] ?? null,
            'key_facts'          => array_slice($data['key_facts'] ?? [], 0, 6),
            'sections'           => array_slice($data['sections'] ?? [], 0, 2),
            'related_entities'   => array_slice($data['related_entities'] ?? [], 0, 3),
        ];
    }

    /**
     * Fetch an entity image using the configured web-search engine (default_realtime).
     * Falls back to Serper when the primary provider returns empty.
     *
     * @return array{image_url: string, source: string, domain: string, link: string}|null
     */
    public static function fetchEntityImage(string $query): ?array
    {
        try {
            $provider = setting('default_realtime', 'serper');

            return match ($provider) {
                'serper'     => self::searchSerperImage($query),
                'perplexity' => self::searchPerplexityImage($query),
                'openai'     => self::searchOpenAIImage($query),
                default      => null,
            };
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{image_url: string, source: string, domain: string, link: string}|null
     */
    private static function searchSerperImage(string $query): ?array
    {
        $serperKey = SettingTwo::getCache()->serper_api_key ?? null;

        if (! $serperKey) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'X-API-KEY'    => $serperKey,
                'Content-Type' => 'application/json',
            ])->connectTimeout(10)->timeout(10)->post('https://google.serper.dev/images', [
                'q'   => $query,
                'num' => 1,
            ]);

            $images = $response->json('images', []);

            if (empty($images)) {
                return null;
            }

            $img = $images[0];

            return [
                'image_url' => $img['thumbnailUrl'] ?? $img['imageUrl'] ?? null,
                'source'    => $img['source'] ?? '',
                'domain'    => $img['domain'] ?? '',
                'link'      => $img['link'] ?? '',
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{image_url: string, source: string, domain: string, link: string}|null
     */
    private static function searchPerplexityImage(string $query): ?array
    {
        $token = setting('perplexity_key');

        if (! $token) {
            return null;
        }

        try {
            $response = Http::withToken($token)->connectTimeout(10)->timeout(15)->post('https://api.perplexity.ai/chat/completions', [
                'model'    => 'sonar',
                'messages' => [
                    [
                        'role'    => 'user',
                        'content' => "Find 1 relevant image for: {$query}. Return ONLY a JSON array of objects with keys: title, imageUrl, source, domain, link. No other text.",
                    ],
                ],
            ]);

            if (! $response->successful()) {
                return null;
            }

            $content = $response->json('choices.0.message.content', '');

            if (preg_match('/\[.*\]/s', $content, $matches)) {
                $parsed = json_decode($matches[0], true);

                if (is_array($parsed) && ! empty($parsed)) {
                    $img = $parsed[0];

                    if (empty($img['imageUrl'])) {
                        return null;
                    }

                    return [
                        'image_url' => $img['imageUrl'],
                        'source'    => $img['source'] ?? '',
                        'domain'    => $img['domain'] ?? '',
                        'link'      => $img['link'] ?? '',
                    ];
                }
            }

            return null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{image_url: string, source: string, domain: string, link: string}|null
     */
    private static function searchOpenAIImage(string $query): ?array
    {
        try {
            ApiHelper::setOpenAiKey();

            $model = setting('openai_realtime_model', EntityEnum::GPT_4_O_SEARCH_PREVIEW->value);
            $prompt = "Search the web for: {$query} images. "
                . 'Find 6 different web pages with relevant images. '
                . 'For each page you find, tell me the page title, the website name, and include the link to the page. '
                . 'Make sure to cite your sources.';

            $isChatModel = str_contains($model, '-search');
            $annotations = [];

            if ($isChatModel) {
                $response = OpenAI::chat()->create([
                    'model'              => $model,
                    'web_search_options' => ['search_context_size' => 'low'],
                    'messages'           => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);

                foreach ($response->choices as $choice) {
                    foreach ($choice->message->annotations ?? [] as $annotation) {
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
                            'search_context_size' => 'low',
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

            $uniqueAnnotations = collect($annotations)->unique('url')->take(6)->values()->all();

            // Fetch pages one by one until we find a valid OG image (max 6 attempts)
            foreach ($uniqueAnnotations as $annotation) {
                try {
                    $response = Http::timeout(5)
                        ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; MagicAI/1.0)'])
                        ->get($annotation['url']);

                    if (! $response->successful()) {
                        continue;
                    }

                    $ogImage = self::extractOgImage($response->body(), $annotation['url']);

                    if ($ogImage) {
                        $domain = parse_url($annotation['url'], PHP_URL_HOST) ?: '';

                        return [
                            'image_url' => $ogImage,
                            'source'    => $annotation['title'],
                            'domain'    => $domain,
                            'link'      => $annotation['url'],
                        ];
                    }
                } catch (Throwable) {
                    continue;
                }
            }

            return null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Extract the Open Graph image URL from raw HTML.
     */
    private static function extractOgImage(string $html, string $baseUrl): ?string
    {
        $imageUrl = null;

        if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
            $imageUrl = $m[1];
        } elseif (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/i', $html, $m)) {
            $imageUrl = $m[1];
        } elseif (preg_match('/<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
            $imageUrl = $m[1];
        } elseif (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']twitter:image["\']/i', $html, $m)) {
            $imageUrl = $m[1];
        } elseif (preg_match_all('/<img[^>]+src=["\']([^"\']+\.(?:jpg|jpeg|png|webp)[^"\']*)["\'][^>]*>/i', $html, $matches)) {
            foreach ($matches[1] as $src) {
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
