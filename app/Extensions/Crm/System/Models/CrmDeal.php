<?php

namespace App\Extensions\Crm\System\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CrmDeal extends Model
{
    /**
     * Transient label describing what triggered a stage change (e.g. an agent name).
     * Read by CrmDealObserver when recording stage-change history; never persisted on the deal.
     */
    public ?string $stageChangeSourceLabel = null;

    protected $table = 'crm_deals';

    protected $fillable = [
        'user_id',
        'is_favorite',
        'crm_contact_id',
        'crm_company_id',
        'crm_deal_stage_id',
        'title',
        'value',
        'currency',
        'expected_close_date',
        'description',
        'order',
    ];

    protected $casts = [
        'is_favorite'         => 'boolean',
        'value'               => 'decimal:2',
        'expected_close_date' => 'date',
        'order'               => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'crm_contact_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(CrmCompany::class, 'crm_company_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(CrmDealStage::class, 'crm_deal_stage_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(CrmTask::class);
    }

    public function stageChanges(): HasMany
    {
        return $this->hasMany(CrmDealStageChange::class, 'crm_deal_id');
    }

    public function activityNotes(): MorphMany
    {
        return $this->morphMany(CrmNote::class, 'notable');
    }
}
