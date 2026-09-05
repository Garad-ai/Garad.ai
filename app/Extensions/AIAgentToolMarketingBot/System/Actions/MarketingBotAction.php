<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentToolMarketingBot\System\Actions;

use App\Extensions\AIAgent\System\Actions\Contracts\AIAgentActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\AIAgentToolMarketingBot\System\Actions\Tools\CreateMarketingCampaignAction;
use App\Extensions\AIAgentToolMarketingBot\System\Actions\Tools\GetMarketingStatsAction;
use App\Extensions\AIAgentToolMarketingBot\System\Actions\Tools\ListMarketingCampaignsAction;
use App\Extensions\AIAgentToolMarketingBot\System\Actions\Tools\ListMarketingConversationsAction;
use App\Extensions\AIAgentToolMarketingBot\System\Actions\Tools\ScheduleMarketingCampaignAction;
use Exception;

class MarketingBotAction implements AIAgentActionInterface
{
    public function __construct(
        private readonly ListMarketingCampaignsAction $listCampaignsAction,
        private readonly GetMarketingStatsAction $getStatsAction,
        private readonly ListMarketingConversationsAction $listConversationsAction,
        private readonly CreateMarketingCampaignAction $createCampaignAction,
        private readonly ScheduleMarketingCampaignAction $scheduleCampaignAction,
    ) {}

    /**
     * Config keys:
     *   - action_event    (string) : sub-operation to perform (required)
     *   - store_output_as (string) : context key to store results under
     *
     * Per action_event additional keys:
     *   list_marketing_campaigns:     type (string), status (string), limit (int)
     *   get_marketing_stats:          period (string: today|last_7_days|last_30_days)
     *   list_marketing_conversations: channel (string), limit (int)
     *   create_marketing_campaign:    name (string, req), content (string, req), type (string, req), scheduled_at (string)
     *   schedule_marketing_campaign:  campaign_id (int, req), scheduled_at (string)
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $actionEvent = $config['action_event'] ?? '';

        return match ($actionEvent) {
            'list_marketing_campaigns'     => $this->listCampaignsAction->execute($config, $context, $workflow, $run),
            'get_marketing_stats'          => $this->getStatsAction->execute($config, $context, $workflow, $run),
            'list_marketing_conversations' => $this->listConversationsAction->execute($config, $context, $workflow, $run),
            'create_marketing_campaign'    => $this->createCampaignAction->execute($config, $context, $workflow, $run),
            'schedule_marketing_campaign'  => $this->scheduleCampaignAction->execute($config, $context, $workflow, $run),
            default                        => throw new Exception('MarketingBotAction: unknown action_event "' . $actionEvent . '".'),
        };
    }

    public function getCategory(): string
    {
        return 'agents';
    }

    public function getLabel(): string
    {
        return 'Marketing Bot';
    }

    public function getDescription(): string
    {
        return 'Manage marketing campaigns, get stats, browse conversations, and schedule sends.';
    }

    public function getIcon(): string
    {
        return 'tabler-speakerphone';
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
                        'list_marketing_campaigns',
                        'get_marketing_stats',
                        'list_marketing_conversations',
                        'create_marketing_campaign',
                        'schedule_marketing_campaign',
                    ],
                    'description' => 'The marketing bot operation to perform.',
                ],
                'type'            => ['type' => 'string', 'enum' => ['telegram', 'whatsapp'], 'description' => 'Campaign/channel type filter.'],
                'status'          => ['type' => 'string', 'enum' => ['pending', 'scheduled', 'in_progress', 'published', 'failed', 'running'], 'description' => 'Campaign status filter.'],
                'limit'           => ['type' => 'integer', 'description' => 'Max results (1–50).'],
                'period'          => ['type' => 'string', 'enum' => ['today', 'last_7_days', 'last_30_days'], 'description' => 'Stats period.'],
                'channel'         => ['type' => 'string', 'enum' => ['telegram', 'whatsapp'], 'description' => 'Filter conversations by channel.'],
                'name'            => ['type' => 'string', 'description' => 'Campaign name (create_marketing_campaign).'],
                'content'         => ['type' => 'string', 'description' => 'Campaign message content.'],
                'scheduled_at'    => ['type' => 'string', 'description' => 'ISO 8601 datetime for scheduling.'],
                'campaign_id'     => ['type' => 'integer', 'description' => 'Campaign ID (schedule_marketing_campaign).'],
                'store_output_as' => ['type' => 'string', 'description' => 'Context key to store results under.'],
            ],
        ];
    }
}
