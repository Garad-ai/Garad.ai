<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Models;

use App\Extensions\AIAgent\System\Enums\ChannelEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AIAgentChannel extends Model
{
    protected $table = 'ext_ai_agent_channels';

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'credentials',
        'is_active',
        'last_message_at',
    ];

    protected $casts = [
        'type'            => ChannelEnum::class,
        'credentials'     => 'array',
        'is_active'       => 'boolean',
        'last_message_at' => 'datetime',
    ];

    protected $hidden = ['credentials'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AIAgentMessage::class, 'channel_id');
    }

    public function getCredential(string $key): mixed
    {
        return data_get($this->credentials, $key);
    }
}
