<?php

namespace App\Extensions\ElevenLabsVoiceChat\System\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VoiceChatBotTrain extends Model
{
    use HasFactory;

    protected $fillable = ['chatbot_id', 'user_id', 'doc_id', 'name', 'type', 'text', 'url', 'file', 'trained_at'];
}
