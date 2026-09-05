<?php

namespace App\Extensions\Crm\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesProposalItem extends Model
{
    protected $table = 'sales_proposal_items';

    protected $fillable = [
        'sales_proposal_id',
        'description',
        'quantity',
        'unit_price',
        'total',
    ];

    protected $casts = [
        'quantity'   => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total'      => 'decimal:2',
    ];

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(SalesProposal::class, 'sales_proposal_id');
    }
}
