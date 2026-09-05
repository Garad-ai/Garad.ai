<?php

declare(strict_types=1);

namespace App\Extensions\AiChatProSmartImage\System\Models;

use App\Models\User;
use App\Models\UserOpenaiChatMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatSmartImage extends Model
{
    protected $table = 'chat_smart_images';

    protected $fillable = [
        'user_id',
        'message_id',
        'query',
        'images',
    ];

    protected $casts = [
        'images' => 'array',
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
