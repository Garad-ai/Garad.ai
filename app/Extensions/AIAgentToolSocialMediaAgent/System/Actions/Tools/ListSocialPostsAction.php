<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentToolSocialMediaAgent\System\Actions\Tools;

use App\Extensions\AIAgent\System\Actions\Contracts\ActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgentPost;
use Illuminate\Support\Str;

class ListSocialPostsAction implements ActionInterface
{
    /**
     * Config keys:
     *   - agent_id        (int|null)    : filter by agent ID
     *   - status          (string|null) : draft | pending_approval | approved | scheduled | published | failed
     *   - limit           (int)         : max results, default 10, capped at 50
     *   - store_output_as (string)      : context key for the result (default: social_posts_list)
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $storeOutputAs = $config['store_output_as'] ?? 'social_posts_list';
        $agentId = isset($config['agent_id']) ? (int) $config['agent_id'] : null;
        $status = $config['status'] ?? null;
        $limit = min((int) ($config['limit'] ?? 10), 50);

        $query = SocialMediaAgentPost::query()
            ->with(['agent:id,name', 'platform:id,name,platform'])
            ->whereHas('agent', fn ($q) => $q->where('user_id', $workflow->user_id))
            ->orderByDesc('created_at');

        if ($agentId !== null) {
            $query->where('agent_id', $agentId);
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        $posts = $query->limit($limit)->get()->map(fn (SocialMediaAgentPost $post): array => [
            'id'             => $post->id,
            'agent_id'       => $post->agent_id,
            'agent_name'     => $post->agent?->name,
            'platform'       => $post->platform?->platform ?? $post->platform?->name,
            'post_type'      => $post->post_type,
            'status'         => $post->status,
            'content_preview' => Str::limit(strip_tags($post->content ?? ''), 120),
            'scheduled_at'   => $post->scheduled_at?->toIso8601String(),
            'published_at'   => $post->published_at?->toIso8601String(),
            'approved_at'    => $post->approved_at?->toIso8601String(),
        ])->values()->all();

        return array_merge($context, [$storeOutputAs => $posts]);
    }
}
