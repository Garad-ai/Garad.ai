<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AIAgentConversation extends Model
{
    protected $table = 'ext_ai_agent_conversations';

    protected $fillable = [
        'channel_id',
        'workflow_id',
        'sender_id',
        'last_message_at',
        'pinned',
        'closed_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'closed_at'       => 'datetime',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(AIAgentChannel::class, 'channel_id');
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(AIAgentWorkflow::class, 'workflow_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AIAgentMessage::class, 'conversation_id');
    }
}
