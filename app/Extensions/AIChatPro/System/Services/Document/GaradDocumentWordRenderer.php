<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Services\Document;

use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\TextRun;
use RuntimeException;

final class GaradDocumentWordRenderer
{
    public function render(
        Section $section,
        array $blocks,
        GaradDocumentParser $parser,
        string $primary,
        string $dark,
        string $light,
        string $direction = 'ltr',
    ): void {
        $rtl = $direction === 'rtl';
        foreach ($blocks as $index => $block) {
            switch ($block['type'] ?? null) {
                case 'heading':
                    $section->addTitle(
                        $parser->plain((string) ($block['text'] ?? '')),
                        max(1, min(3, (int) ($block['level'] ?? 2))),
                    );

                    break;

                case 'paragraph':
                    $nextType = $blocks[$index + 1]['type'] ?? null;
                    $keepWithNext = mb_strlen((string) ($block['text'] ?? '')) <= 180
                        && in_array($nextType, ['list', 'table', 'image', 'code'], true);
                    $this->paragraph($section, (string) ($block['text'] ?? ''), $parser, $dark, $rtl, $keepWithNext);

                    break;

                case 'list':
                    $this->list($section, $block, $parser, $dark, $primary, $rtl);

                    break;

                case 'table':
                    $this->table($section, $block, $parser, $dark, $primary, $rtl);

                    break;

                case 'quote':
                    $this->callout($section, '', [['type' => 'paragraph', 'text' => $block['text'] ?? '']], $parser, $dark, $primary, $light, $rtl);

                    break;

                case 'callout':
                    $this->callout(
                        $section,
                        (string) ($block['label'] ?? ''),
                        $block['blocks'] ?? [],
                        $parser,
                        $dark,
                        $primary,
                        $light,
                        $rtl,
                    );

                    break;

                case 'code':
                    $this->code($section, (string) ($block['text'] ?? ''), $dark, $light, $rtl);

                    break;

                case 'image':
                    $this->image($section, $block, $parser, $dark);

                    break;

                case 'horizontal_rule':
                    $section->addTextBreak();
                    $section->addText(str_repeat('─', 56), ['color' => $primary, 'size' => 6]);

                    break;

                case 'page_break':
                    $section->addPageBreak();

                    break;

                default:
                    throw new RuntimeException('Unsupported semantic document block: ' . (string) ($block['type'] ?? 'unknown'));
            }
        }
    }

    private function paragraph(
        AbstractContainer $container,
        string $text,
        GaradDocumentParser $parser,
        string $color,
        bool $rtl,
        bool $keepNext = false,
    ): void {
        $run = $container->addTextRun([
            'spaceAfter' => 85,
            'keepLines'  => true,
            'keepNext'   => $keepNext,
            'bidi'       => $rtl,
            'alignment'  => $rtl ? 'right' : 'left',
        ]);
        $this->inline($run, $text, $parser, ['color' => $color], $rtl);
    }

    private function list(Section $section, array $block, GaradDocumentParser $parser, string $dark, string $primary, bool $rtl): void
    {
        foreach ($block['items'] ?? [] as $index => $item) {
            $prefix = ! empty($block['ordered']) ? ($index + 1) . '. ' : ($rtl ? ' •' : '• ');
            $run = $section->addTextRun([
                'indentation' => ['left' => min(720, max(0, (int) ($item['depth'] ?? 0)) * 240)],
                'spaceAfter'  => 45,
                'keepLines'   => true,
                'bidi'        => $rtl,
                'alignment'   => $rtl ? 'right' : 'left',
            ]);
            $run->addText($prefix, ['bold' => ! empty($block['ordered']), 'color' => $primary]);
            $this->inline($run, (string) ($item['text'] ?? ''), $parser, ['color' => $dark], $rtl);
        }
    }

    private function table(Section $section, array $block, GaradDocumentParser $parser, string $dark, string $primary, bool $rtl): void
    {
        $headers = $block['headers'] ?? [];
        if ($headers === []) {
            return;
        }

        $columnWidth = (int) floor(9000 / count($headers));
        $table = $section->addTable([
            'borderSize'  => 5,
            'borderColor' => 'D9DFEA',
            'cellMargin'  => 90,
            'width'       => 100 * 50,
            'unit'        => 'pct',
            'bidiVisual'  => $rtl,
        ]);
        $table->addRow(null, ['tblHeader' => true, 'cantSplit' => true]);

        foreach ($headers as $index => $header) {
            $cell = $table->addCell($columnWidth, ['bgColor' => 'EEF0FF', 'valign' => 'center']);
            $run = $cell->addTextRun([
                'spaceAfter' => 0,
                'keepLines'  => true,
                'bidi'       => $rtl,
                'alignment'  => $rtl ? 'right' : 'left',
            ]);
            $this->inline($run, (string) $header, $parser, ['bold' => true, 'color' => $primary], $rtl);
        }

        foreach ($block['rows'] ?? [] as $rowIndex => $row) {
            $table->addRow(null, ['cantSplit' => true]);
            foreach ($headers as $index => $_header) {
                $align = match ($block['align'][$index] ?? 'left') {
                    'center' => 'center',
                    'right'  => 'right',
                    default  => $rtl ? 'right' : 'left',
                };
                $cell = $table->addCell($columnWidth, [
                    'bgColor' => $rowIndex % 2 === 1 ? 'F7F8FC' : 'FFFFFF',
                    'valign'  => 'top',
                ]);
                $run = $cell->addTextRun([
                    'alignment'  => $align,
                    'spaceAfter' => 0,
                    'keepLines'  => true,
                    'bidi'       => $rtl,
                ]);
                $this->inline($run, (string) ($row[$index] ?? ''), $parser, ['color' => $dark], $rtl);
            }
        }
        $section->addTextBreak();
    }

    private function callout(
        Section $section,
        string $label,
        array $blocks,
        GaradDocumentParser $parser,
        string $dark,
        string $primary,
        string $light,
        bool $rtl,
    ): void {
        $table = $section->addTable([
            'borderSize'  => 5,
            'borderColor' => $primary,
            'cellMargin'  => 120,
            'bidiVisual'  => $rtl,
        ]);
        $cell = $table->addRow()->addCell(null, ['bgColor' => $light]);
        if ($label !== '') {
            $cell->addText($parser->plain($label), ['bold' => true, 'color' => $primary, 'size' => 10, 'rtl' => $rtl]);
        }
        foreach ($blocks as $block) {
            if (($block['type'] ?? null) === 'paragraph') {
                $this->paragraph($cell, (string) ($block['text'] ?? ''), $parser, $dark, $rtl);
            } elseif (($block['type'] ?? null) === 'list') {
                foreach ($block['items'] ?? [] as $index => $item) {
                    $run = $cell->addTextRun([
                        'spaceAfter' => 30,
                        'keepLines'  => true,
                        'bidi'       => $rtl,
                        'alignment'  => $rtl ? 'right' : 'left',
                    ]);
                    $run->addText(! empty($block['ordered']) ? ($index + 1) . '. ' : '• ', ['color' => $primary]);
                    $this->inline($run, (string) ($item['text'] ?? ''), $parser, ['color' => $dark], $rtl);
                }
            } else {
                throw new RuntimeException('Unsupported block inside a Word callout.');
            }
        }
        $section->addTextBreak();
    }

    private function code(Section $section, string $text, string $dark, string $light, bool $rtl): void
    {
        $run = $section->addTextRun([
            'spaceAfter'  => 100,
            'keepLines'   => true,
            'indentation' => ['left' => 140, 'right' => 140],
            'bidi'        => $rtl,
            'alignment'   => $rtl ? 'right' : 'left',
        ]);
        $run->addText($text, ['name' => 'Consolas', 'size' => 9, 'color' => $dark, 'bgColor' => $light]);
    }

    private function image(Section $section, array $block, GaradDocumentParser $parser, string $dark): void
    {
        $image = $parser->loadImage((string) ($block['src'] ?? ''));
        if ($image === null) {
            $alt = trim((string) ($block['alt'] ?? ''));
            if ($alt !== '') {
                $section->addText('[Image unavailable: ' . $alt . ']', ['italic' => true, 'color' => $dark]);
            }

            return;
        }

        try {
            $maxWidth = 500.0;
            $maxHeight = 650.0;
            $scale = min($maxWidth / $image['width'], $maxHeight / $image['height'], 1.0);
            $section->addImage($image['path'], [
                'width'     => $image['width'] * $scale,
                'height'    => $image['height'] * $scale,
                'alignment' => 'center',
            ]);
            $alt = trim((string) ($block['alt'] ?? ''));
            if ($alt !== '') {
                $section->addText($alt, ['size' => 8, 'italic' => true, 'color' => '64748B'], ['alignment' => 'center']);
            }
        } finally {
            if (is_file($image['path'])) {
                unlink($image['path']);
            }
        }
    }

    private function inline(TextRun $run, string $text, GaradDocumentParser $parser, array $baseStyle, bool $rtl): void
    {
        foreach ($parser->inlineRuns($text) as $part) {
            $style = ['rtl' => $rtl] + $baseStyle;
            if ($part['bold']) {
                $style['bold'] = true;
            }
            if ($part['italic']) {
                $style['italic'] = true;
            }
            if ($part['code']) {
                $style['name'] = 'Consolas';
                $style['size'] = 9;
                $style['bgColor'] = 'F7F8FC';
            }

            if ($part['href'] !== null) {
                $run->addLink($part['href'], $part['text'], $style);
            } else {
                $run->addText($part['text'], $style);
            }
        }
    }
}
