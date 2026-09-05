<?php

namespace App\Extensions\SocialMediaAgent\System\Support;

use App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgent;
use App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgentPost;
use Illuminate\Support\Facades\Cache;

class SocialMediaAgentGenerationCache
{
    private const CACHE_PREFIX = 'social-media-agent:generation:';

    public static function key(int $userId): string
    {
        return self::CACHE_PREFIX . $userId;
    }

    public static function mark(SocialMediaAgent $agent, string $status, array $payload = []): array
    {
        if (! array_key_exists('pending_posts_count', $payload)) {
            $payload = array_merge(static::computePostStats($agent), $payload);
        }
        $data = array_merge($payload, [
            'agent_id'  => $agent->id,
            'status'    => $status,
            'updated_at'=> now()->toIso8601String(),
        ]);

        Cache::put(self::key($agent->user_id), $data, now()->addHours(6));

        return $data;
    }

    public static function getForUser(int $userId): ?array
    {
        return Cache::get(self::key($userId));
    }

    public static function forgetForUser(int $userId): void
    {
        Cache::forget(self::key($userId));
    }

    public static function currentStatus(SocialMediaAgent $agent): array
    {
        $status = self::getForUser($agent->user_id) ?? [
            'agent_id'  => $agent->id,
            'status'    => data_get($agent->post_generation_status, 'status', 'idle'),
            'updated_at'=> data_get($agent->post_generation_status, 'updated_at'),
        ];

        if (! array_key_exists('pending_posts_count', $status)) {
            $status = array_merge($status, static::computePostStats($agent));
        }

        return $status;
    }

    public static function computePostStats(SocialMediaAgent $agent): array
    {
        $cachedStatus = self::getForUser($agent->user_id);
        $planned = (int) data_get($cachedStatus, 'total_requested', data_get($cachedStatus, 'planned_posts_count', 0));

        $agentIds = SocialMediaAgent::query()
            ->where('user_id', $agent->user_id)
            ->pluck('id')
            ->all();

        if (empty($agentIds)) {
            $agentIds = [$agent->id];
        }

        $query = SocialMediaAgentPost::query()
            ->whereIn('agent_id', $agentIds);

        $pending = (clone $query)->where('status', SocialMediaAgentPost::STATUS_DRAFT)->count();
        $scheduled = (clone $query)->where('status', SocialMediaAgentPost::STATUS_SCHEDULED)->count();
        $total = $query->count();
        $generated = (int) data_get($cachedStatus, 'generated_count', data_get($agent->post_generation_status, 'generated_count', 0));

        return [
            'pending_posts_count'   => $pending,
            'scheduled_posts_count' => $scheduled,
            'total_posts_count'     => $total,
            'generated_posts_count' => $generated,
            'planned_posts_count'   => $planned,
            'total_requested'       => $planned,
        ];
    }
}
