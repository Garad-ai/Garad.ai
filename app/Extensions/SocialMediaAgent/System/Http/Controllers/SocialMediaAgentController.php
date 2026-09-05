<?php

namespace App\Extensions\SocialMediaAgent\System\Http\Controllers;

use App\Extensions\SocialMedia\System\Enums\PlatformEnum;
use App\Extensions\SocialMedia\System\Enums\PostTypeEnum;
use App\Extensions\SocialMedia\System\Enums\StatusEnum;
use App\Extensions\SocialMedia\System\Models\SocialMediaPlatform;
use App\Extensions\SocialMedia\System\Models\SocialMediaPost;
use App\Extensions\SocialMedia\System\Models\SocialMediaPostDailyMetric;
use App\Extensions\SocialMedia\System\Services\SocialMediaFollowerService;
use App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgent;
use App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgentPost;
use App\Extensions\SocialMediaAgent\System\Services\ImageGenerationService;
use App\Extensions\SocialMediaAgent\System\Services\PostGenerationService;
use App\Extensions\SocialMediaAgent\System\Services\TargetAudienceService;
use App\Extensions\SocialMediaAgent\System\Services\VideoGenerationService;
use App\Extensions\SocialMediaAgent\System\Services\WebScrapingService;
use App\Extensions\SocialMediaAgent\System\Support\SocialMediaAgentGenerationCache;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use DateTime;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SocialMediaAgentController extends Controller
{
    private string $scrapedContentCachePrefix = 'social_media_agent:scrape:';

    private int $scrapedContentCacheTtlMinutes = 120;

    public function __invoke(): View
    {
        return $this->index();
    }

    /**
     * Dashboard page
     */
    public function index(): View
    {
        $userId = Auth::id();

        // Get all posts with status counts in one query
        $postsQuery = SocialMediaAgentPost::query()
            ->whereHas('agent', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            });

        // Get counts efficiently
        $pending_posts_count = (clone $postsQuery)
            ->where('status', SocialMediaAgentPost::STATUS_DRAFT)
            ->count();

        $scheduled_posts_count = (clone $postsQuery)
            ->where('status', SocialMediaAgentPost::STATUS_SCHEDULED)
            ->count();

        $total_posts_count = $postsQuery->count();

        // Get paginated posts
        $posts = SocialMediaAgentPost::query()
            ->whereHas('agent', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->whereIn('status', ['scheduled', 'published'])
            ->with(['agent', 'agent.user', 'platform', 'socialPost'])
            ->orderBy('updated_at', 'desc')
            ->paginate(10);

        $platforms = SocialMediaPlatform::query()
            ->whereNotIn('platform', [PlatformEnum::youtube->value, PlatformEnum::youtube_shorts->value])
            ->where('user_id', Auth::id())
            ->get();

        $agentsQuery = SocialMediaAgent::query()
            ->where('user_id', $userId);

        $defaultAgent = (clone $agentsQuery)->orderByDesc('created_at')->first();

        $agentIds = (clone $agentsQuery)
            ->pluck('id');

        $new_posts = (clone $postsQuery)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        $new_impressions = 0;

        if ($agentIds->isNotEmpty()) {
            $new_impressions = SocialMediaPostDailyMetric::query()
                ->whereIn('agent_id', $agentIds)
                ->whereDate('date', '>=', now()->subDay()->toDateString())
                ->sum('view_count');
        }

        $generationStatus = $defaultAgent ? SocialMediaAgentGenerationCache::currentStatus($defaultAgent) : ['status' => 'idle'];

        return view('social-media-agent::dashboard.index', [
            'pending_posts_count'   => $pending_posts_count,
            'scheduled_posts_count' => $scheduled_posts_count,
            'total_posts_count'     => $total_posts_count,
            'posts'                 => $posts,
            'platforms'             => $platforms,
            'new_posts'             => $new_posts,
            'new_impressions'       => $new_impressions,
            'generation_status'     => $generationStatus,
            'defaultAgent'          => $defaultAgent,
        ]);
    }

    public function postItems(Request $request): View
    {
        $userId = Auth::id();

        // Parse filters - convert comma-separated strings to arrays
        $filters = collect($request->except(['page', 'post_style', 'per_page', 'id', 'start_date', 'end_date', 'date_column', 'sort_by', 'sort_direction', 'platform']))
            ->map(fn ($value) => is_string($value) && str_contains($value, ',')
                ? explode(',', $value)
                : $value
            )
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->toArray();

        // Platform filter (string from PlatformEnum, filtered via relationship)
        $platformFilter = $request->input('platform');
        if ($platformFilter && is_string($platformFilter) && str_contains($platformFilter, ',')) {
            $platformFilter = explode(',', $platformFilter);
        }

        $postStyle = $request->get('post_style', 'carousel');
        $perPage = $request->integer('per_page', 10);

        // Date range filtering
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $dateColumn = $request->input('date_column', 'created_at');

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');

        // Validate sort column
        $allowedSortColumns = ['created_at', 'scheduled_at', 'status', 'platform_id', 'content'];
        if (! in_array($sortBy, $allowedSortColumns, true)) {
            $sortBy = 'created_at';
        }

        // Validate sort direction
        $sortDirection = strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';

        $baseQuery = fn () => SocialMediaAgentPost::query()
            ->whereIn('status', ['scheduled', 'published'])
            ->whereHas('agent', fn ($q) => $q->where('user_id', $userId))
            ->when($filters, function ($q) use ($filters) {
                foreach ($filters as $key => $value) {
                    if (is_array($value)) {
                        $q->whereIn($key, $value);
                    } else {
                        $q->where($key, $value);
                    }
                }
            })
            ->when($platformFilter, function ($q) use ($platformFilter) {
                $q->whereHas('platform', function ($query) use ($platformFilter) {
                    if (is_array($platformFilter)) {
                        $query->whereIn('platform', $platformFilter);
                    } else {
                        $query->where('platform', $platformFilter);
                    }
                });
            })
            ->whereIn('status', ['scheduled', 'published'])
            ->when($startDate, fn ($q) => $q->where($dateColumn, '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where($dateColumn, '<=', $endDate));

        $query = $baseQuery()
            ->with(['agent', 'agent.user', 'platform']);

        // For content sorting, use LEFT() to sort by first 100 characters for efficiency
        if ($sortBy === 'content') {
            $query->orderByRaw('LEFT(content, 100) ' . $sortDirection);
        } else {
            $query->orderBy($sortBy, $sortDirection);
        }

        $posts = $query->paginate($perPage)->appends($request->except('page'));

        $view = 'social-media-agent::components.posts.carousel.post-items';

        if (! empty($postStyle)) {
            $view = 'social-media-agent::components.posts.' . $postStyle . '.post-items';
        }

        return view($view, [
            'posts' => $posts,
        ]);
    }

    /**
     * Calendar page
     */
    public function calendar(): View
    {
        $userId = Auth::id();

        $agents = SocialMediaAgent::query()
            ->where('user_id', Auth::id())
            ->with('posts')
            ->latest()
            ->get();

        return view('social-media-agent::calendar.index', [
            'agents' => $agents,
        ]);
    }

    /**
     * Posts page
     */
    public function posts(): View
    {
        $userId = Auth::id();

        $posts = SocialMediaAgentPost::query()
            ->whereHas('agent', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->whereIn('status', ['scheduled', 'published', 'failed', 'draft'])
            ->with(['agent', 'agent.user'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $platforms = SocialMediaPlatform::query()
            ->where('user_id', Auth::id())
            ->get();

        $agents = SocialMediaAgent::query()
            ->where('user_id', $userId)
            ->get();

        return view('social-media-agent::posts.index', [
            'posts'         => $posts,
            'platformEnums' => PlatformEnum::all(),
            'platforms'     => $platforms,
            'agents'        => $agents,
        ]);
    }

    /**
     * Get pending posts count (API)
     */
    public function getPendingCount(): JsonResponse
    {
        $userId = Auth::id();

        $count = SocialMediaAgentPost::query()
            ->whereHas('agent', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->where('status', SocialMediaAgentPost::STATUS_DRAFT)
            ->count();

        return response()->json([
            'success' => true,
            'count'   => $count,
        ]);
    }

    /**
     * Analytics page
     */
    public function analytics(SocialMediaFollowerService $followerService): View
    {
        $userId = Auth::id();

        $total_posts = SocialMediaAgentPost::whereHas('agent', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->count();

        $published_posts = SocialMediaAgentPost::whereHas('agent', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->where('status', 'published')->count();

        $platforms = SocialMediaPlatform::query()
            ->where('user_id', Auth::id())
            ->get();

        $this->syncPlatformFollowers($platforms, $followerService);

        $averageEngagement = (float) SocialMediaPost::query()
            ->where('user_id', $userId)
            ->whereNotNull('post_engagement_rate')
            ->avg('post_engagement_rate');

        $totalFollowers = $platforms->sum(function (SocialMediaPlatform $platform) {
            if ($platform->followers_count !== null) {
                return (int) $platform->followers_count;
            }

            $credentials = $platform->credentials ?? [];

            return (int) (
                data_get($credentials, 'followers_count')
                ?? data_get($credentials, 'followers')
                ?? data_get($credentials, 'audience_size')
                ?? 0
            );
        });

        $stats = [
            'total_posts'        => $total_posts,
            'published_posts'    => $published_posts,
            'average_engagement' => round($averageEngagement, 2),
            'total_followers'    => $totalFollowers,
        ];

        $monthRange = $this->buildMonthRange(12);
        $impressionsChartData = $this->buildImpressionsChartData($userId, $platforms, $monthRange);
        $publishedChartData = $this->buildPublishedPostsChartData($userId, $platforms, $monthRange);
        $engagementChartData = $this->buildEngagementRateChartData($userId, $platforms, $monthRange);
        $audienceChartData = $this->buildAudienceGrowthChartData($userId, $platforms, $monthRange);
        $newsFeed = $this->buildAnalyticsNews($userId, $stats);

        return view('social-media-agent::analytics.index', [
            'stats'                => $stats,
            'platforms'            => $platforms,
            'news'                 => $newsFeed,
            'impressionsChartData' => $impressionsChartData,
            'impressionsMonths'    => $monthRange,
            'publishedChartData'   => $publishedChartData,
            'publishedMonths'      => $monthRange,
            'engagementChartData'  => $engagementChartData,
            'engagementMonths'     => $monthRange,
            'audienceChartData'    => $audienceChartData,
            'audienceMonths'       => $monthRange,
        ]);
    }

    private function syncPlatformFollowers(Collection $platforms, SocialMediaFollowerService $followerService): void
    {
        $platforms->each(function (SocialMediaPlatform $platform) use ($followerService) {
            if ($platform->followers_count === null && $platform->isConnected()) {
                $followerService->sync($platform);
            }
        });
    }

    private function buildAnalyticsNews(int $userId, array $stats): array
    {
        $items = [];

        if (($stats['total_posts'] ?? 0) > 0) {
            $items[] = __('Total of :count posts are live.', ['count' => $stats['total_posts']]);
        }

        if (($stats['published_posts'] ?? 0) > 0) {
            $items[] = __('In the last day, :count posts were approved.', ['count' => $stats['published_posts']]);
        }

        if (($stats['average_engagement'] ?? 0) > 0) {
            $items[] = __('Your average engagement rate is %:value.', ['value' => $stats['average_engagement']]);
        }

        if (($stats['total_followers'] ?? 0) > 0) {
            $items[] = __('Connected accounts have :count followers in total.', ['count' => number_format($stats['total_followers'])]);
        }

        $recentPost = SocialMediaAgentPost::query()
            ->whereHas('agent', fn ($q) => $q->where('user_id', $userId))
            ->latest()
            ->first();

        if ($recentPost) {
            $items[] = __('The last post was created :date.', ['date' => optional($recentPost->created_at)->diffForHumans()]);
        }

        if (count($items) < 3) {
            $items[] = __('Agents are ready to generate new ideas. Check now!');
        }

        return $items;
    }

    private function buildMonthRange(int $months = 12): array
    {
        $months = max(1, $months);
        $range = [];

        $cursor = now()->copy()->startOfMonth()->subMonths($months - 1);

        for ($i = 0; $i < $months; $i++) {
            $range[] = $cursor->copy();
            $cursor->addMonth();
        }

        return $range;
    }

    private function buildImpressionsChartData(int $userId, $platforms, array $months): array
    {
        [$months, $monthKeys, $startDate, $endDate] = $this->prepareMonthMetadata($months);

        $records = SocialMediaPostDailyMetric::query()
            ->whereBetween('date', [$startDate, $endDate])
            ->whereHas('post.agent', fn ($query) => $query->where('user_id', $userId))
            ->selectRaw('social_media_platform_id, DATE_FORMAT(date, "%Y-%m") as month_key, SUM(view_count) as total')
            ->groupBy('social_media_platform_id', 'month_key')
            ->get();

        $recordsByPlatform = $records->groupBy('social_media_platform_id');

        $todayTotals = SocialMediaPostDailyMetric::query()
            ->whereDate('date', now()->toDateString())
            ->whereHas('post.agent', fn ($query) => $query->where('user_id', $userId))
            ->selectRaw('social_media_platform_id, SUM(view_count) as total')
            ->groupBy('social_media_platform_id')
            ->pluck('total', 'social_media_platform_id');

        return $this->buildChartSeriesFromRecords(
            $platforms,
            $monthKeys,
            $recordsByPlatform,
            $todayTotals,
            'today_impressions'
        );
    }

    private function buildPublishedPostsChartData(int $userId, $platforms, array $months): array
    {
        [$months, $monthKeys, $startDate, $endDate] = $this->prepareMonthMetadata($months);

        $records = SocialMediaPost::query()
            ->where('user_id', $userId)
            ->where('status', StatusEnum::published)
            ->whereNotNull('social_media_platform_id')
            ->whereBetween('posted_at', [$startDate, $endDate])
            ->selectRaw('social_media_platform_id, DATE_FORMAT(posted_at, "%Y-%m") as month_key, COUNT(*) as total')
            ->groupBy('social_media_platform_id', 'month_key')
            ->get();

        $recordsByPlatform = $records->groupBy('social_media_platform_id');

        $todayTotals = SocialMediaPost::query()
            ->where('user_id', $userId)
            ->where('status', StatusEnum::published)
            ->whereNotNull('social_media_platform_id')
            ->whereDate('posted_at', now()->toDateString())
            ->selectRaw('social_media_platform_id, COUNT(*) as total')
            ->groupBy('social_media_platform_id')
            ->pluck('total', 'social_media_platform_id');

        return $this->buildChartSeriesFromRecords(
            $platforms,
            $monthKeys,
            $recordsByPlatform,
            $todayTotals,
            'today_posts'
        );
    }

    private function buildEngagementRateChartData(int $userId, $platforms, array $months): array
    {
        [$months, $monthKeys, $startDate, $endDate] = $this->prepareMonthMetadata($months);

        $records = SocialMediaPost::query()
            ->where('user_id', $userId)
            ->whereNotNull('social_media_platform_id')
            ->whereNotNull('post_engagement_rate')
            ->whereBetween('posted_at', [$startDate, $endDate])
            ->selectRaw('social_media_platform_id, DATE_FORMAT(posted_at, "%Y-%m") as month_key, AVG(post_engagement_rate) as total')
            ->groupBy('social_media_platform_id', 'month_key')
            ->get();

        $recordsByPlatform = $records->groupBy('social_media_platform_id');

        $currentMonthKey = now()->format('Y-m');
        $currentMonthTotals = $records
            ->where('month_key', $currentMonthKey)
            ->pluck('total', 'social_media_platform_id');

        return $this->buildChartSeriesFromRecords(
            $platforms,
            $monthKeys,
            $recordsByPlatform,
            $currentMonthTotals,
            'monthly_engagement',
            true
        );
    }

    private function buildAudienceGrowthChartData(int $userId, $platforms, array $months): array
    {
        [$months, $monthKeys, $startDate, $endDate] = $this->prepareMonthMetadata($months);

        $records = SocialMediaPostDailyMetric::query()
            ->whereBetween('date', [$startDate, $endDate])
            ->whereHas('post.agent', fn ($query) => $query->where('user_id', $userId))
            ->selectRaw('social_media_platform_id, DATE_FORMAT(date, "%Y-%m") as month_key, SUM(like_count + comment_count + share_count) as total')
            ->groupBy('social_media_platform_id', 'month_key')
            ->get();

        $recordsByPlatform = $records->groupBy('social_media_platform_id');

        $currentMonthKey = now()->format('Y-m');
        $currentTotals = $records
            ->where('month_key', $currentMonthKey)
            ->pluck('total', 'social_media_platform_id');

        return $this->buildChartSeriesFromRecords(
            $platforms,
            $monthKeys,
            $recordsByPlatform,
            $currentTotals,
            'today_growth',
            true
        );
    }

    private function prepareMonthMetadata(array $months): array
    {
        if (empty($months)) {
            $months = $this->buildMonthRange(12);
        }

        $normalizedMonths = collect($months)
            ->map(fn ($month) => $month instanceof Carbon ? $month->copy() : Carbon::parse($month))
            ->values();

        $monthKeys = $normalizedMonths->map(fn (Carbon $month) => $month->format('Y-m'))->values();

        return [
            $normalizedMonths->all(),
            $monthKeys->all(),
            $normalizedMonths->first()->copy(),
            $normalizedMonths->last()->copy()->endOfMonth(),
        ];
    }

    private function buildChartSeriesFromRecords($platforms, array $monthKeys, $recordsByPlatform, $currentTotals, string $statKey, bool $asFloat = false): array
    {
        $allSeries = array_fill(0, count($monthKeys), 0);
        $chartData = [];

        foreach ($platforms as $platform) {
            $platformId = $platform->id;
            $platformName = $platform->platform ?: ('platform_' . $platformId);

            $platformData = optional($recordsByPlatform->get($platformId))->keyBy('month_key') ?? collect();
            $seriesData = [];

            foreach ($monthKeys as $index => $key) {
                $value = (float) data_get($platformData->get($key), 'total', 0);
                $seriesData[] = $value;
                $allSeries[$index] += $value;
            }

            $chartData[] = [
                'label'        => Str::headline($platformName),
                'id'           => $platformId,
                'chart_series' => [
                    'name'   => $platformName,
                    'data'   => $seriesData,
                    'hidden' => true,
                ],
                $statKey      => $asFloat
                    ? round((float) ($currentTotals[$platformId] ?? 0), 2)
                    : (int) ($currentTotals[$platformId] ?? 0),
            ];
        }

        $chartData = array_values($chartData);
        array_unshift($chartData, [
            'label'        => __('All'),
            'id'           => '*',
            'chart_series' => [
                'name' => 'all',
                'data' => $allSeries,
            ],
            $statKey       => $asFloat
                ? round((float) collect($currentTotals)->sum(), 2)
                : (int) collect($currentTotals)->sum(),
        ]);

        return $chartData;
    }

    private function decodeJsonField($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return (array) $decoded;
            }
        }

        return [];
    }

    private function sanitizeScheduleTimes(array $times): array
    {
        return collect($times)
            ->map(function ($slot) {
                if (! is_array($slot)) {
                    return null;
                }

                $start = $slot['start'] ?? null;
                $end = $slot['end'] ?? null;

                if (! $start || ! $end) {
                    return null;
                }

                return array_filter([
                    'key'   => $slot['key'] ?? null,
                    'label' => $slot['label'] ?? null,
                    'start' => $start,
                    'end'   => $end,
                ], fn ($value) => $value !== null);
            })
            ->filter()
            ->values()
            ->all();
    }

    private function normalizeScheduleDays(array $days): array
    {
        $map = $this->getWeekDayMap();

        return collect($days)
            ->map(function ($day) use ($map) {
                if (is_numeric($day)) {
                    $intDay = (int) $day;

                    if ($intDay >= 1 && $intDay <= 7) {
                        return $map[$intDay] ?? null;
                    }

                    if ($intDay >= 0 && $intDay <= 6) {
                        $converted = (($intDay + 6) % 7) + 1; // Accept JS-style 0=Sunday

                        return $map[$converted] ?? null;
                    }

                    return null;
                }

                $normalized = strtolower(trim((string) $day));
                foreach ($map as $name) {
                    if ($normalized === strtolower($name)) {
                        return $name;
                    }
                }

                try {
                    return Carbon::parse($day)->format('l');
                } catch (Exception) {
                    return null;
                }
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function sanitizeScrapedContent(array $scrapedContent): array
    {
        if (empty($scrapedContent)) {
            return [];
        }

        $pages = collect($scrapedContent['pages'] ?? [])
            ->map(function ($page) {
                if (! is_array($page)) {
                    return null;
                }

                $headings = collect($page['headings'] ?? [])
                    ->map(fn ($heading) => $this->sanitizeString($heading ?? null, 160))
                    ->filter()
                    ->take(5)
                    ->values()
                    ->all();

                $sanitizedPage = array_filter([
                    'title'            => $this->sanitizeString($page['title'] ?? null, 200),
                    'meta_description' => $this->sanitizeString($page['meta_description'] ?? null, 400),
                    'content'          => $this->sanitizeString($page['content'] ?? null, 500),
                    'headings'         => ! empty($headings) ? $headings : null,
                ], fn ($value) => $value !== null && (! is_array($value) || ! empty($value)));

                return empty($sanitizedPage) ? null : $sanitizedPage;
            })
            ->filter()
            ->values()
            ->all();

        $pagesCount = count($pages);

        return array_filter([
            'summary'     => $this->sanitizeString($scrapedContent['summary'] ?? null, 1000),
            'base_url'    => $this->sanitizeString($scrapedContent['base_url'] ?? null, 255),
            'pages_count' => $pagesCount,
            'pages'       => $pages,
        ], fn ($value) => $value !== null && (! is_array($value) || ! empty($value)));
    }

    private function storeScrapedContentInCache(array $scrapedContent): ?string
    {
        if (empty($scrapedContent)) {
            return null;
        }

        $key = (string) Str::uuid();

        Cache::put(
            $this->scrapedContentCachePrefix . $key,
            $scrapedContent,
            now()->addMinutes($this->scrapedContentCacheTtlMinutes)
        );

        return $key;
    }

    private function getCachedScrapedContent(?string $cacheKey): array
    {
        if (! $cacheKey) {
            return [];
        }

        return Cache::get($this->scrapedContentCachePrefix . $cacheKey, []);
    }

    private function forgetScrapedContentCache(?string $cacheKey): void
    {
        if (! $cacheKey) {
            return;
        }

        Cache::forget($this->scrapedContentCachePrefix . $cacheKey);
    }

    private function sanitizeString($value, int $maxLength = 500): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        return Str::substr($trimmed, 0, $maxLength);
    }

    private function generateAISchedule(string $planType, int $dailyPostCount): array
    {
        $planType = strtolower($planType);

        $planDays = [
            'daily'   => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
            'weekly'  => ['Monday', 'Wednesday', 'Friday'],
            'monthly' => ['Tuesday', 'Thursday'],
        ];

        $days = $planDays[$planType] ?? $planDays['weekly'];

        $timeSlots = [
            ['key' => 'morning', 'label' => 'Morning Boost', 'start' => '09:00', 'end' => '11:00'],
            ['key' => 'midday', 'label' => 'Midday Momentum', 'start' => '12:00', 'end' => '14:00'],
            ['key' => 'evening', 'label' => 'Evening Spotlight', 'start' => '18:00', 'end' => '20:00'],
        ];

        $times = [];
        $totalSlots = max(1, min($dailyPostCount, 10));

        for ($i = 0; $i < $totalSlots; $i++) {
            $times[] = $timeSlots[$i % count($timeSlots)];
        }

        return [
            'days'  => $days,
            'times' => $times,
        ];
    }

    private function getWeekDayMap(): array
    {
        return [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
        ];
    }

    /**
     * Accounts page
     */
    public function accounts(): View
    {
        $userPlatforms = SocialMediaPlatform::query()
            ->where('user_id', Auth::id())
            ->get();

        $agents = SocialMediaAgent::query()
            ->where('user_id', Auth::id())
            ->get();

        return view('social-media-agent::accounts.index', [
            'platforms'     => PlatformEnum::all(),
            'userPlatforms' => $userPlatforms,
            'agents'        => $agents,
        ]);
    }

    /**
     * Chat page
     */
    public function chat(): View
    {
        return view('social-media-agent::chat.index', [
        ]);
    }

    /**
     * Show create wizard - Step 1: Platform Selection
     */
    public function create(): View
    {
        $platforms = SocialMediaPlatform::query()
            ->whereNotIn('platform', [PlatformEnum::youtube->value, PlatformEnum::youtube_shorts->value])
            ->where('user_id', Auth::id())
            ->get();

        return view('social-media-agent::create.index', [
            'platforms' => $platforms,
        ]);
    }

    /**
     * Store the agent (Final wizard step)
     */
    public function store(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'success' => false,
                'message' => __('This action is not allowed in the demo mode.'),
            ], 403);
        }

        if ($response = $this->ensureAgentCreationAllowed()) {
            return $response;
        }

        $validated = $request->validate([
            'name'                      => 'required|string|max:255',
            'platform_ids'              => 'required|array|min:1',
            'platform_ids.*'            => 'exists:ext_social_media_platforms,id',
            'site_url'                  => 'nullable|string|max:255|regex:/^(https?:\/\/)?([\w\-]+\.)+[\w\-]+(\/[\w\-._~:\/?#[\]@!$&\'()*+,;=]*)?$/i',
            'site_description'          => 'nullable|string',
            'scraped_content'           => 'nullable', // JSON string
            'scraped_content_cache_key' => 'nullable|string',
            'target_audience'           => 'nullable', // JSON string
            'post_types'                => 'required|array|min:1',
            'post_types.*'              => 'string',
            'tone'                      => 'required|string',
            'language'                  => 'required|string',
            'plan_type'                 => 'required|in:daily,weekly,monthly',
            'include_hashtags'          => 'nullable|in:0,1',
            'include_emoji'             => 'nullable|in:0,1',
            'has_image'                 => 'nullable|in:0,1',
            'publishing_type'           => 'nullable|string|in:post,story',
            'hashtag_count'             => 'required|integer|min:0|max:30',
            'schedule_days'             => 'nullable|array',
            'schedule_days.*'           => 'string',
            'schedule_times'            => 'nullable', // JSON string
            'daily_post_count'          => 'required|integer|min:1|max:10',
            'ai_target_audience'        => 'sometimes|boolean',
            'ai_schedule'               => 'sometimes|boolean',
        ]);

        // Decode JSON strings
        $scrapedContentCacheKey = $validated['scraped_content_cache_key'] ?? null;
        $scrapedContentInput = $this->decodeJsonField($validated['scraped_content'] ?? null);
        if (empty($scrapedContentInput) && ! empty($scrapedContentCacheKey)) {
            $scrapedContentInput = $this->getCachedScrapedContent($scrapedContentCacheKey);
        }
        $scrapedContent = $this->sanitizeScrapedContent($scrapedContentInput);
        $targetAudience = $this->decodeJsonField($validated['target_audience'] ?? []);
        $scheduleDaysInput = $validated['schedule_days'] ?? [];
        $scheduleTimes = $this->sanitizeScheduleTimes($this->decodeJsonField($validated['schedule_times'] ?? []));

        // Convert string booleans to actual booleans
        $includeHashtags = ($validated['include_hashtags'] ?? '0') == '1';
        $includeEmoji = ($validated['include_emoji'] ?? '0') == '1';
        $hasImage = ($validated['has_image'] ?? '0') == '1';
        $aiTargetAudience = filter_var($validated['ai_target_audience'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $aiSchedule = filter_var($validated['ai_schedule'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $normalizedScheduleDays = $this->normalizeScheduleDays($scheduleDaysInput);

        if (! $aiTargetAudience && empty($targetAudience)) {
            throw ValidationException::withMessages([
                'target_audience' => __('Please select a target audience or enable the AI option.'),
            ]);
        }

        if ($aiTargetAudience && empty($targetAudience)) {
            $targetService = new TargetAudienceService;
            $targetAudience = $targetService->generateTargets($scrapedContent, $validated['site_description'] ?? null);
        }

        if ($aiSchedule) {
            if (empty($normalizedScheduleDays) || empty($scheduleTimes)) {
                $generatedSchedule = $this->generateAISchedule($validated['plan_type'], (int) $validated['daily_post_count']);

                if (empty($normalizedScheduleDays)) {
                    $normalizedScheduleDays = $generatedSchedule['days'];
                }

                if (empty($scheduleTimes)) {
                    $scheduleTimes = $generatedSchedule['times'];
                }
            }
        } else {
            if (empty($normalizedScheduleDays)) {
                throw ValidationException::withMessages([
                    'schedule_days' => __('Please select at least one day or enable AI scheduling.'),
                ]);
            }

            if (empty($scheduleTimes)) {
                throw ValidationException::withMessages([
                    'schedule_times' => __('Please choose at least one time slot.'),
                ]);
            }
        }

        $agent = SocialMediaAgent::create([
            'user_id'            => Auth::id(),
            'name'               => $validated['name'],
            'platform_ids'       => $validated['platform_ids'],
            'site_url'           => $validated['site_url'] ?? null,
            'site_description'   => $validated['site_description'] ?? null,
            'scraped_content'    => $scrapedContent ?: null,
            'target_audience'    => $targetAudience,
            'post_types'         => $validated['post_types'],
            'tone'               => $validated['tone'],
            'language'           => $validated['language'],
            'hashtag_count'      => $includeHashtags ? $validated['hashtag_count'] : 0,
            'schedule_days'      => $normalizedScheduleDays,
            'schedule_times'     => $scheduleTimes,
            'daily_post_count'   => $validated['daily_post_count'],
            'has_image'          => $hasImage,
            'publishing_type'    => $validated['publishing_type'] ?? 'post',
            'is_active'          => true,
            'settings'           => [
                'plan_type'          => $validated['plan_type'],
                'include_hashtags'   => $includeHashtags,
                'include_emoji'      => $includeEmoji,
                'ai_target_audience' => $aiTargetAudience,
                'ai_schedule'        => $aiSchedule,
            ],
            'post_generation_status' => [
                'status' => 'idle',
            ],
        ]);

        if ($scrapedContentCacheKey) {
            $this->forgetScrapedContentCache($scrapedContentCacheKey);
        }

        $this->queuePostGeneration($agent);

        return response()->json([
            'success'  => true,
            'agent_id' => $agent->id,
            'message'  => __('Agent created successfully! Posts are being generated in the background.'),
        ]);
    }

    public function agents(): View
    {
        $agents = SocialMediaAgent::query()
            ->where('user_id', Auth::id())
            ->get();

        $platforms = SocialMediaPlatform::query()
            ->where('user_id', Auth::id())
            ->get();

        $determineAgentOfMonth = $this->determineAgentOfMonth($agents);

        return view('social-media-agent::agents.index', [
            'agents'                => $agents,
            'platforms'             => $platforms,
            'determineAgentOfMonth' => $determineAgentOfMonth,
        ]);
    }

    /**
     * Show edit form
     */
    public function edit(SocialMediaAgent $agent): View
    {
        $this->authorize('update', $agent);

        $platforms = SocialMediaPlatform::query()
            ->where('user_id', Auth::id())
            ->get();

        return view('social-media-agent::agent.edit', [
            'agent'     => $agent,
            'platforms' => $platforms,
        ]);
    }

    protected function determineAgentOfMonth(Collection $agents): ?SocialMediaAgent
    {
        if ($agents->isEmpty()) {
            return null;
        }

        $activeAgents = $agents->filter->is_active;
        $pool = $activeAgents->isNotEmpty() ? $activeAgents : $agents;

        return $pool
            ->sortByDesc(function (SocialMediaAgent $agent) {
                $engagement = $agent->average_engagement ?? 0;
                $impressions = $agent->average_impressions ?? 0;
                $created = optional($agent->created_at)->timestamp ?? 0;

                return ($engagement * 1000) + $impressions + ($created / 100000);
            })
            ->first();
    }

    /**
     * Update agent
     */
    public function update(Request $request, SocialMediaAgent $agent): RedirectResponse
    {
        $this->authorize('update', $agent);

        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'platform_ids'     => 'required|array|min:1',
            'is_active'        => 'boolean',
            'daily_post_count' => 'required|integer|min:1|max:10',
        ]);

        $validated['is_active'] = (bool) $request->has('is_active');

        $agent->update($validated);

        return redirect()
            ->route('dashboard.user.social-media.agent.edit', $agent->id)
            ->with([
                'status'  => 'success',
                'message' => __('Agent updated successfully!'),
                'type'    => 'success',
                'success' => __('Agent updated successfully!'),
            ]);
    }

    /**
     * Delete agent
     */
    public function destroy(SocialMediaAgent $agent): RedirectResponse
    {
        $this->authorize('delete', $agent);

        $agent->delete();

        return redirect()
            ->route('dashboard.user.social-media.agent.agents');
    }

    // ==================== WIZARD AJAX ENDPOINTS ====================

    /**
     * AJAX: Scrape website (Step 2)
     */
    public function scrapeWebsite(Request $request): JsonResponse
    {
        $url = trim((string) $request->input('url'));
        if ($url !== '' && ! Str::startsWith($url, ['http://', 'https://'])) {
            $url = 'https://' . $url;
            $request->merge(['url' => $url]);
        }

        $request->validate([
            'url' => 'required|url',
        ]);

        $scraper = new WebScrapingService;
        $result = $scraper->setMaxPages(6)->scrapeWebsite($request->input('url'));

        if (! data_get($result, 'success')) {
            return response()->json($result);
        }

        $sanitizedContent = $this->sanitizeScrapedContent($result);
        $cacheKey = $this->storeScrapedContentInCache($sanitizedContent);

        return response()->json([
            'success'          => true,
            'cache_key'        => $cacheKey,
            'scraped_content'  => $sanitizedContent,
            'pages_count'      => data_get($sanitizedContent, 'pages_count', 0),
            'base_url'         => $sanitizedContent['base_url'] ?? null,
            'summary'          => $sanitizedContent['summary'] ?? null,
        ]);
    }

    /**
     * AJAX: Generate target audiences (Step 3)
     */
    public function generateTargets(Request $request): JsonResponse
    {
        $request->validate([
            'scraped_content'           => 'nullable|array',
            'scraped_content_cache_key' => 'nullable|string',
            'site_description'          => 'nullable|string',
            'existing_targets'          => 'nullable|array',
            'existing_targets.*'        => 'nullable|string',
        ]);

        $scrapedContent = $request->input('scraped_content');

        if (! is_array($scrapedContent)) {
            $scrapedContent = $this->decodeJsonField($scrapedContent);
        }

        if (empty($scrapedContent) && $request->filled('scraped_content_cache_key')) {
            $scrapedContent = $this->getCachedScrapedContent($request->input('scraped_content_cache_key'));
        }

        $scrapedContent = $this->sanitizeScrapedContent($scrapedContent);

        $targetService = new TargetAudienceService;
        $targets = $targetService->generateTargets(
            $scrapedContent,
            $request->input('site_description'),
            $request->input('existing_targets', [])
        );

        return response()->json([
            'success' => true,
            'targets' => $targets,
        ]);
    }

    /**
     * AJAX: Preview post generation
     */
    public function previewPost(Request $request): JsonResponse
    {
        // Create temporary agent object for preview
        $tempAgent = new SocialMediaAgent($request->all());

        $postService = new PostGenerationService;
        $post = $postService->generatePost($tempAgent);

        return response()->json($post);
    }

    // ==================== POST MANAGEMENT ====================

    /**
     * Get posts (API)
     */
    public function getPosts(Request $request): JsonResponse
    {
        if (Helper::appIsDemo() && ! $request->has('id')) {
            return $this->getDemoPosts($request);
        }

        $userId = Auth::id();
        $perPage = (int) $request->integer('per_page', 10);

        $allowedDateColumns = ['created_at', 'scheduled_at', 'published_at'];
        $dateColumn = $request->input('date_column', 'created_at');
        if (! in_array($dateColumn, $allowedDateColumns, true)) {
            $dateColumn = 'created_at';
        }

        $startDate = $this->parseDateOrNull($request->input('start_date'));
        $endDate = $this->parseDateOrNull($request->input('end_date'));

        $rawFilters = $request->only([
            'status',
            'platform_id',
            'agent_id',
            'post_type',
        ]);

        $filters = array_filter($rawFilters, fn ($value) => $value !== null && $value !== '');
        $search = trim((string) $request->input('search', ''));

        $filterHandlers = [
            'status'      => fn ($query, $value) => $query->where('status', $value),
            'platform_id' => fn ($query, $value) => $query->where('platform_id', $value),
            'agent_id'    => fn ($query, $value) => $query->where('agent_id', $value),
            'post_type'   => fn ($query, $value) => $query->where('post_type', $value),
        ];

        $baseQuery = fn () => tap(SocialMediaAgentPost::query(), function ($query) use ($userId, $filters, $filterHandlers, $startDate, $endDate, $dateColumn, $search) {
            $query->whereHas('agent', fn ($q) => $q->where('user_id', $userId));

            foreach ($filters as $key => $value) {
                if (isset($filterHandlers[$key])) {
                    $filterHandlers[$key]($query, $value);
                }
            }

            if ($startDate) {
                $query->whereDate($dateColumn, '>=', $startDate);
            }

            if ($endDate) {
                $query->whereDate($dateColumn, '<=', $endDate);
            }

            if ($search !== '') {
                $query->where(function ($builder) use ($search) {
                    $builder
                        ->where('content', 'like', "%{$search}%")
                        ->orWhere('hashtags', 'like', "%{$search}%");
                });
            }
        });

        // Cursor-based pagination when 'id' is provided
        if ($request->has('id')) {
            $postId = $request->input('id');
            $post = $baseQuery()->with(['agent', 'platform'])->find($postId);

            if (! $post) {
                return response()->json([
                    'success' => false,
                    'message' => 'Post not found',
                ], 404);
            }

            // Get total count
            $total = $baseQuery()->count();

            // Get position of current post (how many posts come before it)
            $position = $baseQuery()
                ->where('created_at', '>', $post->created_at)
                ->count();

            // Get previous post (newer than current, ordered by created_at desc)
            $prevPost = $baseQuery()
                ->where('created_at', '>', $post->created_at)
                ->orderBy('created_at', 'asc')
                ->first(['id']);

            // Get next post (older than current, ordered by created_at desc)
            $nextPost = $baseQuery()
                ->where('created_at', '<', $post->created_at)
                ->orderBy('created_at', 'desc')
                ->first(['id']);

            // Build URL helper
            $buildUrl = function (?int $id) use ($request, $filters, $perPage) {
                if ($id === null) {
                    return null;
                }
                $params = array_merge($filters, [
                    'id'       => $id,
                    'per_page' => $perPage,
                ]);

                return $request->url() . '?' . http_build_query($params);
            };

            $path = $request->url();
            $prevPageUrl = $buildUrl($prevPost?->id);
            $nextPageUrl = $buildUrl($nextPost?->id);

            // Build Laravel paginator-like response
            return response()->json([
                'success' => true,
                'posts'   => [
                    'current_page'   => $position + 1,
                    'data'           => [$post],
                    'first_page_url' => null,
                    'from'           => $position + 1,
                    'last_page'      => $total,
                    'last_page_url'  => null,
                    'links'          => [
                        [
                            'url'    => $prevPageUrl,
                            'label'  => 'pagination.previous',
                            'active' => false,
                        ],
                        [
                            'url'    => $buildUrl($post->id),
                            'label'  => (string) ($position + 1),
                            'active' => true,
                        ],
                        [
                            'url'    => $nextPageUrl,
                            'label'  => 'pagination.next',
                            'active' => false,
                        ],
                    ],
                    'next_page_url' => $nextPageUrl,
                    'path'          => $path,
                    'per_page'      => $perPage,
                    'prev_page_url' => $prevPageUrl,
                    'to'            => $position + 1,
                    'total'         => $total,
                    // Extra fields for cursor navigation
                    'prev_post_id'    => $prevPost?->id,
                    'next_post_id'    => $nextPost?->id,
                    'current_post_id' => $post->id,
                ],
            ]);
        }

        // Standard page-based pagination
        $query = $baseQuery()
            ->with(['agent', 'platform'])
            ->latest();

        $posts = $query->paginate($perPage)->appends($request->except('page'));

        return response()->json([
            'success' => true,
            'posts'   => $posts,
        ]);
    }

    /**
     * Generate deterministic demo posts for the calendar.
     */
    private function getDemoPosts(Request $request): JsonResponse
    {
        $startDate = $this->parseDateOrNull($request->input('start_date'));
        $endDate = $this->parseDateOrNull($request->input('end_date'));

        if (! $startDate || ! $endDate) {
            $startDate = Carbon::now()->startOfMonth();
            $endDate = Carbon::now()->endOfMonth();
        }

        $platforms = [
            ['id' => 1, 'platform' => 'instagram', 'credentials' => ['username' => '@designstudio']],
            ['id' => 2, 'platform' => 'facebook', 'credentials' => ['username' => 'MagicAI Official']],
            ['id' => 3, 'platform' => 'linkedin', 'credentials' => ['username' => 'MagicAI Inc.']],
        ];

        $agents = [
            ['id' => 1, 'name' => 'Brand Awareness'],
            ['id' => 2, 'name' => 'Product Launch'],
            ['id' => 3, 'name' => 'Engagement Booster'],
        ];

        $contents = [
            'Discover how AI is transforming the way we create content. Stay ahead of the curve with MagicAI!',
            'New feature alert! Our latest update makes content creation 10x faster. Try it now.',
            'Behind the scenes of our latest product development. Exciting things are coming!',
            'Tips for growing your social media presence with AI-powered tools.',
            'Join our webinar this week to learn about the future of AI content creation.',
            'Customer spotlight: See how our users are achieving amazing results.',
            'Weekly roundup: The best AI trends and insights you need to know.',
            'Introducing our new dashboard - designed for maximum productivity.',
            'How to create a month\'s worth of content in just one hour.',
            'The future of marketing is here. Let AI handle the heavy lifting.',
            'Boost your engagement with these proven social media strategies.',
            'We just hit a major milestone! Thank you to our amazing community.',
        ];

        $statuses = ['published', 'scheduled', 'draft', 'published', 'published', 'scheduled'];
        $hours = [9, 10, 12, 14, 16, 18, 20];

        $demoImages = [
            asset('vendor/social-media-agent/images/img-1.png'),
            asset('vendor/social-media-agent/images/img-2.png'),
            asset('vendor/social-media-agent/images/img-3.png'),
            asset('vendor/social-media-agent/images/img-4.jpg'),
            asset('vendor/social-media-agent/images/img-5.jpg'),
            asset('vendor/social-media-agent/images/img-7.png'),
            asset('vendor/social-media-agent/images/img-8.png'),
            asset('vendor/social-media-agent/images/img-9.png'),
            asset('vendor/social-media-agent/images/img-10.png'),
        ];

        $posts = [];
        $postId = 1;
        $current = Carbon::parse($startDate)->copy();

        while ($current->lte($endDate)) {
            $daySeed = abs(crc32($current->format('Y-m-d')));

            $postCount = ($daySeed % 3) + 1;

            for ($i = 0; $i < $postCount; $i++) {
                $seed = abs(crc32($current->format('Y-m-d') . '-' . $i));

                $platform = $platforms[$seed % count($platforms)];
                $agent = $agents[($seed >> 2) % count($agents)];
                $content = $contents[$seed % count($contents)];
                $status = $statuses[$seed % count($statuses)];
                $hour = $hours[$seed % count($hours)];
                $minute = ($seed % 4) * 15;

                $scheduledAt = $current->copy()->setTime($hour, $minute);

                $mediaUrls = [];
                $platformName = $platform['platform'];
                if ($platformName === 'instagram' || $platformName === 'facebook') {
                    $imageCount = ($seed % 3) + 1;
                    for ($j = 0; $j < $imageCount; $j++) {
                        $mediaUrls[] = $demoImages[($seed + $j) % count($demoImages)];
                    }
                }

                $posts[] = [
                    'id'           => $postId++,
                    'agent_id'     => $agent['id'],
                    'platform_id'  => $platform['id'],
                    'content'      => $content,
                    'media_urls'   => $mediaUrls,
                    'scheduled_at' => $scheduledAt->toIso8601String(),
                    'published_at' => $status === 'published' ? $scheduledAt->toIso8601String() : null,
                    'created_at'   => $scheduledAt->copy()->subDays(2)->toIso8601String(),
                    'status'       => $status,
                    'post_type'    => $mediaUrls ? 'image' : 'text',
                    'agent'        => $agent,
                    'platform'     => $platform,
                ];
            }

            $current->addDay();
        }

        return response()->json([
            'success' => true,
            'posts'   => [
                'current_page' => 1,
                'data'         => $posts,
                'total'        => count($posts),
                'per_page'     => 1000,
                'last_page'    => 1,
            ],
        ]);
    }

    /**
     * Store a manually created post
     */
    public function storePost(Request $request): JsonResponse
    {
        if ($response = $this->ensureMonthlyPostCreationAllowed()) {
            return $response;
        }

        $this->validate($request, [
            'platform_id'          => 'required|exists:ext_social_media_platforms,id',
            'agent_id'             => 'required|exists:ext_social_media_agents,id',
            'post_type'            => 'required|string|max:100',
            'publishing_type'      => 'nullable|string|in:post,story',
            'content'              => 'required|string',
            'media_urls'           => 'nullable|array',
            'media_urls.*'         => 'string|max:2048',
            'video_urls'           => 'nullable|array',
            'video_urls.*'         => 'string|max:2048',
            'scheduled_at'         => 'nullable|string',
            'personalized_content' => 'nullable|boolean',
        ]);

        $platform = SocialMediaPlatform::query()
            ->where('id', $request->integer('platform_id'))
            ->where('user_id', Auth::id())
            ->first();

        if (! $platform) {
            return response()->json([
                'success' => false,
                'message' => __('The selected platform could not be found.'),
            ], 422);
        }

        $agent = SocialMediaAgent::query()
            ->where('id', $request->integer('agent_id'))
            ->where('user_id', Auth::id())
            ->first();

        if (! $agent) {
            return response()->json([
                'success' => false,
                'message' => __('The selected agent could not be found.'),
            ], 422);
        }

        if (! $request->input('scheduled_at')) {
            $request->merge(['scheduled_at' => now()->format('Y-m-d H:i:s')]);
        }

        $scheduledAt = $this->parseDateOrNull($request->input('scheduled_at'));

        //        if ($scheduledAt && $scheduledAt->isPast()) {
        //            return response()->json([
        //                'success' => false,
        //                'message' => __('The scheduled date cannot be in the past.'),
        //            ], 422);
        //        }

        $status = SocialMediaAgentPost::STATUS_SCHEDULED;

        $mediaUrls = collect($request->input('media_urls', []))
            ->filter(fn ($url) => is_string($url) && $url !== '')
            ->values()
            ->all();
        $videoUrls = collect($request->input('video_urls', []))
            ->filter(fn ($url) => is_string($url) && $url !== '')
            ->values()
            ->all();

        $aiMetadata = array_filter([
            'personalized_content' => (bool) $request->boolean('personalized_content'),
            'source'               => 'manual_composer',
        ]);

        $post = SocialMediaAgentPost::create([
            'agent_id'        => $agent->id,
            'platform_id'     => $platform->id,
            'content'         => $request->input('content'),
            'post_type'       => $request->input('post_type'),
            'publishing_type' => $request->input('publishing_type', 'post'),
            'media_urls'      => $mediaUrls,
            'video_urls'      => $videoUrls,
            'video_status'    => count($videoUrls) ? 'completed' : 'none',
            'status'          => $status,
            'scheduled_at'    => $scheduledAt,
            'ai_metadata'     => $aiMetadata,
        ]);

        if ($status === SocialMediaAgentPost::STATUS_SCHEDULED) {
            $this->syncSocialMediaPost($post);
        }

        return response()->json([
            'success' => true,
            'message' => __('Post created successfully.'),
            'post'    => $post->fresh(['agent', 'platform', 'socialPost']),
        ]);
    }

    /**
     * Generate AI content for manual composer
     */
    public function generatePostContent(Request $request): JsonResponse
    {
        $this->validate($request, [
            'platform_id' => 'required|exists:ext_social_media_platforms,id',
            'agent_id'    => 'required|exists:ext_social_media_agents,id',
            'post_type'   => 'nullable|string',
        ]);

        $agent = SocialMediaAgent::query()
            ->where('id', $request->integer('agent_id'))
            ->where('user_id', Auth::id())
            ->first();

        if (! $agent) {
            return response()->json([
                'success' => false,
                'message' => __('The selected agent could not be found.'),
            ], 422);
        }

        $postService = new PostGenerationService;
        $options = [];
        if ($request->filled('post_type')) {
            $options['post_type'] = $request->input('post_type');
        }

        $result = $postService->generatePost($agent, $options);

        if (! data_get($result, 'success')) {
            return response()->json([
                'success' => false,
                'message' => data_get($result, 'error', __('Content could not be generated.')),
            ], 422);
        }

        return response()->json([
            'success'   => true,
            'content'   => $result['content'] ?? '',
            'hashtags'  => $result['hashtags'] ?? [],
            'full_text' => $result['full_text'] ?? '',
            'post_type' => $result['post_type'] ?? 'text',
        ]);
    }

    /**
     * Generate AI image for manual composer
     */
    public function generatePostImage(Request $request): JsonResponse
    {
        $this->validate($request, [
            'content'     => 'required|string|min:10',
            'platform_id' => 'nullable|exists:ext_social_media_platforms,id',
            'agent_id'    => 'required|exists:ext_social_media_agents,id',
        ]);

        $agent = SocialMediaAgent::query()
            ->where('id', $request->integer('agent_id'))
            ->where('user_id', Auth::id())
            ->first();

        if (! $agent) {
            return response()->json([
                'success' => false,
                'message' => __('The selected agent could not be found.'),
            ], 422);
        }

        $imageService = new ImageGenerationService;
        $result = $imageService->generateImageForPost($request->input('content'), [
            'tone'     => $agent->tone,
            'language' => $agent->language,
        ]);

        if (! data_get($result, 'success')) {
            return response()->json([
                'success' => false,
                'message' => data_get($result, 'error', __('Image could not be generated.')),
            ], 422);
        }

        return response()->json([
            'success'    => true,
            'image_url'  => $result['image_url'] ?? null,
            'request_id' => $result['request_id'] ?? null,
            'status'     => $result['status'] ?? null,
        ]);
    }

    /**
     * Generate posts for agent
     */
    public function generatePosts(SocialMediaAgent $agent): RedirectResponse
    {
        $this->authorize('update', $agent);

        $count = $this->generateInitialPosts($agent, 10);

        return back()->with('success', "{$count} posts generated successfully!");
    }

    /**
     * Approve a post (move to ext_social_media_posts)
     */
    public function approvePost(Request $request, SocialMediaAgentPost $post): JsonResponse
    {
        $this->authorize('update', $post->agent);

        DB::beginTransaction();

        try {

            $date = $post->scheduled_at;

            if ($post->scheduled_at instanceof DateTime) {
                $post->markAsScheduled($date);
            } else {
                $date = now();
                $post->markAsScheduled($date);
            }

            // Create in SocialMedia extension's table
            $socialMediaPost = SocialMediaPost::create([
                'user_id'                   => $post->agent->user_id,
                'social_media_platform_id'  => $post->platform_id,
                'content'                   => $post->content,
                'image'                     => $post->media_urls[0] ?? null,
                'post_type'                 => $post->publishing_type ?? PostTypeEnum::Post,
                'status'                    => StatusEnum::scheduled,
                'scheduled_at'              => $date,
            ]);

            // Update agent post with reference
            $post->update([
                'status'           => SocialMediaAgentPost::STATUS_SCHEDULED,
                'platform_post_id' => (string) $socialMediaPost->id,
            ]);

            DB::commit();
            $this->queuePostGeneration($post->agent);

            return response()->json([
                'success' => true,
                'message' => 'Post approved and scheduled!',
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to approve post: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Approve multiple posts
     */
    public function approveBulk(Request $request, SocialMediaAgent $agent): JsonResponse
    {
        $this->authorize('update', $agent);

        $request->validate([
            'post_ids'   => 'required|array',
            'post_ids.*' => 'exists:ext_social_media_agent_posts,id',
        ]);

        $approved = 0;
        $failed = 0;

        foreach ($request->input('post_ids') as $postId) {
            $post = SocialMediaAgentPost::find($postId);

            if ($post && $post->agent_id === $agent->id) {
                try {
                    DB::beginTransaction();

                    $post->markAsScheduled($post->scheduled_at);

                    $socialMediaPost = SocialMediaPost::create([
                        'user_id'                  => $post->agent->user_id,
                        'social_media_platform_id' => $post->platform_id,
                        'content'                  => $post->content,
                        'image'                    => $post->media_urls[0] ?? null,
                        'post_type'                => $post->publishing_type ?? PostTypeEnum::Post,
                        'status'                   => StatusEnum::scheduled,
                        'scheduled_at'             => $post->scheduled_at,
                    ]);

                    $post->update([
                        'status'           => SocialMediaAgentPost::STATUS_SCHEDULED,
                        'platform_post_id' => (string) $socialMediaPost->id,
                    ]);

                    DB::commit();
                    $approved++;
                } catch (Exception $e) {
                    DB::rollBack();
                    $failed++;
                }
            }
        }

        if ($approved > 0) {
            $this->queuePostGeneration($agent);
        }

        return response()->json([
            'success' => true,
            'message' => "{$approved} posts approved, {$failed} failed.",
        ]);
    }

    /**
     * Reject/delete a post
     */
    public function rejectPost(SocialMediaAgentPost $post): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'success' => false,
                'message' => __('This action is not allowed in the demo mode.'),
            ], 403);
        }

        $this->authorize('update', $post->agent);

        $post->delete();

        return response()->json([
            'success' => true,
            'message' => 'Post rejected and deleted.',
        ]);
    }

    /**
     * Duplicate a post
     */
    public function duplicatePost(SocialMediaAgentPost $post): JsonResponse
    {
        $this->authorize('update', $post->agent);

        $duplicate = $post->replicate([
            'status',
            'scheduled_at',
            'published_at',
            'approved_at',
            'platform_post_id',
            'platform_response',
            'image_request_id',
            'image_status',
        ]);

        $duplicate->status = SocialMediaAgentPost::STATUS_DRAFT;
        $duplicate->scheduled_at = null;
        $duplicate->published_at = null;
        $duplicate->approved_at = null;
        $duplicate->platform_post_id = null;
        $duplicate->platform_response = null;
        $duplicate->image_request_id = null;
        $duplicate->image_status = 'none';
        $duplicate->save();

        return response()->json([
            'success' => true,
            'message' => __('Post duplicated successfully.'),
            'post'    => $duplicate->fresh(['agent', 'platform']),
        ]);
    }

    /**
     * Upload images (API)
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $files = $request->file('image');

        if (empty($files)) {
            return response()->json([
                'success' => false,
                'message' => 'Please select a file',
            ]);
        }

        $files = is_array($files) ? $files : [$files];
        $stored = [];

        foreach ($files as $uploadedFile) {
            if (! $uploadedFile instanceof UploadedFile) {
                continue;
            }

            if (! isFileSecure($uploadedFile)) {
                continue;
            }

            $userId = auth()?->id();

            $fileDirectory = match ($uploadedFile->getClientMimeType()) {
                'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp' => 'images',
                'audio/mpeg', 'audio/wav', 'audio/ogg'                            => 'voices',
                default                                                           => 'others',
            };

            // Always relative, never full URL
            $baseDirectory = $userId
                ? "/uploads/media/{$fileDirectory}/u-{$userId}/"
                : '/uploads/guest/';

            $absoluteBase = public_path($baseDirectory);

            if (! File::exists($absoluteBase)) {
                File::makeDirectory($absoluteBase, 0755, true);
            }

            $originalName = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
            $extension = strtolower($uploadedFile->guessExtension() ?: 'bin');

            if ($extension === 'bin') {
                continue;
            }

            $safeBaseName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $originalName);
            $fileName = "{$safeBaseName}.{$extension}";
            $absolutePath = $absoluteBase . $fileName;

            // If file exists, compare hashes
            if (File::exists($absolutePath)) {
                $existingHash = sha1_file($absolutePath);
                $currentHash = sha1_file($uploadedFile->getRealPath());

                if ($existingHash === $currentHash) {
                    // Return existing file (relative)
                    $stored[] = $baseDirectory . $fileName;

                    continue;
                }

                // Different file, same name → append timestamp
                $timestamp = now()->format('YmdHis');
                $fileName = "{$safeBaseName}_{$timestamp}.{$extension}";
                $absolutePath = $absoluteBase . $fileName;
            }

            // Move file into place
            $uploadedFile->move($absoluteBase, $fileName);

            $stored[] = $baseDirectory . $fileName;
        }

        return response()->json([
            'success' => true,
            'items'   => $stored,
        ]);
    }

    /**
     * Update a post
     */
    public function updatePost(Request $request, SocialMediaAgentPost $post): JsonResponse
    {
        $this->authorize('update', $post->agent);

        if ($post->status === SocialMediaAgentPost::STATUS_PUBLISHED) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update a published post.',
            ], 422);
        }

        $validated = $request->validate([
            'media_urls'   => 'nullable|array',
            'media_urls.*' => 'string|max:255',
            'hashtags'     => 'nullable|array',
            'hashtags.*'   => 'string|max:100',
            'ai_metadata'  => 'nullable|array',
            'platform_id'  => 'required|exists:ext_social_media_platforms,id',
            'post_type'    => 'nullable|string',
            'scheduled_at' => 'nullable',
            'content'      => 'required|string',
            'status'       => 'nullable|in:draft,scheduled',
        ]);

        if (isset($validated['platform_id'])) {
            $platformBelongsToUser = SocialMediaPlatform::query()
                ->where('id', $validated['platform_id'])
                ->where('user_id', $post->agent->user_id)
                ->exists();

            if (! $platformBelongsToUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not allowed to use this platform.',
                ], 422);
            }
        }

        if (! empty($validated['scheduled_at'])) {
            try {
                $scheduledAt = Carbon::parse($validated['scheduled_at']);
            } catch (Exception) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid scheduled date.',
                ], 422);
            }

            if ($scheduledAt->isPast()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Scheduled time must be in the future.',
                ], 422);
            }

            $validated['scheduled_at'] = $scheduledAt;
            $validated['status'] = SocialMediaAgentPost::STATUS_SCHEDULED;
        }

        if (empty($validated['scheduled_at']) && isset($validated['status'])) {
            unset($validated['status']);
        }

        $post->update(array_filter($validated, fn ($value) => $value !== null));

        if ($post->status === SocialMediaAgentPost::STATUS_SCHEDULED) {

            $this->syncSocialMediaPost($post);
        }

        return response()->json([
            'success' => true,
            'message' => 'Post updated successfully.',
            'post'    => $post->fresh(['agent', 'platform']),
        ]);
    }

    // ==================== HELPER METHODS ====================

    protected function resolveAgentForPlatform(int $platformId): ?SocialMediaAgent
    {
        return SocialMediaAgent::query()
            ->where('user_id', Auth::id())
            ->whereJsonContains('platform_ids', $platformId)
            ->first();
    }

    protected function queuePostGeneration(SocialMediaAgent $agent): void
    {
        SocialMediaAgentGenerationCache::forgetForUser($agent->user_id);

        $stats = SocialMediaAgentGenerationCache::computePostStats($agent);
        $plannedGenerationCount = $this->estimatePlannedGenerationCount($agent);

        $agent->update([
            'post_generation_status' => array_merge($agent->post_generation_status ?? [], [
                'status'    => 'queued',
                'queued_at' => now()->toDateTimeString(),
            ]),
        ]);

        SocialMediaAgentGenerationCache::mark($agent, 'queued', [
            'queued_at'           => now()->toIso8601String(),
            'generated_count'     => 0,
            'failed_count'        => 0,
            'total_requested'     => $plannedGenerationCount,
            'planned_posts_count' => $plannedGenerationCount,
        ] + $stats);
    }

    public function getGenerationStatus(Request $request): JsonResponse
    {
        $userId = Auth::id();
        $status = SocialMediaAgentGenerationCache::getForUser($userId);

        $agent = null;
        $agentIdFromStatus = (int) data_get($status, 'agent_id');

        if ($agentIdFromStatus > 0) {
            $agent = SocialMediaAgent::query()
                ->where('user_id', $userId)
                ->where('id', $agentIdFromStatus)
                ->first();
        }

        if (! $agent) {
            $agent = SocialMediaAgent::query()
                ->where('user_id', $userId)
                ->orderByDesc('created_at')
                ->first();
        }

        if (! $agent) {
            return response()->json([
                'success' => false,
                'message' => __('Agent not found.'),
            ], 404);
        }

        if (! $status) {
            $status = [
                'agent_id'        => $agent->id,
                'status'          => data_get($agent->post_generation_status, 'status', 'idle'),
                'updated_at'      => data_get($agent->post_generation_status, 'updated_at'),
                'generated_count' => (int) data_get($agent->post_generation_status, 'generated_count', 0),
            ];
        }

        if (! array_key_exists('generated_count', $status)) {
            $status['generated_count'] = (int) data_get($agent->post_generation_status, 'generated_count', 0);
        }

        $stats = SocialMediaAgentGenerationCache::computePostStats($agent);
        $pendingCount = $stats['pending_posts_count'];
        $scheduledCount = $stats['scheduled_posts_count'];
        $totalCount = $stats['total_posts_count'];
        $status = array_merge($status, $stats);
        $plannedCount = (int) data_get($status, 'total_requested', data_get($status, 'planned_posts_count', 0));

        return response()->json([
            'success'               => true,
            'status'                => $status,
            'total_posts_count'     => $totalCount,
            'pending_posts_count'   => $pendingCount,
            'scheduled_posts_count' => $scheduledCount,
            'generated_posts_count' => (int) data_get($status, 'generated_count', 0),
            'planned_posts_count'   => $plannedCount,
            'ready_text_template'   => __(':generated of :total posts are ready for review.'),
        ]);
    }

    private function parseDateOrNull(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Generate initial posts for a newly created agent
     */
    protected function generateInitialPosts(SocialMediaAgent $agent, int $count = 7): int
    {
        $postService = new PostGenerationService;
        $generated = 0;

        // Generate posts for each platform
        foreach ($agent->platform_ids as $platformId) {
            for ($i = 0; $i < $count; $i++) {
                $post = $postService->generatePost($agent);

                if ($post['success']) {
                    $scheduledAt = $this->calculateNextScheduleTime($agent, $i);

                    $mediaUrls = [];
                    $imageRequestId = $post['image_request_id'] ?? data_get($post, 'metadata.image_request_id');
                    $imageStatus = $post['image_status'] ?? data_get($post, 'metadata.image_status', 'none');

                    if (! empty($post['image_url'])) {
                        $mediaUrls[] = $post['image_url'];
                        $imageStatus = 'completed';
                    } elseif ($imageRequestId) {
                        $imageStatus = $imageStatus !== 'none' ? $imageStatus : 'pending';
                    } else {
                        $imageStatus = 'none';
                    }

                    SocialMediaAgentPost::create([
                        'agent_id'         => $agent->id,
                        'platform_id'      => $platformId,
                        'content'          => $post['full_text'],
                        'post_type'        => $post['post_type'] ?? 'text',
                        'media_urls'       => $mediaUrls,
                        'image_request_id' => $imageRequestId,
                        'image_status'     => $imageStatus,
                        'hashtags'         => $post['hashtags'] ?? [],
                        'ai_metadata'      => $post['metadata'] ?? [],
                        'status'           => SocialMediaAgentPost::STATUS_DRAFT,
                        'scheduled_at'     => $scheduledAt,
                    ]);

                    $generated++;
                }

                // Avoid rate limiting
                usleep(500000); // 0.5 seconds
            }
        }

        return $generated;
    }

    protected function syncSocialMediaPost(SocialMediaAgentPost $post): void
    {
        $post->loadMissing(['agent', 'platform']);

        if (! $post->scheduled_at || ! $post->platform_id) {
            return;
        }

        if (! $post->agent || ! $post->platform) {
            return;
        }

        $payload = [
            'agent_id'                   => $post->agent_id,
            'user_id'                    => $post->agent->user_id,
            'social_media_platform_id'   => $post->platform_id,
            'social_media_platform'      => $post->platform->platform ?? null,
            'content'                    => $post->content,
            'image'                      => $post->media_urls[0] ?? null,
            'video'                      => $post->video_urls[0] ?? null,
            'post_type'                  => $post->publishing_type ?? PostTypeEnum::Post,
            'status'                     => StatusEnum::scheduled,
            'scheduled_at'               => $post->scheduled_at,
            'hashtags'                   => $post->hashtags,
            'social_media_agent_post_id' => $post->id,
        ];

        $socialMediaPost = null;

        if ($post->platform_post_id) {
            $socialMediaPost = SocialMediaPost::find($post->platform_post_id);
        }

        if ($socialMediaPost) {
            $socialMediaPost->update($payload);
        } else {
            $socialMediaPost = SocialMediaPost::create($payload);
        }

        if ($socialMediaPost && (string) $socialMediaPost->id !== $post->platform_post_id) {
            $post->platform_post_id = (string) $socialMediaPost->id;
            if (method_exists($post, 'saveQuietly')) {
                $post->saveQuietly();
            } else {
                $post->save();
            }
        }
    }

    /**
     * Calculate next schedule time based on agent settings
     */
    protected function calculateNextScheduleTime(SocialMediaAgent $agent, int $offset = 0): DateTime
    {
        $now = now();
        $scheduleDays = $agent->schedule_days ?? ['Monday'];
        $scheduleTimes = $agent->schedule_times ?? [['start' => '10:00', 'end' => '12:00']];

        // Simple algorithm: spread posts across configured days and times
        $dayIndex = $offset % count($scheduleDays);
        $timeIndex = intdiv($offset, count($scheduleDays)) % count($scheduleTimes);

        $targetDay = $scheduleDays[$dayIndex];
        $targetTime = $scheduleTimes[$timeIndex]['start'];

        // Find next occurrence of target day
        $date = $now->copy();
        while ($date->format('l') !== $targetDay) {
            $date->addDay();
        }

        // Set time
        [$hour, $minute] = explode(':', $targetTime);
        $date->setTime((int) $hour, (int) $minute);

        // If in the past, move to next week
        if ($date->isPast()) {
            $date->addWeek();
        }

        return $date->toDateTime();
    }

    /**
     * Webhook endpoint for Fal.ai image generation results
     */
    public function falWebhook(Request $request): JsonResponse
    {
        try {
            Log::info('Fal.ai webhook received', $request->all());

            $requestId = $request->input('request_id');
            $status = $request->input('status');

            if (! $requestId) {
                return response()->json(['error' => 'Missing request_id'], 400);
            }

            // Find post with this request_id
            $post = SocialMediaAgentPost::where('image_request_id', $requestId)->first();

            if (! $post) {
                Log::warning("Post not found for request_id: {$requestId}");

                return response()->json(['error' => 'Post not found'], 404);
            }

            if ($status === 'COMPLETED') {
                $imageUrl = $request->input('images.0.url');

                if ($imageUrl) {
                    // Download and store the image
                    $imageService = new ImageGenerationService;
                    $storedPath = $this->downloadAndStoreImage($imageUrl);

                    // Update post with image
                    $post->update([
                        'media_urls'   => [$storedPath],
                        'image_status' => 'completed',
                    ]);

                    Log::info("Image generated successfully for post {$post->id}");
                }
            } elseif ($status === 'FAILED') {
                $post->update([
                    'image_status' => 'failed',
                ]);

                Log::error("Image generation failed for post {$post->id}");
            }

            return response()->json(['success' => true]);
        } catch (Exception $e) {
            Log::error('Fal.ai webhook error: ' . $e->getMessage());

            return response()->json(['error' => 'Internal error'], 500);
        }
    }

    public function getVideoStatus(Request $request, VideoGenerationService $videoService): JsonResponse
    {
        $validated = $request->validate([
            'request_id' => 'required|string',
        ]);

        $requestId = $validated['request_id'];

        $post = SocialMediaAgentPost::query()
            ->where('video_request_id', $requestId)
            ->whereHas('agent', fn ($q) => $q->where('user_id', Auth::id()))
            ->first();

        if (! $post) {
            return response()->json([
                'success' => false,
                'status'  => 'not_found',
                'message' => __('Video request not found.'),
            ], 404);
        }

        $result = $videoService->checkStatus($requestId);
        $status = $result['status'] ?? 'pending';

        if (! $result['success']) {
            if ($status === 'failed') {
                $post->update(['video_status' => 'failed']);
            }

            return response()->json([
                'success' => false,
                'status'  => $status,
                'post'    => $post->fresh(['platform', 'agent']),
            ]);
        }

        $updates = [
            'video_status' => $status,
        ];

        if (! empty($result['video_url'])) {
            $videoUrls = $post->video_urls ?? [];
            if (! in_array($result['video_url'], $videoUrls, true)) {
                $videoUrls[] = $result['video_url'];
            }

            $updates['video_urls'] = $videoUrls;
            $updates['video_status'] = 'completed';
        }

        $post->fill($updates);
        $post->save();

        return response()->json([
            'success' => true,
            'status'  => $updates['video_status'],
            'post'    => $post->fresh(['platform', 'agent']),
        ]);
    }

    /**
     * Download and store image from URL
     */
    protected function downloadAndStoreImage(string $url): string
    {
        $imageContents = file_get_contents($url);
        $filename = 'social-media-agent/' . uniqid('post_', true) . '.png';

        Storage::disk('public')->put($filename, $imageContents);

        return Storage::disk('public')->url($filename);
    }

    private function estimatePlannedGenerationCount(SocialMediaAgent $agent): int
    {
        $targetPerPlatform = $this->estimateTargetPostsPerPlatform($agent);
        $needs = $this->estimatePostsNeededByPlatform($agent, $targetPerPlatform);

        return array_sum($needs);
    }

    private function estimateTargetPostsPerPlatform(SocialMediaAgent $agent): int
    {
        $dailyPostCount = max(1, (int) $agent->daily_post_count);
        $scheduleDays = $agent->schedule_days ?? [];
        $daysSelected = max(1, count($scheduleDays));
        $planType = strtolower((string) data_get($agent->settings, 'plan_type', 'weekly'));

        $multiplier = $planType === 'monthly' ? 4 : 1;

        return $dailyPostCount * $daysSelected * $multiplier;
    }

    private function estimatePostsNeededByPlatform(SocialMediaAgent $agent, int $targetPerPlatform): array
    {
        $needs = [];

        foreach ((array) ($agent->platform_ids ?? []) as $platformId) {
            $existing = $this->countExistingFuturePosts($agent, (int) $platformId);
            $needed = max(0, $targetPerPlatform - $existing);

            if ($needed > 0) {
                $needs[$platformId] = $needed;
            }
        }

        return $needs;
    }

    private function countExistingFuturePosts(SocialMediaAgent $agent, int $platformId): int
    {
        return $agent->posts()
            ->where('platform_id', $platformId)
            ->whereIn('status', [
                SocialMediaAgentPost::STATUS_DRAFT,
                SocialMediaAgentPost::STATUS_SCHEDULED,
            ])
            ->where(function ($query) {
                $query->whereNull('scheduled_at')
                    ->orWhere('scheduled_at', '>=', now());
            })
            ->count();
    }

    private function ensureAgentCreationAllowed(): ?JsonResponse
    {
        $limit = $this->getSocialMediaAgentLimitValue('agents');

        if ($limit === 0) {
            return $this->socialMediaAgentLimitErrorResponse(__('Your current plan does not allow creating AI Social Media Agents.'));
        }

        if ($limit > 0) {
            $agentCount = SocialMediaAgent::query()
                ->where('user_id', Auth::id())
                ->count();

            if ($agentCount >= $limit) {
                return $this->socialMediaAgentLimitErrorResponse(__('You have reached the maximum number of agents included in your plan.'));
            }
        }

        return null;
    }

    private function ensureMonthlyPostCreationAllowed(): ?JsonResponse
    {
        $limit = $this->getSocialMediaAgentLimitValue('monthly_posts');

        if ($limit === 0) {
            return $this->socialMediaAgentLimitErrorResponse(__('Your current plan does not allow creating posts with the AI Social Media Agent.'));
        }

        if ($limit > 0) {
            $now = Carbon::now();
            $postsThisMonth = SocialMediaAgentPost::query()
                ->whereHas('agent', fn ($query) => $query->where('user_id', Auth::id()))
                ->whereBetween('created_at', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])
                ->count();

            if ($postsThisMonth >= $limit) {
                return $this->socialMediaAgentLimitErrorResponse(__('You have reached your monthly Social Media Agent post limit.'));
            }
        }

        return null;
    }

    private function getSocialMediaAgentLimitValue(string $key): int
    {
        $plan = Auth::user()?->relationPlan;
        $limits = (array) ($plan?->social_media_agent_limits ?? []);
        $value = $limits[$key] ?? -1;

        return is_numeric($value) ? (int) $value : -1;
    }

    private function socialMediaAgentLimitErrorResponse(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], 422);
    }
}
