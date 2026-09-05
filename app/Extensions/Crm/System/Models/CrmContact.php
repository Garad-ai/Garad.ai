<?php

namespace App\Extensions\Crm\System\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CrmContact extends Model
{
    protected $table = 'crm_contacts';

    protected $fillable = [
        'user_id',
        'is_favorite',
        'crm_company_id',
        'first_name',
        'last_name',
        'avatar',
        'email',
        'phone',
        'job_title',
        'status',
        'source_label',
        'notes',
    ];

    protected $casts = [
        'is_favorite' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(CrmCompany::class, 'crm_company_id');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(CrmDeal::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(CrmTask::class);
    }

    public function activityNotes(): MorphMany
    {
        return $this->morphMany(CrmNote::class, 'notable');
    }

    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ? '/uploads/' . ltrim($this->avatar, '/') : null;
    }

    public function getInitialsAttribute(): string
    {
        return strtoupper(mb_substr($this->first_name ?? '', 0, 1) . mb_substr($this->last_name ?? '', 0, 1));
    }
}
