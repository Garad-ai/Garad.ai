<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentToolSocialMediaAgent\System\Actions\Tools;

use App\Extensions\AIAgent\System\Actions\Contracts\ActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgent;
use App\Extensions\SocialMediaAgent\System\Services\Analysis\AgentInsightsService;
use Exception;

class GetSocialPostAnalyticsAction implements ActionInterface
{
    public function __construct(private readonly AgentInsightsService $insightsService) {}

    /**
     * Config keys:
     *   - agent_id        (int): ID of the Social Media Agent (required)
     *   - lookback_days   (int): days to look back for the recent period (default: 14)
     *   - store_output_as (string): context key for the result (default: social_analytics)
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $storeOutputAs = $config['store_output_as'] ?? 'social_analytics';
        $agentId = isset($config['agent_id']) ? (int) $config['agent_id'] : null;
        $lookbackDays = isset($config['lookback_days']) ? max(1, (int) $config['lookback_days']) : 14;

        if ($agentId === null) {
            throw new Exception('get_social_post_analytics requires agent_id.');
        }

        $agent = SocialMediaAgent::query()
            ->where('id', $agentId)
            ->where('user_id', $workflow->user_id)
            ->firstOrFail();

        $metrics = $this->insightsService->buildMetricsSnapshot($agent, $lookbackDays);

        $result = [
            'agent_id'   => $agent->id,
            'agent_name' => $agent->name,
            'metrics'    => $metrics,
        ];

        return array_merge($context, [$storeOutputAs => $result]);
    }
}
