<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Actions;

use App\Extensions\AIAgent\System\Actions\Contracts\AIAgentActionInterface;
use App\Extensions\AIAgent\System\Memory\MemoryRepository;
use App\Extensions\AIAgent\System\Models\AIAgentMemory;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\AIAgent\System\Services\AIAgentPlanService;
use Exception;

class MemoryAction implements AIAgentActionInterface
{
    public function __construct(
        private readonly MemoryRepository $memoryRepository,
        private readonly AIAgentPlanService $planService,
    ) {}

    /**
     * Config keys (injected by the tool calling engine):
     *   - operation (string) : "set" or "delete"
     *   - memory (string)    : free-text memory to store (required for "set")
     *   - id (int)           : memory record ID to delete (required for "delete")
     */
    public function getCategory(): string
    {
        return 'utilities';
    }

    public function getLabel(): string
    {
        return 'Update Memory';
    }

    public function getDescription(): string
    {
        return 'Save or delete a memory entry for the user.';
    }

    public function getIcon(): string
    {
        return 'tabler-database';
    }

    public function getConfigSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'operation' => ['type' => 'string', 'enum' => ['set', 'delete']],
                'memory'    => ['type' => 'string', 'description' => 'Memory text to save.'],
                'id'        => ['type' => 'integer', 'description' => 'Memory ID to delete.'],
            ],
        ];
    }

    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $operation = $config['operation'] ?? 'set';
        $userId = $workflow->user_id;

        if ($operation === 'delete') {
            $id = (int) ($config['id'] ?? 0);

            if (! $id) {
                throw new Exception('MemoryAction: id is required for delete operation.');
            }

            $memory = AIAgentMemory::query()->where('user_id', $userId)->find($id);

            if ($memory) {
                $this->memoryRepository->forget($memory);
            }

            $toolResult = __('✓ Memory deleted.');
        } else {
            $memoryText = trim((string) ($config['memory'] ?? ''));

            if (empty($memoryText)) {
                throw new Exception('MemoryAction: memory is required for set operation.');
            }

            $this->planService->checkMemoryLimit($workflow->user);

            $this->memoryRepository->remember($userId, $memoryText);
            $toolResult = __('✓ Memory saved: :memory', ['memory' => $memoryText]);
        }

        return array_merge($context, ['tool_result' => $toolResult]);
    }
}
