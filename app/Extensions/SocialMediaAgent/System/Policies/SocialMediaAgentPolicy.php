<?php

namespace App\Extensions\SocialMediaAgent\System\Policies;

use App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgent;
use App\Models\User;

class SocialMediaAgentPolicy
{
    /**
     * Determine whether the user can view any agents.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the agent.
     */
    public function view(User $user, SocialMediaAgent $agent): bool
    {
        return $user->id === $agent->user_id;
    }

    /**
     * Determine whether the user can create agents.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the agent.
     */
    public function update(User $user, SocialMediaAgent $agent): bool
    {
        return $user->id === $agent->user_id;
    }

    /**
     * Determine whether the user can delete the agent.
     */
    public function delete(User $user, SocialMediaAgent $agent): bool
    {
        return $user->id === $agent->user_id;
    }

    /**
     * Determine whether the user can restore the agent.
     */
    public function restore(User $user, SocialMediaAgent $agent): bool
    {
        return $user->id === $agent->user_id;
    }

    /**
     * Determine whether the user can permanently delete the agent.
     */
    public function forceDelete(User $user, SocialMediaAgent $agent): bool
    {
        return $user->id === $agent->user_id;
    }
}
