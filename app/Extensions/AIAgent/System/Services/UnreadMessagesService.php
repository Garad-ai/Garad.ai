<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Services;

use App\Extensions\AIAgent\System\Models\AIAgentMessage;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class UnreadMessagesService
{
    private const CACHE_TTL_SECONDS = 60 * 60 * 24 * 30;

    public function lastSeenAt(int $userId): DateTimeInterface
    {
        return Cache::remember(
            $this->cacheKey($userId),
            self::CACHE_TTL_SECONDS,
            fn (): Carbon => Carbon::now(),
        );
    }

    public function markSeen(int $userId): void
    {
        Cache::put($this->cacheKey($userId), Carbon::now(), self::CACHE_TTL_SECONDS);
    }

    public function unreadCount(int $userId): int
    {
        return AIAgentMessage::query()
            ->where('direction', 'inbound')
            ->whereHas('channel', fn ($q) => $q->where('user_id', $userId))
            ->where('created_at', '>', $this->lastSeenAt($userId))
            ->count();
    }

    private function cacheKey(int $userId): string
    {
        return "ai_agent:last_messages_seen:{$userId}";
    }
}
