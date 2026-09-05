<?php

namespace App\Extensions\SocialMedia\System\Services\Publisher;

use App\Extensions\SocialMedia\System\Enums\PlatformEnum;
use App\Extensions\SocialMedia\System\Helpers\Youtube;
use App\Extensions\SocialMedia\System\Services\Publisher\Contracts\BasePublisherService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class YoutubeService extends BasePublisherService
{
    public function handle(): Response|string
    {
        $videoPath = $this->resolveVideoPath($this->post->video);

        if (! $videoPath || ! file_exists($videoPath)) {
            return 'Video file not found.';
        }

        $platformEnum = $this->post->social_media_platform
            ?? PlatformEnum::tryFrom($this->platform->platform)
            ?? PlatformEnum::youtube;

        $api = (new Youtube($platformEnum))->setAccessToken($this->accessToken);

        $metadata = $this->buildMetadata($platformEnum, $api->configs());

        $mime = mime_content_type($videoPath) ?: 'video/*';
        $size = @filesize($videoPath) ?: null;

        $uploadUrl = rtrim(data_get($api->configs(), 'upload_url', 'https://www.googleapis.com/upload/youtube/v3'), '/')
            . '/videos?uploadType=resumable&part=snippet,status';

        $initResponse = Http::withToken($this->accessToken)
            ->withHeaders(array_filter([
                'Content-Type'            => 'application/json; charset=UTF-8',
                'X-Upload-Content-Type'   => $mime,
                'X-Upload-Content-Length' => $size,
            ]))
            ->post($uploadUrl, $metadata);

        if (! $initResponse->successful()) {
            return $initResponse;
        }

        $uploadLocation = $initResponse->header('Location');

        if (! $uploadLocation) {
            return $initResponse;
        }

        $videoContent = file_get_contents($videoPath);

        if ($videoContent === false) {
            return 'Unable to read the video file.';
        }

        return Http::withToken($this->accessToken)
            ->withHeaders(array_filter([
                'Content-Length' => $size,
                'Content-Type'   => $mime,
            ]))
            ->withBody($videoContent, $mime)
            ->put($uploadLocation);
    }

    private function buildMetadata(PlatformEnum $platform, array $config): array
    {
        $isShorts = $platform === PlatformEnum::youtube_shorts;

        $content = trim(strip_tags((string) $this->post->content));

        $titleLimit = $isShorts ? 80 : 100;

        // Build title with proper fallback
        if (empty($content)) {
            $title = $isShorts ? 'New Short' : 'New video';
        } else {
            $title = Str::of($content)->limit($titleLimit)->toString();

            // If limit resulted in empty string, use fallback
            if (empty(trim($title))) {
                $title = $isShorts ? 'New Short' : 'New video';
            }
        }

        $description = Str::of($content)->limit(4500)->toString();

        $hashtags = collect($this->post->hashtags ?? [])
            ->map(fn ($tag) => ltrim((string) $tag, '#'))
            ->filter()
            ->values()
            ->all();

        // For YouTube Shorts, ensure #Shorts is in title, description, and tags
        if ($isShorts) {
            if (! in_array('Shorts', $hashtags, true)) {
                array_unshift($hashtags, 'Shorts');
            }

            // Add #Shorts to title if not present
            if (! str_contains($title, '#Shorts')) {
                $shortsTag = ' #Shorts';
                $title = Str::of($title)->limit($titleLimit - strlen($shortsTag), '')->toString() . $shortsTag;
            }

            // Add #Shorts to description if not present
            if (! str_contains($description, '#Shorts')) {
                $description = trim($description . "\n\n#Shorts");
            }
        }

        // Don't use array_filter on snippet as it might remove the title if it evaluates to false
        $snippet = [
            'title'       => $title,
            'description' => $description,
            'categoryId'  => data_get($config, 'options.category_id', '22'),
        ];

        // Add tags only if not empty
        if (! empty($hashtags)) {
            $snippet['tags'] = $hashtags;
        }

        $status = [
            'privacyStatus'           => data_get($config, 'options.default_privacy_status', 'unlisted'),
            'selfDeclaredMadeForKids' => false,
            'notifySubscribers'       => (bool) data_get($config, 'options.notify_subscribers', false),
        ];

        return [
            'snippet' => $snippet,
            'status'  => $status,
        ];
    }

    private function resolveVideoPath(?string $video): ?string
    {
        if (! $video) {
            return null;
        }

        $relative = ltrim($video, '/');

        $publicPath = public_path($relative);

        if (file_exists($publicPath)) {
            return $publicPath;
        }

        if (str_starts_with($relative, 'uploads/')) {
            $storagePath = storage_path('app/public/' . substr($relative, strlen('uploads/')));

            if (file_exists($storagePath)) {
                return $storagePath;
            }
        }

        return null;
    }
}
