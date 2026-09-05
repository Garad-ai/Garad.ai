<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Services;

use App\Extensions\AiChatProSmartImage\System\Services\SmartImageService;
use App\Helpers\Classes\MarketplaceHelper;
use App\Http\Controllers\AIController;
use Illuminate\Support\Facades\Log;
use JsonException;

class AiChatProService
{
    private static function availableTools(): array
    {
        $tools = [
            [
                'type'        => 'function',
                'name'        => 'generate_image',
                'description' => 'Generate a new AI image based on the given prompt. Use this when the user asks to create, generate, draw, or make an image. This creates a brand new image — do NOT use search_images for image generation requests.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'prompt' => [
                            'type'        => 'string',
                            'description' => 'The text prompt to generate the image.',
                        ],
                    ],
                    'required' => ['prompt'],
                ],
            ],
        ];

        // Add smart image search tool if extension is enabled
        if (MarketplaceHelper::isRegistered('ai-chat-pro-smart-image')
            && SmartImageService::isEnabled()) {
            $tools[] = SmartImageService::toolDefinition();
        }

        return $tools;
    }

    public static function tools(): array
    {
        return self::availableTools();
    }

    public static function callFunction(?string $functionName, ?string $argsString): ?string
    {
        return match ($functionName) {
            'generate_image' => self::generateImage($argsString),
            'search_images'  => self::searchImages($argsString),
            default          => null,
        };
    }

    private static function searchImages(?string $argsString): string
    {
        if (! MarketplaceHelper::isRegistered('ai-chat-pro-smart-image')) {
            return '';
        }

        try {
            $args = json_decode($argsString, true, 512, JSON_THROW_ON_ERROR);
            $query = $args['query'] ?? '';
            if (empty($query)) {
                return '';
            }

            $maxImages = SmartImageService::getMaxImages();

            return SmartImageService::searchAndFormat($query, $maxImages);
        } catch (JsonException $e) {
            Log::error('SmartImage: searchImages failed', ['error' => $e->getMessage()]);

            return '';
        }
    }

    public static function generateImage(?string $argsString): string
    {
        try {
            $args = json_decode($argsString, true, 512, JSON_THROW_ON_ERROR);
            if (! isset($args['prompt'])) {
                throw new JsonException('Invalid arguments: prompt is required');
            }

            $prompt = $args['prompt'];

            return app(AIController::class)->generateImageWithOpenAI($prompt);
        } catch (JsonException $e) {
            Log::error($e->getMessage());

            return '';
        }
    }
}
