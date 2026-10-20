<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Controllers\OpenAi\GeneratorController;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

final class GeneratorControllerImageReferencesTest extends TestCase
{
    public function test_normalizes_array_and_legacy_csv_image_references(): void
    {
        $controller = (new ReflectionClass(GeneratorController::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(GeneratorController::class, 'normalizeImageReferences');

        self::assertSame(
            ['/uploads/one.png', '/uploads/two.png'],
            $method->invoke($controller, [' /uploads/one.png ', '/uploads/two.png'])
        );
        self::assertSame(
            ['/uploads/one.png', '/uploads/two.png'],
            $method->invoke($controller, '/uploads/one.png,/uploads/two.png')
        );
        self::assertSame([], $method->invoke($controller, null));
    }

    public function test_builds_structured_text_and_image_content_from_multiple_attachments(): void
    {
        $directory = 'test-uploads/' . bin2hex(random_bytes(8));
        $absoluteDirectory = public_path($directory);
        mkdir($absoluteDirectory, 0777, true);
        $firstImage = $absoluteDirectory . DIRECTORY_SEPARATOR . 'one.png';
        $secondImage = $absoluteDirectory . DIRECTORY_SEPARATOR . 'two.png';
        file_put_contents($firstImage, 'first-image');
        file_put_contents($secondImage, 'second-image');
        $firstReference = '/' . $directory . '/one.png';
        $secondReference = '/' . $directory . '/two.png';

        try {
            $controller = (new ReflectionClass(GeneratorController::class))->newInstanceWithoutConstructor();
            $method = new ReflectionMethod(GeneratorController::class, 'addVisionPromptToHistory');
            $history = $method->invoke($controller, [], [
                'chat_type' => 'chatPro',
                'images' => [$firstReference, $secondReference],
                'prompt' => 'Describe the attached images.',
                'assistant' => 'claude-opus-4-7',
            ], 'system');

            self::assertSame('user', $history[0]['role']);
            self::assertSame([
                ['type' => 'input_text', 'text' => 'Describe the attached images.'],
                ['type' => 'input_image', 'image_url' => $firstReference],
                ['type' => 'input_image', 'image_url' => $secondReference],
            ], $history[0]['content']);
        } finally {
            @unlink($firstImage);
            @unlink($secondImage);
            @rmdir($absoluteDirectory);
            @rmdir(dirname($absoluteDirectory));
        }
    }
}
