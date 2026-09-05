<?php

namespace App\Extensions\SocialMediaAgent\System\Services;

use App\Domains\Entity\Enums\EntityEnum;
use App\Helpers\Classes\ApiHelper;
use App\Helpers\Classes\OpenAiParamHelper;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImageGenerationService
{
    protected ?string $model = null;

    protected string $falApiKey;

    private static string $baseApiUrl = 'https://queue.fal.run/fal-ai/';

    public function getModel(): string
    {
        if ($this->model === null) {
            $this->model = setting('social_media_agent_image_model', 'nano-banana-pro');
        }

        return $this->model;
    }

    /**
     * Generate an image for a post (async with webhook)
     * Returns request_id for tracking
     */
    public function generateImageForPost(string $postContent, array $options = []): array
    {
        $this->falApiKey = ApiHelper::setFalAIKey();

        try {
            // Create image prompt from post content
            $imagePrompt = $this->createImagePrompt($postContent, $options);

            // Submit to Fal.ai (async)
            $result = $this->submitToFalAi($imagePrompt, $options);

            if (! $result['success']) {
                return [
                    'success' => false,
                    'error'   => $result['error'] ?? 'Failed to submit image generation',
                ];
            }

            return [
                'success'      => true,
                'request_id'   => $result['request_id'],
                'prompt'       => $imagePrompt,
                'status'       => $result['status'] ?? 'pending', // Image is being generated
                'image_url'    => $result['image_url'] ?? null,
                'submitted_at' => now()->toIso8601String(),
            ];
        } catch (Exception $e) {
            Log::error('ImageGenerationService Error: ' . $e->getMessage());

            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Submit image generation request to Fal.ai
     */
    protected function submitToFalAi(string $prompt, array $options = []): array
    {
        $this->falApiKey = ApiHelper::setFalAIKey();

        try {
            $webhookUrl = route('dashboard.user.social-media.agent.fal-webhook');
            $apiUrl = $this->buildApiUrl();

            $response = Http::withHeaders([
                'Authorization' => 'Key ' . $this->falApiKey,
                'Content-Type'  => 'application/json',
            ])->timeout(30)->post($apiUrl, [
                'prompt'                => $prompt,
                'image_size'            => $options['image_size'] ?? 'landscape_4_3',
                'num_inference_steps'   => 28,
                'guidance_scale'        => 3.5,
                'num_images'            => 1,
                'enable_safety_checker' => true,
                'output_format'         => 'jpeg',
                'webhook_url'           => $webhookUrl,
            ]);

            if ($response->failed()) {
                Log::error('Fal.ai API Error: ' . $response->body());

                return [
                    'success' => false,
                    'error'   => 'Fal.ai API request failed',
                ];
            }

            Log::info('Fal.ai API Response: ' . $response->body());

            $data = $response->json();

            $imageUrl = $data['images'][0]['url'] ?? null;
            $status = strtolower($data['status'] ?? ($imageUrl ? 'completed' : 'pending'));

            $result = [
                'success'    => true,
                'request_id' => $data['request_id'] ?? uniqid('fal_', true),
                'status'     => $status,
            ];

            if ($imageUrl) {
                $result['image_url'] = $this->downloadAndStoreImage($imageUrl);
            }

            Log::info('result: ', $result);

            return $result;
        } catch (Exception $e) {
            Log::error('Fal.ai submission error: ' . $e->getMessage());

            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Poll Fal.ai for image generation status (alternative to webhook)
     */
    public function checkStatus(string $requestId): array
    {
        $this->falApiKey = ApiHelper::setFalAIKey();

        try {
            // First check status endpoint
            $statusUrl = $this->buildApiUrl() . "/requests/{$requestId}/status";

            $statusResponse = Http::withHeaders([
                'Authorization' => 'Key ' . $this->falApiKey,
                'Content-Type'  => 'application/json',
            ])->timeout(10)->get($statusUrl);

            if ($statusResponse->failed()) {
                Log::error('Fal.ai status check failed: ' . $statusResponse->body());

                return [
                    'success' => false,
                    'status'  => 'failed',
                ];
            }

            $statusData = $statusResponse->json();
            $status = strtoupper($statusData['status'] ?? 'PENDING');

            Log::info('Fal.ai status check response', [
                'request_id' => $requestId,
                'status'     => $status,
            ]);

            if ($status === 'COMPLETED') {
                // Fetch result endpoint to get actual image data
                $resultUrl = $this->buildApiUrl() . "/requests/{$requestId}";

                $resultResponse = Http::withHeaders([
                    'Authorization' => 'Key ' . $this->falApiKey,
                    'Content-Type'  => 'application/json',
                ])->timeout(30)->get($resultUrl);

                if ($resultResponse->failed()) {
                    Log::error('Fal.ai result fetch failed: ' . $resultResponse->body());

                    return [
                        'success' => false,
                        'status'  => 'failed',
                    ];
                }

                $resultData = $resultResponse->json();
                $imageUrl = $resultData['images'][0]['url'] ?? null;

                if ($imageUrl) {
                    $storedPath = $this->downloadAndStoreImage($imageUrl);

                    return [
                        'success'   => true,
                        'status'    => 'completed',
                        'image_url' => $storedPath,
                    ];
                }

                // Completed but no image found
                return [
                    'success' => true,
                    'status'  => 'completed',
                ];
            }

            if ($status === 'FAILED') {
                return [
                    'success' => true,
                    'status'  => 'failed',
                ];
            }

            // Still pending/processing (IN_QUEUE, IN_PROGRESS)
            return [
                'success' => true,
                'status'  => strtolower($status),
            ];
        } catch (Exception $e) {
            Log::error('Fal.ai status check error: ' . $e->getMessage());

            return [
                'success' => false,
                'status'  => 'error',
            ];
        }
    }

    /**
     * Build the FalAI API URL for the selected model
     */
    protected function buildApiUrl(): string
    {
        return self::$baseApiUrl . $this->getModel();
    }

    /**
     * Create an image prompt from post content
     */
    protected function createImagePrompt(string $postContent, array $options = []): string
    {
        try {
            ApiHelper::setOpenAiKey();

            $systemPrompt = <<<'SYSTEM'
You are an expert at creating image prompts for AI image generation.
Create a detailed, visual prompt for a social media post image.

Guidelines:
- Focus on visual elements, composition, colors, and style
- Make it relevant to the post content
- Keep it concise but descriptive (max 100 words)
- Avoid text, logos, or brand names in the image
- Create professional, eye-catching imagery
- Use modern, clean aesthetics
SYSTEM;

            $userPrompt = <<<PROMPT
Create an image generation prompt for this social media post:

"{$postContent}"

Return only the image prompt, nothing else.
PROMPT;

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . ApiHelper::setOpenAiKey(),
                'Content-Type'  => 'application/json',
            ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', OpenAiParamHelper::sanitizeChatParams([
                'model'       => EntityEnum::GPT_5_MINI->value,
                'messages'    => [
                    [
                        'role'    => 'system',
                        'content' => $systemPrompt,
                    ],
                    [
                        'role'    => 'user',
                        'content' => $userPrompt,
                    ],
                ],
                'temperature' => 0.7,
                'max_tokens'  => 200,
            ]));

            if ($response->failed()) {
                Log::error('Failed to create image prompt: ' . $response->body());

                return 'Professional social media image, modern design, clean composition, vibrant colors';
            }

            return trim($response->json('choices.0.message.content'));
        } catch (Exception $e) {
            Log::warning('Error creating image prompt: ' . $e->getMessage());

            return 'Professional social media image, modern design, clean composition, vibrant colors';
        }
    }

    /**
     * Download and store the generated image
     */
    protected function downloadAndStoreImage(string $url): string
    {
        try {
            $imageContents = file_get_contents($url);
            $filename = 'social-media-agent/' . uniqid('post_', true) . '.png';

            Storage::disk('public')->put($filename, $imageContents);

            return '/uploads/' . $filename;
        } catch (Exception $e) {
            Log::error('Failed to download/store image: ' . $e->getMessage());

            throw $e;
        }
    }

    /**
     * Set the AI model to use
     */
    public function setModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }
}
