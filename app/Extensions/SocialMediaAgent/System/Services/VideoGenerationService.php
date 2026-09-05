<?php

namespace App\Extensions\SocialMediaAgent\System\Services;

use App\Extensions\SocialMedia\System\Services\Generator\GoogleVeo2Service;
use App\Helpers\Classes\ApiHelper;
use Exception;
use Illuminate\Support\Facades\Log;

class VideoGenerationService
{
    /**
     * Submit a video generation request for a given post.
     */
    public function generateVideoForPost(string $postContent, array $options = []): array
    {
        try {
            ApiHelper::setFalAIKey();

            $platform = strtolower($options['platform'] ?? '');
            $prompt = $this->buildPrompt($postContent, $platform);

            $response = GoogleVeo2Service::generate($prompt, [
                'aspect_ratio' => $this->aspectRatioForPlatform($platform),
                'duration'     => $this->durationForPlatform($platform),
            ]);

            if ($response->failed()) {
                Log::error('VideoGenerationService generate failed', ['body' => $response->body()]);

                return [
                    'success' => false,
                    'error'   => 'Video generation request failed.',
                ];
            }

            $status = strtoupper((string) $response->json('status', 'IN_QUEUE'));
            $requestId = $response->json('request_id');
            $videoUrl = $response->json('video.url');

            $result = [
                'success'     => true,
                'request_id'  => $requestId,
                'status'      => $this->normalizeStatus($status),
                'prompt'      => $prompt,
                'video_url'   => null,
                'platform'    => $platform,
            ];

            if ($videoUrl) {
                $storedPath = GoogleVeo2Service::downloadAndSaveVideoFromUrl($videoUrl);
                if ($storedPath) {
                    $result['video_url'] = $storedPath;
                    $result['status'] = 'completed';
                }
            }

            return $result;
        } catch (Exception $e) {
            Log::error('VideoGenerationService error', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Poll Fal.ai queue for an existing request.
     */
    public function checkStatus(string $requestId): array
    {
        try {
            ApiHelper::setFalAIKey();

            $statusResponse = GoogleVeo2Service::status($requestId);

            if ($statusResponse->failed()) {
                return [
                    'success' => false,
                    'status'  => 'failed',
                ];
            }

            $status = strtoupper((string) $statusResponse->json('status', 'IN_QUEUE'));

            if ($status === 'COMPLETED') {
                $content = GoogleVeo2Service::content($requestId);

                if ($content->failed()) {
                    return [
                        'success' => false,
                        'status'  => 'failed',
                    ];
                }

                $videoUrl = $content->json('video.url');

                if ($videoUrl) {
                    $storedPath = GoogleVeo2Service::downloadAndSaveVideoFromUrl($videoUrl);

                    if ($storedPath) {
                        return [
                            'success'   => true,
                            'status'    => 'completed',
                            'video_url' => $storedPath,
                        ];
                    }
                }

                return [
                    'success' => false,
                    'status'  => 'failed',
                ];
            }

            if (in_array($status, ['IN_QUEUE', 'IN_PROGRESS'], true)) {
                return [
                    'success' => true,
                    'status'  => $this->normalizeStatus($status),
                ];
            }

            return [
                'success' => false,
                'status'  => 'failed',
            ];
        } catch (Exception $e) {
            Log::error('VideoGenerationService checkStatus error', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'status'  => 'failed',
            ];
        }
    }

    protected function buildPrompt(string $content, string $platform): string
    {
        $audience = $platform === 'youtube-shorts'
            ? 'Create a dynamic vertical video optimized for YouTube Shorts'
            : 'Create a cinematic horizontal video tailored for the main YouTube feed';

        return <<<PROMPT
{$audience}. Keep it tightly synced to the following message:
"{$content}"

Focus on storytelling visuals, scene transitions, and pacing that matches {$platform} best practices. Avoid on-screen text.
PROMPT;
    }

    protected function aspectRatioForPlatform(string $platform): string
    {
        return $platform === 'youtube' ? '16:9' : '9:16';
    }

    protected function durationForPlatform(string $platform): string
    {
        return $platform === 'youtube' ? '12s' : '8s';
    }

    protected function normalizeStatus(string $status): string
    {
        return match ($status) {
            'COMPLETED'   => 'completed',
            'IN_PROGRESS' => 'generating',
            default       => 'pending',
        };
    }
}
