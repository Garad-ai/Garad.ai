<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentToolMarketingBot\System\Actions\Tools;

use App\Extensions\AIAgent\System\Actions\Contracts\ActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\MarketingBot\System\Models\MarketingCampaign;

class ListMarketingCampaignsAction implements ActionInterface
{
    /**
     * Config keys:
     *   - type            (string|null) : telegram | whatsapp
     *   - status          (string|null) : pending | scheduled | in_progress | published | failed | running
     *   - limit           (int)         : max results, default 10, capped at 50
     *   - store_output_as (string)      : context key (default: marketing_campaigns_list)
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $storeOutputAs = $config['store_output_as'] ?? 'marketing_campaigns_list';
        $type          = $config['type'] ?? null;
        $status        = $config['status'] ?? null;
        $limit         = min((int) ($config['limit'] ?? 10), 50);

        $query = MarketingCampaign::query()
            ->where('user_id', $workflow->user_id)
            ->orderByDesc('created_at');

        if ($type !== null) {
            $query->where('type', $type);
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        $campaigns = $query->limit($limit)->get(['id', 'name', 'type', 'status', 'scheduled_at', 'started_at', 'finished_at'])
            ->map(fn (MarketingCampaign $c): array => [
                'id'           => $c->id,
                'name'         => $c->name,
                'type'         => $c->type?->value,
                'status'       => $c->status?->value,
                'scheduled_at' => $c->scheduled_at?->toIso8601String(),
                'started_at'   => $c->started_at?->toIso8601String(),
                'finished_at'  => $c->finished_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return array_merge($context, [$storeOutputAs => $campaigns]);
    }
}
