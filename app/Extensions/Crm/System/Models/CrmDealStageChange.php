<?php

namespace App\Extensions\Crm\System\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmDealStageChange extends Model
{
    protected $table = 'crm_deal_stage_changes';

    protected $fillable = [
        'user_id',
        'source_label',
        'crm_deal_id',
        'from_stage_id',
        'to_stage_id',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(CrmDeal::class, 'crm_deal_id');
    }

    public function fromStage(): BelongsTo
    {
        return $this->belongsTo(CrmDealStage::class, 'from_stage_id');
    }

    public function toStage(): BelongsTo
    {
        return $this->belongsTo(CrmDealStage::class, 'to_stage_id');
    }
}
