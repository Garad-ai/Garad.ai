<?php

namespace App\Extensions\BlogPilot\System\Http\Controllers;

use App\Extensions\BlogPilot\System\Models\BlogPilot;
use App\Extensions\BlogPilot\System\Models\BlogPilotPost;
use App\Extensions\BlogPilot\System\Services\PostGenerationService;
use App\Extensions\BlogPilot\System\Support\BlogPilotGenerationCache;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\Integration\Integration;
use App\Models\Integration\UserIntegration;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BlogPilotController extends Controller
{
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
        $postsQuery = BlogPilotPost::query()
            ->where('user_id', $userId);

        // Get counts efficiently
        $pending_posts_count = (clone $postsQuery)
            ->where('status', BlogPilotPost::STATUS_DRAFT)
            ->count();

        $scheduled_posts_count = (clone $postsQuery)
            ->where('status', BlogPilotPost::STATUS_SCHEDULED)
            ->count();

        $total_posts_count = $postsQuery->count();

        // Get paginated posts
        $posts = BlogPilotPost::query()
            ->where('user_id', $userId)
            ->orderBy('updated_at', 'desc')
            ->paginate(10);

        $agentsQuery = BlogPilot::query()
            ->where('user_id', $userId);

        $defaultAgent = (clone $agentsQuery)->orderByDesc('created_at')->first();

        $agentIds = (clone $agentsQuery)
            ->pluck('id');

        $new_posts = (clone $postsQuery)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        $new_impressions = 0;

        $generationStatus = $defaultAgent ? BlogPilotGenerationCache::currentStatus($defaultAgent) : ['status' => 'idle'];

        return view('blogpilot::dashboard.index', [
            'pending_posts_count'   => $pending_posts_count,
            'scheduled_posts_count' => $scheduled_posts_count,
            'total_posts_count'     => $total_posts_count,
            'posts'                 => $posts,
            'platforms'             => '',
            'new_posts'             => $new_posts,
            'new_impressions'       => '',
            'generation_status'     => $generationStatus,
            'defaultAgent'          => $defaultAgent,
        ]);
    }

    public function postItems(Request $request): View
    {
        $userId = Auth::id();

        // Parse filters - convert comma-separated strings to arrays
        $filters = collect($request->except(['page', 'post_style', 'per_page', 'id', 'start_date', 'end_date', 'date_column', 'sort_by', 'sort_direction']))
            ->map(fn ($value) => is_string($value) && str_contains($value, ',')
                ? explode(',', $value)
                : $value
            )
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->toArray();

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
        $allowedSortColumns = ['created_at', 'scheduled_at', 'status', 'title'];
        if (! in_array($sortBy, $allowedSortColumns, true)) {
            $sortBy = 'created_at';
        }

        // Validate sort direction
        $sortDirection = strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';

        $baseQuery = fn () => BlogPilotPost::query()
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
            ->when($startDate, fn ($q) => $q->where($dateColumn, '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where($dateColumn, '<=', $endDate));

        $query = $baseQuery()
            ->with(['agent', 'agent.user']);

        // For content sorting, use LEFT() to sort by first 100 characters for efficiency
        if ($sortBy === 'title') {
            $query->orderByRaw('LEFT(title, 100) ' . $sortDirection);
        } else {
            $query->orderBy($sortBy, $sortDirection);
        }

        $posts = $query->paginate($perPage)->appends($request->except('page'));

        $view = 'blogpilot::components.posts.carousel.post-items';

        if (! empty($postStyle)) {
            $view = 'blogpilot::components.posts.' . $postStyle . '.post-items';
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

        $agents = BlogPilot::query()
            ->where('user_id', Auth::id())
            ->with('posts')
            ->latest()
            ->get();

        return view('blogpilot::calendar.index', [
            'agents' => $agents,
        ]);
    }

    /**
     * Posts page
     */
    public function posts(): View
    {
        $userId = Auth::id();

        $posts = BlogPilotPost::query()
            ->orderBy('scheduled_at', 'desc')
            ->paginate(999);

        $agents = BlogPilot::query()
            ->where('user_id', $userId)
            ->get();

        return view('blogpilot::posts.index', [
            'posts'         => $posts,
            'platformEnums' => '',
            'platforms'     => '',
            'agents'        => $agents,
        ]);
    }

    /**
     * Get pending posts count (API)
     */
    public function getPendingCount(): \Illuminate\Http\JsonResponse
    {
        $userId = Auth::id();

        $count = BlogPilotPost::query()
            ->where('status', 0)
            ->where('user_id', $userId)
            ->count();

        return response()->json([
            'success' => true,
            'count'   => $count,
        ]);
    }

    /**
     * Analytics page
     */
    public function analytics(): View
    {
        $userId = Auth::id();

        $baseQuery = BlogPilotPost::query()
            ->where('user_id', $userId);

        $stats = [
            'total_posts' => (clone $baseQuery)->count(),

            'draft_posts' => (clone $baseQuery)
                ->where('status', BlogPilotPost::STATUS_DRAFT)
                ->count(),

            'scheduled_posts' => (clone $baseQuery)
                ->where('status', BlogPilotPost::STATUS_SCHEDULED)
                ->count(),

            'published_posts' => (clone $baseQuery)
                ->where('status', BlogPilotPost::STATUS_PUBLISHED)
                ->count(),

            'created_today' => (clone $baseQuery)
                ->whereDate('created_at', today())
                ->count(),
        ];

        $agents = BlogPilot::query()->get();

        $monthRange = $this->buildMonthRange(12);
        $publishedChartData = $this->buildPublishedPostsChartData($userId, $agents, $monthRange);
        $newsFeed = $this->buildAnalyticsNews($userId, $stats);

        return view('blogpilot::analytics.index', [
            'stats'              => $stats,
            'agents'             => $agents,
            'news'               => $newsFeed,
            'publishedChartData' => $publishedChartData,
            'publishedMonths'    => $monthRange,
        ]);
    }

    private function buildAnalyticsNews(int $userId, array $stats): array
    {
        $items = [];

        if (($stats['created_today'] ?? 0) > 0) {
            $items[] = __(':count posts were created today.', ['count' => $stats['scheduled_posts']]);
        }

        if (($stats['total_posts'] ?? 0) > 0) {
            $items[] = __('Total of :count posts are created.', ['count' => $stats['total_posts']]);
        }

        if (($stats['published_posts'] ?? 0) > 0) {
            $items[] = __('Total of :count posts were published.', ['count' => $stats['published_posts']]);
        }

        if (($stats['scheduled_posts'] ?? 0) > 0) {
            $items[] = __('Total of :count posts were scheduled.', ['count' => $stats['scheduled_posts']]);
        }

        $recentPost = BlogPilotPost::query()
            ->where('user_id', $userId)
            ->latest()
            ->first();

        if ($recentPost) {
            $items[] = __('The last post was created :date.', ['date' => optional($recentPost->created_at)->diffForHumans()]);
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

    private function buildPublishedPostsChartData(int $userId, $agents, array $months): array
    {
        [$months, $monthKeys, $startDate, $endDate] = $this->prepareMonthMetadata($months);

        $records = BlogPilotPost::query()
            ->where('user_id', $userId)
            ->whereBetween('scheduled_at', [$startDate, $endDate])
            ->get();

        $recordsByAgent = $records->groupBy('agent_id');

        $todayTotals = BlogPilotPost::query()
            ->where('user_id', $userId)
            ->whereNotNull('agent_id')
            ->whereDate('scheduled_at', now()->toDateString())
            ->selectRaw('agent_id, COUNT(*) as total')
            ->groupBy('agent_id')
            ->pluck('total', 'agent_id');

        return $this->buildChartSeriesFromRecords(
            $agents,
            $monthKeys,
            $recordsByAgent,
            $todayTotals,
            'today_posts'
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

    private function buildChartSeriesFromRecords($agents, array $monthKeys, $recordsByAgent, $currentTotals, string $statKey, bool $asFloat = false): array
    {
        $allSeries = array_fill(0, count($monthKeys), 0);
        $chartData = [];

        foreach ($agents as $agent) {
            $agentId = $agent->id;
            $agentName = $agent->name ?: ('agent_' . $agentId);

            $agentData = $recordsByAgent
                ->get($agentId, collect())
                ->groupBy(function ($row) {
                    return Carbon::parse($row->scheduled_at)->format('Y-m');
                })
                ->map(function ($rows) {
                    return [
                        'total' => $rows->count(),
                    ];
                });

            $seriesData = [];

            foreach ($monthKeys as $index => $key) {
                $value = (float) data_get($agentData->get($key), 'total', 0);
                $seriesData[] = $value;
                $allSeries[$index] += $value;
            }

            $chartData[] = [
                'label'        => Str::headline($agentName),
                'id'           => $agentId,
                'chart_series' => [
                    'name'   => $agentName,
                    'data'   => $seriesData,
                    'hidden' => true,
                ],
                $statKey      => $asFloat
                    ? round((float) ($currentTotals[$agentId] ?? 0), 2)
                    : (int) ($currentTotals[$agentId] ?? 0),
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
     * Show create wizard - Step 1: Platform Selection
     */
    public function create(): View
    {
        return view('blogpilot::create.index');
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
            'topic_options'             => 'nullable|array',
            'topic_options.*'           => 'string',
            'selected_topics'           => 'nullable|array|min:1',
            'selected_topics.*'         => 'string',
            'post_types'                => 'required|array|min:1',
            'post_types.*'              => 'string',
            'has_image'                 => 'nullable|in:0,1',
            'has_emoji'                 => 'nullable|in:0,1',
            'has_web_search'            => 'nullable|in:0,1',
            'has_keyword_search'        => 'nullable|in:0,1',
            'language'                  => 'required|string',
            'article_length'            => 'required|string',
            'tone'                      => 'required|string',
            'frequency'                 => 'required|in:daily,weekly,monthly',
            'daily_post_count'          => 'required|integer|min:1|max:10',
            'schedule_days'             => 'nullable|array',
            'schedule_days.*'           => 'string',
            'schedule_times'            => 'nullable',
        ]);

        // Decode JSON strings
        $scheduleDaysInput = $validated['schedule_days'] ?? [];
        $scheduleTimes = $this->sanitizeScheduleTimes($this->decodeJsonField($validated['schedule_times'] ?? []));

        // Convert string booleans to actual booleans
        $hasImage = ($validated['has_image'] ?? '0') == '1';
        $hasEmoji = ($validated['has_emoji'] ?? '0') == '1';
        $hasWebSearch = ($validated['has_web_search'] ?? '0') == '1';
        $hasKeywordSearch = ($validated['has_keyword_search'] ?? '0') == '1';
        $normalizedScheduleDays = $this->normalizeScheduleDays($scheduleDaysInput);

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

        $agent = BlogPilot::create([
            'user_id'                => Auth::id(),
            'name'                   => $validated['name'],
            'topic_options'          => $validated['topic_options'],
            'selected_topics'        => $validated['selected_topics'],
            'post_types'             => $validated['post_types'],
            'has_image'              => $hasImage,
            'has_emoji'              => $hasEmoji,
            'has_web_search'         => $hasWebSearch,
            'has_keyword_search'     => $hasKeywordSearch,
            'language'               => $validated['language'],
            'article_length'         => $validated['article_length'],
            'tone'                   => $validated['tone'],
            'frequency'              => $validated['frequency'],
            'daily_post_count'       => $validated['daily_post_count'],
            'schedule_days'          => $normalizedScheduleDays,
            'schedule_times'         => $scheduleTimes,
            'is_active'              => true,
            'post_generation_status' => [
                'status' => 'idle',
            ],
        ]);

        $this->queuePostGeneration($agent);

        return response()->json([
            'success'  => true,
            'agent_id' => $agent->id,
            'message'  => __('Agent created successfully! Posts are being generated in the background.'),
        ]);
    }

    public function agents(): View
    {
        $agents = BlogPilot::query()
            ->where('user_id', Auth::id())
            ->get();

        $determineAgentOfMonth = $this->determineAgentOfMonth($agents);

        return view('blogpilot::agents.index', [
            'agents'                => $agents,
            'determineAgentOfMonth' => $determineAgentOfMonth,
        ]);
    }

    /**
     * Show edit form
     */
    public function edit(BlogPilot $agent): View
    {
        $this->authorize('update', $agent);

        return view('blogpilot::agent.edit', [
            'agent'     => $agent,
        ]);
    }

    protected function determineAgentOfMonth(Collection $agents): ?BlogPilot
    {
        if ($agents->isEmpty()) {
            return null;
        }

        $activeAgents = $agents->filter->is_active;
        $pool = $activeAgents->isNotEmpty() ? $activeAgents : $agents;

        return $pool
            ->sortByDesc(function (BlogPilot $agent) {
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
    public function update(Request $request, BlogPilot $agent): RedirectResponse
    {
        if (Helper::appIsDemo()) {
            return redirect()
                ->route('dashboard.user.blogpilot.agent.edit', $agent->id)
                ->with([
                    'type'    => 'error',
                    'message' => __('This action is not allowed in the demo mode.'),
                ]);
        }

        $this->authorize('update', $agent);

        $validated = $request->validate([
            'name'               => 'required|string|max:255',
            'selected_topics'    => 'required|array|min:1',
            'post_types'         => 'required|array|min:1',
            'is_active'          => 'boolean',
            'daily_post_count'   => 'required|integer|min:1|max:10',
        ]);

        $validated['is_active'] = (bool) $request->has('is_active');
        $validated['has_image'] = (bool) $request->has('has_image');
        $validated['has_emoji'] = (bool) $request->has('has_emoji');
        $validated['has_web_search'] = (bool) $request->has('has_web_search');
        $validated['has_keyword_search'] = (bool) $request->has('has_keyword_search');

        $agent->update($validated);

        return redirect()
            ->route('dashboard.user.blogpilot.agent.edit', $agent->id)
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
    public function destroy(BlogPilot $agent): RedirectResponse
    {
        if (Helper::appIsDemo()) {
            return redirect()
                ->route('dashboard.user.blogpilot.agent.agents')
                ->with([
                    'type'    => 'error',
                    'message' => __('This action is not allowed in the demo mode.'),
                ]);
        }

        $this->authorize('delete', $agent);

        $agent->delete();

        return redirect()
            ->route('dashboard.user.blogpilot.agent.agents');
    }

    // ==================== WIZARD AJAX ENDPOINTS ====================

    /**
     * AJAX: Generate topics (step 2)
     */
    public function generateTopics(Request $request): JsonResponse
    {
        $topic = trim((string) $request->input('topic'));

        $request->validate([
            'topic' => 'required|string',
        ]);

        $postService = new PostGenerationService;
        $topics = $postService->generateTopics($topic);

        return response()->json([
            'success' => true,
            'topics'  => $topics,
        ]);
    }

    // ==================== POST MANAGEMENT ====================

    /**
     * Get posts (API)
     */
    public function getPosts(Request $request): JsonResponse
    {
        $userId = Auth::id();
        $query = BlogPilotPost::query()
            ->where('user_id', $userId)
            ->whereDate('scheduled_at', '>=', $this->parseDateOrNull($request->input('start_date')))
            ->whereDate('scheduled_at', '<=', $this->parseDateOrNull($request->input('end_date')));

        $perPage = (int) $request->integer('per_page', 10);
        $posts = $query->paginate($perPage)->appends($request->except('page'));

        return response()->json([
            'success' => true,
            'posts'   => $posts,
        ]);
    }

    /**
     * Edit a post
     */
    public function editPost(BlogPilotPost $post): View
    {
        $integrations = Auth::user()->getAttribute('integrations');

        $wordpress = UserIntegration::query()
            ->where('user_id', Auth::user()->id)->first();

        if (isset($wordpress)) {
            $wordpressExist = (bool) $wordpress->credentials['domain']['value'];
        } else {
            $wordpressExist = false;
        }

        $checkIntegration = Integration::query()->whereHas('hasExtension')->count();

        return view('blogpilot::components.edit-post', [
            'post'             => $post,
            'wordpressExist'   => $wordpressExist,
            'checkIntegration' => $checkIntegration,
            'integrations'     => $integrations,
        ]);
    }

    /**
     * Publish a post with integrations
     */
    public function publishPostAjax(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'success' => false,
                'message' => trans('This feature is disabled in demo mode.'),
            ], 403);
        }

        $validated = $request->validate([
            'id' => 'required',
        ]);

        $this->publishPost($validated['id']);

        $post = BlogPilotPost::query()->find($validated['id']);
        $message = $post?->status === BlogPilotPost::STATUS_SCHEDULED
            ? trans('Post scheduled successfully')
            : trans('Post published successfully');

        return response()->json([
            'success' => true,
            'message' => $message,
            'status'  => $post?->status,
        ]);
    }

    /**
     * Reject/delete a post
     */
    public function rejectPost(BlogPilotPost $post): JsonResponse
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
    public function duplicatePost(BlogPilotPost $post): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'success' => false,
                'message' => __('This action is not allowed in the demo mode.'),
            ], 403);
        }

        $this->authorize('update', $post->agent);

        $duplicate = $post->replicate([
            'status',
            'scheduled_at',
            'published_at',
        ]);

        $duplicate->status = BlogPilotPost::STATUS_DRAFT;
        $duplicate->scheduled_at = null;
        $duplicate->published_at = null;
        $duplicate->save();

        return response()->json([
            'success' => true,
            'message' => __('Post duplicated successfully.'),
            'post'    => $duplicate->fresh(['agent']),
        ]);
    }

    /**
     * Duplicate a post
     */
    public function updatePost(Request $request)
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'success' => false,
                'message' => __('This action is not allowed in the demo mode.'),
            ], 403);
        }

        if ($request->id !== 'undefined') {
            $post = BlogPilotPost::where('id', $request->id)->firstOrFail();
        } else {
            $post = new BlogPilotPost;
        }

        $validated = $request->validate([
            'title'        => 'required|string|max:255',
            'content'      => 'required|string',
            'tags'         => 'string',
            'categories'   => 'string',
            'status'       => 'required|string',
            'scheduled_at' => 'required|string',
        ]);

        $status = $validated['status'];
        if ($status === BlogPilotPost::STATUS_PUBLISHED && ! empty($validated['scheduled_at'])) {
            $scheduledAt = Carbon::parse($validated['scheduled_at']);
            if ($scheduledAt->isFuture()) {
                $status = BlogPilotPost::STATUS_SCHEDULED;
            }
        }

        // Fill only allowed fields
        $post->fill([
            'title'        => $validated['title'],
            'content'      => $validated['content'],
            'status'       => $status,
            'scheduled_at' => $validated['scheduled_at'],
        ]);

        // If tags & categories are JSON columns
        if (array_key_exists('tags', $validated)) {
            $post->tags = explode(',', $validated['tags']);
        }

        if (array_key_exists('categories', $validated)) {
            $post->categories = explode(',', $validated['categories']);
        }

        if (array_key_exists('thumbnail', $validated)) {
            $post->thumbnail = $validated['thumbnail'];
        }

        if ($request->hasFile('thumbnail')) {
            $path = 'uploads/images/blogpilot/';
            $image = $request->file('thumbnail');
            $image_name = Str::random(4) . '-' . Str::slug($request->slug) . '.' . $image->guessExtension();

            // Resim uzantı kontrolü
            $imageTypes = ['jpg', 'jpeg', 'png', 'svg', 'webp'];
            if (! in_array(Str::lower($image->guessExtension()), $imageTypes)) {
                $data = [
                    'errors' => ['The file extension must be jpg, jpeg, png, webp or svg.'],
                ];

                return response()->json($data, 419);
            }

            $image->move($path, $image_name);

            $thumbnail = $path . $image_name;

            $post->thumbnail = $thumbnail ?? $post->thumbnail;
        }

        $post->save();

        return response()->json([
            'success' => true,
            'post'    => $post,
        ]);
    }

    // ==================== HELPER METHODS ====================

    public function publishPost($post_id, $user_id = '')
    {
        $user_id = ! empty($user_id) ? $user_id : Auth::id();
        $userIntegration = UserIntegration::query()
            ->where('user_id', $user_id)
            ->firstOrFail();

        $class = $userIntegration->integration->getFormClassName();

        if (! class_exists($class)) {
            abort(404);
        }

        $service = new $class($userIntegration);

        if ($service->login() === false) {
            throw new Exception(trans('Invalid credentials. Please check your credentials and try again.'), 401);
        }

        $post = BlogPilotPost::query()
            ->where('id', $post_id)
            ->firstOrFail();

        if ($post->scheduled_at && Carbon::parse($post->scheduled_at)->isFuture()) {
            $post->status = BlogPilotPost::STATUS_SCHEDULED;
            $post->save();

            return;
        }

        $thumbnail_id = null;
        $categories = null;
        $tags = null;

        // thumbnail
        if ($post->thumbnail) {
            $imagePath = $post->thumbnail;
            if (str_contains($imagePath, 'uploads')) {
                $parsedUrl = parse_url($imagePath);
                $path = $parsedUrl['path'];
                $cleanedPath = str_replace('uploads', 'uploads', $path);

                $tempFilePath = realpath(public_path($cleanedPath));

                $thumbnail_id = $service->addImage([
                    'file'  => fopen($tempFilePath, 'r'),
                    'title' => basename($imagePath),
                ]);
            }
        }

        if ($post->scheduled_at) {
            $gmtDateTime = Carbon::parse($post->scheduled_at, config('app.timezone'))
                ->setTimezone('UTC')
                ->toIso8601String();
        }

        // tags
        if ($post->tags) {
            $tags = $service->tags($post->tags);
        }

        // tags
        if ($post->categories) {
            $categories = $service->category($post->categories);
        }

        $service->create([
            'title'          => $post->title,
            'content'        => $post->content,
            'status'         => 'publish',
            'comment_status' => null,
            'categories'     => $categories,
            'tags'           => $tags,
            'featured_media' => $thumbnail_id,
            'date_gmt'       => $gmtDateTime ?? null,
        ]);

        $post->published_at = now();
        $post->status = BlogPilotPost::STATUS_PUBLISHED;
        $post->save();
    }

    protected function queuePostGeneration(BlogPilot $agent): void
    {
        BlogPilotGenerationCache::forgetForUser($agent->user_id);

        $stats = BlogPilotGenerationCache::computePostStats($agent);
        $plannedGenerationCount = $this->estimatePlannedGenerationCount($agent);

        Log::info("Queuing post generation for Agent ID {$agent->id} with planned count {$plannedGenerationCount}.");

        $agent->update([
            'post_generation_status' => array_merge($agent->post_generation_status ?? [], [
                'status'    => 'queued',
                'queued_at' => now()->toDateTimeString(),
            ]),
        ]);

        BlogPilotGenerationCache::mark($agent, 'queued', [
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
        $status = BlogPilotGenerationCache::getForUser($userId);

        $agent = null;
        $agentIdFromStatus = (int) data_get($status, 'agent_id');

        if ($agentIdFromStatus > 0) {
            $agent = BlogPilot::query()
                ->where('user_id', $userId)
                ->where('id', $agentIdFromStatus)
                ->first();
        }

        if (! $agent) {
            $agent = BlogPilot::query()
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

        $stats = BlogPilotGenerationCache::computePostStats($agent);
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
            'ready_text_template'   => __(':generated of :total posts are being generated.'),
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

            if ($status === 'COMPLETED') {
                $imageUrl = $request->input('images.0.url');

                if ($imageUrl) {
                    // Download and store the image
                    $imageService = new \App\Extensions\BlogPilot\System\Services\ImageGenerationService;
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

    /**
     * Download and store image from URL
     */
    protected function downloadAndStoreImage(string $url): string
    {
        $imageContents = file_get_contents($url);
        $filename = 'blogpilot/' . uniqid('post_', true) . '.png';

        Storage::disk('public')->put($filename, $imageContents);

        return Storage::disk('public')->url($filename);
    }

    private function estimatePlannedGenerationCount(BlogPilot $agent): int
    {
        $count = $this->estimateTargetPostsCount($agent);

        return $count;
    }

    private function estimateTargetPostsCount(BlogPilot $agent): int
    {
        $dailyPostCount = max(1, (int) $agent->daily_post_count);
        $scheduleDays = $agent->schedule_days ?? [];
        $daysSelected = max(1, count($scheduleDays));
        $frequency = $agent->frequency;

        $multiplier = $frequency === 'monthly' ? 4 : 1;

        return $dailyPostCount * $daysSelected * $multiplier;
    }

    private function ensureAgentCreationAllowed(): ?JsonResponse
    {
        $limit = $this->getBlogPilotLimitValue('agents');

        if ($limit === 0) {
            return $this->blogPilotLimitErrorResponse(__('Your current plan does not allow creating AI Blog Pilot.'));
        }

        if ($limit > 0) {
            $agentCount = BlogPilot::query()
                ->where('user_id', Auth::id())
                ->count();

            if ($agentCount >= $limit) {
                return $this->blogPilotLimitErrorResponse(__('You have reached the maximum number of agents included in your plan.'));
            }
        }

        return null;
    }

    private function getBlogPilotLimitValue(string $key): int
    {
        $plan = Auth::user()?->relationPlan;
        $limits = (array) ($plan->blogpilot_limits ?? []);
        $value = $limits[$key] ?? -1;

        return is_numeric($value) ? (int) $value : -1;
    }

    private function blogPilotLimitErrorResponse(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], 422);
    }
}
