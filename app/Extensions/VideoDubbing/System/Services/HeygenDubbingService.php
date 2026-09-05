<?php

declare(strict_types=1);

namespace App\Extensions\VideoDubbing\System\Services;

use App\Models\Setting;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request as PsrRequest;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class HeygenDubbingService
{
    public const BASE_URL = 'https://api.heygen.com';

    private ?string $secretKey;

    private Client $client;

    public function __construct()
    {
        $this->secretKey = Setting::getCache()?->heygen_secret_key;

        $stack = HandlerStack::create();
        $stack->push(Middleware::retry(
            function (int $retries, PsrRequest $request, ?ResponseInterface $response = null, ?Throwable $exception = null): bool {
                if ($retries >= 8) {
                    return false;
                }

                return $exception instanceof ConnectException;
            },
            fn (int $retries): int => 250 * $retries,
        ));

        $this->client = new Client([
            'handler'         => $stack,
            'connect_timeout' => 20,
            'timeout'         => 180,
            'curl'            => [
                CURLOPT_IPRESOLVE             => CURL_IPRESOLVE_V4,
                CURLOPT_DNS_SHUFFLE_ADDRESSES => true,
                CURLOPT_FORBID_REUSE          => true,
                CURLOPT_FRESH_CONNECT         => true,
            ],
        ]);
    }

    /**
     * Create a video translation job (v3).
     *
     * @param  array{
     *     video: array{type: string, url?: string, asset_id?: string},
     *     output_languages: array<int, string>,
     *     mode?: string,
     *     title?: string,
     *     speaker_num?: int,
     *     translate_audio_only?: bool,
     *     enable_dynamic_duration?: bool,
     *     callback_url?: string,
     *     callback_id?: string,
     * }  $params
     */
    public function createTranslation(array $params): array
    {
        return $this->post('/v3/video-translations', $params);
    }

    /**
     * Check the status of a video translation job (v3).
     */
    public function getTranslationStatus(string $videoTranslateId): array
    {
        return $this->get("/v3/video-translations/{$videoTranslateId}");
    }

    /**
     * Get the list of supported target languages (v3).
     */
    public function getTargetLanguages(): array
    {
        return $this->get('/v3/video-translations/languages');
    }

    private function post(string $url, array $params): array
    {
        if (blank($this->secretKey)) {
            return ['error' => __('The video dubbing service is not configured yet. Please contact the administrator.')];
        }

        try {
            $response = $this->client->post(self::BASE_URL . $url, [
                'headers' => $this->getHeaders(),
                'body'    => json_encode($params),
            ])->getBody()->getContents();

            return json_decode($response, true);
        } catch (RequestException $e) {
            $body = $e->hasResponse() ? (string) $e->getResponse()->getBody() : null;
            Log::error('Heygen POST RequestException', [
                'url'      => $url,
                'params'   => $params,
                'status'   => $e->hasResponse() ? $e->getResponse()->getStatusCode() : null,
                'response' => $body,
                'message'  => $e->getMessage(),
            ]);

            return [
                'error' => __('Something went wrong while processing your video. Please try again later.'),
            ];
        } catch (Exception $e) {
            Log::error('Heygen POST Exception', [
                'url'     => $url,
                'params'  => $params,
                'message' => $e->getMessage(),
                'class'   => get_class($e),
            ]);

            return [
                'error' => __('An unexpected error occurred. Please try again later.'),
            ];
        }
    }

    private function get(string $url): array
    {
        if (blank($this->secretKey)) {
            return ['error' => __('The video dubbing service is not configured yet. Please contact the administrator.')];
        }

        try {
            $response = $this->client->get(self::BASE_URL . $url, [
                'headers' => $this->getHeaders(),
            ])->getBody()->getContents();

            return json_decode($response, true);
        } catch (RequestException $e) {
            $body = $e->hasResponse() ? (string) $e->getResponse()->getBody() : null;
            Log::error('Heygen GET RequestException', [
                'url'      => $url,
                'status'   => $e->hasResponse() ? $e->getResponse()->getStatusCode() : null,
                'response' => $body,
                'message'  => $e->getMessage(),
            ]);

            return [
                'error' => __('Could not check the video status. Please try again later.'),
            ];
        } catch (Exception $e) {
            Log::error('Heygen GET Exception', [
                'url'     => $url,
                'message' => $e->getMessage(),
                'class'   => get_class($e),
            ]);

            return [
                'error' => __('Could not check the video status. Please try again later.'),
            ];
        }
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
