<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Console\Commands;

use App\Extensions\AIAgent\System\Engine\WorkflowEngine;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs only the scheduled tasks created from Garad AI Chat Pro (trigger_config.garad_task = true).
 * It deliberately does not touch legacy AI Agent workflows and is NOT registered with the Laravel
 * scheduler: invoke it explicitly (for example from a dedicated Windows Task Scheduler entry or cron).
 */
class ProcessGaradScheduledTasksCommand extends Command
{
    protected $signature = 'garad-chat:process-scheduled-tasks {--user= : Only process tasks owned by this user id}';

    protected $description = 'Fire due Garad AI Chat Pro scheduled tasks (isolated from legacy AI Agent workflows)';

    public function handle(WorkflowEngine $engine): int
    {
        $userId = $this->option('user');
        if ($userId !== null && ! ctype_digit((string) $userId)) {
            $this->error('The --user option must be a numeric user id.');

            return self::INVALID;
        }

        $this->info('[GaradChat] Checking Garad scheduled tasks...');
        Log::info('[GaradChat] Isolated scheduler pass started.', [
            'user_id' => $userId !== null ? (int) $userId : null,
        ]);

        try {
            $fired = $engine->processScheduledWorkflows(
                $this,
                static function (Builder $query) use ($userId): void {
                    $query->where('trigger_config->garad_task', true)
                        ->whereNotNull('next_run_at')
                        ->where('next_run_at', '<=', now());
                    if ($userId !== null) {
                        $query->where('user_id', (int) $userId);
                    }
                },
                ['garad_chat_runner' => true],
            );

            $delivery = app(\App\Extensions\AIChatPro\System\Services\GaradScheduledDeliveryService::class);
            $delivered = $userId !== null
                ? $delivery->deliver((int) $userId)
                : $delivery->deliverForAllUsers();
        } catch (Throwable $e) {
            Log::error('[GaradChat] Scheduled task processing failed.', ['error' => $e->getMessage()]);
            $this->error('Scheduled task processing failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        if ($fired === 0) {
            Log::info('[GaradChat] No Garad scheduled tasks were due.', [
                'user_id' => $userId !== null ? (int) $userId : null,
            ]);
        }

        $this->info($fired === 0
            ? '[GaradChat] No Garad scheduled tasks were due.'
            : "[GaradChat] Done. {$fired} task(s) processed.");
        $this->info("[GaradChat] Delivered {$delivered} result(s) to chat.");
        Log::info('[GaradChat] Isolated scheduler pass completed.', [
            'user_id' => $userId !== null ? (int) $userId : null,
            'processed' => $fired,
            'delivered' => $delivered,
        ]);

        return self::SUCCESS;
    }
}
