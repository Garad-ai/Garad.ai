<?php

namespace App\Extensions\MarketingBot\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MarketingConversation extends Model
{
    protected $table = 'ext_marketing_conversations';

    protected $fillable = [
        'user_id',
        'type',
        'telegram_group_id',
        'whatsapp_channel_id',
        'ip_address',
        'conversation_name',
        'session_id',
        'connect_agent_at',
        'customer_payload',
        'is_showed_on_history',
        'last_activity_at',
    ];

    protected $casts = [
        'session_id'           => 'string',
        'customer_payload'     => 'json',
        'is_showed_on_history' => 'boolean',
        'last_activity_at'	    => 'datetime',
    ];

    public function sessionId(): string
    {
        return $this->session_id;
    }

    public function lastMessage(): HasOne
    {
        return $this->hasOne(MarketingMessageHistory::class, 'conversation_id')
            ->where('role', 'user')
            ->orderByDesc('id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(MarketingMessageHistory::class, 'conversation_id');
    }
}
