<?php

declare(strict_types=1);

namespace App\Extensions\VideoEditor\System\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VideoEditorProject extends Model
{
    protected $table = 'video_editor_projects';

    protected $fillable = [
        'name',
        'aspect_ratio',
        'width',
        'height',
        'fps',
        'duration_ms',
        'timeline_data',
        'thumbnail_path',
        'last_saved_at',
    ];

    protected $casts = [
        'timeline_data' => 'array',
        'duration_ms'   => 'integer',
        'width'         => 'integer',
        'height'        => 'integer',
        'fps'           => 'integer',
        'last_saved_at' => 'datetime',
    ];

    protected $appends = [
        'thumbnail_url',
    ];

    public function getThumbnailUrlAttribute(): ?string
    {
        if ($this->thumbnail_path) {
            if (str_starts_with($this->thumbnail_path, 'http://') || str_starts_with($this->thumbnail_path, 'https://')) {
                return $this->thumbnail_path;
            }

            return url('uploads/' . $this->thumbnail_path);
        }

        // Fallback: use the media referenced by the first clip in the timeline.
        // Only walks `media` when it's already loaded to avoid N+1 queries.
        if ($this->relationLoaded('media')) {
            $firstClipMediaId = $this->getFirstClipMediaId();
            if ($firstClipMediaId !== null) {
                $match = $this->media->firstWhere('id', $firstClipMediaId);
                if ($match && $match->thumbnail_url) {
                    return $match->thumbnail_url;
                }
            }
        }

        return null;
    }

    /**
     * Find the mediaId of the earliest visual clip across the timeline tracks.
     */
    public function getFirstClipMediaId(): ?int
    {
        $tracks = $this->timeline_data['tracks'] ?? null;
        if (! is_array($tracks)) {
            return null;
        }

        $best = null;
        foreach ($tracks as $track) {
            $type = $track['type'] ?? null;
            if ($type !== 'video' && $type !== 'image') {
                continue;
            }
            foreach ($track['clips'] ?? [] as $clip) {
                if (empty($clip['mediaId'])) {
                    continue;
                }
                $start = (int) ($clip['startTime'] ?? 0);
                if ($best === null || $start < $best['start']) {
                    $best = ['start' => $start, 'mediaId' => (int) $clip['mediaId']];
                }
            }
        }

        return $best['mediaId'] ?? null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(VideoEditorMedia::class);
    }

    public function exportJobs(): HasMany
    {
        return $this->hasMany(VideoEditorExportJob::class);
    }
}
