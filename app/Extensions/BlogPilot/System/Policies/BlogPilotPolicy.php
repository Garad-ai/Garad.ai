<?php

namespace App\Extensions\BlogPilot\System\Policies;

use App\Extensions\BlogPilot\System\Models\BlogPilot;
use App\Models\User;

class BlogPilotPolicy
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
    public function view(User $user, BlogPilot $agent): bool
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
    public function update(User $user, BlogPilot $agent): bool
    {
        return $user->id === $agent->user_id;
    }

    /**
     * Determine whether the user can delete the agent.
     */
    public function delete(User $user, BlogPilot $agent): bool
    {
        return $user->id === $agent->user_id;
    }

    /**
     * Determine whether the user can restore the agent.
     */
    public function restore(User $user, BlogPilot $agent): bool
    {
        return $user->id === $agent->user_id;
    }

    /**
     * Determine whether the user can permanently delete the agent.
     */
    public function forceDelete(User $user, BlogPilot $agent): bool
    {
        return $user->id === $agent->user_id;
    }
}
