<?php

namespace App\Extensions\SocialMedia\System\Models;

use App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialMediaAnalysis extends Model
{
    protected $table = 'ext_social_media_analyses';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'type',
        'agent_id',
        'summary',
        'report_text',
        'created_at',
        'read_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'read_at'    => 'datetime',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(SocialMediaAgent::class, 'agent_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function markAsRead(): self
    {
        if (! $this->read_at) {
            $this->update(['read_at' => now()]);
        }

        return $this;
    }
}
