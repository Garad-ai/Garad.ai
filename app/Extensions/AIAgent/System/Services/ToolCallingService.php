<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Services;

use App\Domains\Engine\Enums\EngineEnum;
use App\Domains\Engine\Services\AnthropicService;
use App\Domains\Engine\Services\GeminiService;
use App\Helpers\Classes\ApiHelper;
use App\Helpers\Classes\Helper;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use OpenAI as OpenAIMain;

class ToolCallingService
{
    /**
     * Call the configured AI engine with native tool calling.
     *
     * @param  string[]  $enabledToolKeys
     *
     * @return array{tool: string|null, params: array, reply: string}
     */
    public function call(string $userMessage, array $enabledToolKeys): array
    {
        $toolDefs = $this->buildToolDefinitions($enabledToolKeys);

        if (empty($toolDefs)) {
            return ['tool' => null, 'params' => [], 'reply' => ''];
        }

        $engine = Helper::defaultEngine();

        try {
            return match ($engine) {
                EngineEnum::ANTHROPIC => $this->viaAnthropic($userMessage, $toolDefs),
                EngineEnum::GEMINI    => $this->viaGemini($userMessage, $toolDefs, $engine),
                EngineEnum::DEEP_SEEK => $this->viaDeepSeek($userMessage, $toolDefs, $engine),
                EngineEnum::X_AI      => $this->viaXAi($userMessage, $toolDefs, $engine),
                default               => $this->viaOpenAi($userMessage, $toolDefs),
            };
        } catch (Exception $e) {
            Log::error('[AIAgent] ToolCallingService error', [
                'engine' => $engine->value,
                'error'  => $e->getMessage(),
            ]);

            return ['tool' => null, 'params' => [], 'reply' => ''];
        }
    }

    /**
     * Build engine-agnostic tool definitions from config.
     *
     * @return array<string, array{name: string, description: string, parameters: array}>
     */
    private function buildToolDefinitions(array $enabledToolKeys): array
    {
        $allTools = config('ai-agent.tools', []);
        $defs = [];

        foreach ($enabledToolKeys as $key) {
            if (! isset($allTools[$key])) {
                continue;
            }

            $tool = $allTools[$key];
            $defs[$key] = [
                'name'        => $key,
                'description' => $tool['description'],
                'parameters'  => $tool['parameters'] ?? ['type' => 'object', 'properties' => []],
            ];
        }

        return $defs;
    }

    /**
     * OpenAI format: {type: "function", function: {name, description, parameters}}
     * Response: choices[0].message.tool_calls[0].function.{name, arguments}
     */
    private function viaOpenAi(string $message, array $toolDefs): array
    {
        $apiKey = ApiHelper::setOpenAiKey();
        $model = Helper::defaultWordModel();

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type'  => 'application/json',
        ])
            ->timeout(60)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'       => $model->value,
                'messages'    => [['role' => 'user', 'content' => $message]],
                'tools'       => $this->toOpenAiFormat($toolDefs),
                'tool_choice' => 'auto',
            ]);

        return $this->parseOpenAiResponse($response->json());
    }

    /**
     * Anthropic format: {name, description, input_schema}
     * Response: content[].type === "tool_use" → {name, input}
     */
    private function viaAnthropic(string $message, array $toolDefs): array
    {
        $tools = array_values(array_map(fn (array $def) => [
            'name'         => $def['name'],
            'description'  => $def['description'],
            'input_schema' => $def['parameters'],
        ], $toolDefs));

        $response = app(AnthropicService::class)
            ->setMessages([['role' => 'user', 'content' => $message]])
            ->setTools($tools)
            ->setStream(false)
            ->stream();

        $content = $response->json('content') ?? [];

        foreach ($content as $block) {
            if (($block['type'] ?? '') === 'tool_use') {
                return [
                    'tool'   => $block['name'],
                    'params' => $block['input'] ?? [],
                    'reply'  => '',
                ];
            }
        }

        $reply = collect($content)
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');

        return ['tool' => null, 'params' => [], 'reply' => $reply];
    }

    /**
     * Gemini format: {functionDeclarations: [{name, description, parameters}]}
     * Response: candidates[0].content.parts[].functionCall → {name, args}
     */
    private function viaGemini(string $message, array $toolDefs, EngineEnum $engine): array
    {
        $model = $engine->getDefaultWordModel(null);

        $functionDeclarations = array_values(array_map(fn (array $def) => [
            'name'        => $def['name'],
            'description' => $def['description'],
            'parameters'  => $def['parameters'],
        ], $toolDefs));

        $response = app(GeminiService::class)
            ->setHistory([['role' => 'user', 'parts' => [['text' => $message]]]])
            ->setTools([['functionDeclarations' => $functionDeclarations]])
            ->generateContent($model->value);

        $parts = $response->json('candidates.0.content.parts') ?? [];

        foreach ($parts as $part) {
            if (isset($part['functionCall'])) {
                return [
                    'tool'   => $part['functionCall']['name'],
                    'params' => $part['functionCall']['args'] ?? [],
                    'reply'  => '',
                ];
            }
        }

        $reply = collect($parts)->pluck('text')->filter()->implode('');

        return ['tool' => null, 'params' => [], 'reply' => $reply];
    }

    /**
     * DeepSeek (OpenAI-compatible) format.
     */
    private function viaDeepSeek(string $message, array $toolDefs, EngineEnum $engine): array
    {
        ApiHelper::setDeepseekKey();

        $model = $engine->getDefaultWordModel(null);
        $apiKey = config('deepseek.api_key');

        $response = (new Client)->post('https://api.deepseek.com/chat/completions', [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'Authorization' => "Bearer {$apiKey}",
            ],
            'json' => [
                'model'       => $model->value,
                'messages'    => [['role' => 'user', 'content' => $message]],
                'tools'       => $this->toOpenAiFormat($toolDefs),
                'tool_choice' => 'auto',
                'stream'      => false,
            ],
        ]);

        $body = json_decode($response->getBody()->getContents(), true);

        return $this->parseOpenAiResponse($body);
    }

    /**
     * xAI Grok (OpenAI-compatible via SDK).
     */
    private function viaXAi(string $message, array $toolDefs, EngineEnum $engine): array
    {
        $apiKey = ApiHelper::setXAiKey();
        $model = $engine->getDefaultWordModel(null);

        $tools = $this->toOpenAiFormat($toolDefs);

        $result = OpenAIMain::factory()
            ->withApiKey($apiKey)
            ->withBaseUri('https://api.x.ai/v1')
            ->make()
            ->chat()
            ->create([
                'model'       => $model->value,
                'messages'    => [['role' => 'user', 'content' => $message]],
                'tools'       => $tools,
                'tool_choice' => 'auto',
            ]);

        $toolCalls = $result->choices[0]->message->toolCalls ?? null;

        if (! empty($toolCalls)) {
            $call = $toolCalls[0];

            return [
                'tool'   => $call->function->name,
                'params' => json_decode($call->function->arguments, true) ?? [],
                'reply'  => '',
            ];
        }

        return [
            'tool'   => null,
            'params' => [],
            'reply'  => $result->choices[0]->message->content ?? '',
        ];
    }

    /**
     * Convert neutral tool definitions to OpenAI function calling format.
     */
    private function toOpenAiFormat(array $toolDefs): array
    {
        return array_values(array_map(fn (array $def) => [
            'type'     => 'function',
            'function' => [
                'name'        => $def['name'],
                'description' => $def['description'],
                'parameters'  => $def['parameters'],
            ],
        ], $toolDefs));
    }

    /**
     * Parse an OpenAI-compatible response (OpenAI, DeepSeek).
     *
     * @return array{tool: string|null, params: array, reply: string}
     */
    private function parseOpenAiResponse(array $body): array
    {
        $toolCalls = $body['choices'][0]['message']['tool_calls'] ?? null;

        if (! empty($toolCalls)) {
            $call = $toolCalls[0];

            return [
                'tool'   => $call['function']['name'],
                'params' => json_decode($call['function']['arguments'], true) ?? [],
                'reply'  => '',
            ];
        }

        return [
            'tool'   => null,
            'params' => [],
            'reply'  => $body['choices'][0]['message']['content'] ?? '',
        ];
    }
}
