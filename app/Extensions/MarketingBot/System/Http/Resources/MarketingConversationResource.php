<?php

declare(strict_types=1);

namespace App\Extensions\MarketingBot\System\Http\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use JsonSerializable;

class MarketingConversationResource extends JsonResource
{
    public function toArray(Request $request): array|Arrayable|JsonSerializable
    {
        return [
            'id'                => $this->getAttribute('id'),
            'type'              => $this->getAttribute('type'),
            'color'             => Arr::random(['#879EC4', '#018a1a', '#7f00c8', '#e633ec']),
            'ip_address'        => $this->getAttribute('ip_address'),
            'chatbot_channel'   => $this->getAttribute('type'),
            'conversation_name' => $this->getAttribute('conversation_name'),
            'lastMessage'       => $this->getAttribute('lastMessage') ? MarketingMessageResource::make($this->getAttribute('lastMessage')) : [
                'message' => 'No message',
                'read_at' => now(),
            ],
            'created_at'  => $this->getAttribute('created_at'),
            'histories'   => MarketingMessageResource::collection($this->getAttribute('histories')),
        ];
    }
}
