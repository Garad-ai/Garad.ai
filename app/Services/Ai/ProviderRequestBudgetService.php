<?php

declare(strict_types=1);

namespace App\Services\Ai;

use Illuminate\Http\Request;
use RuntimeException;

final class ProviderRequestBudgetService
{
    public const MAX_INPUT_TOKENS = 20000;

    public const MAX_OUTPUT_TOKENS = 3000;

    public const MAX_PROVIDER_REQUESTS = 3;

    public const TOKENIZER_OVERHEAD_RESERVE = 1000;

    public const MAX_SERIALIZED_INPUT_BYTES = self::MAX_INPUT_TOKENS - self::TOKENIZER_OVERHEAD_RESERVE;

    private const MAX_RETAINED_HISTORY_MESSAGES = 8;

    public function initializeChatPro(Request $request): void
    {
        $request->attributes->set('garad_provider_request_count', 0);
        $request->attributes->set('garad_provider_request_max', self::MAX_PROVIDER_REQUESTS);
    }

    public function endScope(Request $request): void
    {
        $request->attributes->remove('garad_provider_request_count');
        $request->attributes->remove('garad_provider_request_max');
    }

    /**
     * Fit conversation history to the selected model's real context window.
     * Only older history is dropped; system messages and the newest messages are never removed
     * and this method never throws.
     */
    public function fitHistoryToContextWindow(array $history, string $model): array
    {
        $limit = (int) (self::contextWindowTokens($model) * 0.7);
        $estimate = static fn (array $messages): int => (int) ceil(strlen((string) json_encode($messages, JSON_UNESCAPED_UNICODE)) / 3);
        $history = array_values($history);

        while ($estimate($history) > $limit) {
            $removed = false;
            $lastIndex = count($history) - 1;
            foreach ($history as $index => $message) {
                if (($message['role'] ?? null) === 'system' || $index >= $lastIndex) {
                    continue;
                }
                unset($history[$index]);
                $history = array_values($history);
                $removed = true;
                break;
            }
            if (! $removed) {
                break;
            }
        }

        return $history;
    }

    public static function contextWindowTokens(string $model): int
    {
        $model = strtolower($model);

        return match (true) {
            str_contains($model, 'gpt-5'), str_contains($model, 'gemini'), str_contains($model, 'gpt-4.1') => 400000,
            str_contains($model, 'claude'), str_contains($model, 'o3'), str_contains($model, 'o4') => 200000,
            default => 64000,
        };
    }

    public function capConversationHistory(array $history): array
    {
        $systemMessages = [];
        $conversationMessages = [];
        foreach ($history as $message) {
            if (($message['role'] ?? null) === 'system') {
                $systemMessages[] = $message;
            } else {
                $conversationMessages[] = $message;
            }
        }

        $history = array_merge(
            $systemMessages,
            array_slice($conversationMessages, -self::MAX_RETAINED_HISTORY_MESSAGES),
        );

        return $this->fitPayload(['messages' => array_values($history)])['messages'];
    }

    public function preparePayload(array $payload): array
    {
        if (! request()?->attributes->has('garad_provider_request_max')) {
            return $payload;
        }

        foreach (['max_tokens', 'max_output_tokens'] as $outputLimit) {
            if (array_key_exists($outputLimit, $payload)) {
                $payload[$outputLimit] = min(
                    self::MAX_OUTPUT_TOKENS,
                    max(1, (int) $payload[$outputLimit]),
                );
            }
        }
        if (isset($payload['generationConfig']) && is_array($payload['generationConfig'])) {
            $payload['generationConfig']['maxOutputTokens'] = min(
                self::MAX_OUTPUT_TOKENS,
                max(1, (int) ($payload['generationConfig']['maxOutputTokens'] ?? self::MAX_OUTPUT_TOKENS)),
            );
        }

        return $this->fitPayload($payload);
    }

    public function recordProviderRequest(): void
    {
        $request = request();
        if (! $request || ! $request->attributes->has('garad_provider_request_max')) {
            return;
        }

        $requestCount = (int) $request->attributes->get('garad_provider_request_count', 0);
        $maximum = min(self::MAX_PROVIDER_REQUESTS, (int) $request->attributes->get('garad_provider_request_max'));
        if ($requestCount >= $maximum) {
            throw new RuntimeException('The document-generation request reached its three-provider-request limit. No further provider request was sent.');
        }

        $request->attributes->set('garad_provider_request_count', $requestCount + 1);
    }

    private function fitPayload(array $payload): array
    {
        $this->assertInlineMultimodalPayloads($payload);

        while ($this->estimatedInputTokens($payload) > self::MAX_INPUT_TOKENS) {
            $trimmed = false;
            foreach (['messages', 'input', 'contents'] as $historyKey) {
                if (! isset($payload[$historyKey]) || ! is_array($payload[$historyKey])) {
                    continue;
                }

                $messages = array_values($payload[$historyKey]);
                $lastUserIndex = null;
                foreach ($messages as $index => $message) {
                    if (($message['role'] ?? null) === 'user') {
                        $lastUserIndex = $index;
                    }
                }

                foreach ($messages as $index => $message) {
                    if (($message['role'] ?? null) === 'system'
                        || $index === $lastUserIndex
                        || $index >= count($messages) - 2) {
                        continue;
                    }

                    unset($messages[$index]);
                    $payload[$historyKey] = array_values($messages);
                    $trimmed = true;
                    break 2;
                }
            }

            if (! $trimmed) {
                // Nothing left to trim: the provider enforces its own real context window.
                break;
            }
        }

        return $payload;
    }

    private function assertInlineMultimodalPayloads(array $value, ?string $parentKey = null): void
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $this->assertInlineMultimodalPayloads($item, (string) $key);

                continue;
            }

            if (! is_string($item)) {
                continue;
            }

            $isImageLocation = (string) $key === 'image_url'
                || ((string) $key === 'url' && $parentKey === 'source')
                || ((string) $key === 'fileUri' && $parentKey === 'fileData');
            if ($isImageLocation && preg_match('/^https?:\/\//iu', $item)) {
                throw new RuntimeException('An externally hosted image cannot be safely counted against the document-generation input-token budget. Attach the image directly or provide its extracted text.');
            }
        }
    }

    private function serializedBytes(array $payload): int
    {
        return strlen(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function estimatedInputTokens(array $payload): int
    {
        $imageTokenReserve = 0;
        $textualPayload = $this->replaceInlineImagesWithMarkers($payload, $imageTokenReserve);

        return $this->serializedBytes($textualPayload)
            + self::TOKENIZER_OVERHEAD_RESERVE
            + $imageTokenReserve;
    }

    private function replaceInlineImagesWithMarkers(array $value, int &$imageTokenReserve): array
    {
        if (isset($value['image_url'])) {
            if (is_string($value['image_url']) && str_starts_with($value['image_url'], 'data:image/')) {
                $imageTokenReserve += $this->validateInlineImage($value['image_url']);
                $value['image_url'] = 'data:image/inline-budgeted';
            } elseif (is_array($value['image_url'])
                && isset($value['image_url']['url'])
                && is_string($value['image_url']['url'])
                && str_starts_with($value['image_url']['url'], 'data:image/')) {
                $imageTokenReserve += $this->validateInlineImage($value['image_url']['url']);
                $value['image_url']['url'] = 'data:image/inline-budgeted';
            }
        }

        foreach (['source', 'inlineData'] as $imageContainer) {
            if (! isset($value[$imageContainer]) || ! is_array($value[$imageContainer])) {
                continue;
            }

            $imageData = $value[$imageContainer]['data'] ?? null;
            $isImage = $imageContainer === 'source'
                ? ($value[$imageContainer]['type'] ?? null) === 'base64'
                : str_starts_with((string) ($value[$imageContainer]['mimeType'] ?? ''), 'image/');
            if (is_string($imageData) && $isImage) {
                $imageTokenReserve += $this->validateInlineImage($imageData, $imageContainer === 'source');
                $value[$imageContainer]['data'] = '[inline image budgeted]';
            }
        }

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->replaceInlineImagesWithMarkers($item, $imageTokenReserve);
            }
        }

        return $value;
    }

    private function validateInlineImage(string $imageData, bool $rawBase64 = false): int
    {
        $base64 = $rawBase64 ? $imageData : (string) substr(strstr($imageData, ','), 1);
        $decoded = base64_decode($base64, true);
        if ($decoded === false || ! function_exists('getimagesizefromstring')) {
            throw new RuntimeException('An inline image could not be validated for the document-generation input-token budget.');
        }

        $dimensions = getimagesizefromstring($decoded);
        if ($dimensions === false || $dimensions[0] > 4096 || $dimensions[1] > 4096) {
            throw new RuntimeException('An inline image exceeds the dimensions that can be safely budgeted for document generation.');
        }

        $estimatedTiles = (int) ceil($dimensions[0] / 512) * (int) ceil($dimensions[1] / 512);

        return max(5000, $estimatedTiles * 200);
    }
}
