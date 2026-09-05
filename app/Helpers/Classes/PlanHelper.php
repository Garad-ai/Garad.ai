<?php

namespace App\Helpers\Classes;

use App\Models\Common\Menu;
use App\Models\Plan;
use Illuminate\Support\Facades\Auth;

class PlanHelper
{
    public static function userPlanAiModel(): ?string
    {
        return self::userPlan()?->default_ai_model;
    }

    public static function userPlan()
    {
        if (! Auth::check()) {
            return null;
        }

        $user = Auth::user();

        // Team member uses the team manager's plan
        if ($user?->getAttribute('team_manager_id')) {
            $user = $user?->getAttribute('teamManager');
        }

        if (! $user) {
            return null;
        }

        $userId = $user->getKey();
        $subscription = getCurrentActiveSubscription($userId);

        if (! $subscription) {
            return null;
        }

        return Plan::getCache(
            static function () use ($subscription) {
                return Plan::query()
                    ->where('id', $subscription->plan_id)
                    ->first();
            },
            '_user_plan_' . $userId . '_' . $subscription->plan_id
        );
    }

    public static function planMenuCheck($plan, ?string $key = null): bool
{
    // Admin always sees everything
    if (Auth::user()?->isAdmin()) {
        return true;
    }

    if (! $key) {
        return false;
    }

    // Always available for every user
    $alwaysAvailable = [
        'dashboard',
        'ai_chat_all',
        'ai_chat_pro',
        'ai-chat-pro',
        'documents',
        'document',
        'docs',
    ];

    if (in_array($key, $alwaysAvailable, true)) {
        return true;
    }

    // No active plan = no premium tools
    if (! $plan) {
        return false;
    }
      
      /*
 * SPECIAL PLAN ENTITLEMENTS
 * These features are controlled by dedicated plan fields/limits
 * instead of plan_ai_tools / plan_features.
 */

// Team
if ($key === 'team_menu') {
    return (bool) $plan->is_team_plan
        && (int) $plan->plan_allow_seat > 0;
}

// Human Agent
if ($key === 'ext_chat_bot_agent') {
    return (bool) $plan->chatbot_human_agent
        && $plan->checkOpenAiItem('ext_chat_bot');
}

// Scheduled Social Media Posts
if ($key === 'scheduled_posts_extension') {
    return $plan->checkOpenAiItem('ai_social_media_extension');
}

// Affiliates
if ($key === 'affiliates') {
    return (bool) $plan->affiliate_status;
}

/*
 * Marketing Bot
 * 0  = disabled
 * -1 = unlimited
 * >0 = limited access
 */
$marketingLimits = (array) ($plan->marketing_bot_limits ?? []);

$maxContacts = (int) ($marketingLimits['max_contacts'] ?? 0);
$monthlyMessages = (int) ($marketingLimits['monthly_messages'] ?? 0);
$marketingChannels = (array) ($marketingLimits['channels'] ?? []);

$marketingEnabled = $maxContacts !== 0 || $monthlyMessages !== 0;

if (in_array($key, [
    'marketing_bot_dashboard',
    'marketing_bot_settings',
    'marketing_bot_inbox',
], true)) {
    return $marketingEnabled;
}

if ($key === 'marketing_bot_campaigns') {
    return $monthlyMessages !== 0
        && ! empty($marketingChannels);
}

if (
    str_starts_with($key, 'marketing_bot_campaigns_whatsapp')
) {
    return $monthlyMessages !== 0
        && in_array('whatsapp', $marketingChannels, true);
}

if (
    str_starts_with($key, 'marketing_bot_campaigns_telegram')
) {
    return $monthlyMessages !== 0
        && in_array('telegram', $marketingChannels, true);
}

if (
    $key === 'marketing_bot_whatsapp'
    || str_starts_with($key, 'marketing_bot_whatsapp_')
) {
    return $maxContacts !== 0
        && in_array('whatsapp', $marketingChannels, true);
}

if (
    $key === 'marketing_bot_telegram'
    || str_starts_with($key, 'marketing_bot_telegram_')
) {
    return $maxContacts !== 0
        && in_array('telegram', $marketingChannels, true);
}

/*
 * AI Social Media Agent
 */
$socialAgentLimits = (array) ($plan->social_media_agent_limits ?? []);

$socialAgentEnabled =
    (int) ($socialAgentLimits['agents'] ?? 0) !== 0
    || (int) ($socialAgentLimits['monthly_posts'] ?? 0) !== 0;

if (
    $key === 'ext_social_media_agent_dropdown'
    || str_starts_with($key, 'ext_social_media_agent_')
) {
    return $socialAgentEnabled;
}

/*
 * AI Blog Writer / BlogPilot
 */
$blogPilotLimits = (array) ($plan->blogpilot_limits ?? []);

$blogPilotEnabled =
    (int) ($blogPilotLimits['agents'] ?? 0) !== 0
    || (int) ($blogPilotLimits['monthly_posts'] ?? 0) !== 0;

if (
    $key === 'ext_blogpilot_dropdown'
    || str_starts_with($key, 'ext_blogpilot_')
) {
    return $blogPilotEnabled;
}

    // Exact feature enabled in the plan
    if ($plan->checkOpenAiItem($key)) {
        return true;
    }

    static $menusById = null;
    static $menusByKey = null;

    if ($menusById === null || $menusByKey === null) {
        $allMenus = Menu::query()
            ->get(['id', 'parent_id', 'key', 'type']);

        $menusById = $allMenus->keyBy('id');
        $menusByKey = $allMenus->keyBy('key');
    }

    $currentMenu = $menusByKey->get($key);

    if (! $currentMenu) {
        return false;
    }
      
      /*
 * PARENT MENU:
 * If the parent itself is not stored in the plan,
 * show it when at least one direct child is enabled.
 *
 * Examples:
 * UGC -> Factory / Creator
 * Chat Settings -> Chatbot Training
 */
if ((string) $currentMenu->type === 'item') {
    foreach ($menusById as $child) {
        if (
            (int) ($child->parent_id ?? 0) === (int) $currentMenu->id
            && $plan->checkOpenAiItem((string) $child->key)
        ) {
            return true;
        }
    }
}

    /*
     * CATEGORY / LABEL:
     * Show the category only when at least one
     * direct child is enabled in the user's plan.
     */
    if ((string) $currentMenu->type === 'label') {
        foreach ($menusById as $child) {
            if (
                (int) ($child->parent_id ?? 0) === (int) $currentMenu->id
                && $plan->checkOpenAiItem((string) $child->key)
            ) {
                return true;
            }
        }

        return false;
    }

    /*
     * Child menu inheritance:
     * e.g. Sales children inherit ext_sales_dropdown.
     */
    $parentId = (int) ($currentMenu->parent_id ?? 0);
    $visited = [];

    while ($parentId > 0 && ! isset($visited[$parentId])) {
        $visited[$parentId] = true;

        $parent = $menusById->get($parentId);

        if (! $parent) {
            break;
        }

        if (
            (string) $parent->type !== 'label'
            && $plan->checkOpenAiItem((string) $parent->key)
        ) {
            return true;
        }

        $parentId = (int) ($parent->parent_id ?? 0);
    }

    return false;
}
}