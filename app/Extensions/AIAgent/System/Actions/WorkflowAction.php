<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Actions;

use App\Extensions\AIAgent\System\Actions\Contracts\AIAgentActionInterface;
use App\Extensions\AIAgent\System\Enums\TriggerTypeEnum;
use App\Extensions\AIAgent\System\Enums\WorkflowStatusEnum;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\AIAgent\System\Triggers\ScheduleTrigger;
use Exception;

class WorkflowAction implements AIAgentActionInterface
{
    public function __construct(
        private readonly ScheduleTrigger $scheduleTrigger,
    ) {}

    /**
     * Config keys:
     *   - operation     (string)  : create | update | delete | list
     *   - name          (string)  : workflow name (required for create)
     *   - description   (string)  : optional description
     *   - trigger_type  (string)  : schedule | channel_message
     *   - cron          (string)  : cron expression for schedule triggers, e.g. "0 9 * * *"
     *   - steps         (array)   : action steps [{type, config}] (e.g. generate_report, send_message)
     *   - status        (string)  : active | paused (for update)
     *   - workflow_id   (int)     : target workflow id (update/delete)
     *   - workflow_name (string)  : target by name if workflow_id not given (update/delete)
     */
    public function getCategory(): string
    {
        return 'utilities';
    }

    public function getLabel(): string
    {
        return 'Manage Workflow';
    }

    public function getDescription(): string
    {
        return 'Create, update, delete, or list workflows programmatically.';
    }

    public function getIcon(): string
    {
        return 'tabler-workflow';
    }

    public function getConfigSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'operation'     => ['type' => 'string', 'enum' => ['create', 'update', 'delete', 'list']],
                'workflow_id'   => ['type' => 'integer'],
                'workflow_name' => ['type' => 'string'],
                'name'          => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $operation = $config['operation'] ?? 'list';
        $userId = $workflow->user_id;

        $toolResult = match ($operation) {
            'create' => $this->create($config, $context, $userId),
            'update' => $this->update($config, $context, $userId),
            'delete' => $this->delete($config, $userId),
            'list'   => $this->list($userId),
            default  => throw new Exception("AgentAction: unknown operation '{$operation}'."),
        };

        return array_merge($context, ['tool_result' => $toolResult]);
    }

    private function create(array $config, array $context, int $userId): string
    {
        $name = trim((string) ($config['name'] ?? ''));

        if (empty($name)) {
            throw new Exception('AgentAction: name is required for create.');
        }

        $triggerType = TriggerTypeEnum::from($config['trigger_type'] ?? TriggerTypeEnum::Schedule->value);
        $triggerConfig = $this->buildTriggerConfig($triggerType, $config);
        $actions = $this->buildActions($config['steps'] ?? [], $context);

        $data = [
            'user_id'        => $userId,
            'name'           => $name,
            'description'    => $config['description'] ?? null,
            'status'         => WorkflowStatusEnum::Active,
            'trigger_type'   => $triggerType,
            'trigger_config' => $triggerConfig,
            'actions'        => $actions,
        ];

        if ($triggerType === TriggerTypeEnum::Schedule && ! empty($triggerConfig['cron'])) {
            $data['next_run_at'] = $this->scheduleTrigger->nextRunAt($triggerConfig['cron']);
        }

        $created = AIAgentWorkflow::query()->create($data);

        return __('✓ Agent ":name" created successfully (ID: :id).', [
            'name' => $created->name,
            'id'   => $created->id,
        ]);
    }

    private function update(array $config, array $context, int $userId): string
    {
        $target = $this->resolveWorkflow($config, $userId);
        $updates = [];

        if (isset($config['name'])) {
            $updates['name'] = trim((string) $config['name']);
        }

        if (isset($config['description'])) {
            $updates['description'] = $config['description'];
        }

        if (isset($config['status'])) {
            $updates['status'] = WorkflowStatusEnum::from($config['status']);
        }

        if (isset($config['trigger_type'])) {
            $triggerType = TriggerTypeEnum::from($config['trigger_type']);
            $updates['trigger_type'] = $triggerType;
            $updates['trigger_config'] = $this->buildTriggerConfig($triggerType, $config);

            if ($triggerType === TriggerTypeEnum::Schedule && ! empty($updates['trigger_config']['cron'])) {
                $updates['next_run_at'] = $this->scheduleTrigger->nextRunAt($updates['trigger_config']['cron']);
            }
        } elseif (isset($config['cron'])) {
            $triggerConfig = $target->trigger_config ?? [];
            $triggerConfig['cron'] = $config['cron'];
            $updates['trigger_config'] = $triggerConfig;
            $updates['next_run_at'] = $this->scheduleTrigger->nextRunAt($config['cron']);
        }

        if (isset($config['steps'])) {
            $updates['actions'] = $this->buildActions($config['steps'], $context);
        }

        if (empty($updates)) {
            return __('No changes provided for agent ":name".', ['name' => $target->name]);
        }

        $target->update($updates);

        return __('✓ Agent ":name" updated successfully.', ['name' => $target->name]);
    }

    private function delete(array $config, int $userId): string
    {
        $target = $this->resolveWorkflow($config, $userId);
        $name = $target->name;
        $target->delete();

        return __('✓ Agent ":name" deleted successfully.', ['name' => $name]);
    }

    private function list(int $userId): string
    {
        $workflows = AIAgentWorkflow::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'status', 'trigger_type', 'last_run_at']);

        if ($workflows->isEmpty()) {
            return __('You have no agents yet.');
        }

        $lines = $workflows->map(fn (AIAgentWorkflow $w) => sprintf(
            '• [#%d] %s — %s (%s)%s',
            $w->id,
            $w->name,
            $w->trigger_type->value,
            $w->status->value,
            $w->last_run_at ? ' — last run: ' . $w->last_run_at->diffForHumans() : '',
        ));

        return __('Your agents (:count):', ['count' => $workflows->count()])
            . "\n"
            . $lines->implode("\n");
    }

    /**
     * Resolve a workflow by workflow_id (int) or workflow_name (string).
     */
    private function resolveWorkflow(array $config, int $userId): AIAgentWorkflow
    {
        $query = AIAgentWorkflow::query()->where('user_id', $userId);

        if (! empty($config['workflow_id'])) {
            /** @var AIAgentWorkflow|null $workflow */
            $workflow = $query->find((int) $config['workflow_id']);
        } elseif (! empty($config['workflow_name'])) {
            /** @var AIAgentWorkflow|null $workflow */
            $workflow = $query->where('name', $config['workflow_name'])->first();
        } else {
            throw new Exception('AgentAction: workflow_id or workflow_name is required.');
        }

        if ($workflow === null) {
            throw new Exception('AgentAction: workflow not found.');
        }

        return $workflow;
    }

    /**
     * Build trigger_config from incoming config.
     */
    private function buildTriggerConfig(TriggerTypeEnum $triggerType, array $config): array
    {
        if ($triggerType === TriggerTypeEnum::Schedule) {
            return ['cron' => $config['cron'] ?? '0 9 * * *'];
        }

        if ($triggerType === TriggerTypeEnum::ChannelMessage) {
            return [
                'channel_id'     => $config['channel_id'] ?? null,
                'keyword_filter' => $config['keyword_filter'] ?? ['enabled' => false, 'ruleGroups' => []],
            ];
        }

        return [];
    }

    /**
     * Build the actions array, auto-injecting channel context into send_message steps.
     *
     * The default message for send_message is derived from the output key of the nearest
     * preceding content-producing step (generate_report → {report_text}, ai_call → {ai_response}).
     * Falls back to {tool_result} when no such step precedes it.
     *
     * @param  array<int, array{type: string, config?: array<string, mixed>}>  $steps
     * @param  array<string, mixed>  $context
     *
     * @return array<int, array{type: string, config: array<string, mixed>}>
     */
    private function buildActions(array $steps, array $context): array
    {
        $lastOutputKey = null;

        return array_map(function (array $step) use ($context, &$lastOutputKey): array {
            $type = $step['type'] ?? '';
            $cfg = $step['config'] ?? [];

            if ($type === 'generate_report') {
                $lastOutputKey = $cfg['store_output_as'] ?? 'report_text';
            } elseif ($type === 'ai_call') {
                $lastOutputKey = $cfg['store_output_as'] ?? 'ai_response';
            }

            if ($type === 'send_message') {
                $cfg['channel_id'] ??= $context['channel_id'] ?? null;
                $cfg['recipient_id'] ??= '{sender_id}';
                $cfg['message'] ??= $lastOutputKey ? "{{$lastOutputKey}}" : '{tool_result}';
            }

            return ['type' => $type, 'config' => $cfg];
        }, $steps);
    }
}
