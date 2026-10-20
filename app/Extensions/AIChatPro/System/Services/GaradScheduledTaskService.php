<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Services;

use App\Extensions\AIAgent\System\Enums\TriggerTypeEnum;
use App\Extensions\AIAgent\System\Enums\WorkflowStatusEnum;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\AIAgent\System\Engine\WorkflowEngine;
use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class GaradScheduledTaskService
{
    public const TASK_TYPES = ['reminder', 'agent_task', 'monitor'];

    public function create(array $args): array
    {
        $userId = Auth::id();
        if (! $userId) {
            throw new InvalidArgumentException('You must be signed in to create a scheduled task.');
        }

        $title = trim((string) ($args['title'] ?? ''));
        $instructions = trim((string) ($args['instructions'] ?? ''));
        $taskType = strtolower(trim((string) ($args['task_type'] ?? 'reminder')));
        $scheduleType = strtolower(trim((string) ($args['schedule_type'] ?? 'one_time')));
        $timezone = trim((string) ($args['timezone'] ?? config('app.timezone', 'UTC')));
        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            throw new InvalidArgumentException('Choose a valid IANA timezone.');
        }

        if ($title === '' || $instructions === '') {
            throw new InvalidArgumentException('Task title and instructions are required.');
        }
        if (! in_array($taskType, self::TASK_TYPES, true)) {
            throw new InvalidArgumentException('Unsupported task type.');
        }
        if (! in_array($scheduleType, ['one_time', 'recurring'], true)) {
            throw new InvalidArgumentException('Schedule type must be one_time or recurring.');
        }

        $triggerConfig = [
            'garad_task' => true,
            'garad_task_type' => $taskType,
            'timezone' => $timezone,
            'instructions' => $instructions,
            'web_search' => (bool) ($args['web_search'] ?? $taskType !== 'reminder'),
            'notification_enabled' => (bool) ($args['notification_enabled'] ?? true),
            'created_from' => 'chat_pro',
        ];

        $nextRunAt = null;
        if ($scheduleType === 'one_time') {
            $runAt = trim((string) ($args['run_at'] ?? ''));
            if ($runAt === '') {
                throw new InvalidArgumentException('run_at is required for a one-time task.');
            }

            try {
                $when = Carbon::parse($runAt, $timezone);
            } catch (\Throwable) {
                throw new InvalidArgumentException('The requested task time could not be understood.');
            }
            if ($when->isPast()) {
                throw new InvalidArgumentException('The requested task time is in the past.');
            }
            $triggerConfig['run_once_at'] = $when->copy()->timezone(config('app.timezone', 'UTC'))->toIso8601String();
            $nextRunAt = $when->copy()->timezone(config('app.timezone', 'UTC'));
        } else {
            $cron = trim((string) ($args['cron'] ?? ''));
            if ($cron === '' || ! CronExpression::isValidExpression($cron)) {
                throw new InvalidArgumentException('A valid 5-field cron expression is required for a recurring task.');
            }
            $triggerConfig['cron'] = $cron;
            $nextRunAt = Carbon::instance((new CronExpression($cron))->getNextRunDate('now', 0, false, $timezone))
                ->timezone(config('app.timezone', 'UTC'));
        }

        $steps = $this->stepsFor(
            $taskType,
            $title,
            $instructions,
            (bool) $triggerConfig['web_search'],
            (bool) ($args['notification_enabled'] ?? true),
        );

        $workflow = AIAgentWorkflow::query()->create([
            'user_id' => $userId,
            'name' => Str::limit($title, 255, ''),
            'description' => Str::limit($instructions, 1000, ''),
            'role' => 'Garad Scheduled',
            'status' => WorkflowStatusEnum::Active,
            'trigger_type' => TriggerTypeEnum::Schedule,
            'trigger_config' => $triggerConfig,
            'steps' => $steps,
            'next_run_at' => $nextRunAt,
        ]);

        return $this->serialize($workflow->fresh());
    }

    public function list(int $limit = 20): array
    {
        return AIAgentWorkflow::query()
            ->where('user_id', Auth::id())
            ->where('trigger_type', TriggerTypeEnum::Schedule)
            ->where('trigger_config->garad_task', true)
            ->latest('id')
            ->limit(max(1, min($limit, 50)))
            ->get()
            ->map(fn (AIAgentWorkflow $workflow) => $this->serialize($workflow))
            ->values()
            ->all();
    }

    public function setStatus(int $taskId, string $status): array
    {
        $workflow = $this->owned($taskId);

        if (in_array($status, ['paused', 'pause'], true)) {
            $workflow->update(['status' => WorkflowStatusEnum::Paused]);

            return $this->serialize($workflow->fresh());
        }

        if (! in_array($status, ['active', 'resume'], true)) {
            throw new InvalidArgumentException('Unsupported task status.');
        }

        $config = $workflow->trigger_config ?? [];
        $nextRunAt = $workflow->next_run_at;

        if (! empty($config['run_once_at'])) {
            if ($workflow->last_run_at !== null) {
                throw new InvalidArgumentException('This one-time task has already completed. Create a new task to run it again.');
            }

            try {
                $scheduled = Carbon::parse((string) $config['run_once_at']);
                $nextRunAt = $scheduled->isFuture() ? $scheduled : now();
            } catch (\Throwable) {
                throw new InvalidArgumentException('The stored task time is invalid.');
            }
        } elseif (! empty($config['cron'])) {
            $timezone = (string) ($config['timezone'] ?? config('app.timezone', 'UTC'));
            try {
                $nextRunAt = Carbon::instance((new CronExpression((string) $config['cron']))
                    ->getNextRunDate('now', 0, false, $timezone))
                    ->timezone(config('app.timezone', 'UTC'));
            } catch (\Throwable) {
                throw new InvalidArgumentException('The stored recurring schedule is invalid.');
            }
        }

        $workflow->update([
            'status' => WorkflowStatusEnum::Active,
            'next_run_at' => $nextRunAt,
        ]);

        return $this->serialize($workflow->fresh());
    }

    public function delete(int $taskId): void
    {
        $workflow = $this->owned($taskId);
        $workflow->delete();
    }

    public function update(int $taskId, array $args): array
    {
        $workflow = $this->owned($taskId);
        $config = $workflow->trigger_config ?? [];
        $title = trim((string) ($args['title'] ?? $workflow->name));
        $instructions = trim((string) ($args['instructions'] ?? $config['instructions'] ?? $workflow->description));
        $taskType = (string) ($args['task_type'] ?? $config['garad_task_type'] ?? 'agent_task');
        $scheduleType = (string) ($args['schedule_type'] ?? (isset($config['run_once_at']) ? 'one_time' : 'recurring'));
        $timezone = (string) ($args['timezone'] ?? $config['timezone'] ?? config('app.timezone', 'UTC'));
        $scheduleChanged = array_key_exists('schedule_type', $args)
            || array_key_exists('run_at', $args)
            || array_key_exists('cron', $args)
            || array_key_exists('timezone', $args);

        if ($title === '' || $instructions === '') {
            throw new InvalidArgumentException('Task title and instructions are required.');
        }
        if (! in_array($taskType, self::TASK_TYPES, true)) {
            throw new InvalidArgumentException('Unsupported task type.');
        }
        if ($scheduleChanged && ! in_array($scheduleType, ['one_time', 'recurring'], true)) {
            throw new InvalidArgumentException('Schedule type must be one_time or recurring.');
        }
        if ($scheduleChanged && ! in_array($timezone, timezone_identifiers_list(), true)) {
            throw new InvalidArgumentException('Choose a valid IANA timezone.');
        }

        $config['garad_task_type'] = $taskType;
        $config['timezone'] = $timezone;
        $config['instructions'] = $instructions;
        $config['web_search'] = (bool) ($args['web_search'] ?? ($config['web_search'] ?? $taskType !== 'reminder'));
        $config['notification_enabled'] = (bool) ($args['notification_enabled'] ?? ($config['notification_enabled'] ?? true));
        $nextRunAt = $workflow->next_run_at;
        if ($scheduleChanged) {
            if ($scheduleType === 'one_time') {
                $runAt = trim((string) ($args['run_at'] ?? $config['run_once_at'] ?? ''));
                if ($runAt === '') {
                    throw new InvalidArgumentException('run_at is required for a one-time task.');
                }
                try {
                    $when = Carbon::parse($runAt, $timezone);
                } catch (\Throwable) {
                    throw new InvalidArgumentException('The requested task time could not be understood.');
                }
                if ($when->isPast()) {
                    throw new InvalidArgumentException('The requested task time is in the past.');
                }
                $config['run_once_at'] = $when->copy()->timezone(config('app.timezone', 'UTC'))->toIso8601String();
                unset($config['cron']);
                $nextRunAt = $when->copy()->timezone(config('app.timezone', 'UTC'));
            } else {
                $cron = trim((string) ($args['cron'] ?? $config['cron'] ?? ''));
                if ($cron === '' || ! CronExpression::isValidExpression($cron)) {
                    throw new InvalidArgumentException('A valid 5-field cron expression is required for a recurring task.');
                }
                $config['cron'] = $cron;
                unset($config['run_once_at']);
                $nextRunAt = Carbon::instance((new CronExpression($cron))->getNextRunDate('now', 0, false, $timezone))
                    ->timezone(config('app.timezone', 'UTC'));
            }
        }

        $updates = [
            'name' => Str::limit($title, 255, ''),
            'description' => Str::limit($instructions, 1000, ''),
            'trigger_config' => $config,
            'steps' => $this->stepsFor(
                $taskType,
                $title,
                $instructions,
                (bool) $config['web_search'],
                (bool) ($args['notification_enabled'] ?? $config['notification_enabled']),
            ),
        ];
        if ($scheduleChanged) {
            $updates['next_run_at'] = $nextRunAt;
            if ($scheduleType === 'one_time') {
                $updates['last_run_at'] = null;
            }
        }
        $workflow->update($updates);

        return $this->serialize($workflow->fresh());
    }

    public function runNow(int $taskId, string $idempotencyKey): array
    {
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 100) {
            throw new InvalidArgumentException('A valid execution idempotency key is required.');
        }

        $workflow = $this->owned($taskId);
        $existing = $workflow->runs()
            ->where('trigger_data->idempotency_key', $idempotencyKey)
            ->first();
        if ($existing) {
            return $this->serialize($workflow->fresh());
        }

        $lock = Cache::lock('garad-scheduled-workflow:' . $workflow->id, 1800);
        if (! $lock->get()) {
            throw new InvalidArgumentException('This scheduled task is already running.');
        }

        try {
            $workflow->refresh();
            $existing = $workflow->runs()
                ->where('trigger_data->idempotency_key', $idempotencyKey)
                ->first();
            if (! $existing) {
                app(WorkflowEngine::class)->run(
                    $workflow,
                    [
                        'trigger_type' => 'manual',
                        'fired_at' => now()->toIso8601String(),
                        'idempotency_key' => $idempotencyKey,
                        'garad_chat_runner' => true,
                    ],
                );
                $taskConfig = $workflow->trigger_config ?? [];
                $updates = ['last_run_at' => now()];
                if (isset($taskConfig['run_once_at'])) {
                    $updates['next_run_at'] = null;
                } elseif (! empty($taskConfig['cron'])) {
                    $nextRun = $workflow->next_run_at;
                    if ($nextRun === null || $nextRun->lessThanOrEqualTo(now())) {
                        try {
                            $cron = (string) $taskConfig['cron'];
                            if (! CronExpression::isValidExpression($cron)) {
                                throw new InvalidArgumentException('The stored recurring schedule is invalid.');
                            }
                            $nextRun = Carbon::instance((new CronExpression($cron))
                                ->getNextRunDate('now', 0, false, (string) ($taskConfig['timezone'] ?? config('app.timezone', 'UTC'))))
                                ->timezone(config('app.timezone', 'UTC'));
                        } catch (\Throwable $e) {
                            $nextRun = null;
                            \Illuminate\Support\Facades\Log::warning('[GaradChat] Could not advance a manually-run scheduled task.', [
                                'workflow_id' => $workflow->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                    $updates['next_run_at'] = $nextRun;
                }
                $workflow->update($updates);
            }
        } finally {
            $lock->release();
        }

        return $this->serialize($workflow->fresh());
    }

    public function serialize(AIAgentWorkflow $workflow): array
    {
        $config = $workflow->trigger_config ?? [];
        $runs = $workflow->runs()->latest('id')->limit(20)->get();
        $lastRun = $runs->first();
        $status = $workflow->status->value;
        if ($lastRun?->status->value === 'running') {
            $status = 'running';
        } elseif ($lastRun?->status->value === 'failed') {
            $status = 'failed';
        } elseif (! empty($config['run_once_at']) && $workflow->last_run_at !== null) {
            $status = $lastRun?->status->value === 'completed' ? 'completed' : $status;
        }
        $cron = $config['cron'] ?? null;

        return [
            'id' => $workflow->id,
            'title' => $workflow->name,
            'instructions' => $config['instructions'] ?? $workflow->description,
            'task_type' => $config['garad_task_type'] ?? 'agent_task',
            'notification_enabled' => (bool) ($config['notification_enabled'] ?? true),
            'status' => $status,
            'schedule_type' => isset($config['run_once_at']) ? 'one_time' : 'recurring',
            'run_at' => $config['run_once_at'] ?? null,
            'cron' => $cron,
            'schedule' => $this->scheduleLabel($config),
            'timezone' => $config['timezone'] ?? config('app.timezone', 'UTC'),
            'next_run_at' => $workflow->next_run_at?->toIso8601String(),
            'last_run_at' => $workflow->last_run_at?->toIso8601String(),
            'last_run_status' => $lastRun?->status?->value,
            'last_run_id' => $lastRun?->id,
            'history' => $runs->map(fn (AIAgentWorkflowRun $run): array => [
                'id' => $run->id,
                'status' => $run->status->value,
                'started_at' => $run->started_at?->toIso8601String(),
                'completed_at' => $run->completed_at?->toIso8601String(),
                'duration_ms' => $run->started_at && $run->completed_at
                    ? $run->completed_at->diffInMilliseconds($run->started_at)
                    : null,
                'result' => $this->executionResult($run),
                'error' => $run->error_message,
                'delivery_status' => $this->deliveryStatus($run),
                'delivery_error' => $run->trigger_data['delivery_error'] ?? null,
                'delivery_skip_reason' => $run->trigger_data['delivery_skip_reason'] ?? null,
            ])->all(),
        ];
    }

    private function deliveryStatus(AIAgentWorkflowRun $run): string
    {
        $triggerData = (array) ($run->trigger_data ?? []);
        $delivered = $triggerData['chat_delivered'] ?? null;

        if ($delivered === 'skipped') {
            return 'skipped';
        }
        if (is_numeric($delivered) && (int) $delivered > 0) {
            return 'delivered';
        }
        if (! empty($triggerData['delivery_error'])) {
            return 'failed';
        }

        return 'pending';
    }

    private function scheduleLabel(array $config): string
    {
        if (isset($config['run_once_at'])) {
            return Carbon::parse($config['run_once_at'])
                ->timezone((string) ($config['timezone'] ?? config('app.timezone', 'UTC')))
                ->format('M j, Y · g:i A');
        }

        $cron = (string) ($config['cron'] ?? '');
        return match ($cron) {
            '0 8 * * 1' => 'Every Monday at 8:00 AM',
            default => $cron !== '' ? 'Cron: ' . $cron : 'Scheduled',
        };
    }

    public function executionResult(AIAgentWorkflowRun $run): ?string
    {
        foreach (array_reverse((array) $run->steps_output) as $step) {
            $output = $step['output'] ?? null;
            if (! is_array($output)) {
                continue;
            }
            foreach (['task_result', 'ai_response', 'notification_message', 'result'] as $key) {
                if (isset($output[$key]) && is_scalar($output[$key])) {
                    return (string) $output[$key];
                }
            }
        }
        return null;
    }

    private function owned(int $taskId): AIAgentWorkflow
    {
        return AIAgentWorkflow::query()
            ->where('id', $taskId)
            ->where('user_id', Auth::id())
            ->where('trigger_config->garad_task', true)
            ->firstOrFail();
    }

    private function stepsFor(
        string $taskType,
        string $title,
        string $instructions,
        bool $webSearch,
        bool $notificationEnabled,
    ): array
    {
        $notify = [
            'id' => (string) Str::uuid(),
            'type' => 'notify_user',
            'app' => 'Garad',
            'action' => 'notify_user',
            'config' => [
                'title' => $title,
                'message' => $taskType === 'reminder' ? $instructions : '{task_result}',
                'skip_if' => $taskType === 'monitor' ? 'NO_UPDATE' : null,
            ],
            'order' => $taskType === 'reminder' ? 0 : 1,
        ];

        if ($taskType === 'reminder') {
            return $notificationEnabled ? [$notify] : [];
        }

        $system = $taskType === 'monitor'
            ? 'You are a scheduled monitoring agent. Check the requested condition using available web search when enabled. If there is no meaningful new development worth notifying the user about, reply exactly NO_UPDATE. Otherwise provide a concise useful update with the key change and why it matters.'
            : 'You are Garad AI running an unattended scheduled task for the user, who will read the result later in chat. Complete the task thoroughly using web search when available. For news, briefings, research and updates write a detailed, well-structured Markdown report: many distinct items (typically 8-15), each with a bold headline, 2-4 sentences of substance, the source name with its link, and the date when known; then a short Key takeaways section. Include relevant images only if real image URLs were returned by tools. Use only facts actually retrieved; say plainly when something could not be found. Write in the language of the task. Do not claim an external action happened unless the workflow actually performed it.';

        $steps = [
            [
                'id' => (string) Str::uuid(),
                'type' => 'ai_call',
                'app' => 'AI',
                'action' => 'ai_call',
                'config' => [
                    'system_prompt' => $system,
                    'user_message' => $instructions,
                    'history_turns' => 0,
                    'inject_memory' => true,
                    'web_search' => $webSearch,
                    'store_output_as' => 'task_result',
                ],
                'order' => 0,
            ],
        ];
        if ($notificationEnabled) {
            $steps[] = $notify;
        }

        return $steps;
    }
}
