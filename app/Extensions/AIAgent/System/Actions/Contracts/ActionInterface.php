<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Actions\Contracts;

use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;

interface ActionInterface
{
    /**
     * Execute the action.
     *
     * @param  array<string, mixed>  $config   Action-specific config from the workflow definition.
     * @param  array<string, mixed>  $context  Accumulated context from previous steps.
     * @return array<string, mixed>  Updated context after this action.
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array;
}
