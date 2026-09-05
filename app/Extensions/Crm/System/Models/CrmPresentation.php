<?php

namespace App\Extensions\Crm\System\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmPresentation extends Model
{
    protected $table = 'crm_presentations';

    protected $fillable = [
        'user_id',
        'crm_deal_id',
        'crm_company_id',
        'crm_contact_id',
        'title',
        'status',
        'engine',
        'style',
        'generation_id',
        'gamma_url',
        'pdf_url',
        'pptx_url',
        'total_slides',
        'completed_slides',
        'credits_deducted_amount',
        'credits_deducted_at',
        'completed_at',
        'metadata',
    ];

    protected $casts = [
        'metadata'                => 'array',
        'total_slides'            => 'integer',
        'completed_slides'        => 'integer',
        'credits_deducted_amount' => 'float',
        'credits_deducted_at'     => 'datetime',
        'completed_at'            => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(CrmDeal::class, 'crm_deal_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(CrmCompany::class, 'crm_company_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'crm_contact_id');
    }

    public function slides(): HasMany
    {
        return $this->hasMany(CrmPresentationSlide::class)->orderBy('sort_order');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isGamma(): bool
    {
        return $this->engine === 'gamma';
    }

    public function getProgressAttribute(): int
    {
        return $this->total_slides > 0
            ? (int) round(($this->completed_slides / $this->total_slides) * 100)
            : 0;
    }
}
