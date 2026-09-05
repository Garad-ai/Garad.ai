<?php

namespace App\Extensions\SocialMedia\System\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialMediaCampaign extends Model
{
    protected $table = 'ext_social_media_campaigns';

    protected $fillable = [
        'user_id',
        'name',
        'target_audience',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
