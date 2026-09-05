<?php

namespace App\Extensions\Crm\System\Http\Controllers;

use App\Extensions\Crm\System\Models\CrmCompany;
use App\Extensions\Crm\System\Models\CrmContact;
use App\Extensions\Crm\System\Models\CrmDeal;
use App\Extensions\Crm\System\Models\CrmDealStage;
use App\Extensions\Crm\System\Models\CrmDealStageChange;
use App\Extensions\Crm\System\Models\CrmPresentation;
use App\Extensions\Crm\System\Models\CrmProject;
use App\Extensions\Crm\System\Models\CrmTask;
use App\Extensions\Crm\System\Models\SalesInvoice;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\UserOpenaiChat;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class CrmDashboardController extends Controller
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

        $whatsNewStats = [
            'today'        => $this->getWhatsNewStats(now()->startOfDay(), now()),
            'last_7_days'  => $this->getWhatsNewStats(now()->subDays(7), now()),
            'last_30_days' => $this->getWhatsNewStats(now()->subDays(30), now()),
        ];

        // ── Daily Summary ─────────────────────────────────────────
        $userName = Auth::user()->name;
        $today = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $weekEnd = now()->endOfWeek();

        $todayTasksByType = CrmTask::where('user_id', $userId)
            ->where('status', 'pending')
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$today, $todayEnd])
            ->selectRaw('type, count(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type');

        $todayTasksTotal = $todayTasksByType->sum();

        $dealsClosingThisWeek = CrmDeal::where('user_id', $userId)
            ->whereNotNull('expected_close_date')
            ->whereBetween('expected_close_date', [$today, $weekEnd])
            ->count();

        $highPriorityTasks = CrmTask::where('user_id', $userId)
            ->where('status', 'pending')
            ->whereIn('priority', ['high', 'urgent'])
            ->count();

        $newContactsToday = CrmContact::where('user_id', $userId)
            ->whereBetween('created_at', [$today, $todayEnd])
            ->count();

        // Build summary items array
        $summaryItems = [];

        if ($todayTasksTotal > 0) {
            $parts = [];
            $typeLabels = [
                'meeting'   => ['meeting', 'meetings'],
                'call'      => ['call', 'calls'],
                'email'     => ['email', 'emails'],
                'follow_up' => ['follow-up', 'follow-ups'],
                'task'      => ['task', 'tasks'],
            ];
            foreach ($todayTasksByType as $type => $count) {
                $label = $typeLabels[$type] ?? [$type, $type . 's'];
                $parts[] = $count . ' ' . ($count === 1 ? $label[0] : $label[1]);
            }
            $summaryItems[] = [
                'icon'  => 'tabler-calendar-check',
                'color' => 'primary',
                'text'  => __(':count items today: :details', ['count' => $todayTasksTotal, 'details' => implode(', ', $parts)]),
            ];
        }

        if ($overdueTasks > 0) {
            $summaryItems[] = [
                'icon'  => 'tabler-alert-triangle',
                'color' => 'red-500',
                'text'  => trans_choice(':count overdue task needs attention|:count overdue tasks need attention', $overdueTasks, ['count' => $overdueTasks]),
            ];
        }

        if ($dealsClosingThisWeek > 0) {
            $summaryItems[] = [
                'icon'  => 'tabler-trophy',
                'color' => '[#20C69F]',
                'text'  => trans_choice(':count deal closing this week|:count deals closing this week', $dealsClosingThisWeek, ['count' => $dealsClosingThisWeek]),
            ];
        }

        if ($highPriorityTasks > 0) {
            $summaryItems[] = [
                'icon'  => 'tabler-flame',
                'color' => 'orange-500',
                'text'  => trans_choice(':count high-priority task pending|:count high-priority tasks pending', $highPriorityTasks, ['count' => $highPriorityTasks]),
            ];
        }

        if ($newContactsToday > 0) {
            $summaryItems[] = [
                'icon'  => 'tabler-user-plus',
                'color' => 'secondary',
                'text'  => trans_choice(':count new contact added today|:count new contacts added today', $newContactsToday, ['count' => $newContactsToday]),
            ];
        }

        // ── Pipeline Overview (deal counts per stage) ──────────────
        $stages = CrmDealStage::where('user_id', $userId)
            ->withCount('deals')
            ->orderBy('order')
            ->get();

        $totalDealsInPipeline = $stages->sum('deals_count');

        // Revenue stats
        $wonDeals = CrmDeal::where('user_id', $userId)
            ->whereHas('stage', fn ($q) => $q->whereIn('name', ['Won', 'Closed Won', 'Closed']))
            ->count();

        $tasksCompletedTotal = CrmTask::where('user_id', $userId)
            ->where('status', 'completed')
            ->count();

        // ── Top Contacts (favorites first, then most deals) ───────
        $topContacts = CrmContact::where('user_id', $userId)
            ->withCount('deals')
            ->with('company')
            ->orderByDesc('is_favorite')
            ->orderByDesc('deals_count')
            ->limit(4)
            ->get();

        // ── Recent Activity ───────────────────────────────────────
        $recentContacts = CrmContact::where('user_id', $userId)
            ->with('company')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $recentDeals = CrmDeal::where('user_id', $userId)
            ->with(['stage', 'contact', 'company'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $upcomingTasks = CrmTask::where('user_id', $userId)
            ->where('status', 'pending')
            ->with(['contact', 'deal'])
            ->orderBy('due_date', 'asc')
            ->limit(5)
            ->get();

        // ── AI Conversations ────────────────────────────────────
        $conversationCount = UserOpenaiChat::query()
            ->where('user_id', $userId)
            ->where('chat_type', 'crm-assistant')
            ->count();

        // ── Activity Log (unified timeline) ─────────────────────────
        $activityLog = collect();

        // Contacts added
        CrmContact::where('user_id', $userId)
            ->with('company')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->each(function ($c) use ($activityLog) {
                $activityLog->push([
                    'icon'  => 'tabler-user-circle',
                    'color' => 'primary',
                    'title' => __(':name added', ['name' => $c->full_name]),
                    'desc'  => $c->company?->name ?? __('Contact'),
                    'time'  => $c->created_at,
                ]);
            });

        // Tasks completed
        CrmTask::where('user_id', $userId)
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->limit(8)
            ->get()
            ->each(function ($t) use ($activityLog) {
                $activityLog->push([
                    'icon'  => 'tabler-list-details',
                    'color' => 'emerald-500',
                    'title' => __('Task completed'),
                    'desc'  => $t->title,
                    'time'  => $t->completed_at,
                ]);
            });

        // Deals created
        CrmDeal::where('user_id', $userId)
            ->with('stage')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->each(function ($d) use ($activityLog) {
                $activityLog->push([
                    'icon'  => 'tabler-license',
                    'color' => 'secondary',
                    'title' => __('Deal created'),
                    'desc'  => $d->title . ($d->value > 0 ? ' — $' . number_format($d->value, 0) : ''),
                    'time'  => $d->created_at,
                ]);
            });

        // Deal stage changes
        CrmDealStageChange::where('user_id', $userId)
            ->with(['deal', 'toStage'])
            ->orderByDesc('changed_at')
            ->limit(8)
            ->get()
            ->each(function ($sc) use ($activityLog) {
                if (! $sc->deal) {
                    return;
                }
                $activityLog->push([
                    'icon'  => 'tabler-license',
                    'color' => '[#3C82F6]',
                    'title' => __('Deal moved to :stage', ['stage' => $sc->toStage?->name ?? '—']),
                    'desc'  => $sc->deal->title,
                    'time'  => $sc->changed_at,
                ]);
            });

        // Invoices created
        SalesInvoice::where('user_id', $userId)
            ->with('contact')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->each(function ($inv) use ($activityLog) {
                $activityLog->push([
                    'icon'  => 'tabler-cash-banknote',
                    'color' => 'amber-500',
                    'title' => __('Invoice :num', ['num' => $inv->invoice_number]),
                    'desc'  => ($inv->status === 'paid' ? __('Paid') : ucfirst($inv->status)) . ' — $' . number_format($inv->total, 0),
                    'time'  => $inv->created_at,
                ]);
            });

        // Projects created
        CrmProject::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->each(function ($p) use ($activityLog) {
                $activityLog->push([
                    'icon'  => 'tabler-assembly',
                    'color' => '[#20C69F]',
                    'title' => __('Project created'),
                    'desc'  => $p->name,
                    'time'  => $p->created_at,
                ]);
            });

        // Sort by time desc and take the most recent 8
        $activityLog = $activityLog->sortByDesc('time')->take(5)->values();

        return view('crm::index', compact(
            'totalContacts',
            'totalCompanies',
            'totalDeals',
            'totalDealsValue',
            'pendingTasks',
            'overdueTasks',
            'recentContacts',
            'recentDeals',
            'upcomingTasks',
            'activityLog',
            // Week-over-week
            'contactsChange',
            'dealsChange',
            'valueChange',
            'tasksChange',
            // Daily summary
            'userName',
            'summaryItems',
            // Pipeline overview
            'stages',
            'totalDealsInPipeline',
            'wonDeals',
            'tasksCompletedTotal',
            // Top contacts
            'topContacts',
            // AI conversations
            'conversationCount',
            'whatsNewStats'
        ));
    }

    /**
     * Count records created within the given range for the "What's new" widget.
     *
     * @return array{contacts: int, deals: int, tasks: int, reports: int}
     */
    private function getWhatsNewStats(Carbon $startDate, Carbon $endDate): array
    {
        $userId = Auth::id();

        return [
            'contacts' => CrmContact::where('user_id', $userId)->whereBetween('created_at', [$startDate, $endDate])->count(),
            'deals'    => CrmDeal::where('user_id', $userId)->whereBetween('created_at', [$startDate, $endDate])->count(),
            'tasks'    => CrmTask::where('user_id', $userId)->whereBetween('created_at', [$startDate, $endDate])->count(),
            'reports'  => CrmPresentation::where('user_id', $userId)->whereBetween('created_at', [$startDate, $endDate])->count(),
        ];
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json(['status' => 'error', 'message' => __('This feature is disabled in demo mode.')], 422);
        }

        $validated = $request->validate([
            'type'  => 'required|in:contact,company,deal,task,project',
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        $modelMap = [
            'contact' => CrmContact::class,
            'company' => CrmCompany::class,
            'deal'    => CrmDeal::class,
            'task'    => CrmTask::class,
            'project' => CrmProject::class,
        ];

        $class = $modelMap[$validated['type']];
        $deleted = $class::where('user_id', Auth::id())
            ->whereIn('id', $validated['ids'])
            ->delete();

        return response()->json([
            'success' => true,
            'message' => __(':count item(s) deleted successfully.', ['count' => $deleted]),
        ]);
    }

    public function toggleFavorite(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json(['status' => 'error', 'message' => __('This feature is disabled in demo mode.')], 422);
        }

        $validated = $request->validate([
            'type' => 'required|in:contact,company,deal,project',
            'id'   => 'required|integer',
        ]);

        $modelMap = [
            'contact' => CrmContact::class,
            'company' => CrmCompany::class,
            'deal'    => CrmDeal::class,
            'project' => CrmProject::class,
        ];

        $model = $modelMap[$validated['type']]::where('id', $validated['id'])
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $model->update(['is_favorite' => ! $model->is_favorite]);

        return response()->json([
            'success'     => true,
            'is_favorite' => $model->is_favorite,
        ]);
    }
}
