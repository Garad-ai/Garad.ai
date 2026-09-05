<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Jobs;

use App\Extensions\AIAgent\System\Engine\WorkflowEngine;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExecuteDelayedActionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $workflowId,
        public readonly array $context = [],
        public readonly array $triggerData = [],
    ) {}

    public function handle(WorkflowEngine $engine): void
    {
        $workflow = AIAgentWorkflow::query()->find($this->workflowId);

        if ($workflow === null || ! $workflow->isActive()) {
            return;
        }

        $engine->run($workflow, $this->triggerData, $this->context);
    }
}
