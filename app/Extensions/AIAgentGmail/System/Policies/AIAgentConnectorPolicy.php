<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentGmail\System\Policies;

use App\Extensions\AIAgentGmail\System\Models\AIAgentConnector;
use App\Models\User;

class AIAgentConnectorPolicy
{
    public function view(User $user, AIAgentConnector $connector): bool
    {
        return $user->id === $connector->user_id;
    }

    public function delete(User $user, AIAgentConnector $connector): bool
    {
        return $user->id === $connector->user_id;
    }
}
