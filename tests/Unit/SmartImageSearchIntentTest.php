<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Extensions\AiChatProSmartImage\System\Services\SmartImageService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class SmartImageSearchIntentTest extends TestCase
{
    #[DataProvider('imageSearchRequests')]
    public function test_it_detects_explicit_image_search_requests(string $prompt): void
    {
        self::assertTrue(SmartImageService::isExplicitImageSearchRequest($prompt));
    }

    #[DataProvider('imageSearchQueries')]
    public function test_it_extracts_a_search_query_from_an_explicit_request(string $prompt, string $expected): void
    {
        self::assertSame($expected, SmartImageService::searchQueryFromPrompt($prompt));
    }

    public function test_it_tells_the_assistant_that_explicit_image_search_is_handled_by_the_application(): void
    {
        $instruction = SmartImageService::systemPromptAddition();

        self::assertStringContainsString('automatically searches for and displays images', $instruction);
        self::assertStringContainsString('never say image display is unavailable', $instruction);
    }

    public function test_it_prioritizes_attached_image_analysis_over_visual_subject_search(): void
    {
        $attachmentException = 'do NOT call search_images for similar or unrelated images';
        $additionalImagesOnly = 'Only search for images when the user explicitly requests additional external images';

        self::assertStringContainsString($attachmentException, SmartImageService::systemPromptAddition());
        self::assertStringContainsString($additionalImagesOnly, SmartImageService::systemPromptAddition());
        self::assertStringContainsString($attachmentException, SmartImageService::toolDefinition()['description']);
        self::assertStringContainsString($additionalImagesOnly, SmartImageService::toolDefinition()['description']);
        self::assertStringContainsString($attachmentException, SmartImageService::geminiToolDefinition()['functionDeclarations'][0]['description']);
    }

    public static function imageSearchQueries(): array
    {
        return [
            ['Show me a photo of the Eiffel Tower.', 'the Eiffel Tower'],
            ['Fadlan muuji sawirka calanka Soomaaliya.', 'calanka Soomaaliya'],
            ['Please include an icon of the Garad AI logo.', 'the Garad AI logo'],
        ];
    }

    public static function imageSearchRequests(): array
    {
        return [
            ['Show me a photo of the Eiffel Tower.'],
            ['Fadlan muuji sawirka calanka Soomaaliya.'],
            ['Please include an icon of the Garad AI logo.'],
            ['Photos of Nairobi'],
        ];
    }

    #[DataProvider('nonSearchRequests')]
    public function test_it_does_not_search_for_image_creation_or_text_only_requests(string $prompt): void
    {
        self::assertFalse(SmartImageService::isExplicitImageSearchRequest($prompt));
    }

    public static function nonSearchRequests(): array
    {
        return [
            ['Create an image of a blue bird.'],
            ['Do not show images; explain the flag in text only.'],
            ['Write a short article about Paris.'],
            ['Analyze the attached image and describe the village scene.'],
            ['Falanqee sawirka aan ku soo lifaaqay oo ii sheeg waxa ka muuqda.'],
            [''],
        ];
    }
}
