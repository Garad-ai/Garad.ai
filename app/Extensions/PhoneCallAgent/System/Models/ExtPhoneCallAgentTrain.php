<?php

namespace App\Extensions\PhoneCallAgent\System\Models;

use App\Extensions\PhoneCallAgent\System\Enums\TrainTypeEnum;
use App\Models\User;
use Database\Factories\ExtPhoneCallAgentTrainFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtPhoneCallAgentTrain extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_id',
        'user_id',
        'doc_id',
        'name',
        'type',
        'file',
        'url',
        'text',
        'content',
        'trained_at',
    ];

    protected $casts = [
        'type'       => TrainTypeEnum::class,
        'trained_at' => 'datetime',
    ];

    protected static function newFactory(): Factory
    {
        return ExtPhoneCallAgentTrainFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(ExtPhoneCallAgent::class, 'agent_id');
    }
}
