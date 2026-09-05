<?php

namespace App\Extensions\ModelCouncil\System\Models;

use App\Models\UserOpenaiChatMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModelCouncilResponse extends Model
{
    protected $table = 'model_council_responses';

    protected $fillable = [
        'user_openai_chat_message_id',
        'model_slug',
        'model_label',
        'response_text',
        'response_time_ms',
        'failed',
        'credits',
    ];

    protected $casts = [
        'failed' => 'boolean',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(UserOpenaiChatMessage::class, 'user_openai_chat_message_id');
    }
}
