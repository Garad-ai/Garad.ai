<?php

namespace App\Extensions\Crm\System\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesProposal extends Model
{
    protected $table = 'sales_proposals';

    protected $fillable = [
        'user_id',
        'crm_contact_id',
        'crm_company_id',
        'crm_deal_id',
        'title',
        'proposal_number',
        'status',
        'issue_date',
        'valid_until',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'total',
        'currency',
        'content',
        'notes',
    ];

    protected $casts = [
        'issue_date'  => 'date',
        'valid_until' => 'date',
        'subtotal'    => 'decimal:2',
        'tax_rate'    => 'decimal:2',
        'tax_amount'  => 'decimal:2',
        'total'       => 'decimal:2',
    ];

    public static function generateNumber(int $userId): string
    {
        $count = static::where('user_id', $userId)->count() + 1;

        return 'PRP-' . str_pad($count, 5, '0', STR_PAD_LEFT);
    }

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

    public function deal(): BelongsTo
    {
        return $this->belongsTo(CrmDeal::class, 'crm_deal_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesProposalItem::class);
    }
}
