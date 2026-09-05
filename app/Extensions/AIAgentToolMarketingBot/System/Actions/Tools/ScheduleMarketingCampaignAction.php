<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentToolMarketingBot\System\Actions\Tools;

use App\Extensions\AIAgent\System\Actions\Contracts\ActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\MarketingBot\System\Enums\CampaignStatus;
use App\Extensions\MarketingBot\System\Models\MarketingCampaign;
use Exception;

class ScheduleMarketingCampaignAction implements ActionInterface
{
    /**
     * Config keys:
     *   - campaign_id     (int)         : campaign to schedule (required)
     *   - scheduled_at    (string|null) : ISO 8601 datetime; defaults to now
     *   - store_output_as (string)      : context key (default: scheduled_campaign)
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $storeOutputAs = $config['store_output_as'] ?? 'scheduled_campaign';
        $campaignId    = isset($config['campaign_id']) ? (int) $config['campaign_id'] : null;

        if ($campaignId === null) {
            throw new Exception('schedule_marketing_campaign requires campaign_id.');
        }

        $campaign = MarketingCampaign::query()
            ->where('id', $campaignId)
            ->where('user_id', $workflow->user_id)
            ->firstOrFail();

        if (! in_array($campaign->status?->value, ['pending', 'failed'], true)) {
            throw new Exception(
                "Campaign #{$campaignId} cannot be scheduled (current status: {$campaign->status?->value})."
            );
        }

        $scheduledAt = isset($config['scheduled_at'])
            ? new \DateTime($config['scheduled_at'])
            : now()->toDateTime();

        $campaign->update([
            'status'       => CampaignStatus::scheduled,
            'scheduled_at' => $scheduledAt,
        ]);

        $result = [
            'campaign_id'  => $campaign->id,
            'name'         => $campaign->name,
            'type'         => $campaign->type?->value,
            'status'       => $campaign->status?->value,
            'scheduled_at' => $campaign->scheduled_at?->toIso8601String(),
        ];

        return array_merge($context, [$storeOutputAs => $result]);
    }
}
