<?php

namespace App\Extensions\SocialMedia\System\Helpers;

use Illuminate\Support\Facades\Http;
use stdClass;

class Tiktok
{
    protected array $config = [];

    public function __construct(?array $config = null, protected ?string $accessToken = null)
    {
        $this->config = $config ?? config('social-media.tiktok');

        $this->config = array_merge($this->config, [
            'app_id'       => setting('TIKTOK_APP_ID'),
            'app_key'      => setting('TIKTOK_APP_KEY'),
            'app_secret'   => setting('TIKTOK_APP_SECRET'),
        ]);

        $this->config['redirect_uri'] = secure_url($this->config['redirect_uri']);
    }

    private function apiUrl(string $endpoint, array $params = [], bool $isBaseUrl = false): string
    {
        $apiUrl = $isBaseUrl ? $this->config['base_url'] : $this->config['api_url'];

        if (str_starts_with($endpoint, '/')) {
            $endpoint = substr($endpoint, 1);
        }

        $v = $this->config['api_version'];
        $versionedUrlWithEndpoint = $apiUrl . '/' . ($v ? ($v . '/') : '') . $endpoint;

        if (count($params)) {
            $versionedUrlWithEndpoint .= '?' . http_build_query($params);
        }

        return $versionedUrlWithEndpoint;
    }

    public function setToken(string $bearerToken): static
    {
        $this->accessToken = $bearerToken;

        return $this;
    }

    public static function authRedirect()
    {
        $tiktok = new self;
        $client_key = $tiktok->config['app_key'];
        $scope = collect($tiktok->config['scope'])->join(',');
        $response_type = 'code';
        $state = '';
        $redirect_uri = $tiktok->config['redirect_uri'];

        $apiUri = "{$tiktok->config['base_url']}/{$tiktok->config['api_version']}/auth/authorize?client_key=$client_key&response_type=$response_type&scope=$scope&redirect_uri=$redirect_uri&state=$state";

        //        return $apiUri;

        return redirect($apiUri);
    }

    public function getAccessToken($code)
    {
        $apiUri = $this->apiUrl('oauth/token/');

        return Http::asForm()->post(
            $apiUri,
            [
                'client_key'    => $this->config['app_key'],
                'client_secret' => $this->config['app_secret'],
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'redirect_uri'  => $this->config['redirect_uri'],
            ]
        );
    }

    public function refreshAccessToken()
    {
        $apiUri = $this->apiUrl('oauth/token/');

        return Http::asForm()->post(
            $apiUri,
            [
                'client_key'    => $this->config['app_key'],
                'client_secret' => $this->config['app_secret'],
                'grant_type'    => 'refresh_token',
                'refresh_token' => $this->accessToken,
            ]
        );
    }

    public function getAccountInfo(?array $fields = null)
    {
        $apiUri = $this->apiUrl('user/info/', [
            'fields' => collect($fields)->join(','),
        ]);

        return Http::withToken($this->accessToken)->get($apiUri);
    }

    public function getCreatorInfo(): array
    {
        $headers = [
            'Content-Type: application/json; charset=UTF-8',
            "Authorization: Bearer {$this->accessToken}", // USER access token with video.publish
        ];

        $url = 'https://open.tiktokapis.com/v2/post/publish/creator_info/query/';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);

        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(new stdClass));

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        return json_decode($result, true);
    }

    public function postVideo(array $postData)
    {
        $headers = [
            'Content-Type' => 'application/json; charset=UTF-8',
        ];

        $initBody = [
            'source_info' => [
                'source'    => 'PULL_FROM_URL',
                'video_url' => $postData['source_info']['video_url'],
            ],
        ];

        $initHttp = Http::withToken($this->accessToken)
            ->withHeaders($headers)
            ->post('https://open.tiktokapis.com/v2/post/publish/video/init/', $initBody);

        $initJson = $initHttp->json();

        if (empty($initHttp->json('data.publish_id'))) {
            return $initHttp;
        }

        $publishId = $initHttp->json('data.publish_id');

        $submitBody = [
            'post_info' => [
                'title'                    => $postData['post_info']['title'] ?? 'Default Title',
                'privacy_level'            => $postData['post_info']['privacy_level'] ?? 'SELF_ONLY',
                'disable_duet'             => $postData['post_info']['disable_duet'] ?? false,
                'disable_comment'          => $postData['post_info']['disable_comment'] ?? false,
                'disable_stitch'           => $postData['post_info']['disable_stitch'] ?? false,
                'video_cover_timestamp_ms' => $postData['post_info']['video_cover_timestamp_ms'] ?? 1000,
                'post_mode'                => 'PUBLISH_NOW',
            ],
            'publish_id' => $publishId,
        ];

        return Http::withToken($this->accessToken)
            ->withHeaders($headers)
            ->post('https://open.tiktokapis.com/v2/post/publish/video/submit/', $submitBody);
    }

    public function postPhoto(array $postData)
    {
        return Http::withToken($this->accessToken)
            ->acceptJson()
            ->post($this->apiUrl('post/publish/content/init/'), $postData);
    }

    public function getPostAnalytics(array $videoIds, array $fields = [])
    {
        $apiUri = $this->apiUrl('video/query/', ['fields' => collect($fields)->join(',')]);

        return Http::withToken($this->accessToken)
            ->post($apiUri, [
                'filters' => [
                    'video_ids' => $videoIds,
                ],
            ]);
    }
}
