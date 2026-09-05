<?php

namespace App\Extensions\MarketingBot\System\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingBotMonthlyUsage extends Model
{
    protected $table = 'ext_marketing_bot_monthly_usage';

    protected $fillable = [
        'user_id',
        'year',
        'month',
        'messages_sent',
    ];

    protected $casts = [
        'year'          => 'integer',
        'month'         => 'integer',
        'messages_sent' => 'integer',
    ];
}
