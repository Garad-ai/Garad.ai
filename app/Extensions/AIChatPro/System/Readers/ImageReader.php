<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Readers;

use App\Domains\Engine\Enums\EngineEnum;
use App\Services\Ai\AiCompletionService;
use Throwable;

class ImageReader
{
    public function read(string $path, string $name): string
    {
        $prompt = $this->analysisPrompt($name);

        foreach ($this->candidateEngines() as $engine) {
            try {
                $content = trim(app(AiCompletionService::class)->completeWithImagePath(
                    $prompt['system'],
                    $prompt['user'],
                    $path,
                    $engine,
                ));

                if ($content !== '') {
                    return $content;
                }
            } catch (Throwable) {
            }
        }

        return sprintf(
            'Image document attached: %s. Vision extraction is currently unavailable, so only the file reference could be stored.',
            $name
        );
    }

    private function candidateEngines(): array
    {
        $candidates = [];
        $defaultEngine = EngineEnum::tryFrom((string) setting('default_ai_engine', EngineEnum::OPEN_AI->value));

        if ($defaultEngine instanceof EngineEnum && in_array($defaultEngine, [EngineEnum::OPEN_AI, EngineEnum::GEMINI, EngineEnum::ANTHROPIC], true)) {
            $candidates[] = $defaultEngine;
        }

        foreach ([EngineEnum::OPEN_AI->value, EngineEnum::GEMINI, EngineEnum::ANTHROPIC] as $engine) {
            if (! in_array($engine, $candidates, true)) {
                $candidates[] = $engine;
            }
        }

        return $candidates;
    }

    private function analysisPrompt(string $name): array
    {
        return [
            'system' => <<<'PROMPT'
You are GaradAI's multimodal extraction engine.

Your job is to analyze an uploaded image like a high-quality OCR plus visual understanding system.

You must:
- Extract all visible text accurately.
- Understand screenshots, charts, tables, diagrams, forms, invoices, CVs, IDs, receipts, presentations, and handwriting when possible.
- Describe layout, sections, labels, errors, callouts, trends, totals, and important entities.
- Avoid guessing hidden or unreadable values.

Return plain text only using these sections when relevant:
1. Asset Type
2. Visible Text
3. Structured Understanding
4. Key Facts
5. Possible User Help
PROMPT,
            'user' => "Analyze the uploaded image named {$name}. Extract the visible text and provide a structured understanding of everything important in the image.",
        ];
    }
}