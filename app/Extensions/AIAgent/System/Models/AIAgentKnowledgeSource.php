<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AIAgentKnowledgeSource extends Model
{
    protected $table = 'ext_ai_agent_knowledge_sources';

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'content',
        'file_name',
    ];

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
