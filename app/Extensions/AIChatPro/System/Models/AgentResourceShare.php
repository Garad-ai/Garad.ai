<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentResourceShare extends Model
{
    protected $table = 'ext_ai_chat_pro_shares';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'resource_type',
        'resource_id',
        'resource_part',
        'chat_id',
        'owner_id',
        'recipient_id',
        'access_level',
        'token_hash',
        'revoked_at',
    ];

    protected $casts = [
        'revoked_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}
