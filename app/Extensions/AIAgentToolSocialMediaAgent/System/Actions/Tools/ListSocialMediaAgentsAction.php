<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentToolSocialMediaAgent\System\Actions\Tools;

use App\Extensions\AIAgent\System\Actions\Contracts\ActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgent;

class ListSocialMediaAgentsAction implements ActionInterface
{
    /**
     * Config keys:
     *   - store_output_as (string): context key for the result (default: social_agents_list)
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $storeOutputAs = $config['store_output_as'] ?? 'social_agents_list';

        $agents = SocialMediaAgent::query()
            ->where('user_id', $workflow->user_id)
            ->get([
                'id',
                'name',
                'is_active',
                'post_types',
                'tone',
                'language',
                'daily_post_count',
                'average_impressions',
                'average_engagement',
                'platform_ids',
                'schedule_days',
            ])
            ->map(fn (SocialMediaAgent $agent): array => [
                'id'                  => $agent->id,
                'name'                => $agent->name,
                'is_active'           => $agent->is_active,
                'post_types'          => $agent->post_types ?? [],
                'tone'                => $agent->tone,
                'language'            => $agent->language,
                'daily_post_count'    => $agent->daily_post_count,
                'schedule_days'       => $agent->schedule_days ?? [],
                'average_impressions' => $agent->average_impressions,
                'average_engagement'  => $agent->average_engagement,
                'platform_count'      => count($agent->platform_ids ?? []),
            ])
            ->values()
            ->all();

        return array_merge($context, [$storeOutputAs => $agents]);
    }
}
