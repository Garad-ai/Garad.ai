<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Enums;

enum ActionTypeEnum: string
{
    case SendMessage = 'send_message';
    case AiCall = 'ai_call';
    case GenerateReport = 'generate_report';

    public function label(): string
    {
        return match ($this) {
            self::SendMessage    => 'Send Message',
            self::AiCall         => 'Ask Agent',
            self::GenerateReport => 'Generate Report',
        };
    }
}
