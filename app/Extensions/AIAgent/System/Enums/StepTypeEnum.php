<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Enums;

enum StepTypeEnum: string
{
    case AiCall = 'ai_call';
    case SendMessage = 'send_message';
    case GenerateReport = 'generate_report';
    case Path = 'path';

    public function label(): string
    {
        return match ($this) {
            self::AiCall         => 'Ask Agent',
            self::SendMessage    => 'Send Message',
            self::GenerateReport => 'Generate Report',
            self::Path           => 'Path',
        };
    }

    public function app(): string
    {
        return match ($this) {
            self::AiCall         => 'AI',
            self::SendMessage    => 'Channels',
            self::GenerateReport => 'Utilities',
            self::Path           => 'Flow Controls',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::AiCall         => 'tabler-brain',
            self::SendMessage    => 'tabler-send',
            self::GenerateReport => 'tabler-report-analytics',
            self::Path           => 'tabler-git-fork',
        };
    }
}
