<?php

declare(strict_types=1);

namespace App\Domains\Engine\Services;

use App\Helpers\Classes\ApiHelper;
use App\Helpers\Classes\Helper;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class AnthropicService
{
    private const RETIRED_MODEL_REPLACEMENTS = [
        'claude-3-haiku-20240307' => 'claude-haiku-4-5-20251001',
        'claude-3-haiku-20241022' => 'claude-haiku-4-5-20251001',
        'claude-3-5-haiku-20241022' => 'claude-haiku-4-5-20251001',
        'claude-3-7-sonnet-20250219' => 'claude-sonnet-5-5',
        'claude-3-5-sonnet-20241022' => 'claude-sonnet-5-5',
        'claude-3-5-sonnet-20240620' => 'claude-sonnet-5-5',
        'claude-3-sonnet-20240229' => 'claude-sonnet-5-5',
        'claude-sonnet-4-20250514' => 'claude-sonnet-5-5',
        'claude-3-opus-20240229' => 'claude-opus-4-8',
        'claude-opus-4-1-20250805' => 'claude-opus-4-8',
        'claude-opus-4-20250514' => 'claude-opus-4-8',
        'claude-2.1' => 'claude-opus-4-8',
        'claude-2.0' => 'claude-opus-4-8',
    ];

    public ?string $key = null;

    public ?string $model = null;

    public bool $stream = false;

    public array $messages = [];

    public ?string $system = null;

    public array $tools = [];

    public ?array $toolChoice = null;

    public const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    public function stream(): PromiseInterface|Response
    {
        $client = $this->client();

        $system = (bool) $this->system;

        $hasTools = ! empty($this->tools);
        $requestBudget = app(\App\Services\Ai\ProviderRequestBudgetService::class);
        $hasRequestBudget = (bool) request()?->attributes->has('garad_provider_request_max');
        $configuredOutput = (int) setting('anthropic_max_output_length', 4096);

        $body = Helper::arrayMerge($system, [
            'model'      => $this->modelForRequest(),
            'max_tokens' => $hasRequestBudget
                ? \App\Services\Ai\ProviderRequestBudgetService::MAX_OUTPUT_TOKENS
                : max(4096, $configuredOutput),
            'messages'   => $this->messages,
            'stream'     => $this->isStream(),
        ], [
            'system' => $this->system,
        ]);

        if ($hasTools) {
            $body['tools'] = $this->tools;
        }

        if ($this->toolChoice !== null) {
            $body['tool_choice'] = $this->toolChoice;
        }

        if (isset($body['temperature'], $body['top_p'])) {
            throw new \LogicException('Anthropic requests cannot include both temperature and top_p.');
        }

        $body = $requestBudget->preparePayload($body);
        $requestBudget->recordProviderRequest();

        if ($this->isStream()) {
            $client = $client
                ->connectTimeout(15)
                ->timeout(300)
                ->withOptions(['stream' => true]);
        }

        $response = $client->post(self::ENDPOINT, $body);

        // Some Claude models reject a forced tool choice. The user's selected model is kept;
        // only the tool-choice mode is downgraded to auto (the request already exposes only the relevant tool).
        if ($response instanceof Response
            && $response->status() === 400
            && isset($body['tool_choice'])
            && ($body['tool_choice']['type'] ?? null) !== 'auto'
            && str_contains(strtolower((string) $response->json('error.message')), 'tool_choice')) {
            \Illuminate\Support\Facades\Log::warning('Anthropic model rejected forced tool_choice; retrying once with auto', [
                'model' => $body['model'] ?? null,
            ]);
            $body['tool_choice'] = ['type' => 'auto'];
            $response = $client->post(self::ENDPOINT, $body);
        }

        return $response;
    }

    public function client(): PendingRequest
    {
        return Http::withHeaders([
            'x-api-key'         => $this->getKey(),
            'Accept'            => 'application/json',
            'Content-Type'      => 'application/json',
            'anthropic-version' => '2023-06-01',
        ]);
    }

    public function setKey(string $key): self
    {
        $this->key = $key;

        return $this;
    }

    public function setModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function getKey(): ?string
    {
        return $this->key ?? ApiHelper::setAnthropicKey();
    }

    private function modelForRequest(): ?string
    {
        $model = $this->model ?? setting('anthropic_default_model');
        if (! is_string($model)) {
            return $this->model;
        }

        return self::RETIRED_MODEL_REPLACEMENTS[$model] ?? $model;
    }

    public function isStream(): bool
    {
        return $this->stream;
    }

    public function setStream(bool $stream): self
    {
        $this->stream = $stream;

        return $this;
    }

    public function getMessages(): array
    {
        return $this->messages;
    }

    public function setMessages(array $messages): self
    {
        $this->messages = $messages;

        return $this;
    }

    public function setSystem(?string $system): self
    {
        $this->system = $system;

        return $this;
    }

    public function setTools(array $tools): self
    {
        $this->tools = $tools;

        return $this;
    }

    public function setToolChoice(?array $toolChoice): self
    {
        $this->toolChoice = $toolChoice;

        return $this;
    }

    public static function refusalFallback(?string $stopReason, string $responseText): ?string
    {
        if ($stopReason !== 'refusal' || trim($responseText) !== '') {
            return null;
        }

        return __('Claude declined to respond to this request.');
    }
}
