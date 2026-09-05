<?php

namespace App\Extensions\PhoneCallAgent\System\Models;

use App\Extensions\PhoneCallAgent\System\Enums\CallStatusEnum;
use App\Models\User;
use Database\Factories\ExtPhoneCallAgentCallFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExtPhoneCallAgentCall extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'agent_id',
        'user_id',
        'call_sid',
        'caller_number',
        'called_number',
        'status',
        'duration',
        'started_at',
        'ended_at',
        'pinned',
    ];

    protected $casts = [
        'status'     => CallStatusEnum::class,
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(ExtPhoneCallAgent::class, 'agent_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transcripts(): HasMany
    {
        return $this->hasMany(ExtPhoneCallAgentTranscript::class, 'call_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            ExtPhoneCallAgentCallTag::class,
            'ext_phone_call_agent_call_tag_pivot',
            'call_id',
            'tag_id'
        );
    }

    protected static function newFactory(): Factory
    {
        return ExtPhoneCallAgentCallFactory::new();
    }
}
