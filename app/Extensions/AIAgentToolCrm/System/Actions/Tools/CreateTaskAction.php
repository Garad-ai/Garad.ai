<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentToolCrm\System\Actions\Tools;

use App\Extensions\AIAgent\System\Actions\Contracts\ActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\AIAgentToolCrm\System\Actions\Tools\Concerns\NormalizesCrmInput;
use App\Extensions\Crm\System\Models\CrmTask;
use Exception;
use Illuminate\Support\Str;

class CreateTaskAction implements ActionInterface
{
    use NormalizesCrmInput;

    /**
     * Config keys:
     *   - title           (string, req)
     *   - description     (string)
     *   - priority        (string) : low | medium | high | urgent (default: medium)
     *   - due_date        (string) : date/time
     *   - contact_id      (int)
     *   - deal_id         (int)
     *   - store_output_as (string) : context key for the result (default: created_task)
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $storeOutputAs = $config['store_output_as'] ?? 'created_task';
        $title = $this->nullableString($config['title'] ?? null);

        if ($title === null) {
            throw new Exception('create_task requires title.');
        }

        $task = CrmTask::query()->create([
            'user_id'        => $workflow->user_id,
            'title'          => Str::limit($title, 250),
            'description'    => $this->nullableString($config['description'] ?? null),
            'type'           => 'task',
            'status'         => 'pending',
            'priority'       => $this->normalizePriority($config['priority'] ?? null),
            'due_date'       => $this->parseDate($config['due_date'] ?? null),
            'crm_contact_id' => $this->nullableId($config['contact_id'] ?? null),
            'crm_deal_id'    => $this->nullableId($config['deal_id'] ?? null),
        ]);

        $result = [
            'id'       => $task->id,
            'title'    => $task->title,
            'status'   => $task->status,
            'priority' => $task->priority,
            'due_date' => $task->due_date?->toIso8601String(),
        ];

        return array_merge($context, [$storeOutputAs => $result]);
    }

    /**
     * An AI step can answer with anything ("Urgent!", "P1"), so fall back to the default
     * rather than writing a value the CRM filters cannot match.
     */
    private function normalizePriority(mixed $value): string
    {
        $priority = mb_strtolower((string) ($this->nullableString($value) ?? ''));

        return in_array($priority, ['low', 'medium', 'high', 'urgent'], true) ? $priority : 'medium';
    }
}
