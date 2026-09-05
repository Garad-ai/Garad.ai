<?php

namespace App\Extensions\SocialMedia\System\Helpers;

use Abraham\TwitterOAuth\TwitterOAuth;
use Illuminate\Foundation\Application;
use Illuminate\Http\Client\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class X
{
    protected array $config = [];

    public function __construct(?array $config = null, protected ?string $accessToken = null)
    {
        $this->config = $config ?? config('social-media.x');

        $this->config = array_merge($this->config, [
            'app_id'              => setting('X_CLIENT_ID'),
            'client_secret'       => setting('X_CLIENT_SECRET'),
            'consumer_api_key'    => setting('X_API_KEY'),
            'consumer_api_secret' => setting('X_API_SECRET'),
            'access_token'        => setting('X_ACCESS_TOKEN'),
            'access_token_secret' => setting('X_ACCESS_TOKEN_SECRET'),
        ]);

        $this->config['redirect_uri'] = url(config('social-media.x.redirect_uri'));
    }

    private function apiUrl(string $endpoint, array $params = [], bool $isBaseUrl = false): string
    {
        $baseOrApiUrl = $isBaseUrl ? $this->config['base_url'] : $this->config['api_url'];

        if (str_starts_with($endpoint, '/')) {
            $endpoint = substr($endpoint, 1);
        }

        $v = $this->config['api_version'];
        $versionedUrlWithEndpoint = $baseOrApiUrl . '/' . ($v ? ($v . '/') : '') . $endpoint;

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

    public function authRedirect(): Application|Redirector|\Illuminate\Contracts\Foundation\Application|RedirectResponse
    {
        $client_id = $this->config['app_id'];
        $redirect_uri = $this->config['redirect_uri'];
        $scope = 'tweet.read tweet.write users.read offline.access dm.write dm.read';
        $codeChallenge = 'challenge';
        $state = 'state';
        $authorizationUri = "https://twitter.com/i/oauth2/authorize?response_type=code&client_id=$client_id&redirect_uri=$redirect_uri&scope=$scope&state=$state&code_challenge=$codeChallenge&code_challenge_method=plain";

        return redirect($authorizationUri);
    }

    public function getAccessToken($code): Response
    {
        $apiUrl = $this->apiUrl('oauth2/token', [
            'code'          => $code,
            'grant_type'    => 'authorization_code',
            'client_id'     => $this->config['app_id'],
            'redirect_uri'  => $this->config['redirect_uri'],
            'code_verifier' => 'challenge',
        ]);

        $basicAuthCredential = base64_encode($this->config['app_id'] . ':' . $this->config['client_secret']);

        return Http::withHeaders([
            'Authorization' => "Basic $basicAuthCredential",
            'Content-Type'  => 'application/x-www-form-urlencoded;charset=UTF-8',
        ])->post($apiUrl);
    }

    public function refreshAccessToken($refresh_token = null): Response
    {
        $apiUrl = $this->apiUrl('oauth2/token', [
            'refresh_token'          => $refresh_token,
            'grant_type'             => 'refresh_token',
            'client_id'              => $this->config['app_id'],
            'redirect_uri'           => $this->config['redirect_uri'],
        ]);

        $basicAuthCredential = base64_encode($this->config['app_id'] . ':' . $this->config['client_secret']);

        return Http::withHeaders([
            'Authorization' => "Basic $basicAuthCredential",
            'Content-Type'  => 'application/x-www-form-urlencoded;charset=UTF-8',
        ])->post($apiUrl);
    }

    public function getUserInfo(array $fields = ['name', 'profile_image_url', 'username', 'public_metrics']): Response
    {
        $apiUrl = $this->apiUrl('users/me', [
            'user.fields' => collect($fields)->join(','),
        ]);

        return Http::withToken($this->accessToken)->get($apiUrl);
    }

    public function publishTweet(string $text): Response
    {

        $apiUrl = $this->apiUrl('tweets');

        return Http::withToken($this->accessToken)
            ->post($apiUrl, [
                'text' => $text,
            ]);
    }

    public function publishMediaPost(array $files, ?string $message = null, $mediaType = 'image'): array|object|string
    {
        $consumerKey = setting('X_API_KEY');
        $consumerSecret = setting('X_API_SECRET');
        $access_token = setting('X_ACCESS_TOKEN');
        $access_token_secret = setting('X_ACCESS_TOKEN_SECRET');

        $twitter = new TwitterOAuth($consumerKey, $consumerSecret, $access_token, $access_token_secret);
        $twitter->setApiVersion(1.1);
        $twitter->setTimeouts(15, 15);
        $twitter->setRetries(5, 2);
        $mediaIds = [];

        foreach ($files as $key => $filePath) {

            if ($mediaType === 'video' && $key == 1) {
                continue;
            }

            $fileLocalPath = public_path(str_replace('/', DIRECTORY_SEPARATOR, parse_url($filePath, PHP_URL_PATH)));

            throw_if(! file_exists($fileLocalPath));

            switch ($mediaType) {
                case 'image':
                    $media = $twitter->upload('media/upload', ['media' => $fileLocalPath]);

                    break;
                case 'video':
                    $mediaMimeType = File::mimeType($fileLocalPath);
                    $parameters = [
                        'media'          => $fileLocalPath,
                        'media_type'     => $mediaMimeType,
                        'media_category' => 'tweet_video',
                    ];
                    $media = $twitter->upload('media/upload', $parameters, ['chunkedUpload' => true]);

                    break;
            }

            if ($mediaId = $media?->media_id_string ?? null) {
                $mediaIds[] = $mediaId;
            } else {
                throw ValidationException::withMessages([
                    'token' => data_get($media, 'errors.0.message'),
                ]);
            }
        }

        $twitter->setApiVersion(2);
        $parameters = [
            'text'  => $message,
            'media' => ['media_ids' => $mediaIds],
        ];

        sleep(2);

        return $twitter->post('tweets', $parameters);
    }

    public function getUserTweets(string $userId, int $maxResults = 50): Response
    {
        $apiUrl = $this->apiUrl("users/{$userId}/tweets", [
            'max_results'  => min($maxResults, 100),
            'tweet.fields' => 'id,text,created_at,attachments',
            'expansions'   => 'attachments.media_keys',
            'media.fields' => 'type,url,preview_image_url',
        ]);

        return Http::withToken($this->accessToken)->get($apiUrl);
    }

    // analytics
    public function getPostAnalytics(string $tweetId): Response
    {
        $apiUrl = $this->apiUrl("tweets/{$tweetId}", [
            'tweet.fields' => 'public_metrics,organic_metrics,non_public_metrics',
        ]);

        return Http::withToken($this->accessToken)->post($apiUrl);
    }
}
