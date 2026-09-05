<?php

namespace App\Extensions\AiPersona\System\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiPersonaAvatar extends Model
{
    public const STATUS_GENERATING = 'generating';

    public const STATUS_GENERATED = 'generated';

    public const STATUS_GROUPING = 'grouping';

    public const STATUS_TRAINING = 'training';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    public const SOURCE_AI = 'ai';

    public const SOURCE_UPLOAD = 'upload';

    protected $table = 'ai_persona_avatars';

    protected $fillable = [
        'user_id',
        'name',
        'source',
        'status',
        'generation_id',
        'image_key',
        'group_id',
        'flow_id',
        'heygen_avatar_id',
        'preview_image_url',
        'age',
        'gender',
        'ethnicity',
        'orientation',
        'pose',
        'style',
        'appearance',
        'error_message',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_READY, self::STATUS_FAILED], true);
    }
}
