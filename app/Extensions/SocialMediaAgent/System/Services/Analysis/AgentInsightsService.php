<?php

namespace App\Extensions\SocialMediaAgent\System\Services\Analysis;

use App\Extensions\SocialMedia\System\Enums\PlatformEnum;
use App\Extensions\SocialMedia\System\Enums\StatusEnum;
use App\Extensions\SocialMedia\System\Models\SocialMediaPost;
use App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AgentInsightsService
{
    public function buildMetricsSnapshot(SocialMediaAgent $agent, int $lookbackDays = 14): array
    {
        $lookbackStart = Carbon::now()->subDays($lookbackDays * 2);

        $posts = SocialMediaPost::query()
            ->with('platform')
            ->where('agent_id', $agent->id)
            ->where('status', StatusEnum::published)
            ->whereNotNull('posted_at')
            ->where('posted_at', '>=', $lookbackStart)
            ->orderByDesc('posted_at')
            ->limit(300)
            ->get();

        $recentStart = Carbon::now()->subDays($lookbackDays);

        $recentPosts = $posts->filter(fn (SocialMediaPost $post) => $post->posted_at && $post->posted_at->greaterThanOrEqualTo($recentStart));
        $previousPosts = $posts->filter(fn (SocialMediaPost $post) => $post->posted_at && $post->posted_at->lessThan($recentStart));

        $recentRate = $this->average($recentPosts, 'post_engagement_rate');
        $previousRate = $this->average($previousPosts, 'post_engagement_rate');
        $recentCountAvg = $this->average($recentPosts, 'post_engagement_count');
        $previousCountAvg = $this->average($previousPosts, 'post_engagement_count');

        return [
            'data_available'         => $posts->isNotEmpty(),
            'lookback_days'          => $lookbackDays,
            'recent_post_count'      => $recentPosts->count(),
            'previous_post_count'    => $previousPosts->count(),
            'avg_engagement_rate'    => [
                'recent'   => $recentRate,
                'previous' => $previousRate,
            ],
            'avg_engagement_count'   => [
                'recent'   => $recentCountAvg,
                'previous' => $previousCountAvg,
            ],
            'trend_direction'        => $this->trendDirection($recentRate, $previousRate),
            'trend_delta'            => $this->trendDelta($recentRate, $previousRate),
            'platform_breakdown'     => $this->platformBreakdown($posts),
            'top_post'               => $this->summarizePost($this->topPost($posts)),
            'lowest_post'            => $this->summarizePost($this->bottomPost($posts)),
            'recent_posts'           => $recentPosts->take(5)->map(fn ($post) => $this->summarizePost($post))->filter()->values()->all(),
            'published_last_7_days'  => $posts->filter(fn ($post) => $post->posted_at && $post->posted_at->greaterThanOrEqualTo(Carbon::now()->subDays(7)))->count(),
            'total_posts_considered' => $posts->count(),
        ];
    }

    public function buildProfileSummary(SocialMediaAgent $agent): array
    {
        $platforms = $agent->platforms()->map(function ($platform) {
            $value = $platform->platform;

            return $value ? Str::headline(str_replace('_', ' ', $value)) : null;
        })->filter()->values()->all();

        $audiences = collect($agent->target_audience)
            ->pluck('name')
            ->filter()
            ->values()
            ->all();

        return [
            'agent'              => [
                'id'          => $agent->id,
                'name'        => $agent->name,
                'tone'        => $agent->tone,
                'language'    => $agent->language,
                'goals'       => $agent->goals ?? [],
                'categories'  => $agent->categories ?? [],
                'post_types'  => $agent->post_types ?? [],
                'cta_samples' => array_filter($agent->cta_templates ?? []),
            ],
            'brand_context'      => [
                'site_url'             => $agent->site_url,
                'site_description'     => $agent->site_description,
                'branding_description' => $agent->branding_description,
                'scraped_summary'      => data_get($agent->scraped_content, 'summary'),
            ],
            'audience'           => [
                'segments' => $audiences,
            ],
            'platforms'          => $platforms,
            'scheduling'         => [
                'days'  => $agent->schedule_days ?? [],
                'times' => $agent->schedule_times ?? [],
            ],
        ];
    }

    private function average(Collection $posts, string $attribute): ?float
    {
        $values = $posts
            ->map(fn (SocialMediaPost $post) => $post->{$attribute})
            ->filter(fn ($value) => $value !== null)
            ->map(fn ($value) => (float) $value);

        if ($values->isEmpty()) {
            return null;
        }

        return round($values->avg(), 2);
    }

    private function trendDirection(?float $recent, ?float $previous): string
    {
        if ($recent === null || $previous === null) {
            return 'insufficient_data';
        }

        $delta = $recent - $previous;

        if (abs($delta) < 0.2) {
            return 'flat';
        }

        return $delta > 0 ? 'up' : 'down';
    }

    private function trendDelta(?float $recent, ?float $previous): ?float
    {
        if ($recent === null || $previous === null) {
            return null;
        }

        return round($recent - $previous, 2);
    }

    private function platformBreakdown(Collection $posts): array
    {
        return $posts
            ->groupBy(fn (SocialMediaPost $post) => $this->platformValue($post))
            ->map(function (Collection $group, string $platform) {
                return [
                    'platform'              => $this->formatPlatform($platform),
                    'posts'                 => $group->count(),
                    'avg_engagement_rate'   => $this->average($group, 'post_engagement_rate'),
                    'avg_engagement_count'  => $this->average($group, 'post_engagement_count'),
                    'latest_posted_at'      => optional($group->sortByDesc('posted_at')->first()?->posted_at)?->toDateTimeString(),
                ];
            })
            ->values()
            ->all();
    }

    private function platformValue(SocialMediaPost $post): string
    {
        if ($post->social_media_platform instanceof PlatformEnum) {
            return $post->social_media_platform->value;
        }

        if (is_string($post->social_media_platform)) {
            return $post->social_media_platform;
        }

        if ($post->relationLoaded('platform') && $post->platform?->platform) {
            return (string) $post->platform->platform;
        }

        return 'unknown';
    }

    private function formatPlatform(string $platform): string
    {
        return $platform === 'unknown'
            ? 'Unknown'
            : Str::headline(str_replace('_', ' ', $platform));
    }

    private function summarizePost(?SocialMediaPost $post): ?array
    {
        if (! $post) {
            return null;
        }

        return [
            'id'                => $post->id,
            'platform'          => $this->formatPlatform($this->platformValue($post)),
            'engagement_rate'   => $post->post_engagement_rate !== null ? round((float) $post->post_engagement_rate, 2) : null,
            'engagement_count'  => $post->post_engagement_count !== null ? (int) $post->post_engagement_count : null,
            'posted_at'         => optional($post->posted_at)->toDateTimeString(),
            'content_preview'   => Str::limit(trim(strip_tags((string) $post->content)), 140),
            'link'              => $post->link,
        ];
    }

    private function topPost(Collection $posts): ?SocialMediaPost
    {
        return $posts->sortByDesc(fn (SocialMediaPost $post) => (float) ($post->post_engagement_rate ?? 0))->first();
    }

    private function bottomPost(Collection $posts): ?SocialMediaPost
    {
        return $posts->sortBy(fn (SocialMediaPost $post) => (float) ($post->post_engagement_rate ?? 0))->first();
    }
}
