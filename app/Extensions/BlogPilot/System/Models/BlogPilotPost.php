<?php

namespace App\Extensions\BlogPilot\System\Models;

use DateTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BlogPilotPost extends Model
{
    use SoftDeletes;

    protected $table = 'ext_blogpilot_posts';

    protected $fillable = [
        'user_id',
        'agent_id',
        'title',
        'content',
        'thumbnail',
        'tags',
        'categories',
        'status',
        'scheduled_at',
        'published_at',
        'approved_at',
    ];

    protected $casts = [
        'title'        => 'string',
        'content'      => 'string',
        'thumbnail'    => 'string',
        'tags'         => 'array',
        'categories'   => 'array',
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
        'approved_at'  => 'datetime',
    ];

    // Status constants
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_FAILED = 'failed';

    // Post type constants
    public const TYPE_CAROUSEL = 'carousel';

    public const TYPE_SINGLE_IMAGE = 'single_image';

    public const TYPE_TEXT = 'text';

    public const TYPE_VIDEO = 'video';

    // Relationships

    public function agent(): BelongsTo
    {
        return $this->belongsTo(BlogPilot::class, 'agent_id');
    }

    // Helper Methods

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isScheduled(): bool
    {
        return $this->status === self::STATUS_SCHEDULED;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function canBeApproved(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT]);
    }

    public function canBeScheduled(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT]);
    }

    public function canBePublished(): bool
    {
        return $this->status === self::STATUS_SCHEDULED && $this->scheduled_at?->isPast();
    }

    public function markAsScheduled(DateTime $scheduledAt): self
    {
        $this->update([
            'status'       => self::STATUS_SCHEDULED,
            'scheduled_at' => $scheduledAt,
        ]);

        return $this;
    }

    public function markAsPublished(?string $platformPostId = null): self
    {
        $this->update([
            'status'           => self::STATUS_PUBLISHED,
            'published_at'     => now(),
            'platform_post_id' => $platformPostId,
        ]);

        return $this;
    }

    public function markAsFailed(string $errorMessage): self
    {
        $this->update([
            'status'        => self::STATUS_FAILED,
            'error_message' => $errorMessage,
        ]);

        return $this;
    }

    // Scopes

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED);
    }

    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeReadyToPublish($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED)
            ->where('scheduled_at', '<=', now());
    }

    public function scopeForAgent($query, int $agentId)
    {
        return $query->where('agent_id', $agentId);
    }

    public function scopeForPlatform($query, int $platformId)
    {
        return $query->where('platform_id', $platformId);
    }

    public static function getStatusArray(): array
    {
        return [
            self::STATUS_DRAFT     => __('Draft'),
            self::STATUS_SCHEDULED => __('Scheduled'),
            self::STATUS_PUBLISHED => __('Published'),
            self::STATUS_FAILED    => __('Failed'),
        ];
    }
}
