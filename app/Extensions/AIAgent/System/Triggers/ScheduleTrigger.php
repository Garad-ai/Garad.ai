<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Triggers;

use App\Extensions\AIAgent\System\Models\AIAgentMessage;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Triggers\Contracts\TriggerInterface;
use Carbon\Carbon;
use Cron\CronExpression;

class ScheduleTrigger implements TriggerInterface
{
    public function shouldFire(AIAgentWorkflow $workflow): bool
    {
        $config = $workflow->trigger_config ?? [];
        $cron = $config['cron'] ?? null;
$cron = is_string($cron) ? trim($cron) : $cron;

if (empty($cron) || ! CronExpression::isValidExpression($cron)) {
    return false;
}
        // Never ran or next_run_at is in the past
        if ($workflow->next_run_at === null || $workflow->next_run_at->isPast()) {
            return true;
        }

        return false;
    }

    public function buildTriggerData(AIAgentWorkflow $workflow): array
    {
        $data = [
            'trigger_type' => 'schedule',
            'fired_at'     => now()->toIso8601String(),
            'cron'         => $workflow->trigger_config['cron'] ?? null,
        ];

        // Resolve sender_id from the last inbound message on the send_message step's channel,
        // so {sender_id} works as a recipient template in schedule-triggered workflows.
        $sendStep = collect($workflow->resolved_steps)
            ->first(fn (array $s) => ($s['type'] ?? $s['action'] ?? '') === 'send_message');

        $channelId = $sendStep['config']['channel_id'] ?? $workflow->trigger_config['channel_id'] ?? null;

        if (! empty($channelId)) {
            $senderId = AIAgentMessage::query()
                ->where('channel_id', (int) $channelId)
                ->where('direction', 'inbound')
                ->latest('id')
                ->value('sender_id');

            if ($senderId) {
                $data['sender_id'] = $senderId;
            }
        }

        return $data;
    }

    /**
     * Calculate the next fire time from a cron expression.
     */
    public function nextRunAt(string $cron): Carbon
    {
        $expression = new CronExpression($cron);

        return Carbon::instance($expression->getNextRunDate());
    }
}
