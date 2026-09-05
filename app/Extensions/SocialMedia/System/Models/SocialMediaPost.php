<?php

namespace App\Extensions\SocialMedia\System\Models;

use App\Extensions\SocialMedia\System\Enums\PlatformEnum;
use App\Extensions\SocialMedia\System\Enums\PostTypeEnum;
use App\Extensions\SocialMedia\System\Enums\StatusEnum;
use App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgentPost;
use App\Helpers\Classes\MarketplaceHelper;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialMediaPost extends Model
{
    protected $table = 'ext_social_media_posts';

    protected $fillable = [
        'agent_id',
        'social_media_agent_post_id',
        'user_id',
        'post_id',
        'company_id',
        'campaign_id',
        'social_media_platform_id',
        'social_media_platform',
        'post_type',
        'is_personalized_content',
        'tone',
        'content',
        'link',
        'image',
        'images',
        'video',
        'is_repeated',
        'has_replicate',
        'repeat_period',
        'repeat_start_date',
        'repeat_time',
        'status',
        'scheduled_at',
        'posted_at',
        'hashtags',
        'post_metrics',
        'post_engagement_count',
        'post_engagement_rate',
        'post_metric_at',
    ];

    protected $casts = [
        'social_media_platform'      => PlatformEnum::class,
        'post_type'                  => PostTypeEnum::class,
        'has_replicate'              => 'boolean',
        'status'                     => StatusEnum::class,
        'scheduled_at'               => 'datetime',
        'repeat_start_date'          => 'datetime:Y-m-d',
        'posted_at'                  => 'datetime',
        'hashtags'                   => 'json',
        'post_metrics'               => 'json',
        'images'                     => 'json',
        'social_media_agent_post_id' => 'integer',
    ];

    protected $appends = [
        'link',
    ];

    public function images(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (is_null($value) || $value === '' || $value === 'null') {
                    return null;
                }

                if (is_string($value)) {
                    $decoded = json_decode($value, true);

                    if (is_array($decoded)) {
                        return $decoded;
                    }

                    return [$value];
                }

                return is_array($value) ? $value : [$value];
            },
            set: function ($value) {
                if (is_null($value)) {
                    return null;
                }

                return is_string($value) ? $value : json_encode($value);
            },
        );
    }

    public function hasMultipleImages(): bool
    {
        return is_array($this->images) && count($this->images) > 1;
    }

    public function getPlatformEnum()
    {
        if ($this->social_media_platform) {
            return $this->social_media_platform;
        }

        if ($this->platform?->platform) {
            return PlatformEnum::from($this->platform->platform);
        }

        return null;
    }

    public function link(): Attribute
    {
        $status = $this->status;

        $platform = $this->social_media_platform;

        if ($status !== StatusEnum::published || is_null($this->post_id)) {
            return Attribute::make(function () {
                return null;
            });
        }

        $link = match ($platform) {
            //            PlatformEnum::facebook  => "https://www.facebook.com/share/p/{$this->post_id}",
            PlatformEnum::x              => "https://x.com/i/web/status/{$this->post_id}",
            PlatformEnum::linkedin       => "https://www.linkedin.com/feed/update/urn:li:activity:{$this->post_id}",
            PlatformEnum::youtube        => "https://www.youtube.com/watch?v={$this->post_id}",
            PlatformEnum::youtube_shorts => "https://www.youtube.com/shorts/{$this->post_id}",
            //            PlatformEnum::instagram => "https://www.instagram.com/p/{$this->post_id}",
            default                 => null,
        };

        return Attribute::make(function () use ($link) {
            return $link;
        });
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(SocialMediaPlatform::class, 'social_media_platform_id', 'id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(SocialMediaSharedLog::class, 'social_media_post_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo('App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgent', 'agent_id');
    }

    public function agentPost(): BelongsTo
    {
        return $this->belongsTo('App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgentPost', 'social_media_agent_post_id');
    }

    public function agentPostPublished(?string $platformPostId = null): void
    {
        if ($this->social_media_agent_post_id && MarketplaceHelper::isRegistered('social-media-agent')) {
            $agentPost = SocialMediaAgentPost::query()
                ->where('id', $this->post->social_media_agent_post_id)
                ->first();

            if ($agentPost) {
                $agentPost->markAsPublished($platformPostId);
            }
        }
    }

    public function agentPostFailed(string $errorMessage): void
    {
        if ($this->social_media_agent_post_id && MarketplaceHelper::isRegistered('social-media-agent')) {
            $agentPost = SocialMediaAgentPost::query()
                ->where('id', $this->post->social_media_agent_post_id)
                ->first();

            if ($agentPost) {
                $agentPost->markAsFailed($errorMessage);
            }
        }
    }
}
