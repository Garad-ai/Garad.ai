<?php

namespace App\Extensions\MarketingBot\System\Models;

use App\Extensions\MarketingBot\System\Enums\CampaignStatus;
use App\Extensions\MarketingBot\System\Enums\CampaignType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingCampaign extends Model
{
    protected $table = 'ext_marketing_campaigns';

    protected $fillable = [
        'template_id',
        'user_id',
        'name',
        'content',
        'image',
        'contacts',
        'segments',
        'type',
        'status',
        'ai_embedding_model',
        'witch_campaign_question',
        'instruction',
        'ai_reply',
        'meta_template_name',
        'meta_template_language',
        'meta_body_variables',
        'scheduled_at',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'status'              => CampaignStatus::class,
        'type'                => CampaignType::class,
        'contacts'            => 'array',
        'segments'            => 'array',
        'meta_body_variables' => 'array',
        'scheduled_at'        => 'datetime',
        'started_at'          => 'datetime',
        'finished_at'         => 'datetime',
    ];

    public function analytics(): HasMany
    {
        return $this->hasMany(CampaignMessageAnalytic::class, 'campaign_id');
    }

    public function embeddings(): HasMany
    {
        return $this->hasMany(MarketingCampaignEmbedding::class, 'marketing_campaign_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
