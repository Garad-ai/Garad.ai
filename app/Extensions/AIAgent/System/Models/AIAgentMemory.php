<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIAgentMemory extends Model
{
    protected $table = 'ext_ai_agent_memories';

    protected $fillable = [
        'user_id',
        'memory',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
