<?php

namespace App\Extensions\AiPersona\System\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiPersona extends Model
{
    protected $table = 'user_heygen';

    protected $fillable = [
        'user_id',
        'avatar_id',
        'status',
        'input_text',
        'voice_type',
        'avatar_style',
        'matting',
        'caption',
        'circle_background_color',
        'voice_id',
        'audio_asset_id',
        'voice_speed',
        'voice_pitch',
        'voice_emotion',
        'locale',
        'dimension_width',
        'dimension_height',
    ];

    protected $casts = [
        'matting'          => 'boolean',
        'caption'          => 'boolean',
        'voice_speed'      => 'float',
        'voice_pitch'      => 'integer',
        'dimension_width'  => 'integer',
        'dimension_height' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
