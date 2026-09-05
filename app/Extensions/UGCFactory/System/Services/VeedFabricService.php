<?php

declare(strict_types=1);

namespace App\Extensions\UGCFactory\System\Services;

use App\Helpers\Classes\ApiHelper;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for fal.ai's VEED Fabric 1.0 lipsync endpoint.
 *
 * Submits queue requests and polls status. Mirrors the conventions of
 * FashionStudioFalAIService and the queue endpoint contract of
 * App\Domains\Engine\Services\FalAIService.
 */
class VeedFabricService
{
    private const SUBMIT_URL = 'https://queue.fal.run/veed/fabric-1.0';

    private const CHECK_URL = 'https://queue.fal.run/veed/fabric-1.0/requests/%s';

    public static function submit(string $imageUrl, string $audioUrl, string $resolution = '720p'): string
    {
        $response = Http::withHeaders([
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => 'Key ' . ApiHelper::setFalAIKey(),
        ])->timeout(120)->post(self::SUBMIT_URL, [
            'image_url'  => $imageUrl,
            'audio_url'  => $audioUrl,
            'resolution' => $resolution,
        ]);

        if ($response->status() === 200 && $requestId = $response->json('request_id')) {
            return $requestId;
        }

        $detail = $response->json('detail');

        throw new RuntimeException(is_string($detail) && $detail !== ''
            ? $detail
            : __('Failed to submit video to VEED Fabric. Check your FAL API key.'));
    }

    /**
     * Check the status of a submitted request.
     *
     * Returns one of:
     * - ['status' => 'completed', 'video_url' => string, 'duration' => int|null]
     * - ['status' => 'failed', 'error' => string]
     * - ['status' => 'in_progress']
     */
    public static function check(string $requestId): array
    {
        $url = sprintf(self::CHECK_URL, $requestId);

        $response = Http::withHeaders([
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => 'Key ' . ApiHelper::setFalAIKey(),
        ])->timeout(60)->get($url);

        $body = $response->json() ?? [];

        $videoUrl = data_get($body, 'video.url');
        if ($videoUrl) {
            return [
                'status'    => 'completed',
                'video_url' => $videoUrl,
                'duration'  => data_get($body, 'video.duration'),
            ];
        }

        $statusValue = strtoupper((string) data_get($body, 'status'));

        if ($statusValue === 'FAILED') {
            return [
                'status' => 'failed',
                'error'  => self::extractError($body),
            ];
        }

        if (in_array($statusValue, ['CREATED', 'IN_QUEUE', 'IN_PROGRESS', 'COMPLETED'], true)) {
            return ['status' => 'in_progress'];
        }

        $statusResponse = Http::withHeaders([
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => 'Key ' . ApiHelper::setFalAIKey(),
        ])->timeout(60)->get($url . '/status');

        $statusBody = $statusResponse->json() ?? [];
        $statusOnly = strtoupper((string) data_get($statusBody, 'status'));

        if ($statusOnly === 'FAILED') {
            return [
                'status' => 'failed',
                'error'  => self::extractError($statusBody),
            ];
        }

        return ['status' => 'in_progress'];
    }

    private static function extractError(array $body): string
    {
        $detail = data_get($body, 'detail');

        if (is_string($detail) && $detail !== '') {
            return $detail;
        }

        if (is_array($detail)) {
            return (string) (json_encode($detail) ?: __('Video generation failed.'));
        }

        $error = data_get($body, 'error');

        if (is_string($error) && $error !== '') {
            return $error;
        }

        return __('Video generation failed.');
    }
}
