<?php

namespace App\Extensions\MarketingBot\System\Models\Telegram;

use Illuminate\Database\Eloquent\Model;

class TelegramBot extends Model
{
    protected $table = 'ext_telegram_bots';

    protected $fillable = [
        'user_id',
        'access_token',
        'is_connected',
        'webhook_verified',
        'bot_id',
        'name',
        'username',
        'scopes',
    ];

    protected $casts = [
        'scopes' => 'array',
    ];
}
