<?php

declare(strict_types=1);

namespace App\Extensions\UGCCreator\System\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UGCCreatorAsset extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const KIND_PRODUCT = 'product';

    public const KIND_CHARACTER = 'character';

    public const KINDS = [self::KIND_PRODUCT, self::KIND_CHARACTER];

    protected $table = 'ugc_creator_assets';

    protected $fillable = [
        'user_id',
        'kind',
        'title',
        'image_path',
        'mime',
        'size_bytes',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeKind(Builder $query, string $kind): Builder
    {
        return $query->where('kind', $kind);
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return url('/uploads/' . ltrim($this->image_path, '/'));
    }
}
