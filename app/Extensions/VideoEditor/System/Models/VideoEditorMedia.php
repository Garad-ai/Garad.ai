<?php

declare(strict_types=1);

namespace App\Extensions\VideoEditor\System\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoEditorMedia extends Model
{
    protected $table = 'video_editor_media';

    protected $fillable = [
        'video_editor_project_id',
        'type',
        'filename',
        'original_filename',
        'path',
        'mime_type',
        'file_size',
        'duration_ms',
        'width',
        'height',
        'thumbnail_path',
        'source',
        'source_id',
    ];

    protected $casts = [
        'file_size'   => 'integer',
        'duration_ms' => 'integer',
        'width'       => 'integer',
        'height'      => 'integer',
    ];

    protected $appends = [
        'url',
        'thumbnail_url',
    ];

    public function getUrlAttribute(): ?string
    {
        if (! $this->path) {
            return null;
        }

        // External URLs (AI-generated videos, S3/R2/CDN media)
        if (str_starts_with($this->path, 'http://') || str_starts_with($this->path, 'https://')) {
            return $this->path;
        }

        // Paths starting with "uploads/" are web-relative (imported AI images)
        // Resolve with url() so it uses the current request domain
        if (str_starts_with($this->path, 'uploads/')) {
            return url($this->path);
        }

        // Default: video-editor media stored on the public disk (root = public/uploads)
        return url('uploads/' . $this->path);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if (! $this->thumbnail_path) {
            // For images, fall back to the main file URL as thumbnail
            if ($this->type === 'image') {
                return $this->url;
            }

            return null;
        }

        if (str_starts_with($this->thumbnail_path, 'http://') || str_starts_with($this->thumbnail_path, 'https://')) {
            return $this->thumbnail_path;
        }

        return url('uploads/' . $this->thumbnail_path);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(VideoEditorProject::class, 'video_editor_project_id');
    }
}
