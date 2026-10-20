<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AIAgentWorkflowRunStep extends Model
{
    protected $table = 'ext_ai_agent_workflow_run_steps';

    protected $fillable = [
        'workflow_run_id',
        'step_index',
        'step_id',
        'type',
        'status',
        'input',
        'output',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'input'        => 'array',
        'output'       => 'array',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(AIAgentWorkflowRun::class, 'workflow_run_id');
    }

    public function approval(): HasOne
    {
        return $this->hasOne(AIAgentWorkflowApproval::class, 'workflow_run_step_id');
    }
}
