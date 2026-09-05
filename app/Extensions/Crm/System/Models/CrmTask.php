<?php

namespace App\Extensions\Crm\System\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmTask extends Model
{
    protected $table = 'crm_tasks';

    protected $fillable = [
        'user_id',
        'crm_contact_id',
        'crm_deal_id',
        'crm_project_id',
        'title',
        'description',
        'type',
        'status',
        'priority',
        'order',
        'due_date',
        'completed_at',
    ];

    protected $casts = [
        'order'        => 'integer',
        'due_date'     => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'crm_contact_id');
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(CrmDeal::class, 'crm_deal_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(CrmProject::class, 'crm_project_id');
    }
}
