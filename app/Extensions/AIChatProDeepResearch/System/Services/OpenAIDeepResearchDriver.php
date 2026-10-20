<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProDeepResearch\System\Services;

use App\Helpers\Classes\ApiHelper;
use OpenAI\Laravel\Facades\OpenAI;

class OpenAIDeepResearchDriver
{
    public function startResearch(
    string $query,
    string $model = 'gpt-5'
): string
{
    ApiHelper::setOpenAiKey();

    $response = OpenAI::responses()->create([
        'model' => $model,
        'input' => $query,

        'tools' => [
            [
                'type' => 'web_search_preview',
            ]
        ],

        'reasoning' => [
            'effort' => 'high',
            'summary' => 'auto',
        ],
    ]);

    return $response->id;
}

    public function checkStatus(string $responseId): array
    {
        ApiHelper::setOpenAiKey();

        $response = OpenAI::responses()->retrieve($responseId);

        $outputText = $this->extractOutputText($response->output);

        $error = null;
        if ($response->status === 'failed') {
            $error = $response->lastError->message
                ?? $response->lastError->code
                ?? $response->statusDetails
                ?? null;

            if (! $error) {
                $error = 'OpenAI returned failed status with no error details. Response: ' . json_encode($response->toArray());
            }
        }

        return [
            'status'         => $response->status,
            'output_text'    => $outputText,
            'output'         => $response->output,
            'thinking_steps' => $this->extractThinkingSteps($response->output),
            'error'          => $error,
        ];
    }

    public function cancel(string $responseId): void
    {
        ApiHelper::setOpenAiKey();

        OpenAI::responses()->cancel($responseId);
    }

    public function extractSources(array $output): array
    {
        $sources = [];

        foreach ($output as $item) {
            if (! is_array($item)) {
                $item = (array) $item;
            }

            if (($item['type'] ?? '') === 'message') {
                foreach ($item['content'] ?? [] as $content) {
                    if (! is_array($content)) {
                        $content = (array) $content;
                    }

                    foreach ($content['annotations'] ?? [] as $annotation) {
                        if (! is_array($annotation)) {
                            $annotation = (array) $annotation;
                        }

                        $url = $annotation['url'] ?? null;

                        if ($this->isSafeSourceUrl($url)) {
                            $sources[] = [
                                'title' => $annotation['title'] ?? '',
                                'url'   => $url,
                            ];
                        }
                    }
                }
            }
        }

        return collect($sources)->unique('url')->values()->toArray();
    }

    private function isSafeSourceUrl(mixed $url): bool
    {
        if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }

    public function countSearches(array $output): int
    {
        $count = 0;

        foreach ($output as $item) {
            if (! is_array($item)) {
                $item = (array) $item;
            }

            if (($item['type'] ?? '') === 'web_search_call') {
                $count++;
            }
        }

        return $count;
    }

    public function extractThinkingSteps(array $output): array
    {
        $steps = [];

        foreach ($output as $item) {
            if (! is_array($item)) {
                $item = (array) $item;
            }

            $type = $item['type'] ?? '';

            if ($type === 'reasoning') {
                foreach ($item['summary'] ?? [] as $summary) {
                    if (! is_array($summary)) {
                        $summary = (array) $summary;
                    }

                    $text = trim($summary['text'] ?? '');

                    if ($text !== '') {
                        $steps[] = [
                            'type' => 'thinking',
                            'text' => $text,
                        ];
                    }
                }
            } elseif ($type === 'web_search_call') {
                $query = $item['action']['query'] ?? ($item['query'] ?? '');

                if ($query !== '') {
                    $steps[] = [
                        'type'  => 'search',
                        'text'  => $query,
                    ];
                }
            }
        }

        return $steps;
    }

    private function extractOutputText(array $output): ?string
    {
        foreach ($output as $item) {
            if (! is_array($item)) {
                $item = (array) $item;
            }

            if (($item['type'] ?? '') === 'message') {
                foreach ($item['content'] ?? [] as $content) {
                    if (! is_array($content)) {
                        $content = (array) $content;
                    }

                    if (($content['type'] ?? '') === 'output_text') {
                        return $content['text'] ?? null;
                    }
                }
            }
        }

        return null;
    }
}
