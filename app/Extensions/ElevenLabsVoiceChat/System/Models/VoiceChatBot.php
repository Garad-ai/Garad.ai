<?php

namespace App\Extensions\ElevenLabsVoiceChat\System\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VoiceChatBot extends Model
{
    use HasFactory;

    protected $fillable = [
        'welcome_message',
        'instruction',
        'language',
        'voice_id',
        'agent_id',
        'user_id',
        'ai_model',
        'title',
    ];

    /**
     * train datas for this bot
     */
    public function trainData(): HasMany
    {
        return $this->hasMany(VoiceChatBotTrain::class, 'chatbot_id');
    }

    /**
     * voice chabot owner
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
