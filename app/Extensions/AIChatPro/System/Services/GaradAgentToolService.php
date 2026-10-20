<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Services;

use App\Extensions\AIAgent\System\Engine\WorkflowEngine;
use App\Extensions\AIAgent\System\Enums\WorkflowStatusEnum;
use App\Extensions\AIAgent\System\Enums\TriggerTypeEnum;
use Illuminate\Support\Str;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Helpers\Classes\MarketplaceHelper;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class GaradAgentToolService
{
    public function create(array $arguments): string
    {
        $this->ensureAvailable();

        $name = trim((string) ($arguments['name'] ?? ''));
        $instructions = trim((string) ($arguments['instructions'] ?? ''));
        if ($name === '' || $instructions === '') {
            throw new RuntimeException('Agent name and instructions are required.');
        }

        $agent = AIAgentWorkflow::query()->create([
            'user_id' => Auth::id(),
            'name' => Str::limit($name, 255, ''),
            'description' => Str::limit((string) ($arguments['description'] ?? $instructions), 1000, ''),
            'role' => 'Garad Agent',
            'status' => WorkflowStatusEnum::Active,
            'trigger_type' => TriggerTypeEnum::Webhook,
            'trigger_config' => ['created_from' => 'chat_pro', 'manual_agent' => true],
            'steps' => [[
                'id' => (string) Str::uuid(),
                'type' => 'ai_call',
                'app' => 'AI',
                'action' => 'ai_call',
                'config' => [
                    'system_prompt' => $instructions,
                    'user_message' => '{task}',
                    'history_turns' => 0,
                    'inject_memory' => (bool) ($arguments['use_memory'] ?? true),
                    'web_search' => (bool) ($arguments['web_search'] ?? false),
                    'store_output_as' => 'ai_response',
                ],
                'order' => 0,
            ]],
        ]);

        return sprintf('Agent "%s" created successfully with ID #%d. You can ask me to run it at any time.', e($agent->name), $agent->id);
    }

    public function list(): string
    {
        $this->ensureAvailable();

        $agents = $this->selectionCatalog();

        if ($agents === []) {
            return 'No active, runnable saved agents are available for this account.';
        }

        $lines = ['Your Garad AI agents:'];
        foreach ($agents as $agent) {
            $lines[] = sprintf(
                '• #%d — %s (%s)%s',
                $agent['id'],
                $agent['name'],
                $agent['status'],
                $agent['description'] !== '' ? ': ' . $agent['description'] : '',
            );
        }

        return implode('<br>', array_map('e', $lines));
    }

    public function selectionCatalog(): array
    {
        $this->ensureAvailable();

        return AIAgentWorkflow::query()
            ->where('user_id', Auth::id())
            ->where('status', WorkflowStatusEnum::Active->value)
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'status', 'trigger_config', 'steps', 'actions'])
            ->filter(function (AIAgentWorkflow $workflow): bool {
                $raw = (string) ($workflow->getRawOriginal('trigger_config') ?? '');
                $trigger = $raw !== '' ? json_decode($raw, true) : null;
                if (is_array($trigger) && (bool) ($trigger['garad_task'] ?? false)) {
                    return false;
                }

                return app(WorkflowEngine::class)->validateWorkflow($workflow, [
                    'task' => 'task',
                    'user_prompt' => 'task',
                    'message_text' => 'task',
                    'sender_id' => 'user',
                    'source' => 'chat_pro',
                    'chat_id' => '1',
                ]) === [];
            })
            ->map(function (AIAgentWorkflow $workflow): array {
                $status = $workflow->status instanceof WorkflowStatusEnum
                    ? $workflow->status->value
                    : (string) $workflow->status;

                return [
                    'id' => (string) $workflow->id,
                    'name' => (string) $workflow->name,
                    'description' => trim((string) $workflow->description),
                    'status' => $status,
                    'action_types' => $this->workflowActionTypes($workflow->resolved_steps),
                ];
            })
            ->values()
            ->all();
    }

    public function run(array $arguments): string
    {
        $this->ensureAvailable();

        $agentRef = trim((string) ($arguments['agent'] ?? ''));
        $task = trim((string) ($arguments['task'] ?? ''));

        if ($agentRef === '') {
            throw new RuntimeException('Agent name or ID is required.');
        }
        if ($task === '') {
            throw new RuntimeException('A task is required for the agent.');
        }

        $query = AIAgentWorkflow::query()->where('user_id', Auth::id());
        if (ctype_digit($agentRef)) {
            $agent = $query->whereKey((int) $agentRef)->first();
        } else {
            $agent = (clone $query)->where('name', $agentRef)->first()
                ?? $query->where('name', 'like', '%' . addcslashes($agentRef, '%_') . '%')->first();
        }

        if ($agent) {
            $raw = (string) ($agent->getRawOriginal('trigger_config') ?? '');
            $trigger = $raw !== '' ? json_decode($raw, true) : null;
            if (is_array($trigger) && (bool) ($trigger['garad_task'] ?? false)) {
                $agent = null;
            }
        }

        if (! $agent || $agent->status !== WorkflowStatusEnum::Active) {
            throw new RuntimeException('The requested AI agent was not found.');
        }

        $engine = app(WorkflowEngine::class);
        $validationErrors = $engine->validateWorkflow($agent, [
            'task' => $task,
            'user_prompt' => $task,
            'message_text' => $task,
            'sender_id' => (string) Auth::id(),
            'source' => 'chat_pro',
            'chat_id' => (string) request()?->input('chat_id'),
        ]);
        if ($validationErrors !== []) {
            throw new RuntimeException('The selected agent is not runnable: ' . implode(' ', $validationErrors));
        }

        $chatId = (int) request()?->input('chat_id');
        $attachmentContext = $chatId > 0
            ? app(GaradMultimodalService::class)->buildSystemContextMessage($chatId, $task)
            : null;
        $initialContext = [
            'task'         => $task,
            'user_prompt'  => $task,
            'message_text' => $task,
            'sender_id'    => (string) Auth::id(),
            'source'       => 'chat_pro',
        ];
        if ($attachmentContext !== null && trim($attachmentContext) !== '') {
            $initialContext['attachment_context'] = $attachmentContext;
        }

        $messageId = (int) request()?->attributes->get('garad_chat_message_id');
        $run = $engine->run(
            $agent,
            [
                'trigger_type' => 'chat_pro',
                'fired_at'     => now()->toIso8601String(),
                'task'         => $task,
                'chat_id'      => $chatId,
                'message_id'   => $messageId > 0 ? $messageId : null,
                'trigger_type' => 'chat_pro',
            ],
            $initialContext,
            $messageId > 0
                ? sprintf('agent-chat:%d:%d:%d', $chatId, $messageId, $agent->id)
                : null,
        );

        $status = $run->status?->value ?? (string) $run->status;
        request()?->attributes->set('garad_agent_run_status', $status);
        if ($status === 'failed') {
            $reason = trim((string) $run->error_message);

            throw new RuntimeException(
                $reason !== ''
                    ? sprintf('Agent "%s" failed: %s', $agent->name, $reason)
                    : sprintf('Agent "%s" failed before completing the task.', $agent->name),
            );
        }

        if ($status === 'cancelled') {
            return sprintf('Agent "%s" was cancelled. No further workflow steps were run.', e($agent->name));
        }

        if ($status === 'pending_approval') {
            $pendingStep = $run->steps()
                ->with('approval')
                ->where('status', 'waiting_for_approval')
                ->first();
            $approval = $pendingStep?->approval;
            if (! $approval || $approval->status !== 'pending') {
                throw new RuntimeException('The agent run is waiting for approval, but its approval request could not be loaded.');
            }

            if (request()?->boolean('agent_mode')) {
                request()->attributes->set('garad_agent_pending_approval_id', (string) $approval->id);
            }

            $action = trim((string) $pendingStep->type) ?: 'this protected action';

            return sprintf(
                'Agent "%s" is paused. Approval is required before "%s" can run. Approval #%d; run #%d. The protected action has not been executed.',
                e($agent->name),
                e($action),
                $approval->id,
                $run->id,
            );
        }

        $toolResult = $this->extractToolResult((array) $run->steps_output);
        $message = sprintf('Agent “%s” completed the task.', e($agent->name));

        if ($toolResult !== '') {
            $message .= '<br><br>' . nl2br(e($toolResult));
        }

        return $message;
    }

    private function ensureAvailable(): void
    {
        if (! Auth::check()) {
            throw new RuntimeException('Please sign in to use AI agents.');
        }

        if (! MarketplaceHelper::isRegistered('ai-agent')) {
            throw new RuntimeException('AI Agent is not available on this installation.');
        }
    }

    private function extractToolResult(array $steps): string
    {
        $preferredKeys = ['tool_result', 'ai_response', 'report_output', 'response', 'result'];

        foreach (array_reverse($steps) as $step) {
            $output = $step['output'] ?? null;
            if (! is_array($output)) {
                continue;
            }

            foreach ($preferredKeys as $key) {
                if (! array_key_exists($key, $output) || $output[$key] === null || $output[$key] === '') {
                    continue;
                }

                return is_scalar($output[$key])
                    ? (string) $output[$key]
                    : (json_encode($output[$key], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
            }
        }

        return '';
    }

    /**
     * @param  array<int, mixed>  $steps
     * @return array<int, string>
     */
    private function workflowActionTypes(array $steps): array
    {
        $types = [];
        foreach ($steps as $step) {
            if (! is_array($step)) {
                continue;
            }

            $type = trim((string) ($step['type'] ?? $step['action'] ?? ''));
            if ($type !== '') {
                $types[] = $type;
            }

            if ($type === 'path') {
                foreach (($step['config']['paths'] ?? []) as $path) {
                    if (is_array($path) && is_array($path['steps'] ?? null)) {
                        $types = array_merge($types, $this->workflowActionTypes($path['steps']));
                    }
                }
            }
        }

        return array_values(array_unique($types));
    }
}
