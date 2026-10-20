<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domains\Entity\Enums\EntityEnum;
use PHPUnit\Framework\TestCase;

final class SelectedChatModelProviderTest extends TestCase
{
    public function test_available_chat_models_resolve_to_their_configured_provider(): void
    {
        $models = [
            'claude-opus-4-7'                => 'anthropic',
            'gpt-5.4-mini'                   => 'openai',
            'gemini-2.5-flash-preview-05-20' => 'gemini',
            'gemini-3-pro-preview'           => 'gemini',
            'gemini-3.1-pro-preview'         => 'gemini',
            'gemini-1.5-flash'               => 'gemini',
            'deepseek-chat'                  => 'deep_seek',
        ];

        foreach ($models as $modelId => $provider) {
            self::assertSame($provider, EntityEnum::from($modelId)->engine()->value, $modelId);
        }
    }
}
