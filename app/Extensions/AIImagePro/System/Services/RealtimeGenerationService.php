<?php

namespace App\Extensions\AIImagePro\System\Services;

use App\Domains\Entity\Enums\EntityEnum;
use App\Extensions\AIImagePro\System\Models\AiImageProModel;
use App\Models\SettingTwo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RealtimeGenerationService
{
    private const API_URL = 'https://api.together.xyz/v1/images/generations';

    private const IMAGE_WIDTH = 1024;

    private const IMAGE_HEIGHT = 768;

    private const INFERENCE_STEPS = 3;

    /**
     * Generate an image synchronously via Together API and store the result.
     */
    public function generate(AiImageProModel $record): AiImageProModel
    {
        $prompt = $this->buildPrompt($record->prompt, $record->params['style'] ?? null);

        $record->markAsStarted();

        $response = Http::withHeaders([
            'Authorization'     => 'Bearer ' . $this->getApiKey(),
            'x-ratelimit-limit' => 10,
        ])->post(self::API_URL, [
            'prompt' => $prompt,
            'model'  => EntityEnum::BLACK_FOREST_LABS_FLUX_1_SCHNELL->value,
            'width'  => self::IMAGE_WIDTH,
            'height' => self::IMAGE_HEIGHT,
            'steps'  => self::INFERENCE_STEPS,
        ]);

        if (! $response->successful()) {
            $errorMessage = data_get($response->json(), 'error.message', 'Together API request failed');
            $record->markAsFailed($errorMessage);

            return $record;
        }

        $imageUrl = data_get($response->json(), 'data.0.url');

        if (! $imageUrl) {
            $record->markAsFailed('No image URL in API response');

            return $record;
        }

        $storedPath = $this->downloadAndStore($imageUrl, $record->user_id);

        if (! $storedPath) {
            $record->markAsFailed('Failed to download and store generated image');

            return $record;
        }

        $record->markAsCompleted([$storedPath], [
            'is_realtime' => true,
        ]);

        $record->saveDimensions();

        return $record->refresh();
    }

    /**
     * Build the prompt with optional style suffix.
     */
    private function buildPrompt(string $prompt, ?string $style): string
    {
        if ($style && $style !== 'none' && $style !== '') {
            $prompt .= '. Use ' . $style . ' style for the image.';
        }

        return $prompt;
    }

    /**
     * Download an image from a URL and store it on the configured disk.
     */
    private function downloadAndStore(string $url, ?int $userId): ?string
    {
        try {
            $response = Http::get($url);

            if (! $response->successful()) {
                return null;
            }

            $fileContent = $response->body();
            $directory = $userId ? "media/images/u-{$userId}" : 'guest';
            $filename = $directory . '/' . uniqid('rt_', true) . '.jpeg';

            $imageStorage = SettingTwo::getCache()?->getAttribute('ai_image_storage');

            if ($imageStorage === 'r2') {
                Storage::disk('r2')->put($filename, $fileContent);

                return Storage::disk('r2')->url($filename);
            }

            if ($imageStorage === 's3') {
                Storage::disk('s3')->put($filename, $fileContent);

                return Storage::disk('s3')->url($filename);
            }

            Storage::disk('public')->put($filename, $fileContent);

            return '/uploads/' . $filename;
        } catch (Throwable $e) {
            Log::error('Failed to download and store realtime image', [
                'url'     => $url,
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get the Together API key from settings.
     */
    private function getApiKey(): string
    {
        return setting('together_api_key', '');
    }
}
