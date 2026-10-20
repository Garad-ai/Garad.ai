<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Models;

use Illuminate\Database\Eloquent\Model;

class AgentMessageMention extends Model
{
    protected $table = 'ext_ai_chat_pro_message_mentions';

    protected $fillable = [
        'message_id',
        'chat_id',
        'sender_id',
        'recipient_id',
        'recipient_username',
    ];
}
