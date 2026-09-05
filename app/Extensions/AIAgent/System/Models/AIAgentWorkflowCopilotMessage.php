<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIAgentWorkflowCopilotMessage extends Model
{
    protected $table = 'ext_ai_agent_workflow_copilot_messages';

    protected $fillable = [
        'workflow_id',
        'role',
        'content',
        'tool_calls',
        'metadata',
    ];

    protected $casts = [
        'tool_calls' => 'array',
        'metadata'   => 'array',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(AIAgentWorkflow::class, 'workflow_id');
    }
}
