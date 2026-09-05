<?php

declare(strict_types=1);

namespace App\Extensions\AiChatProEntityHighlight\System\Models;

use App\Models\User;
use App\Models\UserOpenaiChatMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatEntityHighlight extends Model
{
    protected $table = 'chat_entity_highlights';

    protected $guarded = [];

    protected $casts = [
        'entities' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(UserOpenaiChatMessage::class, 'message_id');
    }
}
