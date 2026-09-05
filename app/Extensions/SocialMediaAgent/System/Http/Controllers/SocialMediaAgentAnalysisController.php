<?php

namespace App\Extensions\SocialMediaAgent\System\Http\Controllers;

use App\Extensions\SocialMedia\System\Models\SocialMediaAnalysis;
use App\Helpers\Classes\Helper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class SocialMediaAgentAnalysisController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return $this->getDemoAnalyses($request);
        }

        $user = $request->user();

        $query = SocialMediaAnalysis::query()
            ->with('agent:id,name')
            ->where('user_id', $user->id);

        if ($request->filled('type')) {
            $query->where('type', trim((string) $request->input('type')));
        }

        if ($agentId = $request->input('agent_id')) {
            $query->where('agent_id', $agentId);
        }

        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        $perPage = min(max((int) $request->input('per_page', 10), 1), 50);
        $unreadCount = (clone $query)->whereNull('read_at')->count();

        $analyses = $query
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $data = $analyses->getCollection()
            ->map(fn (SocialMediaAnalysis $analysis) => $this->transform($analysis))
            ->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $analyses->currentPage(),
                'last_page'    => $analyses->lastPage(),
                'per_page'     => $analyses->perPage(),
                'total'        => $analyses->total(),
                'unread_count' => $unreadCount,
            ],
        ]);
    }

    public function show(Request $request, $analysis): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json(['data' => $this->findDemoAnalysis((int) $analysis)]);
        }

        $analysis = SocialMediaAnalysis::findOrFail($analysis);
        $this->authorizeAccess($request, $analysis);

        if ($request->boolean('mark_as_read')) {
            $analysis->markAsRead();
            $analysis->refresh();
        }

        return response()->json([
            'data' => $this->transform($analysis),
        ]);
    }

    public function markAsRead(Request $request, $analysis): JsonResponse
    {
        if (Helper::appIsDemo()) {
            $data = $this->findDemoAnalysis((int) $analysis);
            $data['is_unread'] = false;
            $data['read_at'] = Carbon::now()->toIso8601String();

            return response()->json(['data' => $data]);
        }

        $analysis = SocialMediaAnalysis::findOrFail($analysis);
        $this->authorizeAccess($request, $analysis);

        $analysis->markAsRead();
        $analysis->refresh();

        return response()->json([
            'data' => $this->transform($analysis),
        ]);
    }

    public function destroy(Request $request, $analysis): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json(['status' => 'success']);
        }

        $analysis = SocialMediaAnalysis::findOrFail($analysis);
        $this->authorizeAccess($request, $analysis);

        $analysis->delete();

        return response()->json([
            'status' => 'success',
        ]);
    }

    public function clearAll(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json(['status' => 'success', 'deleted' => 0]);
        }

        $userId = $request->user()->id;

        $deleted = SocialMediaAnalysis::query()
            ->where('user_id', $userId)
            ->delete();

        return response()->json([
            'status'  => 'success',
            'deleted' => $deleted,
        ]);
    }

    private function getDemoAnalyses(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->input('per_page', 10), 1), 50);
        $page = max((int) $request->input('page', 1), 1);

        $allAnalyses = $this->buildDemoAnalysesList();

        $total = count($allAnalyses);
        $lastPage = (int) ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;
        $pageData = array_slice($allAnalyses, $offset, $perPage);
        $unreadCount = count(array_filter($allAnalyses, fn ($a) => $a['is_unread']));

        return response()->json([
            'data' => $pageData,
            'meta' => [
                'current_page' => $page,
                'last_page'    => $lastPage,
                'per_page'     => $perPage,
                'total'        => $total,
                'unread_count' => $unreadCount,
            ],
        ]);
    }

    private function findDemoAnalysis(int $id): array
    {
        $allAnalyses = $this->buildDemoAnalysesList();

        foreach ($allAnalyses as $analysis) {
            if ($analysis['id'] === $id) {
                return $analysis;
            }
        }

        abort(404);
    }

    private function buildDemoAnalysesList(): array
    {
        $agents = [
            ['id' => 1, 'name' => 'Brand Awareness', 'image' => null],
            ['id' => 2, 'name' => 'Product Launch', 'image' => null],
            ['id' => 3, 'name' => 'Engagement Booster', 'image' => null],
        ];

        $types = ['post_metrics_analyzer', 'post_performance_advisor', 'trend_finder', 'weekly_social_trends'];

        $summaries = [
            'post_metrics_analyzer' => [
                'Engagement rate increased by 23% this week across all platforms.',
                'Instagram posts outperformed Facebook by 2x in reach this period.',
                'Video content generated 45% more impressions than image posts.',
                'Best performing post reached 12.4K impressions with 8.2% engagement.',
            ],
            'post_performance_advisor' => [
                'Consider posting between 10 AM - 12 PM for maximum engagement.',
                'Carousel posts show 3x higher save rate than single images.',
                'Adding 5-8 relevant hashtags improved discoverability by 34%.',
                'Shorter captions under 125 characters drove 2x more comments.',
            ],
            'trend_finder' => [
                'AI-generated content is trending across LinkedIn and Instagram.',
                'Short-form video content continues to dominate engagement metrics.',
                'Sustainability and eco-friendly messaging resonates with your audience.',
                'Behind-the-scenes content is gaining traction in your niche.',
            ],
            'weekly_social_trends' => [
                'Weekly recap: 28 posts published, 45.2K total impressions.',
                'Follower growth: +342 new followers across all connected platforms.',
                'Top performing day was Wednesday with 8.7K combined reach.',
                'Audience activity peaked between 6 PM - 9 PM this week.',
            ],
        ];

        $reportTexts = [
            'post_metrics_analyzer'    => '<h3>Post Metrics Analysis</h3><p>Your content performance has shown significant improvement this period. Instagram posts achieved an average engagement rate of 6.8%, well above the industry benchmark of 3.5%. Facebook reach expanded by 18% compared to the previous period.</p><p><strong>Key Highlights:</strong></p><ul><li>Total impressions: 45,200 (+23% vs last period)</li><li>Average engagement rate: 6.8%</li><li>Top post format: Carousel images</li><li>Best performing time: 10:30 AM</li></ul><p>Video content continues to outperform static images, with 45% more impressions on average. Consider increasing your video output from 3 to 5 posts per week.</p>',
            'post_performance_advisor' => '<h3>Performance Advisory</h3><p>Based on your recent posting patterns and audience behavior, here are actionable recommendations to boost your social media performance.</p><p><strong>Optimization Opportunities:</strong></p><ul><li>Shift posting time to 10 AM - 12 PM window for 25% more reach</li><li>Increase carousel post frequency - they generate 3x more saves</li><li>Use 5-8 targeted hashtags per post instead of current 3-4</li><li>Experiment with shorter captions (&lt;125 characters) for higher comment rates</li></ul><p>Your audience responds best to educational and behind-the-scenes content. Consider creating a content series around product development updates.</p>',
            'trend_finder'             => '<h3>Trend Analysis Report</h3><p>Current trends in your industry and niche that present content opportunities for your brand.</p><p><strong>Emerging Trends:</strong></p><ul><li>AI-powered content creation tools - high engagement topic on LinkedIn</li><li>Short-form video tutorials - trending format across Instagram Reels</li><li>Sustainability messaging - growing audience interest in eco-friendly practices</li><li>User-generated content campaigns - increasing brand authenticity</li></ul><p>We recommend creating 2-3 trend-aligned posts per week to capitalize on these opportunities while they remain highly visible in platform algorithms.</p>',
            'weekly_social_trends'     => '<h3>Weekly Social Trends Summary</h3><p>Here is your comprehensive weekly performance overview across all connected social media platforms.</p><p><strong>This Week\'s Numbers:</strong></p><ul><li>Posts published: 28</li><li>Total impressions: 45,200</li><li>Total engagement: 3,840 interactions</li><li>New followers: +342</li><li>Profile visits: 1,280</li></ul><p><strong>Platform Breakdown:</strong></p><ul><li>Instagram: 18,400 impressions, 7.2% engagement rate</li><li>Facebook: 15,800 impressions, 4.1% engagement rate</li><li>LinkedIn: 11,000 impressions, 5.8% engagement rate</li></ul>',
        ];

        $allAnalyses = [];
        $now = Carbon::now();

        for ($i = 0; $i < 18; $i++) {
            $seed = abs(crc32('analysis-' . $i));
            $type = $types[$seed % count($types)];
            $agent = $agents[($seed >> 2) % count($agents)];
            $typeSummaries = $summaries[$type];
            $summary = $typeSummaries[$seed % count($typeSummaries)];
            $reportText = $reportTexts[$type];
            $createdAt = $now->copy()->subHours($i * 8 + ($seed % 5));
            $isUnread = $i < 4;

            $allAnalyses[] = [
                'id'             => $i + 1,
                'type'           => $type,
                'type_label'     => Str::headline(str_replace('_', ' ', $type)),
                'agent_id'       => $agent['id'],
                'agent_name'     => $agent['name'],
                'agent_image'    => $agent['image'],
                'report_text'    => $reportText,
                'summary'        => $summary,
                'report_excerpt' => Str::limit(strip_tags($reportText), 220),
                'created_at'     => $createdAt->toIso8601String(),
                'read_at'        => $isUnread ? null : $createdAt->copy()->addHours(2)->toIso8601String(),
                'is_unread'      => $isUnread,
                'link'           => '#',
            ];
        }

        return $allAnalyses;
    }

    private function authorizeAccess(Request $request, SocialMediaAnalysis $analysis): void
    {
        if ($analysis->user_id !== $request->user()->id) {
            abort(404);
        }
    }

    private function transform(SocialMediaAnalysis $analysis): array
    {
        $link = null;

        return [
            'id'             => $analysis->id,
            'type'           => $analysis->type,
            'type_label'     => Str::headline(str_replace('_', ' ', $analysis->type)),
            'agent_id'       => $analysis->agent_id,
            'agent_name'     => $analysis->agent?->name,
            'agent_image'    => $analysis->agent?->image,
            'report_text'    => $analysis->report_text,
            'summary'        => $analysis->summary,
            'report_excerpt' => Str::limit(strip_tags($analysis->report_text), 220),
            'created_at'     => optional($analysis->created_at)->toIso8601String(),
            'read_at'        => optional($analysis->read_at)->toIso8601String(),
            'is_unread'      => $analysis->read_at === null,
            'link'           => route('dashboard.user.social-media.agent.chat.index', ['id' => $analysis->agent_id, 'analysis' => $analysis->id]),
        ];
    }
}
