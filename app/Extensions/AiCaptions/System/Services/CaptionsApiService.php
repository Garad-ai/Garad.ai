<?php

declare(strict_types=1);

namespace App\Extensions\AiCaptions\System\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Wrapper around the Captions (Mirage) Video Captions API.
 *
 * @see https://captions.ai/help/api-reference/video-captions/add-captions-to-a-video
 * @see https://captions.ai/help/api-reference/videos
 *
 * Endpoints:
 *  - POST /v1/videos/captions          (multipart: caption_template_id + video|video_id)
 *  - GET  /v1/videos/{video_id}        (poll for status)
 *  - GET  /v1/videos/{video_id}/content (302 redirect to the rendered video URL)
 */
class CaptionsApiService
{
    public const STATUS_PROCESSING = 'processing';

    public const STATUS_RENDERING = 'rendering';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public function __construct(private ?string $apiKey = null)
    {
        if ($this->apiKey === null) {
            $this->apiKey = (string) setting('ai_captions_api_key', '');
        }
    }

    public function hasApiKey(): bool
    {
        return $this->apiKey !== null && $this->apiKey !== '';
    }

    /**
     * Submit a video for captioning via multipart upload.
     *
     * Exactly one of $absoluteFilePath or $existingVideoId must be provided.
     *
     * @return array{success: bool, video_id?: string, error?: string}
     */
    public function submit(string $templateId, ?string $absoluteFilePath = null, ?string $existingVideoId = null): array
    {
        if (! $this->hasApiKey()) {
            return ['success' => false, 'error' => __('Captions API key is not configured.')];
        }

        if ($absoluteFilePath === null && $existingVideoId === null) {
            return ['success' => false, 'error' => __('A video file or existing video id is required.')];
        }

        try {
            $request = $this->client()->asMultipart();

            if ($absoluteFilePath !== null) {
                if (! is_file($absoluteFilePath)) {
                    return ['success' => false, 'error' => __('Uploaded video file could not be read.')];
                }

                $request = $request->attach('video', file_get_contents($absoluteFilePath), basename($absoluteFilePath));
            }

            $payload = ['caption_template_id' => $templateId];

            if ($existingVideoId !== null) {
                $payload['video_id'] = $existingVideoId;
            }

            $response = $request->post('/v1/videos/captions', $payload);
        } catch (Throwable $e) {
            Log::error('AiCaptions: submit request failed', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => __('Failed to reach Captions API: :err', ['err' => $e->getMessage()])];
        }

        if (! $response->successful()) {
            return [
                'success' => false,
                'error'   => $response->json('message')
                    ?? $response->json('error.message')
                    ?? $response->json('error')
                    ?? __('Captions API returned status :code.', ['code' => $response->status()]),
            ];
        }

        $videoId = $response->json('id') ?? $response->json('video_id');

        if (! $videoId) {
            return ['success' => false, 'error' => __('Captions API did not return a video id.')];
        }

        return ['success' => true, 'video_id' => (string) $videoId];
    }

    /**
     * Poll a captions video id for status. On COMPLETE, resolves the rendered
     * video URL by following the /content redirect.
     *
     * @return array{status: string, output_url?: ?string, error?: ?string, raw?: array}
     */
    public function poll(string $videoId): array
    {
        if (! $this->hasApiKey()) {
            throw new RuntimeException(__('Captions API key is not configured.'));
        }

        try {
            $response = $this->client()->get('/v1/videos/' . rawurlencode($videoId));
        } catch (Throwable $e) {
            Log::warning('AiCaptions: poll request failed', ['video_id' => $videoId, 'error' => $e->getMessage()]);

            return ['status' => self::STATUS_PROCESSING];
        }

        if (! $response->successful()) {
            return [
                'status' => self::STATUS_FAILED,
                'error'  => $response->json('message') ?? __('Captions API returned status :code.', ['code' => $response->status()]),
            ];
        }

        $body = (array) $response->json();
        $rawState = strtoupper((string) ($body['status'] ?? $body['state'] ?? ''));

        $status = match ($rawState) {
            'COMPLETE', 'COMPLETED' => self::STATUS_COMPLETED,
            'FAILED', 'CANCELLED', 'CANCELED' => self::STATUS_FAILED,
            'RENDERING' => self::STATUS_RENDERING,
            default     => self::STATUS_PROCESSING,
        };

        $outputUrl = null;

        if ($status === self::STATUS_COMPLETED) {
            $outputUrl = $this->resolveContentUrl($videoId);
        }

        return [
            'status'     => $status,
            'output_url' => $outputUrl,
            'error'      => is_array($body['error'] ?? null)
                ? ($body['error']['message'] ?? null)
                : ($body['error'] ?? null),
            'raw'        => $body,
        ];
    }

    /**
     * Fetch caption templates from GET /v1/videos/captions/templates,
     * paginating via the `after` cursor until `has_more` is false. Falls back
     * to the curated config list when the API is unavailable or unkeyed.
     *
     * @return array<int, array{id: string, name: string, description?: string, preview_text?: string, preview_url?: ?string}>
     */
    public function templates(): array
    {
        if (! $this->hasApiKey()) {
            return config('aicaptions.fallback_templates', []);
        }

        try {
            $all = [];
            $after = null;

            do {
                $query = ['limit' => 100];

                if ($after !== null) {
                    $query['after'] = $after;
                }

                $response = $this->client()->get('/v1/videos/captions/templates', $query);

                if (! $response->successful()) {
                    Log::info('AiCaptions: template list returned non-success, falling back.', [
                        'status' => $response->status(),
                    ]);

                    return config('aicaptions.fallback_templates', []);
                }

                $items = (array) ($response->json('data') ?? []);

                foreach ($items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $id = (string) ($item['id'] ?? '');

                    if ($id === '') {
                        continue;
                    }

                    $all[] = [
                        'id'          => $id,
                        'name'        => (string) ($item['name'] ?? $id),
                        'preview_url' => isset($item['preview_url']) ? (string) $item['preview_url'] : null,
                    ];

                    $after = $id;
                }

                $hasMore = (bool) $response->json('has_more', false);
            } while ($hasMore && $items !== []);

            return $all !== [] ? $all : config('aicaptions.fallback_templates', []);
        } catch (Throwable $e) {
            Log::info('AiCaptions: template list endpoint unavailable, falling back to curated config.', [
                'error' => $e->getMessage(),
            ]);

            return config('aicaptions.fallback_templates', []);
        }
    }

    /**
     * Resolve the rendered video URL by issuing a non-following GET against
     * /v1/videos/{id}/content and reading the Location header.
     */
    public function resolveContentUrl(string $videoId): ?string
    {
        try {
            $response = $this->client()
                ->withOptions(['allow_redirects' => false])
                ->get('/v1/videos/' . rawurlencode($videoId) . '/content');
        } catch (Throwable $e) {
            Log::warning('AiCaptions: resolve content url failed', [
                'video_id' => $videoId,
                'error'    => $e->getMessage(),
            ]);

            return null;
        }

        $location = $response->header('Location');

        if (is_string($location) && $location !== '') {
            return $location;
        }

        return null;
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('aicaptions.api.base_url'))
            ->timeout((int) config('aicaptions.api.timeout', 120))
            ->withHeaders([
                'x-api-key' => $this->apiKey,
                'Accept'    => 'application/json',
            ]);
    }
}
