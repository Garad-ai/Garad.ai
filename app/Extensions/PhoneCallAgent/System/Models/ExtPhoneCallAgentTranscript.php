<?php

namespace App\Extensions\PhoneCallAgent\System\Models;

use App\Extensions\PhoneCallAgent\System\Enums\RoleEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtPhoneCallAgentTranscript extends Model
{
    use HasFactory;

    protected $fillable = [
        'call_id',
        'role',
        'message',
    ];

    protected $casts = [
        'role' => RoleEnum::class,
    ];

    public function call(): BelongsTo
    {
        return $this->belongsTo(ExtPhoneCallAgentCall::class, 'call_id');
    }
}
