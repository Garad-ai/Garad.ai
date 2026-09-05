<?php

declare(strict_types=1);

namespace App\Extensions\PhoneCallAgent\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ExtPhoneCallAgentCallTag extends Model
{
    protected $table = 'ext_phone_call_agent_call_tags';

    protected $guarded = [];

    public function calls(): BelongsToMany
    {
        return $this->belongsToMany(
            ExtPhoneCallAgentCall::class,
            'ext_phone_call_agent_call_tag_pivot',
            'tag_id',
            'call_id'
        );
    }
}
