<?php

namespace App\Extensions\SocialMediaAgent\System\Services\Analysis;

use App\Domains\Entity\Enums\EntityEnum;
use App\Helpers\Classes\ApiHelper;
use App\Helpers\Classes\OpenAiParamHelper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class OpenAiAnalysisClient
{
    public function generateReport(
        array $messages,
        float $temperature = 0.4,
        int $maxTokens = 900
    ): array {
        try {
            $apiKey = ApiHelper::setOpenAiKey();

            $payload = OpenAiParamHelper::sanitizeChatParams([
                'model'       => config('services.openai.analysis_model', EntityEnum::GPT_5_MINI->value),
                'messages'    => $messages,
                'temperature' => $temperature,
                'max_tokens'  => $maxTokens,
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ])
                ->timeout(60)
                ->post('https://api.openai.com/v1/chat/completions', $payload);

            if ($response->failed()) {
                Log::warning('OpenAI analysis request failed', [
                    'payload' => [
                        'temperature' => $temperature,
                        'max_tokens'  => $maxTokens,
                    ],
                    'body'    => $response->body(),
                    'status'  => $response->status(),
                ]);

                return [
                    'success' => false,
                    'error'   => 'OpenAI returned a non-success status.',
                ];
            }

            $content = trim((string) data_get($response->json(), 'choices.0.message.content', ''));

            if ($content === '') {
                return [
                    'success' => false,
                    'error'   => 'Empty response from OpenAI.',
                ];
            }

            return [
                'success' => true,
                'content' => $content,
            ];
        } catch (Throwable $exception) {
            Log::error('OpenAI analysis request crashed', [
                'message' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'error'   => $exception->getMessage(),
            ];
        }
    }
}
