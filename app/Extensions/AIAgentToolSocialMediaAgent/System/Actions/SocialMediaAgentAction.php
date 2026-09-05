<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentToolSocialMediaAgent\System\Actions;

use App\Extensions\AIAgent\System\Actions\Contracts\AIAgentActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\AIAgentToolSocialMediaAgent\System\Actions\Tools\ApproveSocialPostAction;
use App\Extensions\AIAgentToolSocialMediaAgent\System\Actions\Tools\CreateSocialMediaAgentAction;
use App\Extensions\AIAgentToolSocialMediaAgent\System\Actions\Tools\CreateSocialPostAction;
use App\Extensions\AIAgentToolSocialMediaAgent\System\Actions\Tools\GetSocialPostAnalyticsAction;
use App\Extensions\AIAgentToolSocialMediaAgent\System\Actions\Tools\ListSocialMediaAgentsAction;
use App\Extensions\AIAgentToolSocialMediaAgent\System\Actions\Tools\ListSocialPostsAction;
use Exception;

class SocialMediaAgentAction implements AIAgentActionInterface
{
    public function __construct(
        private readonly ListSocialMediaAgentsAction $listAgentsAction,
        private readonly ListSocialPostsAction $listPostsAction,
        private readonly CreateSocialPostAction $createPostAction,
        private readonly GetSocialPostAnalyticsAction $getAnalyticsAction,
        private readonly ApproveSocialPostAction $approvePostAction,
        private readonly CreateSocialMediaAgentAction $createAgentAction,
    ) {}

    /**
     * Config keys:
     *   - action_event    (string) : sub-operation to perform (required)
     *   - store_output_as (string) : context key to store results under
     *
     * Per action_event additional keys:
     *   list_social_media_agents:  (none)
     *   list_social_posts:         agent_id (int), status (string), limit (int)
     *   create_social_post:        agent_id (int, req), content (string, req), post_type (string), platform_id (int), scheduled_at (string)
     *   get_social_post_analytics: agent_id (int, req), lookback_days (int)
     *   approve_social_post:       post_id (int, req)
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $actionEvent = $config['action_event'] ?? '';

        return match ($actionEvent) {
            'list_social_media_agents'   => $this->listAgentsAction->execute($config, $context, $workflow, $run),
            'list_social_posts'          => $this->listPostsAction->execute($config, $context, $workflow, $run),
            'create_social_post'         => $this->createPostAction->execute($config, $context, $workflow, $run),
            'get_social_post_analytics'  => $this->getAnalyticsAction->execute($config, $context, $workflow, $run),
            'approve_social_post'        => $this->approvePostAction->execute($config, $context, $workflow, $run),
            'create_social_media_agent'  => $this->createAgentAction->execute($config, $context, $workflow, $run),
            default                      => throw new Exception('SocialMediaAgentAction: unknown action_event "' . $actionEvent . '".'),
        };
    }

    public function getCategory(): string
    {
        return 'agents';
    }

    public function getLabel(): string
    {
        return 'Social Media Agent';
    }

    public function getDescription(): string
    {
        return 'Manage social media agents, create and approve posts, and fetch post analytics.';
    }

    public function getIcon(): string
    {
        return 'tabler-social';
    }

    public function getConfigSchema(): array
    {
        return [
            'type'       => 'object',
            'required'   => ['action_event'],
            'properties' => [
                'action_event' => [
                    'type' => 'string',
                    'enum' => [
                        'list_social_media_agents',
                        'list_social_posts',
                        'create_social_post',
                        'get_social_post_analytics',
                        'approve_social_post',
                        'create_social_media_agent',
                    ],
                    'description' => 'The social media agent operation to perform.',
                ],
                'agent_id'        => ['type' => 'integer', 'description' => 'Social media agent ID.'],
                'status'          => ['type' => 'string', 'enum' => ['draft', 'pending_approval', 'approved', 'scheduled', 'published', 'failed'], 'description' => 'Post status filter.'],
                'limit'           => ['type' => 'integer', 'description' => 'Max results (1–50).'],
                'content'         => ['type' => 'string', 'description' => 'Post content (create_social_post).'],
                'post_type'       => ['type' => 'string', 'enum' => ['text', 'single_image', 'carousel', 'video'], 'description' => 'Post type.'],
                'platform_id'     => ['type' => 'integer', 'description' => 'Platform ID (defaults to agent\'s first platform).'],
                'scheduled_at'    => ['type' => 'string', 'description' => 'ISO 8601 datetime for scheduling.'],
                'lookback_days'   => ['type' => 'integer', 'description' => 'Days to look back for analytics (default 14).'],
                'post_id'         => ['type' => 'integer', 'description' => 'Post ID (approve_social_post).'],
                'store_output_as' => ['type' => 'string', 'description' => 'Context key to store results under.'],
            ],
        ];
    }
}
