<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Formatters;

use App\Extensions\AIAgent\System\Enums\ChannelEnum;
use App\Extensions\AIAgent\System\Formatters\Contracts\FormatterInterface;

class MessageFormatter
{
    /** @var array<string, FormatterInterface> */
    private array $formatters = [];

    public function __construct(
        private readonly TelegramFormatter $telegramFormatter,
    ) {}

    public function registerFormatter(ChannelEnum $channel, FormatterInterface $formatter): void
    {
        $this->formatters[$channel->value] = $formatter;
    }

    /**
     * Format text for the given channel.
     */
    public function format(string $text, ChannelEnum $channel): string
    {
        if (isset($this->formatters[$channel->value])) {
            return $this->formatters[$channel->value]->format($text);
        }

        return match ($channel) {
            ChannelEnum::Telegram => $this->telegramFormatter->format($text),
            default               => strip_tags($text),
        };
    }
}
