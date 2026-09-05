<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIAgentAvatar extends Model
{
    protected $table = 'ext_ai_agent_avatars';

    protected $fillable = [
        'user_id',
        'avatar',
    ];

    protected $appends = ['avatar_url'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getAvatarUrlAttribute(): string
    {
        if (str_starts_with($this->avatar, 'http') || str_starts_with($this->avatar, '/')) {
            return $this->avatar;
        }

        return '/' . $this->avatar;
    }
}
