<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Services;

use App\Extensions\AIChatPro\System\Models\AIChatProArtifact;
use App\Extensions\AIChatPro\System\Models\AIChatProAttachment;
use App\Models\Plan;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use UnexpectedValueException;

class AIChatProStorageQuotaService
{
    private const PLAN_LIMITS_BYTES = [
        'basic'        => 2_147_483_648,
        'standard'     => 7_516_192_768,
        'professional' => 21_474_836_480,
        'business'     => 107_374_182_400,
    ];

    public function usedBytes(int $userId): int
    {
        $attachmentBytes = (int) AIChatProAttachment::query()
            ->where('user_id', $userId)
            ->sum('file_size');
        $artifactBytes = (int) AIChatProArtifact::query()
            ->where('user_id', $userId)
            ->sum('file_size');

        return $attachmentBytes + $artifactBytes;
    }

    public function limitBytesForPlan(?Plan $plan): int
    {
        if ($plan === null) {
            return self::PLAN_LIMITS_BYTES['basic'];
        }

        return $this->limitBytesForPlanName((string) $plan->name, (bool) $plan->is_free);
    }

    public function limitBytesForPlanName(string $name, bool $isFree = false): int
    {
        if ($isFree) {
            return self::PLAN_LIMITS_BYTES['basic'];
        }

        $planName = mb_strtolower(trim($name));
        if (preg_match('/\b(basic|free)\b/u', $planName) === 1) {
            return self::PLAN_LIMITS_BYTES['basic'];
        }

        $matches = [];
        foreach (['standard', 'professional', 'business'] as $tier) {
            if (preg_match('/\b' . preg_quote($tier, '/') . '\b/u', $planName) === 1) {
                $matches[$tier] = self::PLAN_LIMITS_BYTES[$tier];
            }
        }

        if (count($matches) !== 1) {
            throw new UnexpectedValueException(
                'A unique storage limit is not configured for plan "' . trim($name) . '".'
            );
        }

        return array_values($matches)[0];
    }

    public function assertCanStore(int $userId, int $incomingBytes, ?Plan $plan): void
    {
        if ($incomingBytes < 0) {
            throw new InvalidArgumentException('The incoming storage size cannot be negative.');
        }

        $usedBytes = $this->usedBytes($userId);
        $limitBytes = $this->limitBytesForPlan($plan);

        if ($incomingBytes > max(0, $limitBytes - $usedBytes)) {
            throw ValidationException::withMessages([
                'storage' => 'Your storage is full. Delete files or upgrade your plan to continue.',
            ]);
        }
    }

    public function withUserLock(int $userId, Closure $operation): mixed
    {
        return DB::transaction(function () use ($userId, $operation): mixed {
            User::query()
                ->whereKey($userId)
                ->lockForUpdate()
                ->firstOrFail();

            return $operation();
        });
    }
}
