<?php

namespace App\Extensions\Crm\System\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CrmNote extends Model
{
    protected $table = 'crm_notes';

    protected $fillable = [
        'user_id',
        'source_label',
        'notable_type',
        'notable_id',
        'type',
        'content',
        'scheduled_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function notable(): MorphTo
    {
        return $this->morphTo();
    }
}
