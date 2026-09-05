<?php

declare(strict_types=1);

namespace App\Extensions\UGCFactory\System\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class UGCFactoryActor extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const TYPE_PRESET = 'preset';

    public const TYPE_UPLOAD = 'upload';

    public const TYPE_GENERATED = 'generated';

    public const STATUS_PENDING = 'pending';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    protected $table = 'ugc_factory_actors';

    protected $fillable = [
        'user_id',
        'type',
        'name',
        'image_path',
        'preset_key',
        'prompt',
        'fal_request_id',
        'fal_entity',
        'status',
        'error',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function videos(): HasMany
    {
        return $this->hasMany(UGCFactoryVideo::class, 'actor_id');
    }

    public function getImageUrlAttribute(): ?string
    {
        if ($this->type === self::TYPE_PRESET) {
            return $this->preset_key
                ? custom_theme_url('/vendor/ugc-factory/images/actors/' . $this->preset_key . '.webp')
                : null;
        }

        return $this->image_path ? url('/uploads/' . ltrim($this->image_path, '/')) : null;
    }
}
