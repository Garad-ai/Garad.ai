<?php

namespace App\Extensions\Crm\System\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmCompany extends Model
{
    protected $table = 'crm_companies';

    protected $fillable = [
        'user_id',
        'is_favorite',
        'name',
        'industry',
        'website',
        'phone',
        'email',
        'address',
        'city',
        'state',
        'country',
        'notes',
    ];

    protected $casts = [
        'is_favorite' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CrmContact::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(CrmDeal::class);
    }
}
