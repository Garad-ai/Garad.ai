<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProDeepResearch\System\Services;

use App\Helpers\Classes\ApiHelper;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiDeepResearchDriver
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/';

    private const MODEL = 'deep-research-pro-preview-12-2025';

    public function startResearch(string $query): string
    {
        $apiKey = ApiHelper::setGeminiKey();

        $response = Http::timeout(120)->post(self::BASE_URL . 'interactions?key=' . $apiKey, [
            'input'        => $query,
            'agent'        => self::MODEL,
            'background'   => true,
            'agent_config' => [
                'type'               => 'deep-research',
                'thinking_summaries' => 'auto',
            ],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Gemini deep research failed: ' . $response->body());
        }

        $interactionId = $response->json('id');

        if (! $interactionId) {
            throw new RuntimeException('Gemini deep research failed: no interaction ID returned');
        }

        return $interactionId;
    }

    public function checkStatus(string $interactionId): array
    {
        $apiKey = ApiHelper::setGeminiKey();

        $response = Http::timeout(60)->get(self::BASE_URL . "interactions/{$interactionId}", [
            'key' => $apiKey,
        ]);

        $data = $response->json();

        $status = $data['status'] ?? 'in_progress';

        if ($status === 'completed' || ($data['done'] ?? false)) {
            $status = 'completed';
        } elseif ($status === 'failed') {
            $status = 'failed';
        } else {
            $status = 'in_progress';
        }

        $outputText = null;
        $outputs = $data['outputs'] ?? [];

        if ($status === 'completed' && ! empty($outputs)) {
            // Get text from the last output
            foreach (array_reverse($outputs) as $output) {
                if (isset($output['text'])) {
                    $outputText = $output['text'];

                    break;
                }

                // Also check nested content parts
                foreach ($output['content']['parts'] ?? [] as $part) {
                    if (isset($part['text'])) {
                        $outputText = $part['text'];

                        break 2;
                    }
                }
            }
        }

        $error = null;
        if ($status === 'failed') {
            $error = $data['error']['message'] ?? $data['error'] ?? $data['statusDetails'] ?? null;
            if (is_array($error)) {
                $error = json_encode($error);
            }

            if (! $error) {
                $error = 'Gemini returned failed status with no error details. Response: ' . json_encode($data);
            }
        }

        return [
            'status'         => $status,
            'output_text'    => $outputText,
            'outputs'        => $outputs,
            'thinking_steps' => $this->extractThinkingSteps($data),
            'error'          => $error,
        ];
    }

    /**
     * Extract thinking steps from the Gemini response.
     *
     * Gemini Interactions API structure:
     * - During polling: may return intermediate outputs before the final report
     * - Completed: outputs[] with type="text", annotations[], and the report text
     *
     * We extract intermediate (non-final) outputs as thinking steps when available.
     */
    public function extractThinkingSteps(array $data): array
    {
        $steps = [];
        $outputs = $data['outputs'] ?? [];

        // If there are multiple outputs, the earlier ones may be intermediate thinking/planning
        if (count($outputs) > 1) {
            foreach (array_slice($outputs, 0, -1) as $output) {
                if (! is_array($output)) {
                    continue;
                }

                $text = $output['text'] ?? '';
                if ($text !== '') {
                    $steps[] = [
                        'type' => 'thinking',
                        'text' => $text,
                    ];
                }

                // Check nested content parts
                foreach ($output['content']['parts'] ?? [] as $part) {
                    $partText = $part['text'] ?? '';
                    if ($partText !== '') {
                        $steps[] = [
                            'type' => 'thinking',
                            'text' => $partText,
                        ];
                    }
                }
            }
        }

        // Also check for grounding metadata search queries (if present in any output)
        foreach ($outputs as $output) {
            if (! is_array($output)) {
                continue;
            }

            foreach ($output['groundingMetadata']['webSearchQueries'] ?? [] as $query) {
                $steps[] = [
                    'type' => 'search',
                    'text' => $query,
                ];
            }

            foreach ($output['searchQueries'] ?? [] as $query) {
                $text = is_string($query) ? $query : ($query['query'] ?? '');

                if ($text !== '') {
                    $steps[] = [
                        'type' => 'search',
                        'text' => $text,
                    ];
                }
            }
        }

        // Check for intermediate steps at the top level
        foreach ($data['intermediateSteps'] ?? [] as $step) {
            if (! is_array($step)) {
                continue;
            }

            $text = $step['description'] ?? ($step['text'] ?? ($step['summary'] ?? ''));

            if ($text !== '') {
                $steps[] = [
                    'type' => 'thinking',
                    'text' => $text,
                ];
            }
        }

        return $steps;
    }

    /**
     * Extract sources from Gemini response outputs.
     *
     * Gemini uses `annotations` array on each output with `source` URLs
     * (redirect URLs through vertexaisearch.cloud.google.com).
     */
    public function extractSources(array $outputs): array
    {
        $sources = [];

        foreach ($outputs as $output) {
            if (! is_array($output)) {
                continue;
            }

            // Primary: Gemini uses annotations with source URLs
            foreach ($output['annotations'] ?? [] as $annotation) {
                if (! is_array($annotation) || ! isset($annotation['source'])) {
                    continue;
                }

                $sources[] = [
                    'title' => $annotation['title'] ?? '',
                    'url'   => $annotation['source'],
                ];
            }

            // Fallback: check groundingMetadata (some API versions)
            foreach ($output['groundingMetadata']['groundingChunks'] ?? [] as $chunk) {
                if (isset($chunk['web'])) {
                    $sources[] = [
                        'title' => $chunk['web']['title'] ?? '',
                        'url'   => $chunk['web']['uri'] ?? '',
                    ];
                }
            }

            // Fallback: check citations
            foreach ($output['citations'] ?? [] as $citation) {
                if (isset($citation['uri'])) {
                    $sources[] = [
                        'title' => $citation['title'] ?? '',
                        'url'   => $citation['uri'],
                    ];
                }
            }
        }

        $unique = collect($sources)->unique('url')->values()->toArray();

        // Resolve vertexaisearch.cloud.google.com redirect URLs to actual destinations
        return $this->resolveRedirectUrls($unique);
    }

    /**
     * Resolve Google Vertex AI Search redirect URLs to their actual destinations.
     * Uses parallel HEAD requests (no body downloaded) to follow the redirect.
     */
    private function resolveRedirectUrls(array $sources): array
    {
        $redirectIndices = [];

        foreach ($sources as $i => $source) {
            if (str_contains($source['url'] ?? '', 'vertexaisearch.cloud.google.com')) {
                $redirectIndices[$i] = $source['url'];
            }
        }

        if (empty($redirectIndices)) {
            return $sources;
        }

        // Parallel HEAD requests to resolve redirects
        $responses = Http::pool(function ($pool) use ($redirectIndices) {
            foreach ($redirectIndices as $i => $url) {
                $pool->as((string) $i)
                    ->withOptions(['allow_redirects' => false, 'timeout' => 5])
                    ->head($url);
            }
        });

        foreach ($redirectIndices as $i => $url) {
            $response = $responses[(string) $i] ?? null;

            if (! $response) {
                continue;
            }

            $location = $response->header('Location');

            if ($location) {
                $sources[$i]['url'] = $location;

                // Use resolved domain as title if no title available
                if (empty($sources[$i]['title'])) {
                    $host = parse_url($location, PHP_URL_HOST);
                    $sources[$i]['title'] = $host ? str_replace('www.', '', $host) : '';
                }
            }
        }

        return $sources;
    }

    /**
     * Count searches based on unique source references.
     */
    public function countSearches(array $outputs): int
    {
        $uniqueSources = [];

        foreach ($outputs as $output) {
            if (! is_array($output)) {
                continue;
            }

            foreach ($output['annotations'] ?? [] as $annotation) {
                if (isset($annotation['source'])) {
                    $uniqueSources[$annotation['source']] = true;
                }
            }

            // Also count grounding metadata queries if present
            foreach ($output['groundingMetadata']['webSearchQueries'] ?? [] as $query) {
                $uniqueSources['search:' . $query] = true;
            }

            foreach ($output['searchQueries'] ?? [] as $query) {
                $text = is_string($query) ? $query : ($query['query'] ?? '');
                if ($text !== '') {
                    $uniqueSources['search:' . $text] = true;
                }
            }
        }

        return count($uniqueSources);
    }
}
