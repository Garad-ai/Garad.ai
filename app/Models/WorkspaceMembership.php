<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceMembership extends Model
{
    protected $fillable = [
        'user_id',
        'workspace_type',
        'workspace_id',
        'role',
        'permissions',
        'status',
    ];

    protected $casts = [
        'permissions' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasRole(string|array $roles): bool
    {
        $normalized = is_array($roles) ? $roles : [$roles];

        return in_array($this->role, $normalized, true);
    }
}
