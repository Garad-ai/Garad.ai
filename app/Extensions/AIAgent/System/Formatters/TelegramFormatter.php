<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Formatters;

use App\Extensions\AIAgent\System\Formatters\Contracts\FormatterInterface;

/**
 * Converts standard Markdown to Telegram HTML parse mode.
 *
 * Telegram HTML supports: <b>, <i>, <u>, <s>, <code>, <pre>, <a href="...">
 * We convert the most common Markdown patterns to these tags.
 */
class TelegramFormatter implements FormatterInterface
{
    public function format(string $text): string
    {
        // Bold: **text** or __text__ → <b>text</b>
        $text = preg_replace('/\*\*(.+?)\*\*/s', '<b>$1</b>', $text);
        $text = preg_replace('/__(.+?)__/s', '<b>$1</b>', $text);

        // Italic: *text* or _text_ → <i>text</i>
        $text = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/s', '<i>$1</i>', $text);
        $text = preg_replace('/(?<!_)_(?!_)(.+?)(?<!_)_(?!_)/s', '<i>$1</i>', $text);

        // Inline code: `code` → <code>code</code>
        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);

        // Code blocks: ```lang\ncode\n``` → <pre>code</pre>
        $text = preg_replace('/```[a-z]*\n?([\s\S]*?)```/', '<pre>$1</pre>', $text);

        // Strip markdown headers (# ## ###) — Telegram doesn't support them
        $text = preg_replace('/^#{1,6}\s+/m', '', $text);

        // Markdown links [text](url) → <a href="url">text</a>
        $text = preg_replace('/\[(.+?)\]\((https?:\/\/[^\)]+)\)/', '<a href="$2">$1</a>', $text);

        return $text;
    }
}
