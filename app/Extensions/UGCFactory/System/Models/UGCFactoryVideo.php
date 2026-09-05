<?php

declare(strict_types=1);

namespace App\Extensions\UGCFactory\System\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UGCFactoryVideo extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const SOURCE_TTS = 'tts';

    public const SOURCE_UPLOAD = 'upload';

    public const SOURCE_RECORD = 'record';

    protected $table = 'ugc_factory_videos';

    protected $fillable = [
        'user_id',
        'actor_id',
        'actor_snapshot',
        'title',
        'script',
        'voice_provider',
        'voice_id',
        'voice_language',
        'audio_path',
        'audio_source',
        'image_url_resolved',
        'audio_url_resolved',
        'resolution',
        'fal_request_id',
        'video_path',
        'video_url',
        'duration_seconds',
        'credits_used',
        'status',
        'error',
    ];

    protected $casts = [
        'actor_snapshot'   => 'array',
        'duration_seconds' => 'integer',
        'credits_used'     => 'decimal:4',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(UGCFactoryActor::class, 'actor_id');
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeCurrentMonth(Builder $query): Builder
    {
        return $query->where('created_at', '>=', now()->startOfMonth());
    }

    public function getThumbUrlAttribute(): ?string
    {
        $snapshot = $this->actor_snapshot ?? [];
        $type = $snapshot['type'] ?? null;
        $path = $snapshot['image_path'] ?? null;
        $presetKey = $snapshot['preset_key'] ?? null;

        if ($type === UGCFactoryActor::TYPE_PRESET && $presetKey) {
            return custom_theme_url('/vendor/ugc-factory/images/actors/' . $presetKey . '.webp');
        }

        if ($path) {
            return url('/uploads/' . ltrim($path, '/'));
        }

        return null;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
