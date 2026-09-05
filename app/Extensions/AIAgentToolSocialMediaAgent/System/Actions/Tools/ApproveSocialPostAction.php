<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentToolSocialMediaAgent\System\Actions\Tools;

use App\Extensions\AIAgent\System\Actions\Contracts\ActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgentPost;
use Exception;

class ApproveSocialPostAction implements ActionInterface
{
    /**
     * Config keys:
     *   - post_id         (int)    : ID of the draft post to approve (required)
     *   - store_output_as (string) : context key for the result (default: approved_post)
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $storeOutputAs = $config['store_output_as'] ?? 'approved_post';
        $postId = isset($config['post_id']) ? (int) $config['post_id'] : null;

        if ($postId === null) {
            throw new Exception('approve_social_post requires post_id.');
        }

        $post = SocialMediaAgentPost::query()
            ->whereHas('agent', fn ($q) => $q->where('user_id', $workflow->user_id))
            ->findOrFail($postId);

        if (! $post->canBeApproved()) {
            throw new Exception("Post #{$postId} cannot be approved (current status: {$post->status}).");
        }

        $post->update([
            'status'      => 'approved',
            'approved_at' => now(),
        ]);

        $result = [
            'post_id'     => $post->id,
            'agent_id'    => $post->agent_id,
            'status'      => $post->status,
            'approved_at' => $post->approved_at?->toIso8601String(),
        ];

        return array_merge($context, [$storeOutputAs => $result]);
    }
}
