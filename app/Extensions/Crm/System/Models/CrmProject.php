<?php

namespace App\Extensions\Crm\System\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmProject extends Model
{
    protected $table = 'crm_projects';

    protected $fillable = [
        'user_id',
        'is_favorite',
        'crm_contact_id',
        'crm_company_id',
        'crm_deal_id',
        'name',
        'description',
        'status',
        'priority',
        'category',
        'start_date',
        'due_date',
        'completed_at',
        'budget',
        'currency',
        'notes',
    ];

    protected $casts = [
        'is_favorite'  => 'boolean',
        'start_date'   => 'date',
        'due_date'     => 'date',
        'completed_at' => 'datetime',
        'budget'       => 'decimal:2',
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

    public function deal(): BelongsTo
    {
        return $this->belongsTo(CrmDeal::class, 'crm_deal_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(CrmTask::class, 'crm_project_id');
    }

    public function getProgressAttribute(): int
    {
        $total = $this->tasks()->count();

        if ($total === 0) {
            return 0;
        }

        $completed = $this->tasks()->where('status', 'completed')->count();

        return (int) round(($completed / $total) * 100);
    }
}
