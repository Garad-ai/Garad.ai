<?php

declare(strict_types=1);

namespace App\Extensions\FashionStudio\System\Services;

use App\Domains\Entity\Enums\EntityEnum;
use App\Helpers\Classes\ApiHelper;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FashionStudioFalAIService
{
    public const FAL_BASE_ENDPOINT = 'https://queue.fal.run/fal-ai/%s';

    public const FAL_CHECK_ENDPOINT = 'https://queue.fal.run/fal-ai/%s/requests/%s';

    public const VIDEO_BASE_ENDPOINT = 'https://queue.fal.run/fal-ai/%s';

    public const VIDEO_CHECK_ENDPOINT = 'https://queue.fal.run/fal-ai/%s/requests/%s';

    /**
     * Resolve the FAL model path for image generation.
     *
     * Accepts an EntityEnum, the raw enum value, or null (which falls back to
     * the admin-configured default). Returns the path segment after `fal-ai/`.
     */
    public static function resolveImageModelPath(EntityEnum|string|null $model): string
    {
        if ($model instanceof EntityEnum) {
            return $model->value;
        }

        if (is_string($model) && $model !== '') {
            return $model;
        }

        return FashionStudioImageModelRegistry::getDefaultModel()->value;
    }

    /**
     * Strip a trailing /edit suffix to derive the base model path used by
     * FAL's status endpoint and text-to-image endpoint.
     */
    public static function getImageModelBasePath(string $modelPath): string
    {
        if (str_ends_with($modelPath, '/edit')) {
            return substr($modelPath, 0, -strlen('/edit'));
        }

        return $modelPath;
    }

    /**
     * Get the video model endpoint path from setting
     */
    public static function getVideoModel(): string
    {
        return setting('fashion-studio-video-default-model', EntityEnum::VEO_3_1_IMAGE_TO_VIDEO->value);
    }

    /**
     * Get the base model path for checking video status (without sub-paths)
     * e.g., 'veo3.1/image-to-video' -> 'veo3.1'
     * e.g., 'kling-video/v2.1/master/image-to-video' -> 'kling-video'
     */
    public static function getVideoModelBasePath(): string
    {
        $model = self::getVideoModel();
        $parts = explode('/', $model);

        return $parts[0] ?? $model;
    }

    public static function generate($prompt, array $imageUrls = [], int $numImages = 1, array $imageSize = [], EntityEnum|string|null $model = null): string
    {
        set_time_limit(0);
        ini_set('max_execution_time', 540);

        $request = [
            'prompt'     => $prompt,
            'image_urls' => $imageUrls,
            'num_images' => $numImages,
        ];

        if (! empty($imageSize['width']) && ! empty($imageSize['height'])) {
            $request['image_size'] = [
                'width'  => $imageSize['width'],
                'height' => $imageSize['height'],
            ];
        }

        $modelPath = self::resolveImageModelPath($model);
        $url = sprintf(self::FAL_BASE_ENDPOINT, $modelPath);

        $http = Http::withHeaders([
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => 'Key ' . ApiHelper::setFalAIKey(),
        ])->post($url, $request);

        if (($http->status() === 200) && $requestId = $http->json('request_id')) {
            return $requestId;
        }

        $detail = $http->json('detail');

        throw new RuntimeException(__($detail ?: 'Check your FAL API key.'));
    }

    /**
     * Generate video from image using configurable model
     */
    public static function generateVideo(string $prompt, string $imageUrl): string
    {
        set_time_limit(0);
        ini_set('max_execution_time', 540);

        $model = self::getVideoModel();
        $url = sprintf(self::VIDEO_BASE_ENDPOINT, $model);

        $request = [
            'prompt'    => $prompt,
            'image_url' => $imageUrl,
        ];

        $http = Http::withHeaders([
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => 'Key ' . ApiHelper::setFalAIKey(),
        ])->post($url, $request);

        if (($http->status() === 200) && $requestId = $http->json('request_id')) {
            return $requestId;
        }

        $detail = $http->json('detail');

        throw new RuntimeException(__($detail ?: 'Check your FAL API key.'));
    }

    /**
     * Check video generation status
     */
    public static function checkVideo(string $uuid): ?array
    {
        set_time_limit(0);
        ini_set('max_execution_time', 540);

        $modelBasePath = self::getVideoModelBasePath();
        $url = sprintf(self::VIDEO_CHECK_ENDPOINT, $modelBasePath, $uuid);

        $http = Http::withHeaders([
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => 'Key ' . ApiHelper::setFalAIKey(),
        ])->get($url);

        // Check if request is still in progress (check this FIRST before treating as error)
        $detail = $http->json('detail');
        $detailString = is_string($detail) ? $detail : (is_array($detail) ? json_encode($detail) : '');
        if ($detailString && str_contains(strtolower($detailString), 'in progress')) {
            return null; // Still processing
        }

        // Handle error responses
        if ($http->status() === 422) {
            $error = is_array($detail) ? ($detail[0]['msg'] ?? json_encode($detail)) : $detail;

            throw new RuntimeException(__($error ?: 'Unable to process request.'));
        }

        // Handle other error status codes (4xx, 5xx)
        if ($http->status() >= 400) {
            $error = is_string($detail) ? $detail : ($http->json('message') ?? $http->json('error') ?? null);

            throw new RuntimeException(__($error ?: 'Video generation failed. Status: ' . $http->status()));
        }

        // Check for error in response body (some APIs return 200 with error in body)
        $error = $http->json('error');
        if ($error) {
            $errorMsg = is_string($error) ? $error : json_encode($error);

            throw new RuntimeException(__($errorMsg));
        }

        // Check for video in the response
        $video = $http->json('video');
        if ($video) {
            $videoUrl = is_array($video) ? data_get($video, 'url') : $video;

            if ($videoUrl) {
                return [
                    'video_url' => $videoUrl,
                    'type'      => 'video',
                ];
            }
        }

        // Some models return videos in different formats
        $videoUrl = $http->json('video_url') ?? $http->json('output.video_url') ?? $http->json('result.video_url');
        if ($videoUrl) {
            return [
                'video_url' => $videoUrl,
                'type'      => 'video',
            ];
        }

        return null;
    }

    /**
     * Generate image from text description
     */
    public static function generateFromText(string $prompt, array $options = [], EntityEnum|string|null $model = null): string
    {
        set_time_limit(0);
        ini_set('max_execution_time', 540);

        $request = array_merge(['prompt' => $prompt], $options);

        $modelPath = self::getImageModelBasePath(self::resolveImageModelPath($model));
        $url = sprintf(self::FAL_BASE_ENDPOINT, $modelPath);

        $http = Http::withHeaders([
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => 'Key ' . ApiHelper::setFalAIKey(),
        ])->post($url, $request);

        if (($http->status() === 200) && $requestId = $http->json('request_id')) {
            return $requestId;
        }

        $detail = $http->json('detail');

        throw new RuntimeException(__($detail ?: 'Failed to generate image. Check your FAL API key.'));
    }

    public static function check($uuid, EntityEnum|string|null $model = null): ?array
    {
        set_time_limit(0);
        ini_set('max_execution_time', 540);

        $modelBasePath = self::getImageModelBasePath(self::resolveImageModelPath($model));
        $url = sprintf(self::FAL_CHECK_ENDPOINT, $modelBasePath, $uuid);

        $http = Http::withHeaders([
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => 'Key ' . ApiHelper::setFalAIKey(),
        ])->get($url);

        // Check if request is still in progress (check this FIRST before treating as error)
        $detail = $http->json('detail');
        $detailString = is_string($detail) ? $detail : (is_array($detail) ? json_encode($detail) : '');
        if ($detailString && str_contains(strtolower($detailString), 'in progress')) {
            return null; // Still processing
        }

        // Handle error responses
        if ($http->status() === 422) {
            $error = is_array($detail) ? ($detail[0]['msg'] ?? json_encode($detail)) : $detail;

            throw new RuntimeException(__($error ?: 'Unable to process request.'));
        }

        // Handle other error status codes (4xx, 5xx)
        if ($http->status() >= 400) {
            $error = is_string($detail) ? $detail : ($http->json('message') ?? $http->json('error') ?? null);

            throw new RuntimeException(__($error ?: 'Generation failed. Status: ' . $http->status()));
        }

        // Check for error in response body (some APIs return 200 with error in body)
        $error = $http->json('error');
        if ($error) {
            $errorMsg = is_string($error) ? $error : json_encode($error);

            throw new RuntimeException(__($errorMsg));
        }

        // Check if we have images in the response
        $images = $http->json('images');

        if (! $images || ! is_array($images) || count($images) === 0) {
            return null; // No images yet or invalid response
        }

        // Check if images are direct URL strings (new format)
        if (is_string($images[0])) {
            return [
                'images' => $images,
                'size'   => $http->json('size') ?: 'unknown',
            ];
        }

        // Handle object format (old/standard format)
        if (is_array($images[0])) {
            $firstImage = $images[0];

            return [
                'images' => array_map(static function ($image) {
                    return data_get($image, 'url');
                }, $images),
                'size'  => (data_get($firstImage, 'width') ?: 'unknown') . 'x' . (data_get($firstImage, 'height') ?: 'unknown'),
            ];
        }

        // Fallback for unexpected format
        return null;
    }

    public static function getStatus($url)
    {
        ini_set('max_execution_time', 440);
        set_time_limit(0);

        $response = Http::withHeaders([
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => 'Key ' . ApiHelper::setFalAIKey(),
        ])
            ->get($url);

        return $response->json();
    }
}
