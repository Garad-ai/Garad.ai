<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIAgentMessage extends Model
{
    protected $table = 'ext_ai_agent_messages';

    protected $fillable = [
        'channel_id',
        'conversation_id',
        'workflow_run_id',
        'direction',
        'sender_id',
        'content',
        'raw_payload',
        'status',
    ];

    protected $casts = [
        'raw_payload' => 'array',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(AIAgentChannel::class, 'channel_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AIAgentConversation::class, 'conversation_id');
    }

    public function workflowRun(): BelongsTo
    {
        return $this->belongsTo(AIAgentWorkflowRun::class, 'workflow_run_id');
    }
}
