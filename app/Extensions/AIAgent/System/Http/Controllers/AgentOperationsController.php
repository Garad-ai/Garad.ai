<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Http\Controllers;

use App\Extensions\AIAgent\System\Engine\WorkflowEngine;
use App\Extensions\AIAgent\System\Enums\WorkflowRunStatusEnum;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowApproval;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRunStep;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class AgentOperationsController extends Controller
{
    public function workflows(): JsonResponse
    {
        return response()->json([
            'data' => AIAgentWorkflow::query()
                ->where('user_id', Auth::id())
                ->with(['runs' => fn ($query) => $query->latest()->limit(1)])
                ->latest()
                ->limit(100)
                ->get()
                ->map(fn (AIAgentWorkflow $workflow): array => [
                    'id'            => (string) $workflow->id,
                    'name'          => $workflow->name,
                    'description'   => $workflow->description,
                    'status'        => $workflow->status->value,
                    'triggerType'   => $workflow->trigger_type->value,
                    'triggerConfig' => $workflow->trigger_config ?? [],
                    'steps'         => $workflow->resolved_steps,
                    'latestRun'     => $workflow->runs->first()
                        ? $this->runPayload($workflow->runs->first())
                        : null,
                    'createdAt' => $workflow->created_at?->toIso8601String(),
                ])
                ->all(),
        ]);
    }

    public function runs(): JsonResponse
    {
        $runs = AIAgentWorkflowRun::query()
            ->whereHas('workflow', fn ($query) => $query->where('user_id', Auth::id()))
            ->with(['workflow:id,user_id,name', 'steps.approval'])
            ->latest()
            ->limit(100)
            ->get();

        return response()->json(['data' => $runs->map(fn (AIAgentWorkflowRun $run): array => $this->runPayload($run))->all()]);
    }

    public function conversationActivity(int $chatId): JsonResponse
    {
        $runs = AIAgentWorkflowRun::query()
            ->where('trigger_data->chat_id', $chatId)
            ->where('trigger_data->trigger_type', 'chat_pro')
            ->whereHas('workflow', fn ($query) => $query->where('user_id', Auth::id()))
            ->with(['workflow:id,user_id,name', 'steps.approval'])
            ->latest()
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $runs->map(function (AIAgentWorkflowRun $run): array {
                $status = $run->status->value;

                return [
                    'id'           => (string) $run->id,
                    'workflowId'   => (string) $run->workflow_id,
                    'workflowName' => $run->workflow?->name ?? __('Workflow'),
                    'messageId'    => isset($run->trigger_data['message_id'])
                        ? (string) $run->trigger_data['message_id']
                        : null,
                    'status'       => $status,
                    'startedAt'    => $run->started_at?->toIso8601String(),
                    'completedAt'  => $run->completed_at?->toIso8601String(),
                    'error'        => $status === 'failed'
                        ? __('The task could not be completed. Check the Agent response for details.')
                        : null,
                    'steps'        => $run->steps->map(fn (AIAgentWorkflowRunStep $step): array => [
                        'id'          => (string) $step->id,
                        'stepId'      => $step->step_id,
                        'index'       => (int) $step->step_index,
                        'type'        => (string) $step->type,
                        'label'       => $this->activityStepLabel((string) $step->type),
                        'status'      => (string) $step->status,
                        'summary'     => $this->activityStepSummary((string) $step->status),
                        'approvalId'  => $step->approval?->status === 'pending'
                            ? (string) $step->approval->id
                            : null,
                        'startedAt'   => $step->started_at?->toIso8601String(),
                        'completedAt' => $step->completed_at?->toIso8601String(),
                    ])->values()->all(),
                ];
            })->values()->all(),
        ])->header('Cache-Control', 'no-store');
    }

    public function cancelRun(AIAgentWorkflowRun $run): JsonResponse
    {
        $run = DB::transaction(function () use ($run): AIAgentWorkflowRun {
            $locked = AIAgentWorkflowRun::query()
                ->with('workflow')
                ->lockForUpdate()
                ->findOrFail($run->id);
            $this->authorizeOwner($locked->workflow);

            if ($locked->status === WorkflowRunStatusEnum::PendingApproval) {
                AIAgentWorkflowApproval::query()
                    ->where('workflow_run_id', $locked->id)
                    ->where('status', 'pending')
                    ->update([
                        'status'      => 'cancelled',
                        'comment'     => 'The task was cancelled by its owner.',
                        'decided_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                AIAgentWorkflowRunStep::query()
                    ->where('workflow_run_id', $locked->id)
                    ->where('status', 'waiting_for_approval')
                    ->update([
                        'status'       => 'cancelled',
                        'completed_at' => now(),
                        'updated_at'   => now(),
                    ]);
                $locked->update([
                    'status'       => WorkflowRunStatusEnum::Cancelled,
                    'completed_at' => now(),
                ]);
            } elseif ($locked->status === WorkflowRunStatusEnum::Running) {
                $locked->update(['status' => WorkflowRunStatusEnum::Cancelling]);
            } elseif (! in_array($locked->status, [
                WorkflowRunStatusEnum::Cancelling,
                WorkflowRunStatusEnum::Cancelled,
            ], true)) {
                abort(409, 'This task can no longer be cancelled.');
            }

            return $locked->fresh(['workflow:id,user_id,name', 'steps.approval']);
        });

        return response()->json([
            'data' => $this->runPayload($run),
        ]);
    }

    public function approvals(): JsonResponse
    {
        $approvals = AIAgentWorkflowApproval::query()
            ->where('requested_by', Auth::id())
            ->where('status', 'pending')
            ->with(['workflow:id,name,user_id', 'run:id,status,started_at', 'step:id,type,input,status'])
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (AIAgentWorkflowApproval $approval): array => [
                'id'           => (string) $approval->id,
                'workflowId'   => (string) $approval->workflow_id,
                'workflowName' => $approval->workflow?->name ?? __('Workflow'),
                'runId'        => (string) $approval->workflow_run_id,
                'stepId'       => (string) $approval->workflow_run_step_id,
                'action'       => $approval->step?->type,
                'payload'      => $approval->payload ?? [],
                'requestedAt'  => $approval->created_at?->toIso8601String(),
                'status'       => $approval->status,
            ])
            ->all();

        return response()->json(['data' => $approvals]);
    }

    public function run(Request $request, AIAgentWorkflow $workflow, WorkflowEngine $engine): JsonResponse
    {
        $this->authorizeOwner($workflow);
        $request->merge(['idempotency_key' => $request->header('Idempotency-Key')]);
        $data = $request->validate([
            'trigger_data'    => ['nullable', 'array'],
            'initial_context' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ]);

        $run = $engine->run(
            $workflow,
            $data['trigger_data'] ?? ['trigger_type' => 'manual', 'fired_at' => now()->toIso8601String()],
            $data['initial_context'] ?? [],
            $data['idempotency_key'] ?? null,
        );

        $run->load(['workflow:id,user_id,name', 'steps.approval']);

        return response()->json(['data' => $this->runPayload($run)], 201);
    }

    public function decideApproval(
        Request $request,
        AIAgentWorkflowApproval $approval,
        WorkflowEngine $engine,
    ): JsonResponse {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'comment'  => ['nullable', 'string', 'max:1000'],
        ]);

        $approval = DB::transaction(function () use ($approval, $data): AIAgentWorkflowApproval {
            $locked = AIAgentWorkflowApproval::query()->lockForUpdate()->findOrFail($approval->id);
            $workflow = AIAgentWorkflow::query()->findOrFail($locked->workflow_id);
            $this->authorizeOwner($workflow);

            if ($locked->status !== 'pending') {
                throw new ConflictHttpException('This approval has already been decided.');
            }

            $locked->update([
                'status'      => $data['decision'] === 'approve' ? 'approved' : 'rejected',
                'decision_by' => Auth::id(),
                'comment'     => $data['comment'] ?? null,
                'decided_at'  => now(),
            ]);

            return $locked->fresh();
        });

        $run = AIAgentWorkflowRun::query()->findOrFail($approval->workflow_run_id);
        if ($approval->status === 'rejected') {
            AIAgentWorkflowRunStep::query()
                ->whereKey($approval->workflow_run_step_id)
                ->update([
                    'status'        => 'rejected',
                    'error_message' => 'Action rejected by an authorized approver.',
                    'completed_at'  => now(),
                    'updated_at'    => now(),
                ]);
            $run->update([
                'status'        => WorkflowRunStatusEnum::Failed,
                'error_message' => 'A protected action was rejected by an authorized approver.',
                'completed_at'  => now(),
            ]);
        } else {
            $run = $engine->resumeAfterApproval($run);
        }

        $run->load(['workflow:id,user_id,name', 'steps.approval']);

        return response()->json([
            'data'     => $this->runPayload($run),
            'decision' => $approval->status,
        ]);
    }

    private function authorizeOwner(AIAgentWorkflow $workflow): void
    {
        abort_unless((int) $workflow->user_id === (int) Auth::id(), 403, 'You are not authorized to operate this workflow.');
    }

    /**
     * @return array<string, mixed>
     */
    private function runPayload(AIAgentWorkflowRun $run): array
    {
        return [
            'id'           => (string) $run->id,
            'workflowId'   => (string) $run->workflow_id,
            'workflowName' => $run->workflow?->name ?? __('Workflow'),
            'status'       => $run->status->value,
            'startedAt'    => $run->started_at?->toIso8601String(),
            'completedAt'  => $run->completed_at?->toIso8601String(),
            'error'        => $run->error_message,
            'steps'        => $run->relationLoaded('steps')
                ? $run->steps->map(fn (AIAgentWorkflowRunStep $step): array => [
                    'id'          => (string) $step->id,
                    'stepId'      => $step->step_id,
                    'index'       => $step->step_index,
                    'type'        => $step->type,
                    'status'      => $step->status,
                    'input'       => $step->input ?? [],
                    'output'      => $step->output ?? [],
                    'error'       => $step->error_message,
                    'approvalId'  => $step->approval ? (string) $step->approval->id : null,
                    'startedAt'   => $step->started_at?->toIso8601String(),
                    'completedAt' => $step->completed_at?->toIso8601String(),
                ])->all()
                : [],
        ];
    }

    private function activityStepLabel(string $type): string
    {
        return match ($type) {
            'ai_call' => __('Processing the task'),
            'send_message' => __('Preparing a message'),
            'generate_report' => __('Preparing a report'),
            'path' => __('Choosing the applicable task path'),
            'web_search' => __('Searching available sources'),
            'read_document' => __('Reading an authorized document'),
            'gmail', 'outlook', 'crm_agent', 'social_media_agent' => __('Using a connected application'),
            'memory' => __('Using saved task context'),
            'workflow' => __('Running a workflow step'),
            default => __('Executing :action', ['action' => str_replace('_', ' ', $type)]),
        };
    }

    private function activityStepSummary(string $status): string
    {
        return match ($status) {
            'running' => __('In progress'),
            'waiting_for_approval' => __('Waiting for your approval'),
            'completed' => __('Completed'),
            'failed' => __('This step failed'),
            'rejected' => __('Rejected'),
            'cancelled' => __('Cancelled'),
            default => __('Waiting to start'),
        };
    }
}
