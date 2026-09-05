<?php

namespace App\Extensions\SocialMediaAgent\System\Models;

use App\Extensions\SocialMedia\System\Models\SocialMediaPlatform;
use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SocialMediaAgent extends Model
{
    use SoftDeletes;

    protected $table = 'ext_social_media_agents';

    protected $fillable = [
        'user_id',
        'name',
        'platform_ids',
        'site_url',
        'site_description',
        'scraped_content',
        'target_audience',
        'post_types',
        'tone',
        'cta_templates',
        'categories',
        'goals',
        'branding_description',
        'creativity',
        'hashtag_count',
        'approximate_words',
        'language',
        'schedule_days',
        'schedule_times',
        'daily_post_count',
        'reserved_post_day',
        'start_train_post_count',
        'has_image',
        'publishing_type',
        'is_active',
        'settings',
        'post_generation_status',
        'average_impressions',
        'average_engagement',
    ];

    protected $casts = [
        'platform_ids'            => 'array',
        'scraped_content'         => 'array',
        'target_audience'         => 'array',
        'post_types'              => 'array',
        'cta_templates'           => 'array',
        'categories'              => 'array',
        'goals'                   => 'array',
        'schedule_days'           => 'array',
        'schedule_times'          => 'array',
        'settings'                => 'array',
        'post_generation_status'  => 'array',
        'has_image'               => 'boolean',
        'is_active'               => 'boolean',
        'hashtag_count'           => 'integer',
        'approximate_words'       => 'integer',
        'daily_post_count'        => 'integer',
        'reserved_post_day'       => 'integer',
        'start_train_post_count'  => 'integer',
        'average_impressions'     => 'float',
        'average_engagement'      => 'float',
    ];

    // Relationships

    public function image(): Attribute
    {
        return Attribute::make(
            get: fn () => asset('vendor/social-media-agent/images/agent-default.png'),
        );
    }

    public function imageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => asset('vendor/social-media-agent/images/agent-default.png'),
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(SocialMediaAgentPost::class, 'agent_id');
    }

    public function platforms()
    {
        return SocialMediaPlatform::whereIn('id', $this->platform_ids ?? [])->get();
    }

    // Helper Methods

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function hasSiteUrl(): bool
    {
        return ! empty($this->site_url);
    }

    public function hasTargetAudience(): bool
    {
        return ! empty($this->target_audience);
    }

    public function getScheduledDays(): array
    {
        return $this->schedule_days ?? [];
    }

    public function getScheduledTimes(): array
    {
        return $this->schedule_times ?? [];
    }

    public function canGeneratePosts(): bool
    {
        return $this->is_active
            && ! empty($this->platform_ids)
            && ! empty($this->target_audience)
            && ! empty($this->schedule_days)
            && ! empty($this->schedule_times);
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
