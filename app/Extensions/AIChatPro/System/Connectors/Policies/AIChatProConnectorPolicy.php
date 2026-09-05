<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Connectors\Policies;

use App\Extensions\AIChatPro\System\Connectors\Models\AIChatProConnector;
use App\Models\User;

class AIChatProConnectorPolicy
{
    public function view(User $user, AIChatProConnector $connector): bool
    {
        return $user->id === $connector->user_id;
    }

    public function delete(User $user, AIChatProConnector $connector): bool
    {
        return $user->id === $connector->user_id;
    }
}
