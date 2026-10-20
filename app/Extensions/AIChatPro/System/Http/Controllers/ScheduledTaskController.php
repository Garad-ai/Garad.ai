<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Http\Controllers;

use App\Extensions\AIChatPro\System\Services\GaradScheduledDeliveryService;
use App\Extensions\AIChatPro\System\Services\GaradScheduledTaskService;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ScheduledTaskController extends Controller
{
    public function __construct(private readonly GaradScheduledTaskService $tasks) {}

    public function tick(GaradScheduledDeliveryService $delivery): JsonResponse
    {
        $userId = (int) Auth::id();
        if ($userId <= 0) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return response()->json([
            'delivered' => $delivery->deliver($userId),
            'started' => false,
        ]);
    }

    public function index(Request $request): View|JsonResponse
    {
        $selectedRun = null;
        $runId = (int) $request->query('run', 0);
        if ($runId > 0) {
            $run = AIAgentWorkflowRun::query()
                ->where('id', $runId)
                ->whereHas('workflow', function ($query): void {
                    $query->where('user_id', Auth::id())
                        ->where('trigger_config->garad_task', true);
                })
                ->with('workflow:id,user_id,name')
                ->first();

            if ($run) {
                $result = '';
                foreach (array_reverse((array) $run->steps_output) as $step) {
                    $output = $step['output'] ?? null;
                    if (! is_array($output)) continue;
                    foreach (['task_result', 'ai_response', 'notification_message', 'result'] as $key) {
                        if (! empty($output[$key])) {
                            $result = is_scalar($output[$key]) ? (string) $output[$key] : (json_encode($output[$key], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) ?: '');
                            break 2;
                        }
                    }
                }
                $selectedRun = [
                    'id' => $run->id,
                    'title' => $run->workflow?->name ?: __('Scheduled task result'),
                    'status' => $run->status->value,
                    'result' => $result,
                    'completed_at' => $run->completed_at,
                    'error' => $run->error_message,
                ];
            }
        }

        $tasks = $this->tasks->list(100);
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'tasks' => $tasks]);
        }

        return view('ai-chat-pro::workspace.scheduled', [
            'tasks' => $tasks,
            'selectedRun' => $selectedRun,
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['required', 'string', 'max:5000'],
            'task_type' => ['required', 'in:reminder,agent_task,monitor'],
            'schedule_type' => ['required', 'in:one_time,recurring'],
            'run_at' => ['nullable', 'string', 'max:100'],
            'cron' => ['nullable', 'string', 'max:100'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'web_search' => ['nullable', 'boolean'],
            'notification_enabled' => ['nullable', 'boolean'],
        ]);
        $task = $this->tasks->create($data);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'task' => $task]);
        }

        return back()->with(['type' => 'success', 'message' => __('Scheduled task created.')]);
    }

    public function status(Request $request, int $taskId): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:active,paused']]);
        $task = $this->tasks->setStatus($taskId, $data['status']);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'task' => $task]);
        }

        return back();
    }

    public function update(Request $request, int $taskId): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'instructions' => ['sometimes', 'required', 'string', 'max:5000'],
            'task_type' => ['sometimes', 'required', 'in:reminder,agent_task,monitor'],
            'schedule_type' => ['sometimes', 'required', 'in:one_time,recurring'],
            'run_at' => ['nullable', 'string', 'max:100'],
            'cron' => ['nullable', 'string', 'max:100'],
            'timezone' => ['sometimes', 'required', 'string', 'max:100'],
            'web_search' => ['nullable', 'boolean'],
            'notification_enabled' => ['nullable', 'boolean'],
        ]);
        $task = $this->tasks->update($taskId, $data);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'task' => $task]);
        }

        return back()->with(['type' => 'success', 'message' => __('Scheduled task updated.')]);
    }

    public function run(Request $request, int $taskId): JsonResponse
    {
        $task = $this->tasks->runNow(
            $taskId,
            (string) $request->header('Idempotency-Key', ''),
        );

        return response()->json(['success' => true, 'task' => $task]);
    }

    public function destroy(Request $request, int $taskId): JsonResponse|RedirectResponse
    {
        $this->tasks->delete($taskId);
        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with(['type' => 'success', 'message' => __('Scheduled task deleted.')]);
    }
}
