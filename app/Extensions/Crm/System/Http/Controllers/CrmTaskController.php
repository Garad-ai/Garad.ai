<?php

namespace App\Extensions\Crm\System\Http\Controllers;

use App\Extensions\Crm\System\Models\CrmContact;
use App\Extensions\Crm\System\Models\CrmDeal;
use App\Extensions\Crm\System\Models\CrmTask;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CrmTaskController extends Controller
{
    public function index(Request $request): View
    {
        $query = CrmTask::query()
            ->where('user_id', Auth::id())
            ->with(['contact', 'deal']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        $userId = Auth::id();

        // Stats (always unfiltered)
        $totalTasks = CrmTask::where('user_id', $userId)->count();
        $pendingTasks = CrmTask::where('user_id', $userId)->where('status', 'pending')->count();
        $overdueTasks = CrmTask::where('user_id', $userId)
            ->where('status', 'pending')
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->count();
        $completedThisWeek = CrmTask::where('user_id', $userId)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', now()->startOfWeek())
            ->count();

        $stats = [
            ['label' => __('Total Tasks'), 'value' => number_format($totalTasks), 'icon' => 'tabler-list-check', 'color' => 'primary'],
            ['label' => __('Pending'), 'value' => number_format($pendingTasks), 'icon' => 'tabler-clock', 'color' => 'amber-500'],
            ['label' => __('Overdue'), 'value' => number_format($overdueTasks), 'icon' => 'tabler-alert-triangle', 'color' => 'red-500'],
            ['label' => __('Done This Week'), 'value' => number_format($completedThisWeek), 'icon' => 'tabler-circle-check', 'color' => 'emerald-500'],
        ];

        return view('crm::tasks.index', [
            'list'     => $query->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")->orderBy('due_date', 'asc')->get(),
            'filters'  => $request->only(['status', 'type', 'priority']),
            'contacts' => CrmContact::where('user_id', $userId)->orderBy('first_name')->get(),
            'deals'    => CrmDeal::where('user_id', $userId)->orderBy('title')->get(),
            'stats'    => $stats,
        ]);
    }

    public function board(): View
    {
        $tasks = CrmTask::query()
            ->where('user_id', Auth::id())
            ->with(['contact', 'deal'])
            ->orderBy('order', 'asc')
            ->orderBy('due_date', 'asc')
            ->get();

        $columns = [
            'pending'   => ['label' => __('Pending'), 'color' => '#f59e0b', 'tasks' => $tasks->where('status', 'pending')->values()],
            'completed' => ['label' => __('Completed'), 'color' => '#22c55e', 'tasks' => $tasks->where('status', 'completed')->values()],
            'cancelled' => ['label' => __('Cancelled'), 'color' => '#ef4444', 'tasks' => $tasks->where('status', 'cancelled')->values()],
        ];

        return view('crm::tasks.board', [
            'columns'  => $columns,
            'contacts' => CrmContact::where('user_id', Auth::id())->orderBy('first_name')->get(),
            'deals'    => CrmDeal::where('user_id', Auth::id())->orderBy('title')->get(),
        ]);
    }

    public function updateStatus(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json(['status' => 'error', 'message' => __('This feature is disabled in demo mode.')], 422);
        }

        $validated = $request->validate([
            'task_id'       => 'required_without:ordered_ids|exists:crm_tasks,id',
            'status'        => 'required|in:pending,completed,cancelled',
            'ordered_ids'   => 'nullable|array',
            'ordered_ids.*' => 'integer',
        ]);

        if (! empty($validated['ordered_ids'])) {
            $this->reorderTasks($validated['ordered_ids'], $validated['status']);

            return response()->json(['message' => __('Task status updated.'), 'type' => 'success']);
        }

        $task = CrmTask::where('id', $validated['task_id'])
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $task->update($this->taskStatusAttributes($validated['status']));

        return response()->json(['message' => __('Task status updated.'), 'type' => 'success', 'taskStatus' => $task->status]);
    }

    /**
     * @param  array<int, int>  $orderedIds
     */
    private function reorderTasks(array $orderedIds, string $status): void
    {
        $userId = Auth::id();

        foreach (array_values($orderedIds) as $position => $taskId) {
            CrmTask::where('id', $taskId)
                ->where('user_id', $userId)
                ->update(array_merge(
                    $this->taskStatusAttributes($status),
                    ['order' => $position],
                ));
        }
    }

    /**
     * @return array{status: string, completed_at: Carbon|null}
     */
    private function taskStatusAttributes(string $status): array
    {
        return [
            'status'       => $status,
            'completed_at' => $status === 'completed' ? now() : null,
        ];
    }

    public function store(Request $request): JsonResponse
    {
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
            'crm_deal_id'    => ['nullable', Rule::exists('crm_deals', 'id')->where('user_id', Auth::id())],
        ]);

        CrmTask::create(array_merge($validated, ['user_id' => Auth::id()]));

        return response()->json(['message' => __('Task created successfully.'), 'type' => 'success']);
    }

    public function update(Request $request, CrmTask $task): JsonResponse
    {
        abort_if($task->user_id !== Auth::id(), 404);

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
            'crm_deal_id'    => ['nullable', Rule::exists('crm_deals', 'id')->where('user_id', Auth::id())],
        ]);

        $task->update($validated);

        return response()->json(['message' => __('Task updated successfully.'), 'type' => 'success']);
    }

    public function delete(CrmTask $task): RedirectResponse
    {
        abort_if($task->user_id !== Auth::id(), 404);

        if (Helper::appIsDemo()) {
            return back()->with(['type' => 'error', 'message' => __('This feature is disabled in demo mode.')]);
        }

        $task->delete();

        return back()->with(['message' => __('Task deleted successfully.'), 'type' => 'success']);
    }

    public function toggleComplete(CrmTask $task): JsonResponse
    {
        abort_if($task->user_id !== Auth::id(), 404);

        if (Helper::appIsDemo()) {
            return response()->json(['status' => 'error', 'message' => __('This feature is disabled in demo mode.')], 422);
        }

        if ($task->status === 'completed') {
            $task->update(['status' => 'pending', 'completed_at' => null]);
        } else {
            $task->update(['status' => 'completed', 'completed_at' => now()]);
        }

        return response()->json(['message' => __('Task status updated.'), 'type' => 'success']);
    }
}
