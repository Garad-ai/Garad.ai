<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Services;

use Carbon\Carbon;

final class GaradTaskToolService
{
    public function __construct(private readonly GaradScheduledTaskService $tasks) {}

    public function create(array $args): string
    {
        $task = $this->tasks->create($args);
        $type = match ($task['task_type']) {
            'monitor' => 'Monitor',
            'agent_task' => 'AI task',
            default => 'Reminder',
        };
        $when = $this->humanTime($task['next_run_at'] ?? null, $task['cron'] ?? null);

        return implode("\n", [
            'Scheduled successfully.',
            (string) $task['title'],
            $type . ' · ' . $when,
            'You can manage it from Scheduled in Chat Pro.',
        ]);
    }

    public function list(array $args = []): string
    {
        $items = $this->tasks->list((int) ($args['limit'] ?? 20));
        if ($items === []) {
            return 'You have no scheduled reminders or AI tasks.';
        }

        return collect($items)->map(function (array $task): string {
            $when = $this->humanTime($task['next_run_at'] ?? null, $task['cron'] ?? null);
            return sprintf('[#%d] %s — %s — %s', $task['id'], $task['title'], ucfirst((string) $task['status']), $when);
        })->implode("\n");
    }

    public function update(array $args): string
    {
        $task = $this->tasks->setStatus((int) ($args['task_id'] ?? 0), (string) ($args['status'] ?? ''));
        return sprintf('Updated “%s”. It is now %s.', $task['title'], $task['status']);
    }

    public function delete(array $args): string
    {
        $id = (int) ($args['task_id'] ?? 0);
        $this->tasks->delete($id);
        return 'The scheduled task was deleted.';
    }

    private function humanTime(?string $nextRunAt, ?string $cron): string
    {
        if ($nextRunAt) {
            try {
                return Carbon::parse($nextRunAt)
                    ->timezone(config('app.timezone', 'UTC'))
                    ->format('M j, Y · g:i A');
            } catch (\Throwable) {
            }
        }

        return $cron ? 'Recurring schedule' : 'Scheduled';
    }
}
