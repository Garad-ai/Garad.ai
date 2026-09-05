<?php

declare(strict_types=1);

namespace App\Extensions\VideoDubbing\System\Services;

use App\Models\SettingTwo;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ElevenLabsDubbingService
{
    public const BASE_URL = 'https://api.elevenlabs.io';

    private ?string $apiKey;

    public function __construct()
    {
        $this->apiKey = SettingTwo::getCache()?->elevenlabs_api_key;
    }

    /**
     * Create a dubbing job.
     *
     * @param  array{
     *     file?: UploadedFile,
     *     source_url?: string,
     *     target_lang: string,
     *     source_lang?: string,
     *     name?: string,
     *     num_speakers?: int,
     *     mode?: string,
     *     watermark?: bool,
     *     start_time?: int,
     *     end_time?: int,
     *     highest_resolution?: bool,
     *     drop_background_audio?: bool,
     *     use_profanity_filter?: bool,
     *     dubbing_studio?: bool,
     *     disable_voice_cloning?: bool,
     *     target_accent?: string,
     * }  $params
     *
     * @return array{error?: string, dubbing_id?: string, expected_duration_sec?: float}
     */
    public function createDubbing(array $params): array
    {
        if (blank($this->apiKey)) {
            return ['error' => __('The video dubbing service is not configured yet. Please contact the administrator.')];
        }

        try {
            $request = Http::withHeaders($this->getHeaders())
                ->timeout(300);

            if (isset($params['file_path'])) {
                $filePath = $params['file_path'];
                $fileName = $params['file_name'] ?? basename($filePath);
                $fileMime = $params['file_mime'] ?? 'application/octet-stream';
                unset($params['file_path'], $params['file_name'], $params['file_mime']);

                if (! is_file($filePath)) {
                    return ['error' => __('The uploaded file could not be read. Please try uploading again.')];
                }

                $request = $request->attach(
                    'file',
                    fopen($filePath, 'r'),
                    $fileName,
                    ['Content-Type' => $fileMime]
                );

                foreach ($params as $key => $value) {
                    if ($value === null) {
                        continue;
                    }

                    $request = $request->attach(
                        $key,
                        is_bool($value) ? ($value ? 'true' : 'false') : (string) $value
                    );
                }

                $response = $request->post(self::BASE_URL . '/v1/dubbing');
            } else {
                $response = $request
                    ->asMultipart()
                    ->post(self::BASE_URL . '/v1/dubbing', $this->buildMultipartData($params));
            }

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('ElevenLabs dubbing failed', ['status' => $response->status(), 'body' => $response->body()]);

            $payload = $response->json();
            $detailCode = data_get($payload, 'detail.code') ?? data_get($payload, 'detail.status');
            $detailMessage = data_get($payload, 'detail.message');

            if ($response->status() === 429 || $detailCode === 'too_many_concurrent_requests' || $detailCode === 'concurrent_limit_exceeded') {
                return [
                    'error'     => __('Dubbing service is busy. Retrying shortly.'),
                    'retryable' => true,
                ];
            }

            if ($detailCode === 'invalid_url') {
                return [
                    'error' => __('This video URL is not supported. Please upload the file or use a direct video link.'),
                ];
            }

            return [
                'error' => $detailMessage ?: __('Something went wrong while processing your video. Please try again later.'),
            ];
        } catch (Exception $e) {
            Log::error('ElevenLabs dubbing exception: ' . $e->getMessage(), ['exception' => $e]);

            return [
                'error' => __('An unexpected error occurred. Please try again later.'),
            ];
        }
    }

    /**
     * Get the status of a dubbing job.
     *
     * @return array{error?: string, dubbing_id?: string, name?: string, status?: string, source_language?: string, target_languages?: array, error_message?: string, media_metadata?: array}
     */
    public function getDubbingStatus(string $dubbingId): array
    {
        if (blank($this->apiKey)) {
            return ['error' => __('The video dubbing service is not configured yet. Please contact the administrator.')];
        }

        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get(self::BASE_URL . "/v1/dubbing/{$dubbingId}");

            if ($response->successful()) {
                return $response->json();
            }

            return [
                'error' => __('Could not check the video status. Please try again later.'),
            ];
        } catch (Exception $e) {
            return [
                'error' => __('An unexpected error occurred. Please try again later.'),
            ];
        }
    }

    /**
     * Download the dubbed audio/video file.
     *
     * @return array{error?: string, content?: string, content_type?: string}
     */
    public function downloadDubbedFile(string $dubbingId, string $languageCode): array
    {
        if (blank($this->apiKey)) {
            return ['error' => __('The video dubbing service is not configured yet. Please contact the administrator.')];
        }

        try {
            $response = Http::withHeaders($this->getHeaders())
                ->timeout(300)
                ->get(self::BASE_URL . "/v1/dubbing/{$dubbingId}/audio/{$languageCode}");

            if ($response->successful()) {
                return [
                    'content'      => $response->body(),
                    'content_type' => $response->header('Content-Type'),
                ];
            }

            if ($response->status() === 425) {
                return ['error' => __('Your video is still being processed. Please wait a moment and try again.')];
            }

            return [
                'error' => __('Could not download the dubbed video. Please try again later.'),
            ];
        } catch (Exception $e) {
            return [
                'error' => __('An unexpected error occurred. Please try again later.'),
            ];
        }
    }

    /**
     * @return array<int, array{name: string, contents: string}>
     */
    private function buildMultipartData(array $params): array
    {
        $multipart = [];

        foreach ($params as $key => $value) {
            if ($value === null) {
                continue;
            }

            $multipart[] = [
                'name'     => $key,
                'contents' => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value,
            ];
        }

        return $multipart;
    }

    private function getHeaders(): array
    {
        return [
            'xi-api-key' => $this->apiKey,
            'accept'     => 'application/json',
        ];
    }
}
