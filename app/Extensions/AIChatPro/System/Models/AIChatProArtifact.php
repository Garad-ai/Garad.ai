<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Models;

use App\Models\User;
use App\Models\UserOpenaiChat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIChatProArtifact extends Model
{
    protected $table = 'ai_chat_pro_artifacts';

    protected $fillable = [
        'user_id',
        'user_openai_chat_id',
        'user_openai_chat_message_id',
        'type',
        'title',
        'original_name',
        'mime_type',
        'storage_disk',
        'storage_path',
        'file_size',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(UserOpenaiChat::class, 'user_openai_chat_id');
    }
}
