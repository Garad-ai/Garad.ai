<?php

namespace App\Extensions\SocialMediaAgent\System\Models;

use App\Extensions\SocialMedia\System\Enums\PlatformEnum;
use App\Extensions\SocialMedia\System\Enums\PostTypeEnum;
use App\Extensions\SocialMedia\System\Models\SocialMediaPlatform;
use App\Extensions\SocialMedia\System\Models\SocialMediaPost;
use DateTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SocialMediaAgentPost extends Model
{
    use SoftDeletes;

    protected $table = 'ext_social_media_agent_posts';

    protected $fillable = [
        'agent_id',
        'platform_id',
        'content',
        'media_urls',
        'image_request_id',
        'image_status',
        'image_model',
        'video_urls',
        'video_request_id',
        'video_status',
        'post_type',
        'publishing_type',
        'status',
        'scheduled_at',
        'published_at',
        'approved_at',
        'ai_metadata',
        'hashtags',
        'error_message',
        'platform_post_id',
        'platform_response',
    ];

    protected $casts = [
        'media_urls'         => 'array',
        'video_urls'         => 'array',
        'ai_metadata'        => 'array',
        'hashtags'           => 'array',
        'platform_response'  => 'array',
        'publishing_type'    => PostTypeEnum::class,
        'scheduled_at'       => 'datetime',
        'published_at'       => 'datetime',
        'approved_at'        => 'datetime',
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
        return $this->belongsTo(SocialMediaAgent::class, 'agent_id');
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(SocialMediaPlatform::class, 'platform_id');
    }

    public function socialPost(): BelongsTo
    {
        return $this->belongsTo(SocialMediaPost::class, 'platform_post_id');
    }

    public function getPlatformEnum()
    {
        if ($this->platform->platform) {
            return PlatformEnum::from($this->platform->platform);
        }

        return null;
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

    public function hasPendingVideo(): bool
    {
        return in_array($this->video_status, ['pending', 'generating'], true);
    }
}
