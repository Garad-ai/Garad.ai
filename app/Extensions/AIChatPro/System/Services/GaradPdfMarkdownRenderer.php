<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Services;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;

final class GaradPdfMarkdownRenderer
{
    private const TABLE_BREAK_TOKEN = 'GARAD_PDF_TABLE_BREAK_7F1C';

    private ?MarkdownConverter $converter = null;

    public function render(string $markdown): string
    {
        if ($this->converter === null) {
            $environment = new Environment([
                'html_input'         => 'strip',
                'allow_unsafe_links' => false,
            ]);
            $environment->addExtension(new CommonMarkCoreExtension);
            $environment->addExtension(new TableExtension);
            $this->converter = new MarkdownConverter($environment);
        }

        $markdown = preg_replace_callback(
            '/^\s*\|.*\|\s*$/m',
            static fn (array $match): string => preg_replace(
                '/<br\s*\/?>/i',
                self::TABLE_BREAK_TOKEN,
                $match[0],
            ) ?? $match[0],
            $markdown,
        ) ?? $markdown;

        return str_replace(
            self::TABLE_BREAK_TOKEN,
            '<br />',
            (string) $this->converter->convert($markdown),
        );
    }
}
