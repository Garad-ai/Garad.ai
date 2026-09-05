<?php

namespace App\Extensions\SocialMedia\System\Helpers\Contracts;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

abstract class BaseMetaHelper
{
    public function getAccessToken(string $code): Response
    {
        $redirect_uri = $this->apiUrl('/oauth/access_token', [
            'code'          => $code,
            'client_id'     => $this->config['app_id'],
            'client_secret' => $this->config['app_secret'],
            'redirect_uri'  => $this->config['redirect_uri'],
        ]);

        return Http::post($redirect_uri);
    }

    protected function apiUrl(string $endpoint, array $params = [], bool $isBaseUrl = false): string
    {
        $apiUrl = $isBaseUrl ? $this->config['base_url'] : $this->config['api_url'];

        if (str_starts_with($endpoint, '/')) {
            $endpoint = substr($endpoint, 1);
        }

        $v = $this->config['api_version'] ?? '';
        $versionedUrlWithEndpoint = $apiUrl . '/' . ($v ? ($v . '/') : '') . $endpoint;

        if (count($params)) {
            $versionedUrlWithEndpoint .= '?' . http_build_query($params);
        }

        return $versionedUrlWithEndpoint;
    }

    public function setToken(string $bearerToken): self
    {
        $this->accessToken = $bearerToken;

        return $this;
    }
}
