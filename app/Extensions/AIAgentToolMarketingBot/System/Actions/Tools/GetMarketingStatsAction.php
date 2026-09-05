<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentToolMarketingBot\System\Actions\Tools;

use App\Extensions\AIAgent\System\Actions\Contracts\ActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\MarketingBot\System\Models\MarketingCampaign;
use App\Extensions\MarketingBot\System\Models\MarketingConversation;
use App\Extensions\MarketingBot\System\Models\Whatsapp\Contact;
use Carbon\Carbon;

class GetMarketingStatsAction implements ActionInterface
{
    /**
     * Config keys:
     *   - period          (string): today | last_7_days | last_30_days (default: today)
     *   - store_output_as (string): context key (default: marketing_stats)
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $storeOutputAs = $config['store_output_as'] ?? 'marketing_stats';
        $period        = $config['period'] ?? 'today';

        [$start, $end] = $this->resolvePeriod($period);

        $campaigns = MarketingCampaign::query()
            ->where('user_id', $workflow->user_id)
            ->whereBetween('created_at', [$start, $end])
            ->get(['type', 'status']);

        $byStatus = $campaigns->groupBy(fn ($c) => $c->status?->value ?? 'unknown')->map->count()->all();
        $byType   = $campaigns->groupBy(fn ($c) => $c->type?->value ?? 'unknown')->map->count()->all();

        $newContacts = class_exists(Contact::class)
            ? Contact::query()->where('user_id', $workflow->user_id)->whereBetween('created_at', [$start, $end])->count()
            : 0;

        $newConversations = MarketingConversation::query()
            ->where('user_id', $workflow->user_id)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $result = [
            'period'           => $period,
            'total_campaigns'  => $campaigns->count(),
            'by_status'        => $byStatus,
            'by_type'          => $byType,
            'new_contacts'     => $newContacts,
            'new_conversations' => $newConversations,
        ];

        return array_merge($context, [$storeOutputAs => $result]);
    }

    /**
     * @return array{Carbon, Carbon}
     */
    private function resolvePeriod(string $period): array
    {
        $end = now()->endOfDay();

        $start = match ($period) {
            'last_7_days'  => now()->subDays(7)->startOfDay(),
            'last_30_days' => now()->subDays(30)->startOfDay(),
            default        => now()->startOfDay(),
        };

        return [$start, $end];
    }
}
