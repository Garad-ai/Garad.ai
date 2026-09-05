<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Services;

use App\Extensions\AIAgent\System\Enums\ChannelEnum;
use App\Extensions\AIAgent\System\Models\AIAgentChannel;

class MagicAiChannelService
{
    public function ensureExists(int $userId): AIAgentChannel
    {
        return AIAgentChannel::query()->firstOrCreate(
            [
                'user_id' => $userId,
                'type'    => ChannelEnum::MagicAi->value,
            ],
            [
                'name'      => 'MagicAI (Web)',
                'is_active' => true,
            ]
        );
    }
}
