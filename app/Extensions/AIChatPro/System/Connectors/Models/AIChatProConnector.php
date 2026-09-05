<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Connectors\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Throwable;

class AIChatProConnector extends Model
{
    protected $table = 'ext_ai_chat_pro_connectors';

    protected $fillable = [
        'uuid',
        'user_id',
        'type',
        'credentials',
        'selected_access',
        'is_active',
        'is_paused',
        'connected_at',
        'expires_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $connector): void {
            if (empty($connector->uuid)) {
                $connector->uuid = (string) Str::uuid();
            }
        });
    }

    protected $hidden = ['credentials'];

    protected $casts = [
        'credentials'     => 'array',
        'selected_access' => 'array',
        'is_active'       => 'boolean',
        'is_paused'       => 'boolean',
        'connected_at'    => 'datetime',
        'expires_at'      => 'datetime',
    ];

    protected static array $encryptedCredentialKeys = ['access_token', 'refresh_token'];

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

    public function scopeNotPaused(Builder $query): Builder
    {
        return $query->where('is_paused', false);
    }

    public function markInactive(): void
    {
        $this->is_active = false;
        $this->credentials = null;
        $this->save();
    }

    /**
     * @return array<int, string>|null
     */
    public function selectedAccessFor(string $key): ?array
    {
        $value = data_get($this->selected_access, $key);

        if (is_array($value) && $value !== []) {
            return array_values(array_filter(array_map('strval', $value)));
        }

        return null;
    }

    public function selectedRecentOnly(): bool
    {
        return (bool) data_get($this->selected_access, 'recent_only', false);
    }

    public function getCredential(string $key): mixed
    {
        $value = data_get($this->credentials, $key);

        if ($value !== null && in_array($key, self::$encryptedCredentialKeys, true)) {
            try {
                return Crypt::decryptString($value);
            } catch (Throwable) {
                return $value;
            }
        }

        return $value;
    }

    public function setCredentials(array $credentials): void
    {
        $encrypted = $credentials;

        foreach (self::$encryptedCredentialKeys as $key) {
            if (isset($encrypted[$key]) && $encrypted[$key] !== null && $encrypted[$key] !== '') {
                $encrypted[$key] = Crypt::encryptString((string) $encrypted[$key]);
            }
        }

        $this->credentials = $encrypted;
    }
}
