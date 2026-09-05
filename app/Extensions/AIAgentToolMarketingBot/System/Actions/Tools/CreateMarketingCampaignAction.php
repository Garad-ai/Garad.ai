<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentToolMarketingBot\System\Actions\Tools;

use App\Extensions\AIAgent\System\Actions\Contracts\ActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\MarketingBot\System\Enums\CampaignStatus;
use App\Extensions\MarketingBot\System\Enums\CampaignType;
use App\Extensions\MarketingBot\System\Models\MarketingCampaign;
use Exception;

class CreateMarketingCampaignAction implements ActionInterface
{
    /**
     * Config keys:
     *   - name            (string)      : campaign name (required)
     *   - content         (string)      : message content (required)
     *   - type            (string)      : telegram | whatsapp (required)
     *   - scheduled_at    (string|null) : ISO 8601 datetime; sets status to "scheduled"
     *   - store_output_as (string)      : context key (default: created_campaign)
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $storeOutputAs = $config['store_output_as'] ?? 'created_campaign';
        $name          = trim((string) ($config['name'] ?? ''));
        $content       = trim((string) ($config['content'] ?? ''));
        $type          = $config['type'] ?? null;

        if ($name === '' || $content === '' || $type === null) {
            throw new Exception('create_marketing_campaign requires name, content, and type.');
        }

        if (! in_array($type, ['telegram', 'whatsapp'], true)) {
            throw new Exception("Invalid campaign type \"{$type}\". Use \"telegram\" or \"whatsapp\".");
        }

        $scheduledAt = isset($config['scheduled_at']) ? new \DateTime($config['scheduled_at']) : null;
        $status      = $scheduledAt !== null ? CampaignStatus::scheduled : CampaignStatus::pending;

        $campaign = MarketingCampaign::query()->create([
            'user_id'      => $workflow->user_id,
            'name'         => $name,
            'content'      => $content,
            'type'         => CampaignType::from($type),
            'status'       => $status,
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
