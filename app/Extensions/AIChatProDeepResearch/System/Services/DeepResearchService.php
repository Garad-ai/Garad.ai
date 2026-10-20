<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProDeepResearch\System\Services;

class DeepResearchService
{
    public function getDriver(string $engine): OpenAIDeepResearchDriver|GeminiDeepResearchDriver
    {
        return match ($engine) {
            'gemini' => new GeminiDeepResearchDriver,
            default  => new OpenAIDeepResearchDriver,
        };
    }

    public function getDefaultEngine(): string
    {
        return setting('deep_research_default_engine', 'openai');
    }

    public function getDefaultModel(string $engine): string
    {
        return match ($engine) {
            'openai' => setting('deep_research_openai_model', 'o3-deep-research'),
            default  => 'deep-research-pro-preview-12-2025',
        };
    }

    public function isAutoCanvasEnabled(): bool
    {
        return (bool) setting('deep_research_auto_canvas', 1);
    }
}
