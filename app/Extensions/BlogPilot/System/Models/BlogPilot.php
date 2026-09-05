<?php

namespace App\Extensions\BlogPilot\System\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BlogPilot extends Model
{
    use SoftDeletes;

    protected $table = 'ext_blogpilot';

    protected $fillable = [
        'user_id',
        'name',
        'topic_options',
        'selected_topics',
        'post_types',
        'has_image',
        'has_emoji',
        'has_web_search',
        'has_keyword_search',
        'language',
        'article_length',
        'tone',
        'frequency',
        'daily_post_count',
        'schedule_days',
        'schedule_times',
        'is_active',
        'post_generation_status',
    ];

    protected $casts = [
        'user_id'                => 'integer',
        'name'                   => 'string',
        'topic_options'          => 'array',
        'selected_topics'        => 'array',
        'post_types'             => 'array',
        'has_image'              => 'boolean',
        'has_emoji'              => 'boolean',
        'has_web_search'         => 'boolean',
        'has_keyword_search'     => 'boolean',
        'language'               => 'string',
        'article_length'         => 'string',
        'tone'                   => 'string',
        'frequency'              => 'string',
        'daily_post_count'       => 'integer',
        'schedule_days'          => 'array',
        'schedule_times'         => 'array',
        'is_active'              => 'boolean',
        'post_generation_status' => 'array',
    ];

    // Relationships

    public function image(): Attribute
    {
        return Attribute::make(
            get: fn () => asset('vendor/blogpilot/images/agent-default.png'),
        );
    }

    public function imageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => asset('vendor/blogpilot/images/agent-default.png'),
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(BlogPilotPost::class, 'agent_id');
    }

    public function platforms()
    {
        return [];
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
