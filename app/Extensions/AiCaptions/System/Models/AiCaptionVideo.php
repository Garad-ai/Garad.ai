<?php

declare(strict_types=1);

namespace App\Extensions\AiCaptions\System\Models;

use App\Extensions\AiCaptions\Database\Factories\AiCaptionVideoFactory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiCaptionVideo extends Model
{
    use HasFactory;

    protected $table = 'ai_captions_videos';

    protected $fillable = [
        'user_id',
        'is_demo',
        'title',
        'template_id',
        'template_name',
        'source_url',
        'source_file_path',
        'captions_video_id',
        'status',
        'error_message',
        'output_url',
        'local_path',
        'duration_seconds',
        'usage_deducted',
        'metadata',
    ];

    protected $casts = [
        'is_demo'          => 'boolean',
        'duration_seconds' => 'integer',
        'usage_deducted'   => 'boolean',
        'metadata'         => 'array',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isComplete(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return in_array($this->status, ['failed', 'cancelled'], true);
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['uploading', 'processing', 'rendering'], true);
    }

    public function getFormattedDurationAttribute(): string
    {
        $seconds = $this->duration_seconds ?? 0;

        return sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    protected static function newFactory(): Factory
    {
        return AiCaptionVideoFactory::new();
    }
}
