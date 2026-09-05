<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProDeepResearch\System\Models;

use App\Models\User;
use App\Models\UserOpenaiChat;
use App\Models\UserOpenaiChatMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeepResearchSession extends Model
{
    protected $table = 'deep_research_sessions';

    protected $fillable = [
        'user_id',
        'message_id',
        'chat_id',
        'query',
        'status',
        'engine_used',
        'model_used',
        'provider_response_id',
        'sources_count',
        'searches_count',
        'duration_seconds',
        'credits_used',
        'sources',
        'thinking_steps',
        'report_output',
        'raw_response',
    ];

    protected $casts = [
        'sources'        => 'array',
        'thinking_steps' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(UserOpenaiChatMessage::class, 'message_id');
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(UserOpenaiChat::class, 'chat_id');
    }
}
