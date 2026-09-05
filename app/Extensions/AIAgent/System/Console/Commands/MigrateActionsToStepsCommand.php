<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Console\Commands;

use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use Illuminate\Console\Command;

class MigrateActionsToStepsCommand extends Command
{
    protected $signature = 'ai-agent:migrate-actions-to-steps';

    protected $description = 'Back-fill the new steps column from the legacy actions column for existing workflows.';

    public function handle(): int
    {
        $count = 0;

        AIAgentWorkflow::query()
            ->whereNull('steps')
            ->whereNotNull('actions')
            ->chunkById(100, function ($workflows) use (&$count): void {
                foreach ($workflows as $workflow) {
                    $workflow->update(['steps' => $workflow->resolved_steps]);
                    $count++;
                    $this->line("[AIAgent] Migrated workflow #{$workflow->id} \"{$workflow->name}\".");
                }
            });

        $this->info("[AIAgent] Migration complete. {$count} workflow(s) updated.");

        return self::SUCCESS;
    }
}
