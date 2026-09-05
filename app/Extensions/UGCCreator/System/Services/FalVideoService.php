<?php

declare(strict_types=1);

namespace App\Extensions\UGCCreator\System\Services;

use App\Helpers\Classes\ApiHelper;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Thin client for fal.ai video generation queue endpoints.
 *
 * Submits queue requests and polls status against any fal.ai video endpoint —
 * the calling job supplies both the endpoint path and the payload, so the
 * service stays model-agnostic. Polling uses the response_url / status_url
 * returned by fal.ai on submit (the only reliable way to know the correct
 * app namespace, since some submit paths include extra sub-action segments).
 */
class FalVideoService
{
    private const BASE_URL = 'https://queue.fal.run/';

    /**
     * Submit a request to fal.ai. Returns request_id and the polling URLs
     * provided by fal.ai itself.
     *
     * @return array{request_id: string, response_url: ?string, status_url: ?string}
     */
    public static function submit(string $endpoint, array $payload): array
    {
        $url = self::BASE_URL . ltrim($endpoint, '/');

        $response = Http::withHeaders([
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => 'Key ' . ApiHelper::setFalAIKey(),
        ])->timeout(120)->post($url, $payload);

        $requestId = $response->json('request_id');

        if ($response->status() === 200 && is_string($requestId) && $requestId !== '') {
            return [
                'request_id'   => $requestId,
                'response_url' => self::stringOrNull($response->json('response_url')),
                'status_url'   => self::stringOrNull($response->json('status_url')),
            ];
        }

        $detail = $response->json('detail');
        $message = is_string($detail) && $detail !== ''
            ? $detail
            : __('Failed to submit video to fal.ai. Check your FAL API key.');

        Log::error('UGCCreator fal.ai submit failed', [
            'endpoint' => $endpoint,
            'http'     => $response->status(),
            'body'     => $response->json() ?? $response->body(),
        ]);

        throw new RuntimeException($message);
    }

    /**
     * Check the status of a submitted request using URLs returned by fal.ai.
     *
     * Returns one of:
     * - ['status' => 'completed', 'video_url' => string, 'duration' => int|null]
     * - ['status' => 'failed', 'error' => string]
     * - ['status' => 'in_progress']
     */
    public static function check(?string $responseUrl, ?string $statusUrl): array
    {
        // status_url is authoritative for queue state. Hit it first so we don't
        // misread a "still in progress" 400 from response_url as a hard failure.
        $queueStatus = null;
        if ($statusUrl) {
            $statusResponse = self::get($statusUrl);
            $statusBody = $statusResponse->json() ?? [];

            $queueStatus = strtoupper((string) data_get($statusBody, 'status'));

            if ($queueStatus === 'FAILED') {
                $error = self::extractError($statusBody);
                Log::error('UGCCreator fal.ai status reported FAILED', [
                    'url'   => $statusUrl,
                    'error' => $error,
                ]);

                return ['status' => 'failed', 'error' => $error];
            }

            // Account-level errors (locked user, exhausted balance, bad key)
            // come through response_url as 401/402/403 even while status_url
            // still reports IN_QUEUE. Probe response_url so we end processing
            // instead of polling forever.
            if (in_array($queueStatus, ['CREATED', 'IN_QUEUE', 'IN_PROGRESS'], true)) {
                if ($responseUrl) {
                    $probe = self::get($responseUrl);
                    if (in_array($probe->status(), [401, 402, 403], true)) {
                        $error = self::extractError($probe->json() ?? []);
                        Log::error('UGCCreator fal.ai account-level failure', [
                            'url'   => $responseUrl,
                            'http'  => $probe->status(),
                            'error' => $error,
                        ]);

                        return ['status' => 'failed', 'error' => $error];
                    }
                }

                return ['status' => 'in_progress'];
            }
        }

        // Only fetch response_url once the queue says COMPLETED (or status_url
        // was not provided). Otherwise we'll get noisy "still in progress" 400s.
        if ($responseUrl && ($queueStatus === 'COMPLETED' || $queueStatus === null)) {
            $response = self::get($responseUrl);
            $body = $response->json() ?? [];

            $videoUrl = data_get($body, 'video.url');
            if ($videoUrl) {
                return [
                    'status'    => 'completed',
                    'video_url' => $videoUrl,
                    'duration'  => data_get($body, 'video.duration'),
                ];
            }

            $bodyStatus = strtoupper((string) data_get($body, 'status'));

            if ($bodyStatus === 'FAILED') {
                $error = self::extractError($body);
                Log::error('UGCCreator fal.ai result reported FAILED', [
                    'url'   => $responseUrl,
                    'error' => $error,
                ]);

                return ['status' => 'failed', 'error' => $error];
            }

            // If the queue already said COMPLETED but response_url is 4xx with a
            // body, fal.ai is reporting an inference error (bad input, file
            // download failure, etc). Treat as a hard failure.
            if ($queueStatus === 'COMPLETED' && $response->status() >= 400 && ! empty($body)) {
                $error = self::extractError($body);
                Log::error('UGCCreator fal.ai inference error', [
                    'url'   => $responseUrl,
                    'http'  => $response->status(),
                    'error' => $error,
                ]);

                return ['status' => 'failed', 'error' => $error];
            }
        }

        return ['status' => 'in_progress'];
    }

    private static function get(string $url): Response
    {
        return Http::withHeaders([
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => 'Key ' . ApiHelper::setFalAIKey(),
        ])->timeout(60)->get($url);
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function extractError(array $body): string
    {
        $detail = data_get($body, 'detail');

        if (is_string($detail) && $detail !== '') {
            return $detail;
        }

        if (is_array($detail)) {
            $first = $detail[0] ?? null;
            if (is_array($first) && is_string($first['msg'] ?? null) && $first['msg'] !== '') {
                return $first['msg'];
            }
            if (is_string($first) && $first !== '') {
                return $first;
            }

            return (string) (json_encode($detail) ?: __('Video generation failed.'));
        }

        $error = data_get($body, 'error');

        if (is_string($error) && $error !== '') {
            return $error;
        }

        return __('Video generation failed.');
    }
}
