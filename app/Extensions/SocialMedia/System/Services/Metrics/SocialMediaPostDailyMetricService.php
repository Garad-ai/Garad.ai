<?php

declare(strict_types=1);

namespace App\Extensions\SocialMedia\System\Services\Metrics;

use App\Extensions\SocialMedia\System\Enums\PlatformEnum;
use App\Extensions\SocialMedia\System\Helpers\Facebook;
use App\Extensions\SocialMedia\System\Helpers\Instagram;
use App\Extensions\SocialMedia\System\Helpers\Tiktok;
use App\Extensions\SocialMedia\System\Helpers\X;
use App\Extensions\SocialMedia\System\Models\SocialMediaPlatform;
use App\Extensions\SocialMedia\System\Models\SocialMediaPost;
use App\Extensions\SocialMedia\System\Models\SocialMediaPostDailyMetric;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class SocialMediaPostDailyMetricService
{
    private const METRIC_KEYS = [
        'like_count',
        'comment_count',
        'share_count',
        'view_count',
    ];

    public function recordForPlatform(SocialMediaPlatform $platform, ?Carbon $runDate = null): int
    {
        if (! $platform->isConnected()) {
            return 0;
        }

        $date = ($runDate ?? now())->toDateString();
        $processedPosts = 0;

        SocialMediaPost::query()
            ->select(['id', 'agent_id', 'social_media_platform_id', 'social_media_platform', 'post_id', 'post_metrics'])
            ->where('social_media_platform_id', $platform->id)
            ->whereNotNull('post_id')
            ->chunkById(100, function (Collection $posts) use ($platform, $date, &$processedPosts) {
                foreach ($posts as $post) {
                    $post->setRelation('platform', $platform);

                    try {
                        if ($this->processPost($post, $platform, $date)) {
                            $processedPosts++;
                        }
                    } catch (Throwable $exception) {
                        Log::warning('Failed to record daily post metric', [
                            'post_id'    => $post->id,
                            'remote_id'  => $post->post_id,
                            'platform'   => $platform->platform,
                            'message'    => $exception->getMessage(),
                        ]);
                    }
                }
            });

        return $processedPosts;
    }

    private function processPost(SocialMediaPost $post, SocialMediaPlatform $platform, string $date): bool
    {
        $metrics = $this->fetchRemoteMetrics($post, $platform);

        if (empty($metrics)) {
            return false;
        }

        $this->updatePostSnapshot($post, $metrics);
        $this->persistDailyMetric($post, $metrics, $date);

        return true;
    }

    private function fetchRemoteMetrics(SocialMediaPost $post, SocialMediaPlatform $platform): ?array
    {
        $platformEnum = $this->resolvePlatformEnum($post, $platform);

        if (! $platformEnum) {
            return null;
        }

        return match ($platformEnum) {
            PlatformEnum::facebook  => $this->fetchFacebookMetrics($post, $platform),
            PlatformEnum::instagram => $this->fetchInstagramMetrics($post, $platform),
            PlatformEnum::tiktok    => $this->fetchTiktokMetrics($post, $platform),
            PlatformEnum::x         => $this->fetchXMetrics($post, $platform),
            default                 => null,
        };
    }

    private function fetchFacebookMetrics(SocialMediaPost $post, SocialMediaPlatform $platform): ?array
    {
        $accessToken = data_get($platform->credentials, 'access_token');

        if (! $accessToken) {
            return null;
        }

        $facebook = new Facebook(accessToken: $accessToken);
        $response = $facebook->getPostAnalytics($post->post_id);

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return [
            'like_count'    => (int) data_get($data, 'likes.summary.total_count', 0),
            'comment_count' => (int) data_get($data, 'comments.summary.total_count', 0),
            'share_count'   => (int) data_get($data, 'shares.count', 0),
            'view_count'    => (int) data_get($data, 'views.summary.total_count', 0),
        ];
    }

    private function fetchInstagramMetrics(SocialMediaPost $post, SocialMediaPlatform $platform): ?array
    {
        $accessToken = data_get($platform->credentials, 'access_token');

        if (! $accessToken) {
            return null;
        }

        $instagram = new Instagram(accessToken: $accessToken);
        $response = $instagram->getPostAnalytics($post->post_id);

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return [
            'like_count'    => (int) data_get($data, 'like_count', 0),
            'comment_count' => (int) data_get($data, 'comments_count', 0),
            'share_count'   => (int) data_get($data, 'share_count', 0),
            'view_count'    => (int) (data_get($data, 'view_count', data_get($data, 'video_views', 0))),
        ];
    }

    private function fetchTiktokMetrics(SocialMediaPost $post, SocialMediaPlatform $platform): ?array
    {
        $accessToken = data_get($platform->credentials, 'access_token');

        if (! $accessToken) {
            return null;
        }

        $tiktok = new Tiktok(accessToken: $accessToken);
        $response = $tiktok->getPostAnalytics([$post->post_id]);

        if (! $response->successful()) {
            return null;
        }

        $video = data_get($response->json(), 'data.videos.0', []);
        $stats = is_array($video) ? ($video['statistics'] ?? $video) : [];

        return [
            'like_count'    => (int) data_get($stats, 'like_count', data_get($stats, 'digg_count', 0)),
            'comment_count' => (int) data_get($stats, 'comment_count', 0),
            'share_count'   => (int) data_get($stats, 'share_count', data_get($stats, 'forward_count', 0)),
            'view_count'    => (int) data_get($stats, 'view_count', data_get($stats, 'play_count', 0)),
        ];
    }

    private function fetchXMetrics(SocialMediaPost $post, SocialMediaPlatform $platform): ?array
    {
        $accessToken = data_get($platform->credentials, 'access_token');

        if (! $accessToken) {
            return null;
        }

        $x = new X(accessToken: $accessToken);
        $response = $x->getPostAnalytics($post->post_id);

        if (! $response->successful()) {
            return null;
        }

        $metrics = data_get($response->json(), 'data.public_metrics', [])
            ?: data_get($response->json(), 'data.organic_metrics', []);

        if (! is_array($metrics)) {
            return null;
        }

        return [
            'like_count'    => (int) data_get($metrics, 'like_count', data_get($metrics, 'favorite_count', 0)),
            'comment_count' => (int) data_get($metrics, 'reply_count', 0),
            'share_count'   => (int) data_get($metrics, 'retweet_count', 0),
            'view_count'    => (int) data_get($metrics, 'view_count', data_get($metrics, 'impression_count', 0)),
        ];
    }

    private function persistDailyMetric(SocialMediaPost $post, array $currentTotals, string $date): void
    {
        $dailyMetric = SocialMediaPostDailyMetric::query()->firstOrCreate(
            [
                'social_media_post_id' => $post->id,
                'date'                 => $date,
            ],
            [
                'agent_id'                 => $post->agent_id,
                'social_media_platform_id' => $post->social_media_platform_id,
                'platform'                 => $this->platformValue($post),
                'post_identifier'          => $post->post_id,
                'like_count'               => 0,
                'comment_count'            => 0,
                'share_count'              => 0,
                'view_count'               => 0,
                'last_totals'              => null,
            ]
        );

        $previousSnapshot = $dailyMetric->wasRecentlyCreated
            ? $this->resolvePreviousSnapshot($post->id, $date)
            : $dailyMetric->last_totals;

        if (! is_array($previousSnapshot) || empty($previousSnapshot)) {
            $previousSnapshot = $this->emptySnapshot();
        }

        $delta = $this->calculateDelta($currentTotals, $previousSnapshot);

        if ($this->hasPositiveDelta($delta)) {
            foreach (self::METRIC_KEYS as $key) {
                $dailyMetric->{$key} = ($dailyMetric->{$key} ?? 0) + $delta[$key];
            }
        }

        $dailyMetric->social_media_platform_id = $post->social_media_platform_id;
        $dailyMetric->agent_id = $post->agent_id;
        $dailyMetric->platform = $this->platformValue($post);
        $dailyMetric->post_identifier = $post->post_id;
        $dailyMetric->last_totals = $currentTotals;
        $dailyMetric->save();
    }

    private function updatePostSnapshot(SocialMediaPost $post, array $metrics): void
    {
        $post->forceFill([
            'post_metrics'   => $metrics,
            'post_metric_at' => now()->addHour(),
        ])->save();
    }

    private function resolvePreviousSnapshot(int $postId, string $date): array
    {
        $previous = SocialMediaPostDailyMetric::query()
            ->where('social_media_post_id', $postId)
            ->where('date', '<', $date)
            ->orderByDesc('date')
            ->value('last_totals');

        return is_array($previous) ? $previous : $this->emptySnapshot();
    }

    private function calculateDelta(array $current, array $previous): array
    {
        $delta = [];

        foreach (self::METRIC_KEYS as $key) {
            $currentValue = (int) ($current[$key] ?? 0);
            $previousValue = (int) ($previous[$key] ?? 0);
            $delta[$key] = max($currentValue - $previousValue, 0);
        }

        return $delta;
    }

    private function hasPositiveDelta(array $delta): bool
    {
        foreach ($delta as $value) {
            if ($value > 0) {
                return true;
            }
        }

        return false;
    }

    private function platformValue(SocialMediaPost $post): ?string
    {
        $platform = $post->social_media_platform;

        if ($platform instanceof PlatformEnum) {
            return $platform->value;
        }

        if (is_object($platform) && property_exists($platform, 'value')) {
            return $platform->value;
        }

        return is_string($platform) ? $platform : null;
    }

    private function resolvePlatformEnum(SocialMediaPost $post, SocialMediaPlatform $platform): ?PlatformEnum
    {
        if ($post->social_media_platform instanceof PlatformEnum) {
            return $post->social_media_platform;
        }

        if (is_string($post->social_media_platform)) {
            return PlatformEnum::tryFrom($post->social_media_platform);
        }

        if ($platform->platform) {
            return PlatformEnum::tryFrom($platform->platform);
        }

        return null;
    }

    private function emptySnapshot(): array
    {
        return array_fill_keys(self::METRIC_KEYS, 0);
    }
}
