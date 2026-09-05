<?php

namespace App\Extensions\AiVideoPro\System\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserFall extends Model
{
    use HasFactory;

    protected $table = 'user_fall';

    protected $fillable = [
        'user_id',
        'is_demo',
        'prompt',
        'prompt_image_url',
        'status',
        'error_message',
        'request_id',
        'response_url',
        'model',
        'video_url',
        'duration_seconds',
        'resolution',
        'aspect_ratio',
        'thumbnail_url',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function getFormattedDurationAttribute(): string
    {
        $seconds = $this->duration_seconds ?? 0;

        return sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    // required for listing videos in documents page
    public function isFavoriteDoc(): false
    {
        return false;
    }
}
