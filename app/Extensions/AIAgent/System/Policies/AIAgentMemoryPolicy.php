<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Policies;

use App\Extensions\AIAgent\System\Models\AIAgentMemory;
use App\Models\User;

class AIAgentMemoryPolicy
{
    public function update(User $user, AIAgentMemory $memory): bool
    {
        return $user->id === $memory->user_id;
    }

    public function delete(User $user, AIAgentMemory $memory): bool
    {
        return $user->id === $memory->user_id;
    }
}
