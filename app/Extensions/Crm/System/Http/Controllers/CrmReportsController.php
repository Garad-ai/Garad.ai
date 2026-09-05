<?php

namespace App\Extensions\Crm\System\Http\Controllers;

use App\Extensions\Crm\System\Models\CrmCompany;
use App\Extensions\Crm\System\Models\CrmContact;
use App\Extensions\Crm\System\Models\CrmDeal;
use App\Extensions\Crm\System\Models\CrmDealStage;
use App\Extensions\Crm\System\Models\CrmDealStageChange;
use App\Extensions\Crm\System\Models\CrmTask;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CrmReportsController extends Controller
{
    public function index(): View
    {
        $userId = Auth::id();

        // ── Basic Counts ──────────────────────────────────────────
        $totalContacts = CrmContact::where('user_id', $userId)->count();
        $totalCompanies = CrmCompany::where('user_id', $userId)->count();
        $totalDeals = CrmDeal::where('user_id', $userId)->count();
        $totalDealsValue = CrmDeal::where('user_id', $userId)->sum('value');
        $pendingTasks = CrmTask::where('user_id', $userId)->where('status', 'pending')->count();
        $overdueTasks = CrmTask::where('user_id', $userId)
            ->where('status', 'pending')
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->count();

        // ── Week-over-Week Changes ──────────────────────────────
        $thisWeekStart = now()->startOfWeek();
        $lastWeekStart = now()->subWeek()->startOfWeek();
        $lastWeekEnd = now()->subWeek()->endOfWeek();

        $contactsThisWeek = CrmContact::where('user_id', $userId)->where('created_at', '>=', $thisWeekStart)->count();
        $contactsLastWeek = CrmContact::where('user_id', $userId)->whereBetween('created_at', [$lastWeekStart, $lastWeekEnd])->count();
        $contactsChange = $contactsLastWeek > 0 ? round((($contactsThisWeek - $contactsLastWeek) / $contactsLastWeek) * 100) : ($contactsThisWeek > 0 ? 100 : 0);

        $dealsThisWeek = CrmDeal::where('user_id', $userId)->where('created_at', '>=', $thisWeekStart)->count();
        $dealsLastWeek = CrmDeal::where('user_id', $userId)->whereBetween('created_at', [$lastWeekStart, $lastWeekEnd])->count();
        $dealsChange = $dealsLastWeek > 0 ? round((($dealsThisWeek - $dealsLastWeek) / $dealsLastWeek) * 100) : ($dealsThisWeek > 0 ? 100 : 0);

        $valueThisWeek = CrmDeal::where('user_id', $userId)->where('created_at', '>=', $thisWeekStart)->sum('value');
        $valueLastWeek = CrmDeal::where('user_id', $userId)->whereBetween('created_at', [$lastWeekStart, $lastWeekEnd])->sum('value');
        $valueChange = $valueLastWeek > 0 ? round((($valueThisWeek - $valueLastWeek) / $valueLastWeek) * 100) : ($valueThisWeek > 0 ? 100 : 0);

        $tasksCompletedThisWeek = CrmTask::where('user_id', $userId)->whereNotNull('completed_at')->where('completed_at', '>=', $thisWeekStart)->count();
        $tasksCompletedLastWeek = CrmTask::where('user_id', $userId)->whereNotNull('completed_at')->whereBetween('completed_at', [$lastWeekStart, $lastWeekEnd])->count();
        $tasksChange = $tasksCompletedLastWeek > 0 ? round((($tasksCompletedThisWeek - $tasksCompletedLastWeek) / $tasksCompletedLastWeek) * 100) : ($tasksCompletedThisWeek > 0 ? 100 : 0);

        // ── Deal Stage Distribution ──────────────────────────────
        $stageDistribution = CrmDealStage::where('user_id', $userId)
            ->orderBy('order')
            ->withCount(['deals' => fn ($q) => $q->where('user_id', $userId)])
            ->get()
            ->map(fn ($s) => [
                'name'  => $s->name,
                'color' => $s->color,
                'count' => $s->deals_count,
            ]);

        // ── Win / Loss Ratio ────────────────────────────────────
        $wonStage = CrmDealStage::where('user_id', $userId)->where('order', 4)->first();
        $lostStage = CrmDealStage::where('user_id', $userId)->where('order', 5)->first();

        $wonCount = $wonStage ? CrmDeal::where('user_id', $userId)->where('crm_deal_stage_id', $wonStage->id)->count() : 0;
        $lostCount = $lostStage ? CrmDeal::where('user_id', $userId)->where('crm_deal_stage_id', $lostStage->id)->count() : 0;
        $winRate = ($wonCount + $lostCount) > 0 ? round(($wonCount / ($wonCount + $lostCount)) * 100) : 0;

        // ── Revenue Forecast (6 past + 3 future months) ─────────
        $revenueMonths = [];
        $closedRevenue = [];
        $forecastRevenue = [];

        $wonDeals = CrmDeal::where('user_id', $userId)
            ->whereHas('stage', fn ($q) => $q->where('order', 4))
            ->where('updated_at', '>=', now()->subMonths(6)->startOfMonth())
            ->selectRaw("DATE_FORMAT(updated_at, '%Y-%m') as month, SUM(value) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        $forecastDeals = CrmDeal::where('user_id', $userId)
            ->whereHas('stage', fn ($q) => $q->whereNotIn('order', [4, 5]))
            ->whereNotNull('expected_close_date')
            ->where('expected_close_date', '>=', now()->startOfMonth())
            ->where('expected_close_date', '<=', now()->addMonths(3)->endOfMonth())
            ->selectRaw("DATE_FORMAT(expected_close_date, '%Y-%m') as month, SUM(value) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        for ($i = -5; $i <= 3; $i++) {
            $m = now()->addMonths($i)->format('Y-m');
            $revenueMonths[] = now()->addMonths($i)->format('M Y');
            $closedRevenue[] = (float) ($wonDeals[$m] ?? 0);
            $forecastRevenue[] = (float) ($forecastDeals[$m] ?? 0);
        }

        // ── Deal Conversion Funnel ──────────────────────────────
        $stages = CrmDealStage::where('user_id', $userId)->orderBy('order')->get();
        $funnelData = [];

        foreach ($stages as $stage) {
            $count = CrmDeal::where('user_id', $userId)
                ->whereHas('stage', fn ($q) => $q->where('order', '>=', $stage->order))
                ->count();
            $funnelData[] = [
                'name'  => $stage->name,
                'color' => $stage->color,
                'count' => $count,
            ];
        }

        // ── Pipeline Velocity ───────────────────────────────────
        $hasStageHistory = CrmDealStageChange::where('user_id', $userId)->exists();
        $velocityData = [];

        if ($hasStageHistory) {
            $velocityData = DB::select('
                SELECT
                    s.name    AS stage_name,
                    s.color   AS stage_color,
                    s.`order` AS stage_order,
                    ROUND(AVG(TIMESTAMPDIFF(HOUR, sc.changed_at,
                        COALESCE(sc_next.changed_at, NOW())
                    )) / 24, 1) AS avg_days
                FROM crm_deal_stage_changes sc
                JOIN crm_deal_stages s ON s.id = sc.to_stage_id
                LEFT JOIN crm_deal_stage_changes sc_next
                    ON  sc_next.crm_deal_id = sc.crm_deal_id
                    AND sc_next.id = (
                        SELECT MIN(id)
                        FROM crm_deal_stage_changes
                        WHERE crm_deal_id = sc.crm_deal_id AND id > sc.id
                    )
                WHERE sc.user_id = ?
                  AND s.`order` NOT IN (4, 5)
                GROUP BY s.id, s.name, s.color, s.`order`
                ORDER BY s.`order`
            ', [$userId]);
        }

        // ── Task Activity (last 8 weeks) ────────────────────────
        $taskActivityWeeks = [];
        $taskActivityCounts = [];

        $completedTasks = CrmTask::where('user_id', $userId)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', now()->subWeeks(8)->startOfWeek())
            ->selectRaw('YEARWEEK(completed_at, 1) as yw, COUNT(*) as total')
            ->groupBy('yw')
            ->pluck('total', 'yw');

        for ($i = 7; $i >= 0; $i--) {
            $weekStart = now()->subWeeks($i)->startOfWeek();
            $yw = $weekStart->format('oW');
            $taskActivityWeeks[] = $weekStart->format('M d');
            $taskActivityCounts[] = (int) ($completedTasks[$yw] ?? 0);
        }

        // ── Contact Growth (last 6 months) ──────────────────────
        $contactGrowthMonths = [];
        $contactGrowthCounts = [];

        $contactsByMonth = CrmContact::where('user_id', $userId)
            ->where('created_at', '>=', now()->subMonths(6)->startOfMonth())
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        for ($i = -5; $i <= 0; $i++) {
            $m = now()->addMonths($i)->format('Y-m');
            $contactGrowthMonths[] = now()->addMonths($i)->format('M Y');
            $contactGrowthCounts[] = (int) ($contactsByMonth[$m] ?? 0);
        }

        // ── Deal Value by Stage ─────────────────────────────────
        $dealValueByStage = CrmDealStage::where('user_id', $userId)
            ->orderBy('order')
            ->get()
            ->map(fn ($s) => [
                'name'  => $s->name,
                'color' => $s->color,
                'value' => (float) CrmDeal::where('user_id', $userId)
                    ->where('crm_deal_stage_id', $s->id)
                    ->sum('value'),
            ]);

        return view('crm::reports', compact(
            'totalContacts',
            'totalCompanies',
            'totalDeals',
            'totalDealsValue',
            'pendingTasks',
            'overdueTasks',
            // Week-over-week
            'contactsChange',
            'dealsChange',
            'valueChange',
            'tasksChange',
            'stageDistribution',
            // Analytics
            'wonCount',
            'lostCount',
            'winRate',
            'revenueMonths',
            'closedRevenue',
            'forecastRevenue',
            'funnelData',
            'hasStageHistory',
            'velocityData',
            'taskActivityWeeks',
            'taskActivityCounts',
            // Reports-specific
            'contactGrowthMonths',
            'contactGrowthCounts',
            'dealValueByStage',
        ));
    }
}
