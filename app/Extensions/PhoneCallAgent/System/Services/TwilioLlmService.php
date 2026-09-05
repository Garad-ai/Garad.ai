<?php

declare(strict_types=1);

namespace App\Extensions\PhoneCallAgent\System\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Log;

class TwilioLlmService
{
    public function complete(string $model, string $systemPrompt, array $messages): string
    {
        $engine = $this->engineFromModel($model);

        return match ($engine) {
            'anthropic'  => $this->viaAnthropic($model, $systemPrompt, $messages),
            'gemini'     => $this->viaGemini($model, $systemPrompt, $messages),
            'deep_seek'  => $this->viaDeepSeek($model, $systemPrompt, $messages),
            default      => $this->viaOpenAi($model, $systemPrompt, $messages),
        };
    }

    private function engineFromModel(string $model): string
    {
        return match (true) {
            str_starts_with($model, 'claude')    => 'anthropic',
            str_starts_with($model, 'gemini')    => 'gemini',
            str_starts_with($model, 'deepseek')  => 'deep_seek',
            default                              => 'openai',
        };
    }

    private function viaOpenAi(string $model, string $systemPrompt, array $messages): string
    {
        $apiKey = $this->readApiKey('openai_api_secret', 'openai.api_key');

        $payload = [
            'model'    => $model,
            'messages' => array_merge(
                [['role' => 'system', 'content' => $systemPrompt]],
                $messages
            ),
        ];

        $response = $this->post('https://api.openai.com/v1/chat/completions', $payload, [
            'Authorization' => 'Bearer ' . $apiKey,
        ]);

        return $response['choices'][0]['message']['content'] ?? '';
    }

    private function viaAnthropic(string $model, string $systemPrompt, array $messages): string
    {
        $apiKey = $this->readApiKey('anthropic_api_secret');

        $payload = [
            'model'      => $model,
            'max_tokens' => 1024,
            'system'     => $systemPrompt,
            'messages'   => $messages,
        ];

        $response = $this->post('https://api.anthropic.com/v1/messages', $payload, [
            'x-api-key'         => $apiKey,
            'anthropic-version' => '2023-06-01',
        ]);

        return $response['content'][0]['text'] ?? '';
    }

    private function viaGemini(string $model, string $systemPrompt, array $messages): string
    {
        $apiKey = $this->readApiKey('gemini_api_secret', 'gemini.api_key');

        $geminiMessages = array_map(fn ($m) => [
            'role'  => $m['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $m['content']]],
        ], $messages);

        $payload = [
            'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
            'contents'           => $geminiMessages,
        ];

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
        $response = $this->post($url, $payload, []);

        return $response['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }

    private function viaDeepSeek(string $model, string $systemPrompt, array $messages): string
    {
        $apiKey = $this->readApiKey('deepseek_api_secret');

        $payload = [
            'model'    => $model,
            'messages' => array_merge(
                [['role' => 'system', 'content' => $systemPrompt]],
                $messages
            ),
        ];

        $response = $this->post('https://api.deepseek.com/v1/chat/completions', $payload, [
            'Authorization' => 'Bearer ' . $apiKey,
        ]);

        return $response['choices'][0]['message']['content'] ?? '';
    }

    private function readApiKey(string $settingKey, ?string $configKey = null): string
    {
        if ($configKey && config($configKey)) {
            return config($configKey);
        }

        // Keys live on the single-row Setting model (e.g. openai_api_secret), NOT the key/value
        // setting() store. Comma-separated values are key pools — pick one at random.
        $value = (string) (Setting::getCache()?->{$settingKey} ?? '');

        if (str_contains($value, ',')) {
            $keys = array_values(array_filter(array_map('trim', explode(',', $value))));

            return $keys === [] ? '' : $keys[array_rand($keys)];
        }

        return $value;
    }

    /**
     * Call the LLM with tool definitions, execute any tool calls, then return the final text plus a
     * summary of the tool results from this turn so the caller can persist them into conversation
     * memory (ConversationRelay only feeds plain user/assistant strings back on later turns).
     *
     * @param  array<int, array<string, mixed>>  $tools  Provider-format tool definitions
     * @param  callable(string, array): string  $toolHandler  fn(toolName, input): resultString
     *
     * @return array{text: string, toolSummary: string}
     */
    public function completeWithTools(string $model, string $systemPrompt, array $messages, array $tools, callable $toolHandler): array
    {
        $engine = $this->engineFromModel($model);

        return match ($engine) {
            'anthropic' => $this->viaAnthropicWithTools($model, $systemPrompt, $messages, $tools, $toolHandler),
            'gemini'    => $this->viaGeminiWithTools($model, $systemPrompt, $messages, $tools, $toolHandler),
            default     => $this->viaOpenAiWithTools($model, $systemPrompt, $messages, $tools, $toolHandler),
        };
    }

    /**
     * Render the tool exchanges into a compact note for conversation memory.
     *
     * @param  array<int, array{name: string, result: string}>  $calls
     */
    private function toolSummary(array $calls): string
    {
        if ($calls === []) {
            return '';
        }

        $lines = array_map(fn (array $c) => "- {$c['name']} → {$c['result']}", $calls);

        return "[Internal tool results from this turn — rely on these and do not re-run the same lookup unless the caller asks for new information:\n"
            . implode("\n", $lines) . "\n]";
    }

    /** @return array{text: string, toolSummary: string} */
    private function viaAnthropicWithTools(string $model, string $systemPrompt, array $messages, array $tools, callable $toolHandler): array
    {
        $apiKey = $this->readApiKey('anthropic_api_secret');

        $payload = [
            'model'      => $model,
            'max_tokens' => 1024,
            'system'     => $systemPrompt,
            'messages'   => $messages,
            'tools'      => $tools,
        ];

        $response = $this->post('https://api.anthropic.com/v1/messages', $payload, [
            'x-api-key'         => $apiKey,
            'anthropic-version' => '2023-06-01',
        ]);

        $content = $response['content'] ?? [];
        $toolUses = array_filter($content, fn ($block) => ($block['type'] ?? '') === 'tool_use');

        if (empty($toolUses)) {
            return ['text' => $content[0]['text'] ?? '', 'toolSummary' => ''];
        }

        $toolResults = [];
        $calls = [];
        foreach ($toolUses as $toolUse) {
            $result = $toolHandler($toolUse['name'], $toolUse['input'] ?? []);
            $toolResults[] = [
                'type'        => 'tool_result',
                'tool_use_id' => $toolUse['id'],
                'content'     => $result,
            ];
            $calls[] = ['name' => $toolUse['name'], 'result' => $result];
        }

        $messages[] = ['role' => 'assistant', 'content' => $content];
        $messages[] = ['role' => 'user', 'content' => $toolResults];

        unset($payload['tools']);
        $payload['messages'] = $messages;

        $finalResponse = $this->post('https://api.anthropic.com/v1/messages', $payload, [
            'x-api-key'         => $apiKey,
            'anthropic-version' => '2023-06-01',
        ]);

        return [
            'text'        => $finalResponse['content'][0]['text'] ?? '',
            'toolSummary' => $this->toolSummary($calls),
        ];
    }

    /** @return array{text: string, toolSummary: string} */
    private function viaOpenAiWithTools(string $model, string $systemPrompt, array $messages, array $tools, callable $toolHandler): array
    {
        $isDeepSeek = str_starts_with($model, 'deepseek');
        $baseUrl = $isDeepSeek ? 'https://api.deepseek.com/v1/chat/completions' : 'https://api.openai.com/v1/chat/completions';
        $apiKey = $isDeepSeek
            ? $this->readApiKey('deepseek_api_secret')
            : $this->readApiKey('openai_api_secret', 'openai.api_key');

        $allMessages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $messages,
        );

        $payload = [
            'model'    => $model,
            'messages' => $allMessages,
            'tools'    => $tools,
        ];

        $response = $this->post($baseUrl, $payload, ['Authorization' => 'Bearer ' . $apiKey]);
        $message = $response['choices'][0]['message'] ?? [];
        $toolCalls = $message['tool_calls'] ?? [];

        if (empty($toolCalls)) {
            return ['text' => $message['content'] ?? '', 'toolSummary' => ''];
        }

        $allMessages[] = $message;

        $calls = [];
        foreach ($toolCalls as $toolCall) {
            $name = $toolCall['function']['name'] ?? '';
            $args = json_decode($toolCall['function']['arguments'] ?? '{}', true) ?? [];
            $result = $toolHandler($name, $args);
            $allMessages[] = [
                'role'         => 'tool',
                'tool_call_id' => $toolCall['id'],
                'content'      => $result,
            ];
            $calls[] = ['name' => $name, 'result' => $result];
        }

        unset($payload['tools']);
        $payload['messages'] = $allMessages;

        $finalResponse = $this->post($baseUrl, $payload, ['Authorization' => 'Bearer ' . $apiKey]);

        return [
            'text'        => $finalResponse['choices'][0]['message']['content'] ?? '',
            'toolSummary' => $this->toolSummary($calls),
        ];
    }

    /** @return array{text: string, toolSummary: string} */
    private function viaGeminiWithTools(string $model, string $systemPrompt, array $messages, array $tools, callable $toolHandler): array
    {
        $apiKey = $this->readApiKey('gemini_api_secret', 'gemini.api_key');

        $geminiMessages = array_map(fn ($m) => [
            'role'  => $m['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $m['content']]],
        ], $messages);

        $payload = [
            'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
            'contents'           => $geminiMessages,
            'tools'              => [['function_declarations' => $tools]],
        ];

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
        $response = $this->post($url, $payload, []);

        $parts = $response['candidates'][0]['content']['parts'] ?? [];
        $funcCalls = array_filter($parts, fn ($p) => isset($p['functionCall']));

        if (empty($funcCalls)) {
            return ['text' => $parts[0]['text'] ?? '', 'toolSummary' => ''];
        }

        $geminiMessages[] = ['role' => 'model', 'parts' => $parts];

        $resultParts = [];
        $calls = [];
        foreach ($funcCalls as $part) {
            $fc = $part['functionCall'];
            $result = $toolHandler($fc['name'], $fc['args'] ?? []);
            $resultParts[] = [
                'functionResponse' => [
                    'name'     => $fc['name'],
                    'response' => ['result' => $result],
                ],
            ];
            $calls[] = ['name' => $fc['name'], 'result' => $result];
        }

        $geminiMessages[] = ['role' => 'user', 'parts' => $resultParts];

        unset($payload['tools']);
        $payload['contents'] = $geminiMessages;

        $finalResponse = $this->post($url, $payload, []);

        return [
            'text'        => $finalResponse['candidates'][0]['content']['parts'][0]['text'] ?? '',
            'toolSummary' => $this->toolSummary($calls),
        ];
    }

    private function post(string $url, array $payload, array $headers): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => array_merge(
                ['Content-Type: application/json'],
                array_map(fn ($k, $v) => "{$k}: {$v}", array_keys($headers), $headers)
            ),
            CURLOPT_TIMEOUT => 30,
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $decoded = json_decode((string) $body, true) ?? [];

        if ($error !== '' || $status >= 400 || isset($decoded['error'])) {
            Log::error('[Twilio LLM] API call failed', [
                'url'        => $url,
                'status'     => $status,
                'curl_error' => $error,
                'error'      => $decoded['error'] ?? null,
            ]);
        }

        return $decoded;
    }
}
