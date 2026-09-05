<?php

namespace App\Extensions\MarketingBot\System\Models\Telegram;

use Database\Factories\TelegramGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelegramGroup extends Model
{
    use HasFactory;

    protected static function newFactory(): TelegramGroupFactory
    {
        return TelegramGroupFactory::new();
    }

    protected $table = 'ext_telegram_groups';

    protected $fillable = [
        'user_id',
        'name',
        'group_id',
        'bot_id',
        'type',
        'group_type',
        'supergroup_subscriber_id',
        'status',
    ];
}
