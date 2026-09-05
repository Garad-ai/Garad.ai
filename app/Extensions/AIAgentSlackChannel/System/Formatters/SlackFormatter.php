<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentSlackChannel\System\Formatters;

use App\Extensions\AIAgent\System\Formatters\Contracts\FormatterInterface;

/**
 * Converts standard Markdown to Slack mrkdwn formatting.
 *
 * Slack supports: *bold*, _italic_, ~strikethrough~, `code`, ```code block```
 * Links use Slack's <url|text> syntax.
 */
class SlackFormatter implements FormatterInterface
{
    public function format(string $text): string
    {
        // Strip HTML tags that may arrive from AI output
        $text = strip_tags($text);

        // Code blocks: strip language hint but keep fenced block (Slack supports it)
        $text = preg_replace('/```[a-z]*\n?/', '```' . "\n", $text);

        // Italic before bold: single *text* → _text_ (double asterisks won't match due to lookahead)
        $text = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/s', '_$1_', $text);

        // Bold: **text** or __text__ → *text*
        $text = preg_replace('/\*\*(.+?)\*\*/s', '*$1*', $text);
        $text = preg_replace('/__(.+?)__/s', '*$1*', $text);

        // Strip markdown headers (# ## ###) — Slack has no heading support
        $text = preg_replace('/^#{1,6}\s+/m', '', $text);

        // Links: [text](url) → <url|text>
        $text = preg_replace('/\[(.+?)\]\((https?:\/\/[^\)]+)\)/', '<$2|$1>', $text);

        return $text;
    }
}
