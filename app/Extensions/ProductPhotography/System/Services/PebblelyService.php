<?php

namespace App\Extensions\ProductPhotography\System\Services;

use App\Extensions\ProductPhotography\System\Models\UserPebblely;
use App\Models\Setting;
use App\Models\SettingTwo;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\Eloquent\Builder;

class PebblelyService
{
    use PebblelyTrait;

    public const BASE_URL = 'https://api.pebblely.com/';

    public const REMOVE_BG_URL = 'remove-background/v1';

    public const UPSCALE_URL = 'upscale/v1';

    public const CREATE_BACKGROUND_URL = 'create-background/v2';

    public const THEMES_URL = 'themes/v1';

    private ?string $secretKey;

    private Client $client;

    private $storage;

    public function __construct()
    {
        $this->secretKey = Setting::getCache()->pebblely_key;
        $this->client = new Client;
        $this->storage = SettingTwo::getCache()->ai_image_storage;
    }

    private function get($url)
    {
        $this->apiError();

        try {
            $response = $this->client->get(self::BASE_URL . $url, [
                'headers' => $this->getHeaders(),
            ])->getBody()->getContents();

            return json_decode($response, true);
        } catch (GuzzleException $e) {
            return [
                'error'   => 'API request failed',
                'message' => __('insufficient credit'),
            ];
        }
    }

    private function post($url, $params)
    {
        $this->apiError();

        try {
            $response = $this->client->post(self::BASE_URL . $url, [
                'headers' => $this->getHeaders(),
                'json'    => $params,
            ])->getBody()->getContents();

            return json_decode($response, true);
        } catch (GuzzleException $e) {
            // Get the response body if available, otherwise set a default message
            $errorMessage = $e->getResponse()
                ? $e->getResponse()->getBody()->getContents()
                : 'No response body available';
            $decodedMessage = json_decode($errorMessage, true);
            $detailMessage = is_array($decodedMessage) && isset($decodedMessage['detail'])
                ? $decodedMessage['detail']
                : 'An unexpected error occurred';

            return [
                'error'   => 'An unexpected error occurred',
                'message' => $detailMessage,
            ];
        }
    }

    private function getHeaders(): array
    {
        return [
            'X-Pebblely-Access-Token' => $this->secretKey,
            'content-type'            => 'application/json',
        ];
    }

    private function apiError()
    {
        if (blank($this->secretKey)) {
            return [
                'error'   => 'API key is missing',
                'message' => __('insufficient credit'),
            ];
        }
    }

    public function query(): Builder
    {
        return UserPebblely::query();
    }
}
