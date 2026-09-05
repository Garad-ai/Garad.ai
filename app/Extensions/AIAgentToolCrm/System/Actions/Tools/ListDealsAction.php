<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentToolCrm\System\Actions\Tools;

use App\Extensions\AIAgent\System\Actions\Contracts\ActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\Crm\System\Models\CrmDeal;

class ListDealsAction implements ActionInterface
{
    /**
     * Config keys:
     *   - stage_id        (int)    : filter by CrmDealStage ID
     *   - limit           (int)    : max results (default 10, max 50)
     *   - output_format   (string) : 'json' (default) | 'plain'
     *   - store_output_as (string) : context key for the result (default: crm_deals_list)
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $storeOutputAs = $config['store_output_as'] ?? 'crm_deals_list';
        $stageId = isset($config['stage_id']) ? (int) $config['stage_id'] : null;
        $limit = min((int) ($config['limit'] ?? 10), 50);
        $outputFormat = $config['output_format'] ?? 'json';

        $deals = CrmDeal::query()
            ->where('user_id', $workflow->user_id)
            ->with(['contact:id,first_name,last_name', 'company:id,name', 'stage:id,name'])
            ->when($stageId !== null, fn ($query) => $query->where('crm_deal_stage_id', $stageId))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (CrmDeal $deal): array => [
                'id'                  => $deal->id,
                'title'               => $deal->title,
                'value'               => $deal->value,
                'currency'            => $deal->currency,
                'stage'               => $deal->stage?->name,
                'stage_id'            => $deal->crm_deal_stage_id,
                'contact'             => $deal->contact ? trim($deal->contact->first_name . ' ' . $deal->contact->last_name) : null,
                'company'             => $deal->company?->name,
                'expected_close_date' => $deal->expected_close_date?->toDateString(),
            ])
            ->values()
            ->all();

        if ($outputFormat === 'plain') {
            if ($deals === []) {
                return array_merge($context, [$storeOutputAs => '_No deals found._']);
            }

            $rows = array_map(
                fn (array $d): string => sprintf(
                    '| %d | %s | %s %s | %s | %s | %s |',
                    $d['id'],
                    $d['title'],
                    $d['value'] ?? '0',
                    $d['currency'] ?? '',
                    $d['stage'] ?? '-',
                    $d['contact'] ?? '-',
                    $d['company'] ?? '-',
                ),
                $deals,
            );

            $markdown = implode("\n", array_merge([
                '| ID | Title | Value | Stage | Contact | Company |',
                '| --- | --- | --- | --- | --- | --- |',
            ], $rows));

            return array_merge($context, [$storeOutputAs => $markdown]);
        }

        return array_merge($context, [$storeOutputAs => $deals]);
    }
}
