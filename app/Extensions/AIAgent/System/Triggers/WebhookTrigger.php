<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Triggers;

use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Triggers\Contracts\TriggerInterface;

/**
 * WebhookTrigger fires when an inbound HTTP POST is received.
 * The controller resolves the workflow by UUID and calls WorkflowEngine directly —
 * shouldFire() is not used in the polling loop for this trigger type.
 */
class WebhookTrigger implements TriggerInterface
{
    public function shouldFire(AIAgentWorkflow $workflow): bool
    {
        // Webhook triggers are fired on-demand by the controller, not by the cron loop.
        return false;
    }

    public function buildTriggerData(AIAgentWorkflow $workflow): array
    {
        return [
            'trigger_type' => 'webhook',
            'fired_at'     => now()->toIso8601String(),
        ];
    }

    /**
     * Build trigger data from an inbound webhook payload.
     */
    public function buildFromPayload(array $payload): array
    {
        return [
            'trigger_type' => 'webhook',
            'fired_at'     => now()->toIso8601String(),
            'payload'      => $payload,
        ];
    }
}
