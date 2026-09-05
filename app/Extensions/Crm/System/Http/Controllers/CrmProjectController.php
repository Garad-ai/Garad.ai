<?php

namespace App\Extensions\Crm\System\Http\Controllers;

use App\Extensions\Crm\System\Models\CrmCompany;
use App\Extensions\Crm\System\Models\CrmContact;
use App\Extensions\Crm\System\Models\CrmDeal;
use App\Extensions\Crm\System\Models\CrmProject;
use App\Extensions\Crm\System\Models\CrmTask;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CrmProjectController extends Controller
{
    public function index(Request $request): View
    {
        $userId = Auth::id();
        $filter = $request->query('filter', 'all');
        $sort = $request->query('sort', 'created_at');
        $sortDir = in_array($request->query('sort_dir'), ['asc', 'desc']) ? $request->query('sort_dir') : 'desc';

        $query = CrmProject::query()
            ->where('user_id', $userId)
            ->with(['contact', 'company', 'deal', 'tasks']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        // Apply filter tab
        match ($filter) {
            'favorites'   => $query->where('is_favorite', true),
            'in_progress' => $query->where('status', 'in_progress'),
            'completed'   => $query->where('status', 'completed'),
            'overdue'     => $query->where('status', '!=', 'completed')
                ->where('status', '!=', 'cancelled')
                ->whereNotNull('due_date')
                ->where('due_date', '<', now()),
            default       => null,
        };

        // Apply sort
        match ($sort) {
            'name'       => $query->orderBy('name', $sortDir),
            'due_date'   => $query->orderByRaw("due_date IS NULL, due_date {$sortDir}"),
            'priority'   => $query->orderByRaw("FIELD(priority, 'urgent', 'high', 'medium', 'low') " . ($sortDir === 'desc' ? 'DESC' : 'ASC')),
            'status'     => $query->orderBy('status', $sortDir),
            'budget'     => $query->orderBy('budget', $sortDir),
            default      => $query->orderBy('created_at', $sortDir),
        };

        $projects = $query->get();

        // Stats (always unfiltered — count from all projects)
        $allProjects = CrmProject::where('user_id', $userId)->get();
        $totalProjects = $allProjects->count();
        $inProgress = $allProjects->where('status', 'in_progress')->count();
        $overdue = $allProjects->filter(fn ($p) => $p->due_date && $p->status !== 'completed' && $p->status !== 'cancelled' && $p->due_date->isPast())->count();
        $completedCount = $allProjects->where('status', 'completed')->count();

        $stats = [
            ['label' => __('Total Projects'), 'value' => number_format($totalProjects), 'icon' => 'tabler-layout-grid', 'color' => 'primary'],
            ['label' => __('In Progress'), 'value' => number_format($inProgress), 'icon' => 'tabler-progress', 'color' => '[#3C82F6]'],
            ['label' => __('Overdue'), 'value' => number_format($overdue), 'icon' => 'tabler-alert-triangle', 'color' => 'red-500'],
            ['label' => __('Completed'), 'value' => number_format($completedCount), 'icon' => 'tabler-circle-check', 'color' => 'emerald-500'],
        ];

        $shared = [
            'contacts'  => CrmContact::where('user_id', $userId)->orderBy('first_name')->get(),
            'companies' => CrmCompany::where('user_id', $userId)->orderBy('name')->get(),
            'deals'     => CrmDeal::where('user_id', $userId)->orderBy('title')->get(),
            'filters'   => $request->only(['status', 'priority']),
            'stats'     => $stats,
            'filter'    => $filter,
            'sort'      => $sort,
            'sortDir'   => $sortDir,
        ];

        // Board view
        if ($request->routeIs('*.board')) {
            $columns = [
                'not_started' => ['label' => __('Not Started'), 'color' => '#6b7280', 'projects' => $projects->where('status', 'not_started')->values()],
                'in_progress' => ['label' => __('In Progress'), 'color' => '#3b82f6', 'projects' => $projects->where('status', 'in_progress')->values()],
                'on_hold'     => ['label' => __('On Hold'), 'color' => '#f59e0b', 'projects' => $projects->where('status', 'on_hold')->values()],
                'completed'   => ['label' => __('Completed'), 'color' => '#22c55e', 'projects' => $projects->where('status', 'completed')->values()],
                'cancelled'   => ['label' => __('Cancelled'), 'color' => '#ef4444', 'projects' => $projects->where('status', 'cancelled')->values()],
            ];

            return view('crm::projects.index', array_merge($shared, [
                'viewMode' => 'board',
                'columns'  => $columns,
            ]));
        }

        return view('crm::projects.index', array_merge($shared, [
            'viewMode' => 'list',
            'list'     => $projects,
        ]));
    }

    public function show(CrmProject $project): View
    {
        abort_if($project->user_id !== Auth::id(), 404);

        $project->load(['contact', 'company', 'deal', 'tasks.contact']);

        return view('crm::projects.show', [
            'item'     => $project,
            'contacts' => CrmContact::where('user_id', Auth::id())->orderBy('first_name')->get(),
            'deals'    => CrmDeal::where('user_id', Auth::id())->orderBy('title')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json(['status' => 'error', 'message' => __('This feature is disabled in demo mode.')], 422);
        }

        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'description'    => 'nullable|string|max:5000',
            'status'         => 'nullable|in:not_started,in_progress,on_hold,completed,cancelled',
            'priority'       => 'nullable|in:low,medium,high,urgent',
            'category'       => 'nullable|string|max:100',
            'start_date'     => 'nullable|date',
            'due_date'       => 'nullable|date',
            'budget'         => 'nullable|numeric|min:0',
            'currency'       => 'nullable|string|max:10',
            'notes'          => 'nullable|string|max:5000',
            'crm_contact_id' => ['nullable', Rule::exists('crm_contacts', 'id')->where('user_id', Auth::id())],
            'crm_company_id' => ['nullable', Rule::exists('crm_companies', 'id')->where('user_id', Auth::id())],
            'crm_deal_id'    => ['nullable', Rule::exists('crm_deals', 'id')->where('user_id', Auth::id())],
        ]);

        CrmProject::create(array_merge($validated, ['user_id' => Auth::id()]));

        return response()->json(['message' => __('Project created successfully.'), 'type' => 'success']);
    }

    public function update(Request $request, CrmProject $project): JsonResponse
    {
        abort_if($project->user_id !== Auth::id(), 404);

        if (Helper::appIsDemo()) {
            return response()->json(['status' => 'error', 'message' => __('This feature is disabled in demo mode.')], 422);
        }

        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'description'    => 'nullable|string|max:5000',
            'status'         => 'nullable|in:not_started,in_progress,on_hold,completed,cancelled',
            'priority'       => 'nullable|in:low,medium,high,urgent',
            'category'       => 'nullable|string|max:100',
            'start_date'     => 'nullable|date',
            'due_date'       => 'nullable|date',
            'budget'         => 'nullable|numeric|min:0',
            'currency'       => 'nullable|string|max:10',
            'notes'          => 'nullable|string|max:5000',
            'crm_contact_id' => ['nullable', Rule::exists('crm_contacts', 'id')->where('user_id', Auth::id())],
            'crm_company_id' => ['nullable', Rule::exists('crm_companies', 'id')->where('user_id', Auth::id())],
            'crm_deal_id'    => ['nullable', Rule::exists('crm_deals', 'id')->where('user_id', Auth::id())],
        ]);

        if (($validated['status'] ?? null) === 'completed' && $project->status !== 'completed') {
            $validated['completed_at'] = now();
        } elseif (($validated['status'] ?? null) !== 'completed') {
            $validated['completed_at'] = null;
        }

        $project->update($validated);

        return response()->json(['message' => __('Project updated successfully.'), 'type' => 'success']);
    }

    public function delete(CrmProject $project): RedirectResponse|JsonResponse
    {
        abort_if($project->user_id !== Auth::id(), 404);

        if (Helper::appIsDemo()) {
            if (request()->ajax()) {
                return response()->json(['status' => 'error', 'message' => __('This feature is disabled in demo mode.')], 422);
            }

            return back()->with(['type' => 'error', 'message' => __('This feature is disabled in demo mode.')]);
        }

        $project->delete();

        if (request()->ajax()) {
            return response()->json([
                'message'  => __('Project deleted successfully.'),
                'type'     => 'success',
                'redirect' => route('dashboard.user.crm.projects.index'),
            ]);
        }

        return back()->with(['message' => __('Project deleted successfully.'), 'type' => 'success']);
    }

    public function updateStatus(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json(['status' => 'error', 'message' => __('This feature is disabled in demo mode.')], 422);
        }

        $validated = $request->validate([
            'project_id' => 'required|exists:crm_projects,id',
            'status'     => 'required|in:not_started,in_progress,on_hold,completed,cancelled',
        ]);

        $project = CrmProject::where('id', $validated['project_id'])
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $updateData = ['status' => $validated['status']];

        if ($validated['status'] === 'completed') {
            $updateData['completed_at'] = now();
        } else {
            $updateData['completed_at'] = null;
        }

        $project->update($updateData);

        return response()->json(['message' => __('Project status updated.'), 'type' => 'success']);
    }

    public function storeTask(Request $request, CrmProject $project): JsonResponse
    {
        abort_if($project->user_id !== Auth::id(), 404);

        if (Helper::appIsDemo()) {
            return response()->json(['status' => 'error', 'message' => __('This feature is disabled in demo mode.')], 422);
        }

        $validated = $request->validate([
            'title'          => 'required|string|max:255',
            'description'    => 'nullable|string|max:5000',
            'type'           => 'nullable|in:task,call,meeting,email,follow_up',
            'status'         => 'nullable|in:pending,completed,cancelled',
            'priority'       => 'nullable|in:low,medium,high',
            'due_date'       => 'nullable|date',
            'crm_contact_id' => ['nullable', Rule::exists('crm_contacts', 'id')->where('user_id', Auth::id())],
        ]);

        CrmTask::create(array_merge($validated, [
            'user_id'        => Auth::id(),
            'crm_project_id' => $project->id,
        ]));

        return response()->json(['message' => __('Task added to project.'), 'type' => 'success']);
    }
}
