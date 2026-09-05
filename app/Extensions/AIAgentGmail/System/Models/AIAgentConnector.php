<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentGmail\System\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIAgentConnector extends Model
{
    protected $table = 'ext_ai_agent_connectors';

    protected $fillable = [
        'user_id',
        'type',
        'credentials',
        'is_active',
        'connected_at',
        'expires_at',
    ];

    protected $hidden = ['credentials'];

    protected $casts = [
        'credentials'  => 'array',
        'is_active'    => 'boolean',
        'connected_at' => 'datetime',
        'expires_at'   => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getCredential(string $key): mixed
    {
        return data_get($this->credentials, $key);
    }
}
