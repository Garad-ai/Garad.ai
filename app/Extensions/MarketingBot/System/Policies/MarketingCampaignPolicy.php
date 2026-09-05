<?php

namespace App\Extensions\MarketingBot\System\Policies;

use App\Extensions\MarketingBot\System\Models\MarketingCampaign;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class MarketingCampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return Auth::check();
    }

    public function train(User $user, MarketingCampaign $item): bool
    {
        return $user->id === $item->user_id;
    }

    public function edit(User $user, MarketingCampaign $item): bool
    {
        return $user->id === $item->user_id;
    }

    public function update(User $user, MarketingCampaign $item): bool
    {
        return $user->id === $item->user_id;
    }

    public function delete(User $user, MarketingCampaign $item): bool
    {
        return $user->id === $item->user_id;
    }
}
