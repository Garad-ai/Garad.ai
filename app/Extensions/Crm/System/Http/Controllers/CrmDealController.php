<?php

namespace App\Extensions\Crm\System\Http\Controllers;

use App\Extensions\Crm\System\Models\CrmCompany;
use App\Extensions\Crm\System\Models\CrmContact;
use App\Extensions\Crm\System\Models\CrmDeal;
use App\Extensions\Crm\System\Models\CrmDealStage;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CrmDealController extends Controller
{
    public function index(): View
    {
        $userId = Auth::id();
        CrmDealStage::createDefaultsForUser($userId);

        $stages = CrmDealStage::query()
            ->where('user_id', $userId)
            ->with(['deals' => function ($query) {
                $query->with(['contact', 'company'])->orderBy('order');
            }])
            ->orderBy('order')
            ->get();

        return view('crm::deals.index', [
            'stages' => $stages,
            'stats'  => $this->dealStats($userId),
        ]);
    }

    public function list(Request $request): View
    {
        $userId = Auth::id();
        CrmDealStage::createDefaultsForUser($userId);

        $filter = $request->query('filter', 'all');
        $sort = $request->query('sort', 'created_at');
        $sortDir = in_array($request->query('sort_dir'), ['asc', 'desc']) ? $request->query('sort_dir') : 'desc';

        $query = CrmDeal::query()
            ->where('user_id', $userId)
            ->with(['contact', 'company', 'stage']);

        // Apply filter
        $stages = CrmDealStage::where('user_id', $userId)->orderBy('order')->get();

        match ($filter) {
            'favorites' => $query->where('is_favorite', true),
            default     => null,
        };

        // Check if filter matches a stage name (slug)
        if ($filter !== 'all' && $filter !== 'favorites') {
            $stageMatch = $stages->first(fn ($s) => Str::slug($s->name) === $filter);
            if ($stageMatch) {
                $query->where('crm_deal_stage_id', $stageMatch->id);
            }
        }

        // Apply sort
        match ($sort) {
            'title'               => $query->orderBy('title', $sortDir),
            'value'               => $query->orderBy('value', $sortDir),
            'expected_close_date' => $query->orderByRaw("expected_close_date IS NULL, expected_close_date $sortDir"),
            'stage'               => $query->orderBy(
                CrmDealStage::select('order')
                    ->whereColumn('crm_deal_stages.id', 'crm_deals.crm_deal_stage_id')
                    ->limit(1),
                $sortDir
            ),
            default               => $query->orderBy('created_at', $sortDir),
        };

        return view('crm::deals.list', [
            'list'      => $query->get(),
            'stages'    => $stages,
            'contacts'  => CrmContact::where('user_id', $userId)->orderBy('first_name')->get(),
            'companies' => CrmCompany::where('user_id', $userId)->orderBy('name')->get(),
            'stats'     => $this->dealStats($userId),
            'filter'    => $filter,
            'sort'      => $sort,
            'sortDir'   => $sortDir,
        ]);
    }

    private function dealStats(int $userId): array
    {
        $totalDeals = CrmDeal::where('user_id', $userId)->count();
        $totalValue = CrmDeal::where('user_id', $userId)->sum('value');
        $closingThisMonth = CrmDeal::where('user_id', $userId)
            ->whereNotNull('expected_close_date')
            ->whereBetween('expected_close_date', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();
        $addedThisWeek = CrmDeal::where('user_id', $userId)
            ->where('created_at', '>=', now()->startOfWeek())
            ->count();

        return [
            ['label' => __('Total Deals'), 'value' => number_format($totalDeals), 'icon' => 'tabler-briefcase', 'color' => 'primary'],
            ['label' => __('Pipeline Value'), 'value' => number_format($totalValue, 0), 'prefix' => '$', 'icon' => 'tabler-currency-dollar', 'color' => 'emerald-500'],
            ['label' => __('Closing This Month'), 'value' => number_format($closingThisMonth), 'icon' => 'tabler-calendar-event', 'color' => 'amber-500'],
            ['label' => __('Added This Week'), 'value' => number_format($addedThisWeek), 'icon' => 'tabler-plus', 'color' => '[#3C82F6]'],
        ];
    }

    public function create(): View
    {
        CrmDealStage::createDefaultsForUser(Auth::id());

        return view('crm::deals.form', [
            'item'      => new CrmDeal,
            'action'    => route('dashboard.user.crm.deals.store'),
            'method'    => 'POST',
            'stages'    => CrmDealStage::where('user_id', Auth::id())->orderBy('order')->get(),
            'contacts'  => CrmContact::where('user_id', Auth::id())->orderBy('first_name')->get(),
            'companies' => CrmCompany::where('user_id', Auth::id())->orderBy('name')->get(),
        ]);
    }

    public function edit(CrmDeal $deal): View
    {
        abort_if($deal->user_id !== Auth::id(), 404);

        $deal->load(['activityNotes.user', 'tasks', 'stageChanges.fromStage', 'stageChanges.toStage', 'stageChanges.user']);

        // Build system events for timeline
        $events = [];

        // Deal created
        $events[] = [
            'icon'  => 'tabler-briefcase',
            'color' => 'primary',
            'title' => __('Deal created'),
            'time'  => $deal->created_at,
        ];

        // Stage changes
        foreach ($deal->stageChanges as $change) {
            $toName = $change->toStage?->name ?? __('Unknown');
            $fromName = $change->fromStage?->name ?? __('New');
            $by = $change->source_label ?: $change->user?->name;

            $desc = $fromName . ' → ' . $toName;
            if ($by) {
                $desc .= ' · ' . __('by') . ' ' . $by;
            }

            $events[] = [
                'icon'  => 'tabler-arrows-right-left',
                'color' => 'secondary',
                'title' => __('Stage changed'),
                'desc'  => $desc,
                'time'  => $change->changed_at ?? $change->created_at,
            ];
        }

        // Tasks completed
        foreach ($deal->tasks->whereNotNull('completed_at') as $task) {
            $events[] = [
                'icon'  => 'tabler-circle-check',
                'color' => 'emerald-500',
                'title' => __('Task completed'),
                'desc'  => $task->title,
                'time'  => $task->completed_at,
            ];
        }

        return view('crm::deals.form', [
            'item'      => $deal,
            'action'    => route('dashboard.user.crm.deals.update', $deal->id),
            'method'    => 'PUT',
            'stages'    => CrmDealStage::where('user_id', Auth::id())->orderBy('order')->get(),
            'contacts'  => CrmContact::where('user_id', Auth::id())->orderBy('first_name')->get(),
            'companies' => CrmCompany::where('user_id', Auth::id())->orderBy('name')->get(),
            'notes'     => $deal->activityNotes,
            'events'    => $events,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json(['status' => 'error', 'message' => __('This feature is disabled in demo mode.')], 422);
        }

        $validated = $request->validate([
            'title'               => 'required|string|max:255',
            'crm_deal_stage_id'   => ['required', Rule::exists('crm_deal_stages', 'id')->where('user_id', Auth::id())],
            'crm_contact_id'      => ['nullable', Rule::exists('crm_contacts', 'id')->where('user_id', Auth::id())],
            'crm_company_id'      => ['nullable', Rule::exists('crm_companies', 'id')->where('user_id', Auth::id())],
            'value'               => 'nullable|numeric|min:0',
            'currency'            => 'nullable|string|max:10',
            'expected_close_date' => 'nullable|date',
            'description'         => 'nullable|string|max:5000',
        ]);

        CrmDeal::create(array_merge($validated, ['user_id' => Auth::id()]));

        return response()->json(['message' => __('Deal created successfully.'), 'type' => 'success']);
    }

    public function update(Request $request, CrmDeal $deal): JsonResponse
    {
        abort_if($deal->user_id !== Auth::id(), 404);

        if (Helper::appIsDemo()) {
            return response()->json(['status' => 'error', 'message' => __('This feature is disabled in demo mode.')], 422);
        }

        $validated = $request->validate([
            'title'               => 'required|string|max:255',
            'crm_deal_stage_id'   => ['required', Rule::exists('crm_deal_stages', 'id')->where('user_id', Auth::id())],
            'crm_contact_id'      => ['nullable', Rule::exists('crm_contacts', 'id')->where('user_id', Auth::id())],
            'crm_company_id'      => ['nullable', Rule::exists('crm_companies', 'id')->where('user_id', Auth::id())],
            'value'               => 'nullable|numeric|min:0',
            'currency'            => 'nullable|string|max:10',
            'expected_close_date' => 'nullable|date',
            'description'         => 'nullable|string|max:5000',
        ]);

        $deal->update($validated);

        return response()->json(['message' => __('Deal updated successfully.'), 'type' => 'success']);
    }

    public function delete(CrmDeal $deal): RedirectResponse|JsonResponse
    {
        abort_if($deal->user_id !== Auth::id(), 404);

        if (Helper::appIsDemo()) {
            if (request()->ajax()) {
                return response()->json(['status' => 'error', 'message' => __('This feature is disabled in demo mode.')], 422);
            }

            return back()->with(['type' => 'error', 'message' => __('This feature is disabled in demo mode.')]);
        }

        $deal->delete();

        if (request()->ajax()) {
            return response()->json([
                'message'  => __('Deal deleted successfully.'),
                'type'     => 'success',
                'redirect' => route('dashboard.user.crm.deals.index'),
            ]);
        }

        return back()->with(['message' => __('Deal deleted successfully.'), 'type' => 'success']);
    }

    public function updateStage(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json(['status' => 'error', 'message' => __('This feature is disabled in demo mode.')], 422);
        }

        $validated = $request->validate([
            'deal_id'       => 'required_without:ordered_ids|exists:crm_deals,id',
            'stage_id'      => ['required', Rule::exists('crm_deal_stages', 'id')->where('user_id', Auth::id())],
            'order'         => 'nullable|integer',
            'ordered_ids'   => 'nullable|array',
            'ordered_ids.*' => 'integer',
        ]);

        if (! empty($validated['ordered_ids'])) {
            $userId = Auth::id();

            foreach (array_values($validated['ordered_ids']) as $position => $dealId) {
                CrmDeal::where('id', $dealId)
                    ->where('user_id', $userId)
                    ->update([
                        'crm_deal_stage_id' => $validated['stage_id'],
                        'order'             => $position,
                    ]);
            }

            return response()->json(['message' => __('Deal updated.'), 'type' => 'success']);
        }

        $deal = CrmDeal::where('id', $validated['deal_id'])
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $deal->update([
            'crm_deal_stage_id' => $validated['stage_id'],
            'order'             => $validated['order'] ?? 0,
        ]);

        return response()->json(['message' => __('Deal updated.'), 'type' => 'success']);
    }
}
