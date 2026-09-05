<?php

namespace App\Extensions\AiAvatar\System\Services;

use App\Extensions\AiAvatar\System\Services\Traits\AiAvatar;
use App\Models\Setting;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class AiAvatarService
{
    use AiAvatar;

    public const BASE_URL = 'https://api.synthesia.io/v2';

    public ?string $secretKey;

    private $client;

    public function __construct()
    {
        $this->secretKey = Setting::getCache()->synthesia_secret_key;
        $this->client = new Client;
    }

    private function post($url, $params)
    {
        if (empty($this->secretKey)) {
            return [
                'type'    => 'error',
                'error'   => __('API key is missing'),
                'message' => __('Please add your Synthesia API key in the settings'),
                'result'  => [],
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
                'error'   => __('Request failed without a response'),
                'message' => __('No response context'),
            ];
        } catch (Exception $e) {
            return [
                'error'   => __('An unexpected error occurred'),
                'message' => $e->getMessage(),
            ];
        }
    }

    private function get($url)
    {
        if (empty($this->secretKey)) {
            return [
                'error'   => __('API key is missing'),
                'message' => __('Please add your Synthesia API key in the settings'),
                'result'  => [],
            ];
        }

        try {
            $response = $this->client->get(self::BASE_URL . $url, [
                'headers' => $this->getHeaders(),
            ])->getBody()->getContents();

            return json_decode($response, true)['videos'];
        } catch (Exception $e) {
            return [
                'error'  => 'API request failed: ' . $e->getMessage(),
                'videos' => [],
            ];
        }
    }

    private function delete($url)
    {
        if (empty($this->secretKey)) {
            return [
                'error'   => __('API key is missing'),
                'message' => __('Please add your Synthesia API key in the settings'),
                'result'  => [],
            ];
        }

        return $this->client->delete(self::BASE_URL . $url, [
            'headers' => $this->getHeaders(),
        ]);
    }

    private function getHeaders(): array
    {
        return [
            'Authorization' => $this->secretKey,
            'accept'        => 'application/json',
            'content-type'  => 'application/json',
        ];
    }
}
