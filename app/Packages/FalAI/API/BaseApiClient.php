<?php

namespace App\Packages\FalAI\API;

use App\Packages\FalAI\Concerns\HasAuth;
use App\Packages\FalAI\Concerns\HasStatusResponse;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class BaseApiClient
{
    use HasAuth;
    use HasStatusResponse;

    /**
     * api base url
     */
    private string $apiBaseUrl;

    public function __construct(
        string $falKey,
        string $apiBaseUrl
    ) {
        $this->apiBaseUrl = $apiBaseUrl;
        $this->authFrom($falKey);
    }

    /**
     * get full api endpoint
     */
    public function getFullUrl(string $url): string
    {
        return rtrim($this->apiBaseUrl, '/') . '/' . ltrim($url, '/');
    }

    /**
     * general request
     */
    public function request(string $method, string $url, ?array $params = []): Response
    {
        if (strtolower($method) === 'post' && ! empty($params)) {
            $params = $this->inlineLocalFiles($params);
        }

        return Http::withHeaders($this->getHeaders())->{$method}($this->getFullUrl($url), $params);
    }

    /**
     * fal.ai cannot fetch files from a local/private server (localhost, 127.x, LAN),
     * so send those uploads inline as data URIs instead.
     */
    private function inlineLocalFiles(array $params): array
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        $appHost = parse_url($appUrl, PHP_URL_HOST) ?: '';
        $publicRoot = rtrim((string) public_path(), '/\\');

        $convert = function ($value) use (&$convert, $appUrl, $appHost, $publicRoot) {
            if (is_array($value)) {
                return array_map($convert, $value);
            }

            if (! is_string($value) || ! preg_match('#^https?://#i', $value)) {
                return $value;
            }

            $host = parse_url($value, PHP_URL_HOST) ?: '';
            $isPrivate = in_array($host, ['localhost', '::1'], true)
                || preg_match('/^(127\.|10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)/', $host)
                || ($appHost !== '' && ! str_contains($appHost, '.') && $host === $appHost);

            if (! $isPrivate) {
                return $value;
            }

            $path = (string) parse_url($value, PHP_URL_PATH);
            $base = (string) parse_url($appUrl, PHP_URL_PATH);
            if ($base !== '' && str_starts_with($path, $base)) {
                $path = substr($path, strlen($base));
            }

            $file = $publicRoot . str_replace('/', DIRECTORY_SEPARATOR, rawurldecode($path));
            if (! is_file($file)) {
                return $value;
            }

            $mime = function_exists('mime_content_type') ? (mime_content_type($file) ?: 'application/octet-stream') : 'application/octet-stream';

            return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($file));
        };

        return array_map($convert, $params);
    }

    /**
     * get headers
     */
    protected function getHeaders(?array $headers = []): array
    {
        $authHeaders = $this->authHeader();
        $defaultHeaders = [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];

        return array_merge($authHeaders, $defaultHeaders, $headers);
    }
}
