<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Connectors;

use App\Models\Plan;
use App\Models\User;

/**
 * Resolves whether a user's active subscription plan permits a given AI Chat Pro
 * connector. Plans with no `ai_chat_pro_connectors` configuration (or users with
 * no active plan) are unrestricted, preserving the pre-feature behaviour.
 */
class ConnectorPlanGate
{
    public static function allows(?User $user, string $key): bool
    {
        return self::allowsForPlan(self::resolvePlan($user), $key);
    }

    public static function allowsForPlan(?Plan $plan, string $key): bool
    {
        if (! $plan instanceof Plan) {
            return true;
        }

        return $plan->allowsAiChatProConnector($key);
    }

    public static function resolvePlan(?User $user): ?Plan
    {
        if (! $user instanceof User) {
            return null;
        }

        $plan = $user->activePlan();

        return $plan instanceof Plan ? $plan : null;
    }
}
