<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIAgentWorkflowApproval extends Model
{
    protected $table = 'ext_ai_agent_workflow_approvals';

    protected $fillable = [
        'workflow_id',
        'workflow_run_id',
        'workflow_run_step_id',
        'requested_by',
        'decision_by',
        'status',
        'payload',
        'comment',
        'decided_at',
    ];

    protected $casts = [
        'payload'    => 'array',
        'decided_at' => 'datetime',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(AIAgentWorkflow::class, 'workflow_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AIAgentWorkflowRun::class, 'workflow_run_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(AIAgentWorkflowRunStep::class, 'workflow_run_step_id');
    }
}
