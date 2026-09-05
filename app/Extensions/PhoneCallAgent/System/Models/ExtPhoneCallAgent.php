<?php

namespace App\Extensions\PhoneCallAgent\System\Models;

use App\Extensions\PhoneCallAgent\System\Enums\BookingProviderEnum;
use App\Extensions\PhoneCallAgent\System\Enums\ProviderEnum;
use App\Models\User;
use Database\Factories\ExtPhoneCallAgentFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExtPhoneCallAgent extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'agent_id',
        'title',
        'welcome_message',
        'instructions',
        'language',
        'ai_model',
        'voice_id',
        'phone_number',
        'phone_number_id',
        'twilio_account_sid',
        'twilio_auth_token',
        'provider',
        'max_call_duration',
        'silence_timeout',
        'active',
        'is_favorite',
        'booking_enabled',
        'booking_provider',
        'booking_event_type_id',
        'booking_api_key',
        'booking_tool_ids',
    ];

    protected $hidden = [
        'twilio_auth_token',
        'booking_api_key',
    ];

    protected $casts = [
        'active'            => 'boolean',
        'is_favorite'       => 'boolean',
        'booking_enabled'   => 'boolean',
        'provider'          => ProviderEnum::class,
        'booking_provider'  => BookingProviderEnum::class,
        'booking_tool_ids'  => 'array',
        'twilio_auth_token' => 'encrypted',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trains(): HasMany
    {
        return $this->hasMany(ExtPhoneCallAgentTrain::class, 'agent_id');
    }

    public function calls(): HasMany
    {
        return $this->hasMany(ExtPhoneCallAgentCall::class, 'agent_id');
    }

    protected static function newFactory(): Factory
    {
        return ExtPhoneCallAgentFactory::new();
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
