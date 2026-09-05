<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Http\Controllers\Webhook;

use App\Extensions\AIAgent\System\Engine\WorkflowEngine;
use App\Extensions\AIAgent\System\Enums\TriggerTypeEnum;
use App\Extensions\AIAgent\System\Enums\WorkflowStatusEnum;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class GenericWebhookController extends Controller
{
    public function __construct(private readonly WorkflowEngine $engine) {}

    /**
     * Accept an inbound webhook and fire the matching workflow.
     * The workflow UUID is passed as a route parameter for security.
     */
    public function handle(string $uuid, Request $request): array
    {
        /** @var AIAgentWorkflow|null $workflow */
        $workflow = AIAgentWorkflow::query()
            ->where('id', $uuid) // uuid stored as id for now; switch to uuid column in v2
            ->where('trigger_type', TriggerTypeEnum::Webhook)
            ->where('status', WorkflowStatusEnum::Active)
            ->first();

        if ($workflow === null) {
            return ['ok' => false, 'message' => 'Agent not found.'];
        }

        $this->engine->fireWebhookTrigger($workflow, $request->all());

        return ['ok' => true];
    }
}
