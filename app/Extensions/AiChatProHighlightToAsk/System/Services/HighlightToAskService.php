<?php

declare(strict_types=1);

namespace App\Extensions\AiChatProHighlightToAsk\System\Services;

use App\Helpers\Classes\PlanHelper;
use Illuminate\Support\Facades\Auth;

class HighlightToAskService
{
    /**
     * Check if highlight-to-ask is enabled via settings and user plan.
     */
    public static function isEnabled(): bool
    {
        $settingEnabled = (bool) setting('ai_chat_pro_highlight_to_ask_enabled', true);
        $planCheck = PlanHelper::planMenuCheck(
            Auth::user()?->activePlan(),
            'ai_chat_pro_highlight_to_ask'
        );

        return $settingEnabled && $planCheck;
    }
}
