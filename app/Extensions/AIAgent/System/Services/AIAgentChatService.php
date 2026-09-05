<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Services;

use App\Domains\Engine\Enums\EngineEnum;
use App\Domains\Engine\Services\AnthropicService;
use App\Domains\Engine\Services\GeminiService;
use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity;
use App\Helpers\Classes\ApiHelper;
use App\Helpers\Classes\Helper;
use App\Models\Usage;
use App\Models\User;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use OpenAI as OpenAIMain;
use OpenAI\Contracts\ClientContract;

class AIAgentChatService
{
    protected null|int|User $creditUser = null;

    protected ?EntityEnum $lastUsedModel = null;

    public function forUser(null|int|User $user): static
    {
        $this->creditUser = $user;

        return $this;
    }

    /**
     * Send a multi-turn conversation to the configured AI engine and return the response.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function chat(string $systemPrompt, array $messages, ?EngineEnum $engine = null, ?EntityEnum $model = null, bool $webSearch = false): string
    {
        // Derive engine from model if provided and engine not explicitly set
        if ($model !== null && $engine === null) {
            $engine = $model->engine();
        }

        $engine ??= Helper::defaultEngine();

        $response = match ($engine) {
            EngineEnum::ANTHROPIC => $this->viaAnthropic($systemPrompt, $messages, $model),
            EngineEnum::GEMINI    => $this->viaGemini($systemPrompt, $messages, $engine, $model, $webSearch),
            EngineEnum::DEEP_SEEK => $this->viaDeepSeek($systemPrompt, $messages, $engine, $model),
            EngineEnum::X_AI      => $this->viaXAi($systemPrompt, $messages, $engine, $model),
            default               => $this->viaOpenAi($systemPrompt, $messages, $model, $webSearch),
        };

        $this->deductCredits($response, $this->lastUsedModel);

        return $response;
    }

    /**
     * Stream a copilot conversation, emitting granular events via callback.
     *
     * When $tools are provided, uses native OpenAI function calling (GPT-4.5+).
     * Without tools, falls back to reasoning model chain: GPT_O_4_MINI → GPT_O_03_mini → non-streaming chat().
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<int, array<string, mixed>>|null  $tools  OpenAI tool definitions
     * @param  callable(string $type, array $payload): void  $onChunk
     */
    public function streamCopilot(
        string $systemPrompt,
        array $messages,
        ?EntityEnum $model,
        callable $onChunk,
        ?array $tools = null,
    ): void {
        if ($tools !== null) {
            $apiMessages = array_merge(
                [['role' => 'system', 'content' => $systemPrompt]],
                $this->normalizeMessagesToTextOnly($messages),
            );

            try {
                $usedModel = $model ?? EntityEnum::GPT_5_4_MINI;
                $accumulated = $this->streamViaOpenAiReasoning($usedModel, $apiMessages, $onChunk, $tools);
                $this->deductCredits($accumulated, $usedModel);
            } catch (Exception $e) {
                $onChunk('error', ['message' => $e->getMessage()]);
                $onChunk('done', []);
            }

            return;
        }

        // Reasoning models don't support system role — prepend as first user message
        $apiMessages = array_merge(
            [['role' => 'user', 'content' => $systemPrompt]],
            $this->normalizeMessagesToTextOnly($messages),
        );

        $modelsToTry = array_filter([
            $model,
            EntityEnum::GPT_O_4_MINI,
            EntityEnum::GPT_O_03_mini,
        ]);

        // Deduplicate while preserving order
        $seen = [];
        $modelsToTry = array_values(array_filter($modelsToTry, function (EntityEnum $m) use (&$seen): bool {
            if (in_array($m->value, $seen, true)) {
                return false;
            }
            $seen[] = $m->value;

            return true;
        }));

        $accumulated = '';
        $trackingChunk = function (string $type, array $payload) use ($onChunk, &$accumulated): void {
            if ($type === 'text_delta' && isset($payload['text'])) {
                $accumulated .= $payload['text'];
            }
            $onChunk($type, $payload);
        };

        try {
            foreach ($modelsToTry as $candidate) {
                try {
                    $this->streamViaOpenAiReasoning($candidate, $apiMessages, $trackingChunk);
                    $this->deductCredits($accumulated, $candidate);

                    return;
                } catch (Exception) {
                    $accumulated = '';
                    // Try next model in chain
                }
            }

            // All reasoning models failed — fall back to non-streaming chat()
            // chat() handles credit deduction internally when creditUser is set.
            $onChunk('thinking_start', []);
            $response = $this->chat($systemPrompt, $messages);
            $onChunk('thinking_end', []);

            $parsed = $this->parseToolCallsFromText($response);
            $textToEmit = $parsed !== null ? ($parsed['text'] ?: $response) : $response;

            if ($textToEmit !== '') {
                $onChunk('text_delta', ['text' => $textToEmit]);
            }

            $this->emitParsedResponse($response, $onChunk);
        } catch (Exception $e) {
            $onChunk('error', ['message' => $e->getMessage()]);
            $onChunk('done', []);
        }
    }

    /**
     * Build an OpenAI client for streaming. Overridable for testing.
     *
     * Uses the singleton bound by openai-php/laravel which already has Guzzle
     * configured with the correct timeout. ApiHelper::setOpenAiKey() must be
     * called first to populate config('openai.api_key') before the singleton
     * is resolved.
     */
    protected function makeOpenAiClient(): ClientContract
    {
        ApiHelper::setOpenAiKey();

        return app(ClientContract::class);
    }

    /**
     * Stream a single OpenAI model and emit events via callback.
     *
     * When $tools are provided, runs an agentic loop: each iteration streams a response,
     * emits reasoning text + tool call events, feeds tool results back to the model, and
     * repeats until the model returns no more tool calls. This produces a Zapier-like UX
     * where reasoning and tool calls appear sequentially.
     *
     * The special "emit_action_buttons" tool is intercepted and emitted as an
     * action_buttons SSE event instead of a workflow tool_call event.
     *
     * @param  array<int, array{role: string, content: string}>  $apiMessages
     * @param  array<int, array<string, mixed>>|null  $tools  OpenAI tool definitions
     * @param  callable(string $type, array $payload): void  $onChunk
     */
    private function streamViaOpenAiReasoning(
        EntityEnum $model,
        array $apiMessages,
        callable $onChunk,
        ?array $tools = null,
    ): string {
        $client = $this->makeOpenAiClient();

        if ($tools !== null) {
            return $this->runAgenticLoop($client, $model, $apiMessages, $tools, $onChunk);
        }

        $stream = $client->chat()->createStreamed([
            'model'    => $model->value,
            'messages' => $apiMessages,
        ]);

        $onChunk('thinking_start', []);
        $fullText = '';

        foreach ($stream as $chunk) {
            $content = $chunk->choices[0]->delta->content ?? null;
            if ($content !== null && $content !== '') {
                $fullText .= $content;
            }
        }

        $onChunk('thinking_end', []);

        // No native tool calls — fall back to text parsing path
        $parsed = $this->parseToolCallsFromText($fullText);

        if ($parsed !== null) {
            if ($parsed['text'] !== '') {
                $onChunk('text_delta', ['text' => $parsed['text']]);
            }
        } else {
            $onChunk('text_delta', ['text' => $fullText]);
        }

        $this->emitParsedResponse($fullText, $onChunk);

        return $fullText;
    }

    /**
     * Agentic loop for native tool calling. Iterates until the model stops calling tools.
     * Each iteration: stream response → emit reasoning text → emit tool calls → feed results back.
     *
     * @param  array<int, array<string, mixed>>  $tools
     * @param  callable(string $type, array $payload): void  $onChunk
     */
    private function runAgenticLoop(
        ClientContract $client,
        EntityEnum $model,
        array $messages,
        array $tools,
        callable $onChunk,
        int $maxIterations = 10,
    ): string {
        $totalText = '';

        for ($i = 0; $i < $maxIterations; $i++) {
            $stream = $client->chat()->createStreamed([
                'model'    => $model->value,
                'messages' => $messages,
                'tools'    => $tools,
            ]);

            $onChunk('thinking_start', []);
            $fullText = '';
            $toolCallAccumulator = [];

            foreach ($stream as $chunk) {
                $delta = $chunk->choices[0]->delta;

                $content = $delta->content ?? null;
                if ($content !== null && $content !== '') {
                    $fullText .= $content;
                }

                foreach ($delta->toolCalls ?? [] as $tcDelta) {
                    $idx = $tcDelta->index;
                    if (! isset($toolCallAccumulator[$idx])) {
                        $toolCallAccumulator[$idx] = ['id' => '', 'name' => '', 'arguments' => ''];
                    }
                    if (! empty($tcDelta->id)) {
                        $toolCallAccumulator[$idx]['id'] = $tcDelta->id;
                    }
                    if (! empty($tcDelta->function->name)) {
                        $toolCallAccumulator[$idx]['name'] = $tcDelta->function->name;
                    }
                    if (! empty($tcDelta->function->arguments)) {
                        $toolCallAccumulator[$idx]['arguments'] .= $tcDelta->function->arguments;
                    }
                }
            }

            $onChunk('thinking_end', []);

            if ($fullText !== '') {
                $totalText .= $fullText;
                $onChunk('text_delta', ['text' => $fullText]);
            }

            if (empty($toolCallAccumulator)) {
                break;
            }

            $assistantToolCalls = [];
            $toolResultMessages = [];

            foreach ($toolCallAccumulator as $tc) {
                $args = json_decode($tc['arguments'], true) ?? [];
                $id = $tc['id'] ?: (string) Str::uuid();

                if ($tc['name'] === 'emit_action_buttons') {
                    $onChunk('action_buttons', ['buttons' => $args['buttons'] ?? []]);
                } else {
                    $onChunk('tool_call_start', ['name' => $tc['name'], 'id' => $id]);
                    $onChunk('tool_call_end', [
                        'name'   => $tc['name'],
                        'id'     => $id,
                        'args'   => $args,
                        'status' => 'success',
                    ]);
                }

                $assistantToolCalls[] = [
                    'id'       => $id,
                    'type'     => 'function',
                    'function' => [
                        'name'      => $tc['name'],
                        'arguments' => $tc['arguments'],
                    ],
                ];

                $toolResultMessages[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $id,
                    'content'      => json_encode(['success' => true]),
                ];
            }

            $messages[] = [
                'role'       => 'assistant',
                'content'    => $fullText !== '' ? $fullText : null,
                'tool_calls' => $assistantToolCalls,
            ];
            $messages = array_merge($messages, $toolResultMessages);
        }

        $onChunk('done', []);

        return $totalText;
    }

    /**
     * Parse accumulated text for JSON tool-call blocks and emit tool_call_start/end events.
     *
     * @param  callable(string $type, array $payload): void  $onChunk
     */
    private function emitParsedResponse(string $text, callable $onChunk): void
    {
        $parsed = $this->parseToolCallsFromText($text);

        if ($parsed !== null) {
            foreach ($parsed['tool_calls'] as $toolCall) {
                $id = (string) Str::uuid();
                $onChunk('tool_call_start', ['name' => $toolCall['name'], 'id' => $id]);
                $onChunk('tool_call_end', [
                    'name'   => $toolCall['name'],
                    'id'     => $id,
                    'args'   => $toolCall['args'] ?? [],
                    'status' => 'success',
                ]);
            }

            if (! empty($parsed['action_buttons'])) {
                $onChunk('action_buttons', ['buttons' => $parsed['action_buttons']]);
            }
        }

        $onChunk('done', []);
    }

    /**
     * Parse the AI response text for an optional JSON tool-call block.
     *
     * @return array{text: string, tool_calls: array<int, array{name: string, args: array<string, mixed>}>, action_buttons: array<int, array<string, mixed>>}|null
     */
    private function parseToolCallsFromText(string $response): ?array
    {
        if (preg_match('/```json\s*(\{.*?\})\s*```/s', $response, $matches)) {
            $decoded = json_decode($matches[1], true);

            if (is_array($decoded) && (isset($decoded['tool_calls']) || isset($decoded['action_buttons']))) {
                return [
                    'text'           => $decoded['text'] ?? '',
                    'tool_calls'     => $decoded['tool_calls'] ?? [],
                    'action_buttons' => $decoded['action_buttons'] ?? [],
                ];
            }
        }

        if (preg_match('/\{[\s\S]*"(?:tool_calls|action_buttons)"[\s\S]*\}/m', $response, $matches)) {
            $decoded = json_decode($matches[0], true);

            if (is_array($decoded) && (isset($decoded['tool_calls']) || isset($decoded['action_buttons']))) {
                return [
                    'text'           => $decoded['text'] ?? '',
                    'tool_calls'     => $decoded['tool_calls'] ?? [],
                    'action_buttons' => $decoded['action_buttons'] ?? [],
                ];
            }
        }

        return null;
    }

    private function viaOpenAi(string $systemPrompt, array $messages, ?EntityEnum $modelOverride = null, bool $webSearch = false): string
    {
        $apiKey = ApiHelper::setOpenAiKey();
        $model = $modelOverride ?? Helper::defaultWordModel();
        $this->lastUsedModel = $model;

        if ($webSearch) {
            return $this->viaOpenAiResponses($apiKey, $model->value, $systemPrompt, $messages);
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type'  => 'application/json',
        ])
            ->timeout(120)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'    => $model->value,
                'messages' => array_merge(
                    [['role' => 'system', 'content' => $systemPrompt]],
                    $this->normalizeMessagesForOpenAI($messages),
                ),
            ]);

        if ($response->failed()) {
            $error = $response->json('error.message') ?? $response->json('error') ?? "HTTP {$response->status()}";

            throw new Exception("OpenAI API error (model: {$model->value}): {$error}");
        }

        return $response->json('choices.0.message.content') ?? '';
    }

    /**
     * Use the OpenAI Responses API which supports web_search_preview tool.
     */
    private function viaOpenAiResponses(string $apiKey, string $modelValue, string $systemPrompt, array $messages): string
    {
        $input = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $messages,
        );

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type'  => 'application/json',
        ])
            ->timeout(120)
            ->post('https://api.openai.com/v1/responses', [
                'model'  => $modelValue,
                'tools'  => [['type' => 'web_search_preview']],
                'input'  => $input,
            ]);

        if ($response->failed()) {
            $error = $response->json('error.message') ?? $response->json('error') ?? "HTTP {$response->status()}";

            throw new Exception("OpenAI API error (model: {$modelValue}): {$error}");
        }

        return collect($response->json('output') ?? [])
            ->where('type', 'message')
            ->pluck('content')
            ->flatten(1)
            ->where('type', 'output_text')
            ->pluck('text')
            ->implode('');
    }

    private function viaAnthropic(string $systemPrompt, array $messages, ?EntityEnum $modelOverride = null): string
    {
        $this->lastUsedModel = $modelOverride
            ?? EntityEnum::tryFrom(setting('anthropic_default_model', EntityEnum::CLAUDE_3_OPUS->value))
            ?? EntityEnum::CLAUDE_3_OPUS;

        $service = app(AnthropicService::class)
            ->setSystem($systemPrompt)
            ->setMessages($this->normalizeMessagesForAnthropic($messages))
            ->setStream(false);

        if ($modelOverride !== null && method_exists($service, 'setModel')) {
            $service->setModel($modelOverride->value);
        }

        $response = $service->stream();

        if ($response->failed()) {
            $modelLabel = $modelOverride?->value ?? 'default';
            $error = $response->json('error.message') ?? $response->json('error') ?? "HTTP {$response->status()}";

            throw new Exception("Anthropic API error (model: {$modelLabel}): {$error}");
        }

        return collect($response->json('content') ?? [])
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');
    }

    private function viaGemini(string $systemPrompt, array $messages, EngineEnum $engine, ?EntityEnum $modelOverride = null, bool $webSearch = false): string
    {
        $model = $modelOverride ?? $engine->getDefaultWordModel(null);
        $this->lastUsedModel = $model;

        $history = collect($messages)->map(function ($m): array {
            $role = $m['role'] === 'assistant' ? 'model' : 'user';

            if (is_string($m['content'])) {
                return ['role' => $role, 'parts' => [['text' => $m['content']]]];
            }

            $parts = [];

            foreach ($m['content'] as $part) {
                if ($part['type'] === 'text') {
                    $parts[] = ['text' => $part['text']];
                } elseif ($part['type'] === 'image') {
                    $parts[] = ['inline_data' => ['mime_type' => $part['mime_type'], 'data' => $part['base64']]];
                }
            }

            return ['role' => $role, 'parts' => $parts];
        })->all();

        $history = array_merge(
            [['role' => 'user', 'parts' => [['text' => $systemPrompt]]]],
            $history,
        );

        $gemini = app(GeminiService::class)->setHistory($history);

        if ($webSearch && method_exists($gemini, 'setTools')) {
            $gemini->setTools([['google_search' => (object) []]]);
        }

        $response = $gemini->generateContent($model->value);

        if ($response->failed()) {
            $error = $response->json('error.message') ?? $response->json('error') ?? "HTTP {$response->status()}";

            throw new Exception("Gemini API error (model: {$model->value}): {$error}");
        }

        return collect($response->json('candidates') ?? [])
            ->pluck('content.parts')
            ->flatten(1)
            ->pluck('text')
            ->implode('');
    }

    private function viaDeepSeek(string $systemPrompt, array $messages, EngineEnum $engine, ?EntityEnum $modelOverride = null): string
    {
        ApiHelper::setDeepseekKey();

        $model = $modelOverride ?? $engine->getDefaultWordModel(null);
        $this->lastUsedModel = $model;
        $apiKey = config('deepseek.api_key');

        $response = (new Client)->post('https://api.deepseek.com/chat/completions', [
            'timeout' => 120,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'Authorization' => "Bearer {$apiKey}",
            ],
            'json' => [
                'model'    => $model->value,
                'messages' => array_merge(
                    [['role' => 'system', 'content' => $systemPrompt]],
                    $this->normalizeMessagesToTextOnly($messages),
                ),
                'stream' => false,
            ],
        ]);

        $body = json_decode($response->getBody()->getContents(), true);

        if ($response->getStatusCode() >= 400) {
            $error = $body['error']['message'] ?? $body['error'] ?? "HTTP {$response->getStatusCode()}";

            throw new Exception("DeepSeek API error (model: {$model->value}): {$error}");
        }

        return $body['choices'][0]['message']['content'] ?? '';
    }

    private function viaXAi(string $systemPrompt, array $messages, EngineEnum $engine, ?EntityEnum $modelOverride = null): string
    {
        $apiKey = ApiHelper::setXAiKey();
        $model = $modelOverride ?? $engine->getDefaultWordModel(null);
        $this->lastUsedModel = $model;

        $result = OpenAIMain::factory()
            ->withApiKey($apiKey)
            ->withBaseUri('https://api.x.ai/v1')
            ->make()
            ->chat()
            ->create([
                'model'    => $model->value,
                'messages' => array_merge(
                    [['role' => 'system', 'content' => $systemPrompt]],
                    $this->normalizeMessagesToTextOnly($messages),
                ),
            ]);

        return $result->choices[0]->message->content ?? '';
    }

    /**
     * Convert internal multimodal message format to OpenAI Chat Completions vision format.
     * Text-only messages are passed through unchanged.
     */
    private function normalizeMessagesForOpenAI(array $messages): array
    {
        return array_map(function (array $msg): array {
            if (is_string($msg['content'])) {
                return $msg;
            }

            $content = [];

            foreach ($msg['content'] as $part) {
                if ($part['type'] === 'text') {
                    $content[] = ['type' => 'text', 'text' => $part['text']];
                } elseif ($part['type'] === 'image') {
                    $content[] = [
                        'type'      => 'image_url',
                        'image_url' => ['url' => "data:{$part['mime_type']};base64,{$part['base64']}"],
                    ];
                }
            }

            return ['role' => $msg['role'], 'content' => $content];
        }, $messages);
    }

    /**
     * Convert internal multimodal message format to Anthropic Messages API format.
     * Text-only messages are passed through unchanged.
     */
    private function normalizeMessagesForAnthropic(array $messages): array
    {
        return array_map(function (array $msg): array {
            if (is_string($msg['content'])) {
                return $msg;
            }

            $content = [];

            foreach ($msg['content'] as $part) {
                if ($part['type'] === 'text') {
                    $content[] = ['type' => 'text', 'text' => $part['text']];
                } elseif ($part['type'] === 'image') {
                    $content[] = [
                        'type'   => 'image',
                        'source' => [
                            'type'       => 'base64',
                            'media_type' => $part['mime_type'],
                            'data'       => $part['base64'],
                        ],
                    ];
                }
            }

            return ['role' => $msg['role'], 'content' => $content];
        }, $messages);
    }

    private function deductCredits(string $responseText, ?EntityEnum $model): void
    {
        if ($this->creditUser === null || $model === null || $responseText === '') {
            return;
        }

        $driver = Entity::driver($model)->forUser($this->creditUser);
        $driver->input($responseText)->calculateCredit()->decreaseCredit();
        Usage::getSingle()->updateWordCounts($driver->calculate());
    }

    /**
     * Flatten multimodal content to plain text for providers that don't support vision.
     * Image parts are replaced with a placeholder note.
     */
    private function normalizeMessagesToTextOnly(array $messages): array
    {
        return array_map(function (array $msg): array {
            if (is_string($msg['content'])) {
                return $msg;
            }

            $text = '';

            foreach ($msg['content'] as $part) {
                if ($part['type'] === 'text') {
                    $text .= $part['text'];
                } elseif ($part['type'] === 'image') {
                    $text .= ' [Image attached]';
                }
            }

            return ['role' => $msg['role'], 'content' => trim($text)];
        }, $messages);
    }
}
