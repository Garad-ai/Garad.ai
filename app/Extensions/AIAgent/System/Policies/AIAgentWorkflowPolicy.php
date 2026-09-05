<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Policies;

use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Models\User;

class AIAgentWorkflowPolicy
{
    public function view(User $user, AIAgentWorkflow $workflow): bool
    {
        return $user->id === $workflow->user_id;
    }

    public function update(User $user, AIAgentWorkflow $workflow): bool
    {
        return $user->id === $workflow->user_id;
    }

    public function delete(User $user, AIAgentWorkflow $workflow): bool
    {
        return $user->id === $workflow->user_id;
    }
}
