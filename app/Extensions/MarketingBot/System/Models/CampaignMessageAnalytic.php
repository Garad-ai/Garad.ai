<?php

namespace App\Extensions\MarketingBot\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignMessageAnalytic extends Model
{
    protected $table = 'ext_campaign_message_analytics';

    protected $fillable = [
        'campaign_id',
        'meta_message_id',
        'recipient_phone',
        'meta_template_name',
        'status',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'error_code',
        'error_title',
    ];

    protected $casts = [
        'sent_at'      => 'datetime',
        'delivered_at' => 'datetime',
        'read_at'      => 'datetime',
        'failed_at'    => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class, 'campaign_id');
    }
}
