<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Models;

use App\Extensions\AIAgent\System\Enums\WorkflowRunStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AIAgentWorkflowRun extends Model
{
    protected $table = 'ext_ai_agent_workflow_runs';

    protected $fillable = [
        'workflow_id',
        'idempotency_key',
        'status',
        'trigger_data',
        'steps_output',
        'run_context',
        'current_step_order',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'status'             => WorkflowRunStatusEnum::class,
        'trigger_data'       => 'array',
        'steps_output'       => 'array',
        'run_context'        => 'array',
        'current_step_order' => 'integer',
        'started_at'         => 'datetime',
        'completed_at'       => 'datetime',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(AIAgentWorkflow::class, 'workflow_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(AIAgentWorkflowRunStep::class, 'workflow_run_id')->orderBy('step_index');
    }
}
