<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Triggers\Contracts;

use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;

interface TriggerInterface
{
    /**
     * Determine whether this workflow should fire right now.
     */
    public function shouldFire(AIAgentWorkflow $workflow): bool;

    /**
     * Return context data to pass into the workflow run.
     */
    public function buildTriggerData(AIAgentWorkflow $workflow): array;
}
