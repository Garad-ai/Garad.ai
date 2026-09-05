<?php

declare(strict_types=1);

namespace App\Extensions\VideoDubbing\System\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoDubbing extends Model
{
    protected $table = 'video_dubbings';

    protected $fillable = [
        'user_id',
        'provider',
        'source_type',
        'source_url',
        'source_file_path',
        'target_language',
        'source_language',
        'speaker_num',
        'request_id',
        'status',
        'error_message',
        'output_url',
        'local_output_path',
        'caption_url',
        'duration_seconds',
        'title',
        'mode',
        'translate_audio_only',
        'highest_resolution',
        'drop_background_audio',
        'use_profanity_filter',
        'metadata',
    ];

    protected $casts = [
        'speaker_num'           => 'integer',
        'duration_seconds'      => 'integer',
        'translate_audio_only'  => 'boolean',
        'highest_resolution'    => 'boolean',
        'drop_background_audio' => 'boolean',
        'use_profanity_filter'  => 'boolean',
        'metadata'              => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isComplete(): bool
    {
        return $this->status === 'complete';
    }

    public function isError(): bool
    {
        return $this->status === 'error';
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'running']);
    }
}
