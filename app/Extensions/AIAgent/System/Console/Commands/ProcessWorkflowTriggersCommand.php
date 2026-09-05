<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Console\Commands;

use App\Extensions\AIAgent\System\Engine\WorkflowEngine;
use Illuminate\Console\Command;

class ProcessWorkflowTriggersCommand extends Command
{
    protected $signature = 'ai-agent:process-triggers';

    protected $description = 'Evaluate scheduled workflow triggers and fire any that are due';

    public function handle(WorkflowEngine $engine): void
    {
        $this->info('[AIAgent] Checking scheduled workflows...');

        $fired = $engine->processScheduledWorkflows($this);

        if ($fired === 0) {
            $this->line('[AIAgent] No workflows due to run.');
        } else {
            $this->info("[AIAgent] Done. {$fired} workflow(s) processed.");
        }
    }
}
