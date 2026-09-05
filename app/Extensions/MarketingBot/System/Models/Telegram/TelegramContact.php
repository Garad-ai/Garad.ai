<?php

namespace App\Extensions\MarketingBot\System\Models\Telegram;

use Illuminate\Database\Eloquent\Model;

class TelegramContact extends Model
{
    public $timestamps = false;

    protected $table = 'ext_telegram_contacts';

    protected $fillable = [
        'user_id',
        'telegram_id',
        'contact_id',
        'name',
        'username',
        'group_chat_id',
        'group_id',
        'created_at',
    ];
}
