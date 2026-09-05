<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentToolSocialMediaAgent\System\Actions\Tools;

use App\Extensions\AIAgent\System\Actions\Contracts\ActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgent;
use App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgentPost;
use Exception;

class CreateSocialPostAction implements ActionInterface
{
    /**
     * Config keys:
     *   - agent_id        (int)         : ID of the Social Media Agent (required)
     *   - content         (string)      : post content (required)
     *   - post_type       (string)      : text | single_image | carousel | video (default: text)
     *   - platform_id     (int|null)    : target platform; defaults to agent's first platform
     *   - scheduled_at    (string|null) : ISO 8601 datetime; if provided, status is set to "scheduled"
     *   - store_output_as (string)      : context key for the result (default: created_post)
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $storeOutputAs = $config['store_output_as'] ?? 'created_post';

        $agentId = isset($config['agent_id']) ? (int) $config['agent_id'] : null;
        $content = trim((string) ($config['content'] ?? ''));

        if ($agentId === null || $content === '') {
            throw new Exception('create_social_post requires agent_id and content.');
        }

        $agent = SocialMediaAgent::query()
            ->where('id', $agentId)
            ->where('user_id', $workflow->user_id)
            ->firstOrFail();

        $platformId = isset($config['platform_id'])
            ? (int) $config['platform_id']
            : ($agent->platform_ids[0] ?? null);

        $postType = $config['post_type'] ?? SocialMediaAgentPost::TYPE_TEXT;
        $scheduledAt = isset($config['scheduled_at']) ? new \DateTime($config['scheduled_at']) : null;
        $status = $scheduledAt !== null ? SocialMediaAgentPost::STATUS_SCHEDULED : SocialMediaAgentPost::STATUS_DRAFT;

        $post = SocialMediaAgentPost::query()->create([
            'agent_id'    => $agent->id,
            'platform_id' => $platformId,
            'content'     => $content,
            'post_type'   => $postType,
            'status'      => $status,
            'scheduled_at' => $scheduledAt,
        ]);

        $result = [
            'post_id'      => $post->id,
            'agent_id'     => $agent->id,
            'agent_name'   => $agent->name,
            'status'       => $post->status,
            'post_type'    => $post->post_type,
            'scheduled_at' => $post->scheduled_at?->toIso8601String(),
        ];

        return array_merge($context, [$storeOutputAs => $result]);
    }
}
