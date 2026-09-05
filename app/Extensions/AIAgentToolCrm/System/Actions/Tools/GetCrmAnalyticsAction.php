<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentToolCrm\System\Actions\Tools;

use App\Extensions\AIAgent\System\Actions\Contracts\ActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\Crm\System\Models\CrmCompany;
use App\Extensions\Crm\System\Models\CrmContact;
use App\Extensions\Crm\System\Models\CrmDeal;
use App\Extensions\Crm\System\Models\CrmDealStage;
use App\Extensions\Crm\System\Models\CrmTask;

class GetCrmAnalyticsAction implements ActionInterface
{
    /**
     * Config keys:
     *   - output_format   (string) : 'json' (default) | 'plain'
     *   - store_output_as (string) : context key for the result (default: crm_analytics)
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $storeOutputAs = $config['store_output_as'] ?? 'crm_analytics';
        $outputFormat = $config['output_format'] ?? 'json';

        $analytics = self::buildAnalytics((int) $workflow->user_id);

        if ($outputFormat === 'plain') {
            return array_merge($context, [$storeOutputAs => self::toPlain($analytics)]);
        }

        return array_merge($context, [$storeOutputAs => $analytics]);
    }

    /**
     * Render an analytics snapshot as a Markdown summary.
     *
     * @param  array<string, mixed>  $analytics
     */
    private static function toPlain(array $analytics): string
    {
        $lines = [
            '## CRM Pipeline Snapshot',
            '',
            "- **Total deals:** {$analytics['total_deals']} (value: {$analytics['total_value']})",
            "- **Contacts:** {$analytics['total_contacts']}",
            "- **Companies:** {$analytics['total_companies']}",
            "- **Open tasks:** {$analytics['open_tasks']}",
            '',
            '### Pipeline by stage',
            '',
            '| Stage | Deals | Value |',
            '| --- | --- | --- |',
        ];

        foreach ($analytics['pipeline'] as $stage) {
            $lines[] = "| {$stage['stage']} | {$stage['deal_count']} | {$stage['total_value']} |";
        }

        return implode("\n", $lines);
    }

    /**
     * Build a pipeline analytics snapshot for the given user.
     *
     * @return array<string, mixed>
     */
    public static function buildAnalytics(int $userId): array
    {
        CrmDealStage::createDefaultsForUser($userId);

        $stages = CrmDealStage::query()
            ->where('user_id', $userId)
            ->orderBy('order')
            ->get(['id', 'name']);

        $countByStage = CrmDeal::query()
            ->where('user_id', $userId)
            ->selectRaw('crm_deal_stage_id, count(*) as count, coalesce(sum(value), 0) as total_value')
            ->groupBy('crm_deal_stage_id')
            ->get()
            ->keyBy('crm_deal_stage_id');

        $pipeline = $stages->map(fn (CrmDealStage $stage): array => [
            'stage'       => $stage->name,
            'stage_id'    => $stage->id,
            'deal_count'  => (int) ($countByStage[$stage->id]->count ?? 0),
            'total_value' => (float) ($countByStage[$stage->id]->total_value ?? 0),
        ])->values()->all();

        return [
            'total_deals'     => CrmDeal::query()->where('user_id', $userId)->count(),
            'total_value'     => (float) CrmDeal::query()->where('user_id', $userId)->sum('value'),
            'total_contacts'  => CrmContact::query()->where('user_id', $userId)->count(),
            'total_companies' => CrmCompany::query()->where('user_id', $userId)->count(),
            'open_tasks'      => CrmTask::query()->where('user_id', $userId)->where('status', 'pending')->count(),
            'pipeline'        => $pipeline,
        ];
    }
}
