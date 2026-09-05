<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Services;

use App\Extensions\AIAgent\System\Exceptions\PlanLimitExceededException;
use App\Extensions\AIAgent\System\Memory\MemoryRepository;
use App\Extensions\AIAgent\System\Models\AIAgentChannel;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Models\User;

class AIAgentPlanService
{
    public function __construct(private readonly MemoryRepository $memoryRepository) {}

    /**
     * Throw if the user has reached their workflow limit.
     */
    public function checkWorkflowLimit(User $user): void
    {
        $limit = $this->workflowLimit($user);

        if ($limit < 0) {
            return;
        }

        $current = AIAgentWorkflow::query()->where('user_id', $user->id)->count();

        if ($current >= $limit) {
            throw new PlanLimitExceededException("Your plan allows a maximum of {$limit} workflows.");
        }
    }

    /**
     * Throw if the user has reached their channel limit.
     */
    public function checkChannelLimit(User $user): void
    {
        $limit = $this->channelLimit($user);

        if ($limit < 0) {
            return;
        }

        $current = AIAgentChannel::query()->where('user_id', $user->id)->count();

        if ($current >= $limit) {
            throw new PlanLimitExceededException("Your plan allows a maximum of {$limit} connected channels.");
        }
    }

    /**
     * Throw if the user has reached their memory entry limit.
     */
    public function checkMemoryLimit(User $user): void
    {
        $limit = $this->memoryLimit($user);

        if ($limit < 0) {
            return;
        }

        $current = $this->memoryRepository->countForUser($user->id);

        if ($current >= $limit) {
            throw new PlanLimitExceededException("Your plan allows a maximum of {$limit} memory entries.");
        }
    }

    private function workflowLimit(User $user): int
    {
        $plan = $user->activePlan();

        return (int) ($plan?->ai_agent_workflow_limit ?? -1);
    }

    private function channelLimit(User $user): int
    {
        $plan = $user->activePlan();

        return (int) ($plan?->ai_agent_channel_limit ?? -1);
    }

    private function memoryLimit(User $user): int
    {
        $plan = $user->activePlan();

        return (int) ($plan?->ai_agent_memory_limit ?? -1);
    }
}
