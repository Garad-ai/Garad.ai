<?php

namespace App\Extensions\Crm\System\Observer;

use App\Extensions\Crm\System\Models\CrmDeal;
use App\Extensions\Crm\System\Models\CrmDealStageChange;

class CrmDealObserver
{
    public function created(CrmDeal $deal): void
    {
        if (! $deal->crm_deal_stage_id) {
            return;
        }

        CrmDealStageChange::create([
            'user_id'       => $deal->user_id,
            'source_label'  => $deal->stageChangeSourceLabel ?? null,
            'crm_deal_id'   => $deal->id,
            'from_stage_id' => null,
            'to_stage_id'   => $deal->crm_deal_stage_id,
            'changed_at'    => $deal->created_at ?? now(),
        ]);
    }

    public function updated(CrmDeal $deal): void
    {
        if (! $deal->isDirty('crm_deal_stage_id')) {
            return;
        }

        CrmDealStageChange::create([
            'user_id'       => $deal->user_id,
            'source_label'  => $deal->stageChangeSourceLabel ?? null,
            'crm_deal_id'   => $deal->id,
            'from_stage_id' => $deal->getOriginal('crm_deal_stage_id'),
            'to_stage_id'   => $deal->crm_deal_stage_id,
            'changed_at'    => now(),
        ]);
    }
}
