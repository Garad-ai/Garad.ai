<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Policies;

use App\Extensions\AIAgent\System\Models\AIAgentChannel;
use App\Models\User;

class AIAgentChannelPolicy
{
    public function view(User $user, AIAgentChannel $channel): bool
    {
        return $user->id === $channel->user_id;
    }

    public function update(User $user, AIAgentChannel $channel): bool
    {
        return $user->id === $channel->user_id;
    }

    public function delete(User $user, AIAgentChannel $channel): bool
    {
        return $user->id === $channel->user_id;
    }
}
