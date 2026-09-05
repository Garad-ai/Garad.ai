<?php

namespace App\Extensions\SocialMedia\System\Models;

use App\Extensions\SocialMedia\System\Enums\PlatformEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SocialMediaPlatform extends Model
{
    protected $table = 'ext_social_media_platforms';

    protected $fillable = [
        'user_id',
        'platform',
        'credentials',
        'followers_count',
        'connected_at',
        'expires_at',
    ];

    protected $casts = [
        'credentials'     => 'array',
        'connected_at'    => 'datetime',
        'expires_at'      => 'datetime',
        'followers_count' => 'integer',
    ];

    public function scopeConnected(Builder $builder)
    {
        return $builder->where('expires_at', '>=', now());
    }

    public function username(): string
    {
        return $this->credentials['name'] ?? ($this->credentials['username'] ?? 'John Doe');
    }

    public function label(): string
    {
        $slug = (string) $this->platform;

        return PlatformEnum::tryFrom($slug)?->label()
            ?? Str::headline(str_replace(['_', '-'], ' ', $slug));
    }

    public function platformLabel(): string
    {
        $slug = (string) $this->platform;

        return PlatformEnum::tryFrom($slug)?->label()
            ?? Str::headline(str_replace(['_', '-'], ' ', $slug));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isConnected(): bool
    {
        return $this->connected_at && $this->expires_at && $this->expires_at->gt(now());
    }
}
