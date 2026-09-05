<?php

namespace App\Extensions\MarketingBot\System\Policies;

use App\Extensions\MarketingBot\System\Models\MarketingConversation;
use App\Models\User;

class MarketingConversationPolicy
{
    public function edit(User $user, MarketingConversation $item): bool
    {
        return $user->id === $item->user_id;
    }

    public function update(User $user, MarketingConversation $item): bool
    {
        return $user->id === $item->user_id;
    }

    public function delete(User $user, MarketingConversation $item): bool
    {
        return $user->id === $item->user_id;
    }
}
