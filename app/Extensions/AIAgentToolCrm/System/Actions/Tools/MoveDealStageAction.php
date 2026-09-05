<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentToolCrm\System\Actions\Tools;

use App\Extensions\AIAgent\System\Actions\Contracts\ActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\AIAgentToolCrm\System\Actions\Tools\Concerns\NormalizesCrmInput;
use App\Extensions\Crm\System\Models\CrmDeal;
use App\Extensions\Crm\System\Models\CrmDealStage;
use Exception;

class MoveDealStageAction implements ActionInterface
{
    use NormalizesCrmInput;

    /**
     * Config keys:
     *   - deal_id         (int)    : CrmDeal ID; overrides deal_title
     *   - deal_title      (string) : deal matched by title when the ID is unknown; must exist
     *   - stage_id        (int)    : target CrmDealStage ID; overrides stage_name
     *   - stage_name      (string) : stage matched by name when the ID is unknown; must exist
     *   - store_output_as (string) : context key for the result (default: moved_deal)
     *
     * Stage-change history is recorded automatically by CrmDealObserver.
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $storeOutputAs = $config['store_output_as'] ?? 'moved_deal';
        $dealId = $this->resolveDealId($config, $workflow);
        $stageId = $this->resolveStageId($config, $workflow);

        if ($dealId === null) {
            $dealTitle = $this->nullableString($config['deal_title'] ?? null);

            throw new Exception($dealTitle !== null
                ? sprintf('move_deal_stage: no deal named "%s" was found.', $dealTitle)
                : 'move_deal_stage requires deal_id or deal_title.');
        }

        if ($stageId === null) {
            $stageName = $this->nullableString($config['stage_name'] ?? null);

            throw new Exception($stageName !== null
                ? sprintf('move_deal_stage: no pipeline stage named "%s" was found.', $stageName)
                : 'move_deal_stage requires stage_id or stage_name.');
        }

        $deal = CrmDeal::query()
            ->where('id', $dealId)
            ->where('user_id', $workflow->user_id)
            ->firstOrFail();

        $stage = CrmDealStage::query()
            ->where('id', $stageId)
            ->where('user_id', $workflow->user_id)
            ->firstOrFail();

        $deal->stageChangeSourceLabel = $workflow->name;
        $deal->update(['crm_deal_stage_id' => $stage->id]);

        $result = [
            'id'       => $deal->id,
            'title'    => $deal->title,
            'stage'    => $stage->name,
            'stage_id' => $stage->id,
        ];

        return array_merge($context, [$storeOutputAs => $result]);
    }
}
