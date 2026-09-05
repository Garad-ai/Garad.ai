<?php

declare(strict_types=1);

namespace App\Extensions\UGCCreator\System\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UGCCreatorVideo extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $table = 'ugc_creator_videos';

    protected $fillable = [
        'user_id',
        'title',
        'prompt',
        'camera_preset',
        'video_details',
        'final_prompt',
        'asset_snapshot',
        'product_asset_ids',
        'character_asset_ids',
        'audio_enabled',
        'voice_provider',
        'voice_id',
        'voice_language',
        'audio_path',
        'audio_url_resolved',
        'model',
        'quality',
        'fal_request_id',
        'fal_response_url',
        'fal_status_url',
        'video_path',
        'video_url',
        'duration_seconds',
        'credits_used',
        'status',
        'error',
    ];

    protected $casts = [
        'asset_snapshot'      => 'array',
        'product_asset_ids'   => 'array',
        'character_asset_ids' => 'array',
        'audio_enabled'       => 'boolean',
        'duration_seconds'    => 'integer',
        'credits_used'        => 'decimal:4',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
        $snapshot = $this->asset_snapshot ?? [];
        if (! is_array($snapshot)) {
            return null;
        }

        // Prefer product, then character snapshot.
        $sorted = collect($snapshot)->sortBy(fn ($s) => ($s['kind'] ?? '') === 'product' ? 0 : 1)->values();

        foreach ($sorted as $entry) {
            $path = $entry['image_path'] ?? null;
            if ($path) {
                return url('/uploads/' . ltrim((string) $path, '/'));
            }
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
