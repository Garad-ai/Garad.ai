<?php

namespace App\Extensions\AiPersona\System\Services;

use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity;
use App\Extensions\AiPersona\System\Services\Traits\AiPersona;
use App\Helpers\Classes\Helper;
use App\Models\Setting;
use App\Models\Usage;
use App\Services\Ai\AiCompletionService;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class AiPersonaService
{
    use AiPersona;

    public const BASE_URL = 'https://api.heygen.com';

    public const UPLOAD_URL = 'https://upload.heygen.com';

    public ?string $secretKey;

    private $client;

    public function __construct()
    {
        $this->secretKey = Setting::getCache()->heygen_secret_key;
        $this->client = new Client;
    }

    public function uploadAsset(string $binary, string $mimeType): array
    {
        if (blank($this->secretKey)) {
            return [
                'error'  => 'API key is missing',
                'result' => [],
            ];
        }

        try {
            $response = $this->client->post(self::UPLOAD_URL . '/v1/asset', [
                'headers' => [
                    'x-api-key'    => $this->secretKey,
                    'accept'       => 'application/json',
                    'content-type' => $mimeType,
                ],
                'body' => $binary,
            ])->getBody()->getContents();

            return json_decode($response, true) ?? [];
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $responseBody = $e->getResponse()?->getBody()->getContents();
                $responseJson = json_decode($responseBody, true);

                return [
                    'error'   => $responseJson['error'] ?? 'Unknown error',
                    'message' => $responseJson['message'] ?? 'No context provided',
                ];
            }

            return [
                'error'   => 'Upload failed without a response',
                'message' => 'No response context',
            ];
        } catch (Exception $e) {
            return [
                'error'   => 'An unexpected error occurred',
                'message' => $e->getMessage(),
            ];
        }
    }

    public static function enhanceScript(?string $script): string
    {
        $model = Setting::getCache()->openai_default_model;
        $driver = Entity::driver(EntityEnum::fromSlug($model) ?? EntityEnum::GPT_5_MINI);

        $driver->redirectIfNoCreditBalance();

        if (empty($script)) {
            $prompt = 'Write a short, engaging spoken-video script (roughly 15-20 seconds when read aloud) for an AI avatar presenter. The tone should be natural, clear, and conversational.';
        } else {
            $prompt = 'Rewrite the following script so it sounds natural and engaging when spoken by an AI avatar presenter. Keep the same intent and approximate length, improve flow, clarity, and pacing, and remove anything that would not read well aloud (no stage directions, no markdown, no bullet points):' . "\n\n\"" . $script . '"';
        }

        $prompt .= ' Return only the final script as plain text, ready to be spoken. Do not wrap the result in quotes or add any explanations.';

        $responseText = app(AiCompletionService::class)->completeUserOnly($prompt);
        $driver->input($responseText)->calculateCredit()->decreaseCredit();
        Usage::getSingle()->updateWordCounts($driver->calculate());

        return $responseText;
    }

    private function post($url, $params)
    {
        if (blank($this->secretKey)) {
            return [
                'error'  => 'API key is missing',
                'result' => [],
            ];
        }

        try {
            $response = $this->client->post(self::BASE_URL . $url, [
                'headers' => $this->getHeaders(),
                'body'    => json_encode($params),
            ])->getBody()->getContents();

            return json_decode($response, true);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $responseBody = $e->getResponse()?->getBody()->getContents();
                $responseJson = json_decode($responseBody, true);

                return [
                    'error'   => $responseJson['error'] ?? 'Unknown error',
                    'message' => $responseJson['context'] ?? 'No context provided',
                ];
            }

            return [
                'error'   => 'Request failed without a response',
                'message' => 'No response context',
            ];
        } catch (Exception $e) {
            return [
                'error'   => 'An unexpected error occurred',
                'message' => $e->getMessage(),
            ];
        }
    }

    private function get($url)
    {
        if (blank($this->secretKey)) {
            return [];
        }

        try {
            $response = $this->client->get(Helper::parseUrl(self::BASE_URL, $url), [
                'headers' => $this->getHeaders(),
            ])->getBody()->getContents();

            return json_decode($response, true);
        } catch (Exception $e) {
            return [
                'error'  => 'API request failed: ' . $e->getMessage(),
                'videos' => [],
            ];
        }
    }

    private function delete($url)
    {
        if (blank($this->secretKey)) {
            return [];
        }

        return $this->client->delete(self::BASE_URL . $url, [
            'headers' => $this->getHeaders(),
        ]);
    }

    private function getHeaders(): array
    {
        return [
            'x-api-key'    => $this->secretKey,
            'accept'       => 'application/json',
            'content-type' => 'application/json',
        ];
    }
}
