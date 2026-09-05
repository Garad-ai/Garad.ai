<?php

namespace App\Extensions\MarketingBot\System\Policies;

use App\Extensions\MarketingBot\System\Models\Telegram\TelegramGroup;
use App\Models\User;

class TelegramGroupPolicy
{
    public function delete(User $user, TelegramGroup $item): bool
    {
        return $user->id === $item->user_id;
    }
}
