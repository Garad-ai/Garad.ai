<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Services;

use App\Extensions\AIAgent\System\Engine\WorkflowEngine;
use App\Extensions\AIAgent\System\Enums\WorkflowStatusEnum;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Helpers\Classes\MarketplaceHelper;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class GaradAgentToolService
{
    public function list(): string
    {
        $this->ensureAvailable();

        $agents = AIAgentWorkflow::query()
            ->where('user_id', Auth::id())
            ->where('status', '!=', WorkflowStatusEnum::Archived->value)
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'status']);

        if ($agents->isEmpty()) {
            return 'You do not have any AI agents yet.';
        }

        $lines = ['Your Garad AI agents:'];
        foreach ($agents as $agent) {
            $status = $agent->status instanceof WorkflowStatusEnum ? $agent->status->value : (string) $agent->status;
            $description = trim((string) $agent->description);
            $lines[] = sprintf(
                '• #%d — %s (%s)%s',
                $agent->id,
                $agent->name,
                $status,
                $description !== '' ? ': ' . $description : '',
            );
        }

        return implode('<br>', array_map('e', $lines));
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

        if (! $agent || $agent->status === WorkflowStatusEnum::Archived) {
            throw new RuntimeException('The requested AI agent was not found.');
        }

        $run = app(WorkflowEngine::class)->run(
            $agent,
            [
                'trigger_type' => 'chat_pro',
                'fired_at'     => now()->toIso8601String(),
                'task'         => $task,
                'chat_id'      => (int) request()?->input('chat_id'),
            ],
            [
                'task'         => $task,
                'user_prompt'  => $task,
                'message_text' => $task,
                'sender_id'    => (string) Auth::id(),
                'source'       => 'chat_pro',
            ],
        );

        $status = $run->status?->value ?? (string) $run->status;
        if ($status === 'failed') {
            throw new RuntimeException('Agent run failed: ' . ($run->error_message ?: 'Unknown error'));
        }

        $toolResult = $this->extractToolResult((array) $run->steps_output);
        $message = sprintf('✅ Agent "%s" finished. Run #%d.', e($agent->name), $run->id);

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
}
