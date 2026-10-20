<?php

namespace App\Extensions\OpenRouter\System\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class RouterAiService
{
    public const BASE_URL = 'https://openrouter.ai/api/v1/chat/completions';

    /** OpenRouter model ids that were retired upstream, mapped to their live successor. */
    private const RETIRED_MODELS = [
        'x-ai/grok-beta'                                 => 'x-ai/grok-4.3',
        'anthropic/claude-3-5-haiku-20241022'            => 'anthropic/claude-haiku-4.5',
        'anthropic/claude-3-5-haiku-20241022:beta'       => 'anthropic/claude-haiku-4.5',
        'anthropic/claude-3-5-haiku'                     => 'anthropic/claude-haiku-4.5',
        'anthropic/claude-3-5-haiku:beta'                => 'anthropic/claude-haiku-4.5',
    ];

    public static function resolveModel(string $model): string
    {
        $resolved = self::RETIRED_MODELS[$model] ?? $model;
        if ($resolved !== $model) {
            \Illuminate\Support\Facades\Log::info('OpenRouter model retired upstream; using successor', ['requested' => $model, 'used' => $resolved]);
        }

        return $resolved;
    }

    public function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . setting('open_router_api'),
            'HTTP-Referer'  => config('app.url'),
            'X-Title'       => config('app.url'),
        ];
    }

    /** Sends the full conversation (system + history) and throws with the provider's real error on failure. */
    public function responseWithHistory(array $messages, string $model): string
    {
        $response = Http::withHeaders($this->headers())
            ->connectTimeout(15)
            ->timeout(300)
            ->post(self::BASE_URL, [
                'model'    => self::resolveModel($model),
                'stream'   => true,
                'messages' => array_values($messages),
            ]);

        if (! $response->successful()) {
            $detail = $response->json('error.message') ?? $response->json('error') ?? $response->body();
            throw new \RuntimeException('OpenRouter returned HTTP ' . $response->status() . ': ' . (is_string($detail) ? $detail : json_encode($detail)));
        }

        return $response->body();
    }

    public function response($prompt, $model): JsonResponse|string
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . setting('open_router_api'),
            'HTTP-Referer'  => config('app.url'),
            'X-Title'       => config('app.url'),
        ])
            ->post(self::BASE_URL, [
                'model'    => self::resolveModel($model),
                'stream'   => true,
                'messages' => [
                    [
                        'role'    => 'user',
                        'content' => $prompt,
                    ],
                ],
            ]);

        if ($response->successful()) {
            return $response->body();
        }

        return response()->json(['error' => 'Request failed', 'details' => $response->body()], $response->status());
    }
}
