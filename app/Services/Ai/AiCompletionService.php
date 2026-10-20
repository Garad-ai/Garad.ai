<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Domains\Engine\Enums\EngineEnum;
use App\Domains\Engine\Services\AnthropicService;
use App\Domains\Engine\Services\GeminiService;
use App\Helpers\Classes\ApiHelper;
use App\Helpers\Classes\Helper;
use GuzzleHttp\Client;
use Illuminate\Http\UploadedFile;
use OpenAI as OpenAIMain;
use OpenAI\Laravel\Facades\OpenAI;

class AiCompletionService
{
    public function completeUserOnly(string $userContent, ?EngineEnum $engine = null): string
    {
        return $this->complete('You are a helpful assistant.', $userContent, $engine);
    }

    public function complete(
        string $systemPrompt,
        string $userContent,
        ?EngineEnum $engine = null,
        ?string $model = null,
    ): string
    {
        $engine ??= Helper::defaultEngine();

        return match ($engine) {
            EngineEnum::ANTHROPIC => $this->viaAnthropic($systemPrompt, $userContent, $model),
            EngineEnum::GEMINI    => $this->viaGemini($systemPrompt, $userContent, $engine, $model),
            EngineEnum::DEEP_SEEK => $this->viaDeepSeek($systemPrompt, $userContent, $engine, $model),
            EngineEnum::X_AI      => $this->viaXAi($systemPrompt, $userContent, $engine, $model),
            default               => $this->viaOpenAi($systemPrompt, $userContent, $model),
        };
    }

    private function viaOpenAi(string $systemPrompt, string $userContent, ?string $model = null): string
    {
        ApiHelper::setOpenAiKey();

        $model ??= Helper::defaultWordModel()->value;

        $payload = [
            'model' => $model,
            'input' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userContent],
            ],
        ];
        if (request()?->attributes->has('garad_provider_request_max')) {
            $payload['max_output_tokens'] = ProviderRequestBudgetService::MAX_OUTPUT_TOKENS;
        }
        $payload = app(ProviderRequestBudgetService::class)->preparePayload($payload);
        app(ProviderRequestBudgetService::class)->recordProviderRequest();
        $result = OpenAI::responses()->create($payload);

        return collect($result['output'] ?? [])
            ->flatMap(fn ($item) => $item['content'] ?? [])
            ->pluck('text')
            ->implode('');
    }

    private function viaAnthropic(string $systemPrompt, string $userContent, ?string $model = null): string
    {
        $client = app(AnthropicService::class);

        $response = $client
            ->setModel($model ?? setting('anthropic_default_model'))
            ->setSystem($systemPrompt)
            ->setMessages([
                ['role' => 'user', 'content' => $userContent],
            ])
            ->setStream(false)
            ->stream();

        $body = $response->json();

        return collect($body['content'] ?? [])
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');
    }

    private function viaGemini(
        string $systemPrompt,
        string $userContent,
        EngineEnum $engine,
        ?string $model = null,
    ): string
    {
        $model ??= $engine->getDefaultWordModel(null)->value;

        $client = app(GeminiService::class);
        $response = $client
            ->setHistory([
                ['role' => 'user', 'parts' => [['text' => $systemPrompt . "\n\n" . $userContent]]],
            ])
            ->setGenerationConfig(request()?->attributes->has('garad_provider_request_max')
                ? ['maxOutputTokens' => ProviderRequestBudgetService::MAX_OUTPUT_TOKENS]
                : [])
            ->generateContent($model);

        $body = $response->json();

        return collect($body['candidates'] ?? [])
            ->pluck('content.parts')
            ->flatten(1)
            ->pluck('text')
            ->implode('');
    }

    private function viaDeepSeek(
        string $systemPrompt,
        string $userContent,
        EngineEnum $engine,
        ?string $model = null,
    ): string
    {
        ApiHelper::setDeepseekKey();

        $model ??= $engine->getDefaultWordModel(null)->value;
        $apiKey = config('deepseek.api_key');

        $payload = [
            'model'    => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userContent],
            ],
            'stream' => false,
        ];
        if (request()?->attributes->has('garad_provider_request_max')) {
            $payload['max_tokens'] = ProviderRequestBudgetService::MAX_OUTPUT_TOKENS;
        }
        $payload = app(ProviderRequestBudgetService::class)->preparePayload($payload);
        app(ProviderRequestBudgetService::class)->recordProviderRequest();
        $response = (new Client)->post('https://api.deepseek.com/chat/completions', [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'Authorization' => "Bearer {$apiKey}",
            ],
            'json' => $payload,
        ]);

        $body = json_decode($response->getBody()->getContents(), true);

        return $body['choices'][0]['message']['content'] ?? '';
    }

    private function viaXAi(
        string $systemPrompt,
        string $userContent,
        EngineEnum $engine,
        ?string $model = null,
    ): string
    {
        $apiKey = ApiHelper::setXAiKey();
        $model ??= $engine->getDefaultWordModel(null)->value;

        $client = OpenAIMain::factory()
            ->withApiKey($apiKey)
            ->withBaseUri('https://api.x.ai/v1')
            ->make();

        $result = $client->chat()->create([
            'model'    => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userContent],
            ],
        ]);

        return $result->choices[0]->message->content ?? '';
    }

    public function completeWithImage(string $systemPrompt, string $userContent, ?UploadedFile $image = null, ?EngineEnum $engine = null): string
    {
        if (! $image) {
            return $this->complete($systemPrompt, $userContent, $engine);
        }

        $engine ??= Helper::defaultEngine();
        $base64 = base64_encode(file_get_contents($image->getRealPath()));
        $mimeType = $image->getMimeType() ?: 'image/jpeg';

        return match ($engine) {
            EngineEnum::ANTHROPIC => $this->viaAnthropicWithImage($systemPrompt, $userContent, $base64, $mimeType),
            EngineEnum::GEMINI    => $this->viaGeminiWithImage($systemPrompt, $userContent, $base64, $mimeType, $engine),
            default               => $this->viaOpenAiWithImage($systemPrompt, $userContent, $base64, $mimeType),
        };
    }

    public function completeWithImagePath(string $systemPrompt, string $userContent, string $imagePath, ?EngineEnum $engine = null): string
    {
        $engine ??= Helper::defaultEngine();
        $base64 = base64_encode(file_get_contents($imagePath));
        $mimeType = mime_content_type($imagePath) ?: 'image/png';

        return match ($engine) {
            EngineEnum::ANTHROPIC => $this->viaAnthropicWithImage($systemPrompt, $userContent, $base64, $mimeType),
            EngineEnum::GEMINI    => $this->viaGeminiWithImage($systemPrompt, $userContent, $base64, $mimeType, $engine),
            default               => $this->viaOpenAiWithImage($systemPrompt, $userContent, $base64, $mimeType),
        };
    }

    private function viaOpenAiWithImage(string $systemPrompt, string $userContent, string $base64, string $mimeType): string
    {
        ApiHelper::setOpenAiKey();

        $model = Helper::defaultWordModel();

        $result = OpenAI::responses()->create([
            'model' => $model->value,
            'input' => [
                ['role' => 'system', 'content' => $systemPrompt],
                [
                    'role'    => 'user',
                    'content' => [
                        ['type' => 'input_text', 'text' => $userContent],
                        ['type' => 'input_image', 'image_url' => "data:{$mimeType};base64,{$base64}"],
                    ],
                ],
            ],
        ]);

        return collect($result['output'] ?? [])
            ->flatMap(fn ($item) => $item['content'] ?? [])
            ->pluck('text')
            ->implode('');
    }

    private function viaAnthropicWithImage(string $systemPrompt, string $userContent, string $base64, string $mimeType): string
    {
        $client = app(AnthropicService::class);

        $response = $client
            ->setSystem($systemPrompt)
            ->setMessages([
                [
                    'role'    => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $userContent],
                        [
                            'type'   => 'image',
                            'source' => [
                                'type'       => 'base64',
                                'media_type' => $mimeType,
                                'data'       => $base64,
                            ],
                        ],
                    ],
                ],
            ])
            ->setStream(false)
            ->stream();

        $body = $response->json();

        return collect($body['content'] ?? [])
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');
    }

    private function viaGeminiWithImage(string $systemPrompt, string $userContent, string $base64, string $mimeType, EngineEnum $engine): string
    {
        $model = $engine->getDefaultWordModel(null);

        $client = app(GeminiService::class);

        $response = $client
            ->setHistory([
                [
                    'role'  => 'user',
                    'parts' => [
                        ['text' => $systemPrompt . "\n\n" . $userContent],
                        ['inlineData' => ['mimeType' => $mimeType, 'data' => $base64]],
                    ],
                ],
            ])
            ->generateContent($model->value);

        $body = $response->json();

        return collect($body['candidates'] ?? [])
            ->pluck('content.parts')
            ->flatten(1)
            ->pluck('text')
            ->implode('');
    }
}
