<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentWhatsappChannel\System\Formatters;

use App\Extensions\AIAgent\System\Formatters\Contracts\FormatterInterface;

/**
 * Converts standard Markdown to WhatsApp formatting.
 *
 * WhatsApp supports: *bold*, _italic_, ~strikethrough~, `code`
 * Multi-line code blocks are flattened to inline code since WhatsApp
 * does not support fenced code blocks.
 */
class WhatsappFormatter implements FormatterInterface
{
    public function format(string $text): string
    {
        // Strip HTML tags that may arrive from AI output
        $text = strip_tags($text);

        // Code blocks: ```lang\ncode\n``` → `code` (WhatsApp has no block code)
        $text = preg_replace('/```[a-z]*\n?([\s\S]*?)```/', '`$1`', $text);

        // Bold: **text** or __text__ → *text*
        $text = preg_replace('/\*\*(.+?)\*\*/s', '*$1*', $text);
        $text = preg_replace('/__(.+?)__/s', '*$1*', $text);

        // Italic: *text* → _text_ (convert before next step to avoid double-processing)
        // Only single asterisks not already converted to bold
        $text = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/s', '_$1_', $text);

        // Strip markdown headers (# ## ###)
        $text = preg_replace('/^#{1,6}\s+/m', '', $text);

        // Strip markdown links [text](url) → text (WhatsApp does not render markdown links)
        $text = preg_replace('/\[(.+?)\]\((https?:\/\/[^\)]+)\)/', '$1 ($2)', $text);

        return $text;
    }
}
