<?php

declare(strict_types=1);

namespace App\Extensions\PhoneCallAgent\System\Http\Resources;

use App\Helpers\Classes\Helper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PhoneCallAgentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'uuid'                   => $this->uuid,
            'title'                  => $this->title,
            'welcome_message'        => $this->welcome_message,
            'instructions'           => $this->instructions,
            'language'               => $this->language,
            'ai_model'               => $this->ai_model,
            'voice_id'               => $this->voice_id,
            'provider'               => $this->provider,
            'max_call_duration'      => $this->max_call_duration,
            'silence_timeout'        => $this->silence_timeout,
            'active'                 => $this->active,
            'is_favorite'            => $this->is_favorite,
            'booking_enabled'        => $this->booking_enabled,
            'booking_provider'       => $this->booking_provider?->value,
            'booking_event_type_id'  => $this->booking_event_type_id,
            'booking_api_key'        => $this->booking_api_key,
            'created_at'             => $this->created_at,
            'today_calls_count'      => Helper::appIsDemo()
                ? (crc32((string) $this->id) % 25) + 1
                : ($this->today_calls_count ?? 0),
        ];
    }
}
