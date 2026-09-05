<?php

namespace App\Extensions\SocialMedia\System\Models;

use App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialMediaPostDailyMetric extends Model
{
    protected $table = 'ext_social_media_post_daily_metrics';

    protected $fillable = [
        'social_media_post_id',
        'agent_id',
        'social_media_platform_id',
        'platform',
        'post_identifier',
        'date',
        'like_count',
        'comment_count',
        'share_count',
        'view_count',
        'last_totals',
    ];

    protected $casts = [
        'date'        => 'date',
        'last_totals' => 'array',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(SocialMediaAgent::class, 'agent_id');
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(SocialMediaPlatform::class, 'social_media_platform_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(SocialMediaPost::class, 'social_media_post_id');
    }
}
