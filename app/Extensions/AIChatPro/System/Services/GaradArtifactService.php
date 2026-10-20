<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Services;

use App\Extensions\AIChatPro\System\Models\AIChatProArtifact;
use App\Extensions\AIChatPro\System\Models\AIChatProDocument;
use App\Extensions\AIChatPro\System\Services\Document\GaradDocumentHtmlRenderer;
use App\Extensions\AIChatPro\System\Services\Document\GaradDocumentParser;
use App\Extensions\AIChatPro\System\Services\Document\GaradDocumentWordRenderer;
use App\Models\Company;
use App\Models\UserOpenaiChat;
use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Canvas;
use Dompdf\FontMetrics;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\IOFactory as PhpWordIOFactory;
use PhpOffice\PhpWord\PhpWord;
use RuntimeException;
use Stringable;
use Throwable;
use Traversable;

class GaradArtifactService
{
    private const MAX_TEXT_LENGTH = 200000;

    private const MAX_XLSX_ROWS = 5000;

    public ?AIChatProArtifact $lastArtifact = null;

    /** Imports a binary produced by another engine (e.g. the Presentations engine) into the artifact store. */
    public function importBinary(string $title, string $fileName, string $type, string $mimeType, string $binary, array $metadata = []): AIChatProArtifact
    {
        return $this->persist($this->title($title), $fileName, $type, $mimeType, $binary, $metadata);
    }

    /** Plain-text artifacts (Markdown / TXT) saved to the same owner-checked store. */
    public function createText(string $title, string $content, string $type): AIChatProArtifact
    {
        $type = $type === 'md' ? 'md' : 'txt';
        $title = $this->title($title);
        $mime = $type === 'md' ? 'text/markdown' : 'text/plain';

        return $this->persist(
            $title,
            $this->fileName($title, $type),
            $type,
            $mime . '; charset=UTF-8',
            $this->content($content),
            ['format' => $type],
        );
    }

    public function createPdf(array $arguments): string
    {
        $arguments = app(GaradDocumentGroundingService::class)->prepare($arguments);

        $title = $this->title($arguments['title'] ?? 'Document');
        $content = $this->content($arguments['content'] ?? '');
        $subtitle = $this->optionalText($arguments['subtitle'] ?? '');
        $preparedBy = $this->optionalText($arguments['prepared_by'] ?? '');
        $preparedFor = $this->optionalText($arguments['prepared_for'] ?? '');
        $summary = $this->optionalText($arguments['summary'] ?? '');
        $highlights = $this->normalizeHighlights($arguments['highlights'] ?? [], '');
        $style = $this->normalizeStyle($arguments['document_style'] ?? 'professional');
        $pageSize = $this->normalizePageSize($arguments['page_size'] ?? 'a4');
        $orientation = $this->normalizeOrientation($arguments['orientation'] ?? 'portrait');
        $direction = $this->normalizeDirection($arguments['direction'] ?? 'ltr');
        $brand = $this->resolveBrand();
        $theme = new GaradPdfTheme($brand['primary_color'], $style, $pageSize, $orientation, $direction);
        $contentForBody = $this->removeDuplicateLeadingTitle($content, $title);
        $fileName = $this->fileName($title, 'pdf');

        $html = $this->buildPdfHtml(
            title: $title,
            content: $contentForBody,
            subtitle: $subtitle,
            preparedBy: $preparedBy,
            preparedFor: $preparedFor,
            summary: $summary,
            summaryTitle: $this->optionalText($arguments['summary_title'] ?? 'Executive Summary'),
            highlights: $highlights,
            style: $style,
            direction: $direction,
            brand: $brand,
            theme: $theme,
        );

        $page = $theme->pageConfiguration();
        $canvasLayout = $theme->canvasConfiguration();
        $palette = $theme->palette();
        $pdf = Pdf::loadHTML($html)->setPaper($page['size'], $page['orientation']);
        $pdf->render();
        $pdf->getDomPDF()->getCanvas()->page_script(
            static function (int $pageNumber, int $pageCount, Canvas $canvas, FontMetrics $fontMetrics) use ($brand, $title, $canvasLayout, $palette, $direction): void {
                $width = $canvas->get_width();
                $font = $fontMetrics->getFont('DejaVu Sans');
                $toRgb = static fn (string $hex): array => [
                    hexdec(substr($hex, 1, 2)) / 255,
                    hexdec(substr($hex, 3, 2)) / 255,
                    hexdec(substr($hex, 5, 2)) / 255,
                ];
                $primary = $toRgb($palette['primary']);
                $muted = $toRgb($palette['muted']);
                $horizontalMargin = $canvasLayout['horizontal_margin'];

                if ($pageNumber > 1 && $brand['name'] !== '') {
                    $brandWidth = $fontMetrics->getTextWidth($brand['name'], $font, $canvasLayout['header_brand_font_size']);
                    $brandX = $direction === 'rtl' ? $width - $horizontalMargin - $brandWidth : $horizontalMargin;
                    $canvas->text($brandX, $canvasLayout['interior_brand_y'], $brand['name'], $font, $canvasLayout['header_brand_font_size'], $primary);
                    $canvas->text($horizontalMargin, $canvasLayout['interior_title_y'], Str::limit($title, 72), $font, $canvasLayout['header_title_font_size'], $muted);
                    $canvas->line($horizontalMargin, $canvasLayout['interior_rule_y'], $width - $horizontalMargin, $canvasLayout['interior_rule_y'], $primary, 0.6);
                }

                $footerY = $canvas->get_height() - $canvasLayout['footer_bottom_offset'];
                $canvas->line($horizontalMargin, $footerY - $canvasLayout['footer_rule_offset'], $width - $horizontalMargin, $footerY - $canvasLayout['footer_rule_offset'], $primary, 0.6);
                $footerText = trim($brand['name'] . ($brand['tagline'] !== '' ? ' | ' . $brand['tagline'] : ''));
                if ($footerText !== '') {
                    $footerTextWidth = $fontMetrics->getTextWidth($footerText, $font, $canvasLayout['footer_font_size']);
                    $footerTextX = $direction === 'rtl' ? $width - $horizontalMargin - $footerTextWidth : $horizontalMargin;
                    $canvas->text($footerTextX, $footerY, $footerText, $font, $canvasLayout['footer_font_size'], $muted);
                }
                $pageText = 'Page ' . $pageNumber . ' of ' . $pageCount;
                $pageTextWidth = $fontMetrics->getTextWidth($pageText, $font, $canvasLayout['footer_font_size']);
                $pageTextX = $direction === 'rtl' ? $horizontalMargin : $width - $horizontalMargin - $pageTextWidth;
                $canvas->text($pageTextX, $footerY, $pageText, $font, $canvasLayout['footer_font_size'], $muted);
            }
        );
        $binary = $pdf->output();

        $artifact = $this->persist(
            $title,
            $fileName,
            'pdf',
            'application/pdf',
            $binary,
            [
                'summary'              => $summary,
                'highlights'           => $highlights,
                'brand'                => $brand['name'],
                'brand_color_source'   => $brand['color_source'],
                'logo_source'          => $brand['logo_source'],
                'logo_name'            => $brand['logo_name'],
                'logo_attachment_id'   => $brand['logo_attachment_id'],
                'grounding'            => $arguments['grounding'] ?? null,
                'quality_review'       => $arguments['quality_review'] ?? null,
                'requested_page_count' => $arguments['page_count'] ?? null,
                'style'                => $style,
                'page_size'            => $pageSize,
                'orientation'          => $orientation,
                'direction'            => $direction,
            ],
        );

        return $this->downloadMessage(
            artifact: $artifact,
            label: 'PDF',
            summary: $summary,
            highlights: $highlights,
            brand: $brand,
        );
    }

    public function createDocx(array $arguments): string
    {
        $arguments = app(GaradDocumentGroundingService::class)->prepare($arguments);

        $title = $this->title($arguments['title'] ?? 'Document');
        $content = $this->content($arguments['content'] ?? '');
        $subtitle = $this->optionalText($arguments['subtitle'] ?? '');
        $preparedBy = $this->optionalText($arguments['prepared_by'] ?? '');
        $preparedFor = $this->optionalText($arguments['prepared_for'] ?? '');
        $summary = isset($arguments['grounding'])
            ? $this->optionalText($arguments['summary'] ?? '')
            : ($this->optionalText($arguments['summary'] ?? '') ?: $this->buildSummary($content));
        $highlights = isset($arguments['grounding'])
            ? []
            : $this->normalizeHighlights($arguments['highlights'] ?? [], $content);
        $style = $this->normalizeStyle($arguments['document_style'] ?? 'professional');
        $pageSize = $this->normalizePageSize($arguments['page_size'] ?? 'a4');
        $orientation = $this->normalizeOrientation($arguments['orientation'] ?? 'portrait');
        $direction = $this->normalizeDirection($arguments['direction'] ?? 'ltr');
        $brand = $this->resolveBrand();
        $contentForBody = $this->removeDuplicateLeadingTitle($content, $title);
        $fileName = $this->fileName($title, 'docx');

        $primary = ltrim($brand['primary_color'], '#');
        $dark = '0F172A';
        $muted = '64748B';
        $light = ltrim($this->mixWithWhite($brand['primary_color'], 0.90), '#');

        $phpWord = new PhpWord;
        $fontFamily = match ($style) {
            'academic' => 'Georgia',
            'formal'   => 'Times New Roman',
            default    => 'Arial',
        };
        $phpWord->setDefaultFontName($fontFamily);
        $phpWord->setDefaultFontSize($style === 'minimal' ? 10 : 10.5);

        $phpWord->addTitleStyle(1, [
            'name'  => $fontFamily,
            'size'  => 24,
            'bold'  => true,
            'color' => $dark,
        ], [
            'spaceAfter' => 180,
            'keepNext'   => true,
            'bidi'       => $direction === 'rtl',
            'alignment'  => $direction === 'rtl' ? 'right' : 'left',
        ]);

        $phpWord->addTitleStyle(2, [
            'name'  => $fontFamily,
            'size'  => 16,
            'bold'  => true,
            'color' => $primary,
        ], [
            'spaceBefore' => 220,
            'spaceAfter'  => 90,
            'keepNext'    => true,
            'bidi'        => $direction === 'rtl',
            'alignment'   => $direction === 'rtl' ? 'right' : 'left',
        ]);

        $phpWord->addTitleStyle(3, [
            'name'  => $fontFamily,
            'size'  => 12,
            'bold'  => true,
            'color' => $dark,
        ], [
            'spaceBefore' => 140,
            'spaceAfter'  => 60,
            'keepNext'    => true,
            'bidi'        => $direction === 'rtl',
            'alignment'   => $direction === 'rtl' ? 'right' : 'left',
        ]);

        $section = $phpWord->addSection([
            'paperSize'    => ucfirst($pageSize),
            'orientation'  => $orientation,
            'marginTop'    => 850,
            'marginBottom' => 850,
            'marginLeft'   => 900,
            'marginRight'  => 900,
        ]);

        $header = $section->addHeader();
        $headerTable = $header->addTable([
            'borderSize' => 0,
            'cellMargin' => 0,
            'bidiVisual' => $direction === 'rtl',
        ]);
        $headerTable->addRow();
        $logoCell = $headerTable->addCell(2600);
        $textCell = $headerTable->addCell(6000);

        if ($brand['logo_path'] && $this->wordLogoSupported($brand['logo_path'])) {
            try {
                $logoCell->addImage($brand['logo_path'], [
                    'width'  => 120,
                    'height' => 44,
                    'ratio'  => true,
                ]);
            } catch (Throwable) {
                $logoCell->addText($brand['name'], [
                    'bold'  => true,
                    'size'  => 13,
                    'color' => $primary,
                ]);
            }
        } else {
            $logoCell->addText($brand['name'], [
                'bold'  => true,
                'size'  => 13,
                'color' => $primary,
            ]);
        }

        if ($brand['tagline'] !== '') {
            $textCell->addText($brand['tagline'], [
                'size'  => 8.5,
                'color' => $muted,
                'rtl'   => $direction === 'rtl',
            ], [
                'alignment' => $direction === 'rtl' ? 'left' : 'right',
                'bidi'      => $direction === 'rtl',
            ]);
        }

        $section->addText(str_repeat('━', 72), [
            'color' => $primary,
            'size'  => 7,
        ], [
            'spaceAfter' => 120,
        ]);

        $section->addTitle($title, 1);

        if ($preparedBy !== '') {
            $section->addText('Prepared by', [
                'size'  => 9,
                'color' => $muted,
            ], [
                'spaceAfter' => 20,
                'alignment'  => $direction === 'rtl' ? 'right' : 'left',
                'bidi'       => $direction === 'rtl',
            ]);
            $section->addText($preparedBy, [
                'size'  => 11,
                'bold'  => true,
                'color' => $primary,
            ], [
                'spaceAfter' => 120,
                'alignment'  => $direction === 'rtl' ? 'right' : 'left',
                'bidi'       => $direction === 'rtl',
            ]);
        }

        if ($subtitle !== '') {
            $section->addText($subtitle, [
                'size'  => 13,
                'bold'  => true,
                'color' => $primary,
            ], [
                'spaceAfter' => 80,
                'alignment'  => $direction === 'rtl' ? 'right' : 'left',
                'bidi'       => $direction === 'rtl',
            ]);
        }

        if ($preparedFor !== '') {
            $section->addText('Prepared for ' . $preparedFor, [
                'size'  => 10.5,
                'bold'  => true,
                'color' => $muted,
            ], [
                'spaceAfter' => 160,
                'alignment'  => $direction === 'rtl' ? 'right' : 'left',
                'bidi'       => $direction === 'rtl',
            ]);
        }

        if ($summary !== '') {
            $summaryTable = $section->addTable([
                'borderSize'  => 6,
                'borderColor' => $primary,
                'cellMargin'  => 150,
                'bidiVisual'  => $direction === 'rtl',
            ]);
            $summaryCell = $summaryTable->addRow()->addCell(null, [
                'bgColor' => $light,
            ]);
            $summaryCell->addText('Executive Summary', [
                'bold'  => true,
                'size'  => 11,
                'color' => $primary,
                'rtl'   => $direction === 'rtl',
            ], [
                'bidi'      => $direction === 'rtl',
                'alignment' => $direction === 'rtl' ? 'right' : 'left',
            ]);
            $summaryCell->addText($this->stripMarkdown($summary), [
                'size'  => 10,
                'color' => $dark,
                'rtl'   => $direction === 'rtl',
            ], [
                'bidi'      => $direction === 'rtl',
                'alignment' => $direction === 'rtl' ? 'right' : 'left',
            ]);
            $section->addTextBreak();
        }

        if ($highlights !== []) {
            $section->addTitle('Key Highlights', 2);
            foreach ($highlights as $highlight) {
                $section->addListItem(
                    $this->stripMarkdown($highlight),
                    0,
                    [
                        'size'  => 10,
                        'color' => $dark,
                    ],
                    'bullet',
                    [
                        'spaceAfter' => 45,
                        'bidi'       => $direction === 'rtl',
                        'alignment'  => $direction === 'rtl' ? 'right' : 'left',
                    ]
                );
            }
        }

        $documentParser = new GaradDocumentParser;
        $document = $documentParser->parseSemanticBlocks($contentForBody);
        (new GaradDocumentWordRenderer)->render(
            $section,
            $document['blocks'],
            $documentParser,
            $primary,
            $dark,
            $light,
            $direction,
        );

        $footer = $section->addFooter();
        $footerText = trim($brand['name'] . ($brand['tagline'] !== '' ? ' | ' . $brand['tagline'] : ''));
        if ($footerText !== '') {
            $footer->addText(
                $footerText,
                [
                    'size'  => 8,
                    'color' => $muted,
                ],
                [
                    'alignment' => 'center',
                    'bidi'      => $direction === 'rtl',
                ]
            );
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'garad_docx_');
        if (! $tempPath) {
            throw new RuntimeException('Unable to create a temporary Word document.');
        }

        try {
            PhpWordIOFactory::createWriter($phpWord, 'Word2007')->save($tempPath);
            $binary = file_get_contents($tempPath);
        } finally {
            @unlink($tempPath);
        }

        if ($binary === false) {
            throw new RuntimeException('Unable to render the Word document.');
        }

        $artifact = $this->persist(
            $title,
            $fileName,
            'docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            $binary,
            [
                'summary'        => $summary,
                'highlights'     => $highlights,
                'brand'          => $brand['name'],
                'grounding'      => $arguments['grounding'] ?? null,
                'quality_review' => $arguments['quality_review'] ?? null,
                'style'          => $style,
                'page_size'      => $pageSize,
                'orientation'    => $orientation,
                'direction'      => $direction,
            ],
        );

        return $this->downloadMessage(
            artifact: $artifact,
            label: 'Word',
            summary: $summary,
            highlights: $highlights,
            brand: $brand,
        );
    }

    public function createXlsx(array $arguments): string
    {
        $title = $this->title($arguments['title'] ?? 'Garad AI Spreadsheet');

        $rawHeaders = $arguments['headers'] ?? [];
        if ($rawHeaders instanceof Traversable) {
            $rawHeaders = iterator_to_array($rawHeaders);
        } elseif (is_object($rawHeaders)) {
            $rawHeaders = (array) $rawHeaders;
        }

        $headers = [];
        foreach ((array) $rawHeaders as $header) {
            $header = trim((string) $header);
            if ($header !== '') {
                $headers[] = $header;
            }
        }

        $rawRows = $arguments['rows'] ?? [];
        if ($rawRows instanceof Traversable) {
            $rawRows = iterator_to_array($rawRows);
        } elseif (is_object($rawRows)) {
            $rawRows = (array) $rawRows;
        }
        if (! is_array($rawRows)) {
            $rawRows = [$rawRows];
        }

        $sourceRows = [];
        $associativeKeys = [];
        $maxListColumns = 0;

        foreach (array_values($rawRows) as $row) {
            if ($row instanceof Traversable) {
                $row = iterator_to_array($row);
            } elseif (is_object($row)) {
                $row = (array) $row;
            } elseif (! is_array($row)) {
                $row = [$row];
            }

            if (array_is_list($row)) {
                $maxListColumns = max($maxListColumns, count($row));
            } else {
                foreach (array_keys($row) as $key) {
                    $key = trim((string) $key);
                    if ($key !== '' && ! in_array($key, $associativeKeys, true)) {
                        $associativeKeys[] = $key;
                    }
                }
            }

            $sourceRows[] = $row;
        }

        if ($headers === [] && $associativeKeys !== []) {
            $headers = $associativeKeys;
        }
        if ($headers === [] && $maxListColumns > 0) {
            for ($column = 1; $column <= $maxListColumns; $column++) {
                $headers[] = 'Column ' . $column;
            }
        }

        $rows = [];
        foreach ($sourceRows as $row) {
            $values = [];
            if (array_is_list($row)) {
                $values = array_values($row);
            } else {
                foreach ($headers as $header) {
                    $values[] = $row[$header] ?? '';
                }
            }

            if ($headers !== []) {
                $values = array_slice(array_pad($values, count($headers), ''), 0, count($headers));
            }

            $rows[] = array_map(static function ($value) {
                if ($value === null) {
                    return '';
                }
                if (is_bool($value)) {
                    return $value ? 'Yes' : 'No';
                }
                if (is_scalar($value)) {
                    return $value;
                }
                if ($value instanceof Stringable) {
                    return (string) $value;
                }

                $encoded = json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

                return $encoded === false ? '' : $encoded;
            }, $values);
        }

        if ($headers === [] && $rows === []) {
            throw new RuntimeException('Excel creation requires spreadsheet data.');
        }

        if (count($rows) > self::MAX_XLSX_ROWS) {
            throw new RuntimeException('Excel creation is limited to 5,000 rows per request.');
        }

        $brand = $this->resolveBrand();
        $primary = strtoupper(ltrim((string) $brand['primary_color'], '#'));
        if (! preg_match('/^[0-9A-F]{6}$/', $primary)) {
            $primary = '5B5CF6';
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheetTitle = preg_replace('#[\\\\/?*\[\]:]#', '', $title) ?: 'Sheet1';
        $sheet->setTitle(mb_substr($sheetTitle, 0, 31));

        $rowNumber = 1;
        if ($headers !== []) {
            foreach ($headers as $columnIndex => $header) {
                $cell = Coordinate::stringFromColumnIndex($columnIndex + 1) . $rowNumber;
                $sheet->setCellValueExplicit($cell, (string) $header, DataType::TYPE_STRING);
            }

            $lastColumn = Coordinate::stringFromColumnIndex(max(1, count($headers)));
            $headerRange = 'A1:' . $lastColumn . '1';
            $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle($headerRange)->getFill()->setFillType('solid')->getStartColor()->setARGB('FF' . $primary);
            $sheet->getStyle($headerRange)->getAlignment()->setWrapText(true);
            $sheet->freezePane('A2');
            $rowNumber++;
        }

        foreach ($rows as $row) {
            foreach ($row as $columnIndex => $value) {
                $cell = Coordinate::stringFromColumnIndex($columnIndex + 1) . $rowNumber;

                // Preserve numbers as numbers so budgets/calculations remain useful.
                if (is_int($value) || is_float($value)) {
                    $sheet->setCellValue($cell, $value);
                } elseif (is_string($value) && preg_match('/^=-?[A-Z0-9_().,:+\-*\/ ]+$/i', $value)) {
                    // Explicit formulas are allowed when the AI/user intentionally supplied one.
                    $sheet->setCellValue($cell, $value);
                } else {
                    $sheet->setCellValueExplicit($cell, (string) $value, DataType::TYPE_STRING);
                }
            }
            $rowNumber++;
        }

        $maxColumns = max(1, count($headers), ...array_map(
            static fn ($row) => is_array($row) ? count($row) : 1,
            $rows ?: [[]]
        ));

        for ($i = 1; $i <= $maxColumns; $i++) {
            $column = Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $lastDataRow = max(1, $rowNumber - 1);
        $lastColumn = Coordinate::stringFromColumnIndex($maxColumns);
        if ($headers !== [] && $lastDataRow >= 1) {
            $sheet->setAutoFilter('A1:' . $lastColumn . $lastDataRow);
        }
        if ($lastDataRow >= 1) {
            $sheet->getStyle('A1:' . $lastColumn . $lastDataRow)->getAlignment()->setVertical('top')->setWrapText(true);
        }

        $fileName = $this->fileName($title, 'xlsx');
        $tempPath = tempnam(sys_get_temp_dir(), 'garad_xlsx_');
        if (! $tempPath) {
            throw new RuntimeException('Unable to create a temporary Excel document.');
        }

        try {
            (new Xlsx($spreadsheet))->save($tempPath);
            $binary = file_get_contents($tempPath);
        } finally {
            $spreadsheet->disconnectWorksheets();
            @unlink($tempPath);
        }

        if ($binary === false) {
            throw new RuntimeException('Unable to render the Excel document.');
        }

        $artifact = $this->persist(
            $title,
            $fileName,
            'xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $binary,
            [
                'brand'   => $brand['name'],
                'columns' => $maxColumns,
                'rows'    => count($rows),
            ],
        );

        return $this->downloadMessage(
            artifact: $artifact,
            label: 'Excel',
            summary: 'I created the spreadsheet and formatted it for easy review and editing.',
            highlights: [],
            brand: $brand,
        );
    }

    private function buildPdfHtml(
        string $title,
        string $content,
        string $subtitle,
        string $preparedBy,
        string $preparedFor,
        string $summary,
        string $summaryTitle,
        array $highlights,
        string $style,
        string $direction,
        array $brand,
        GaradPdfTheme $theme,
    ): string {
        $safeTitle = $this->escape($title);
        $safeSubtitle = $this->escape($subtitle);
        $safePreparedBy = $this->escape($preparedBy);
        $safePreparedFor = $this->escape($preparedFor);
        $safeSummaryTitle = $this->escape($summaryTitle);
        $safeTagline = $this->escape($brand['tagline']);
        $logo = $this->pdfLogoHtml($brand);
        $documentParser = new GaradDocumentParser;
        $document = $documentParser->parseSemanticBlocks($content);
        $renderer = new GaradDocumentHtmlRenderer;
        $summaryHtml = $summary !== ''
            ? $renderer->render($documentParser->parseSemanticBlocks($summary)['blocks'], $documentParser, $direction)
            : '';
        $highlightsHtml = '';

        if ($highlights !== []) {
            $items = array_map(
                fn (string $item) => '<li>' . $renderer->render(
                    $documentParser->parseSemanticBlocks($item)['blocks'],
                    $documentParser,
                    $direction,
                ) . '</li>',
                $highlights
            );
            $highlightsHtml = '<div class="key-box"><div class="key-box-title">Key Highlights</div><ul>' . implode('', $items) . '</ul></div>';
        }

        $contentHtml = $renderer->render($document['blocks'], $documentParser, $direction);
        $brandMeta = $safeTagline;
        $preparedBlock = $safePreparedFor !== ''
            ? '<div class="prepared">Prepared for <strong>' . $safePreparedFor . '</strong></div>'
            : '';
        if ($safePreparedBy !== '') {
            $preparedBlock .= '<div class="prepared-by"><span>Prepared by</span><strong>' . $safePreparedBy . '</strong></div>';
        }
        $subtitleBlock = $safeSubtitle !== ''
            ? '<div class="subtitle">' . $safeSubtitle . '</div>'
            : '';
        $summaryBlock = $summaryHtml !== ''
            ? '<section class="summary-box"><div class="summary-title">' . $safeSummaryTitle . '</div>' . $summaryHtml . '</section>'
            : '';
        $css = $theme->styles();

        return <<<HTML
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>{$css}</style>
</head>
<body dir="{$direction}">
<table class="cover-brand">
    <tr>
        <td class="brand-logo-cell">{$logo}</td>
        <td class="brand-meta-cell">{$brandMeta}</td>
    </tr>
</table>
<div class="accent-line"></div>
<section class="title-block">
    <h1>{$safeTitle}</h1>
    {$subtitleBlock}
    {$preparedBlock}
</section>
{$summaryBlock}
{$highlightsHtml}
<div class="document-body style-{$style}">
    {$contentHtml}
</div>
</body>
</html>
HTML;
    }

    private function resolveBrand(): array
    {
        $prompt = $this->documentSourceText();
        $requestedBrand = $this->requestedBrandName($prompt);
        $explicitlyUnbranded = (bool) preg_match(
            '/\b(?:no\s+branding|unbranded|without\s+(?:any\s+)?branding|no\s+logo|neutral\s+branding)\b/iu',
            $prompt,
        );
        $displayBrandName = $requestedBrand !== null && $requestedBrand === mb_strtolower($requestedBrand)
            ? Str::title($requestedBrand)
            : $requestedBrand;

        $brand = [
            'name'                  => $explicitlyUnbranded ? '' : ($displayBrandName ?? ''),
            'tagline'               => '',
            'primary_color'         => '#334155',
            'logo_path'             => null,
            'logo_binary'           => null,
            'logo_mime'             => null,
            'logo_name'             => null,
            'logo_attachment_id'    => null,
            'logo_source'           => 'none',
            'color_source'          => 'neutral',
            'tone_of_voice'         => null,
            'specific_instructions' => null,
            'selected'              => false,
        ];

        $isPlatformBrand = in_array(mb_strtolower((string) $requestedBrand), ['garad ai', 'garadai'], true);
        if (! $explicitlyUnbranded && $isPlatformBrand) {
            $brand['name'] = 'Garad AI';
            $brand['tagline'] = 'Intelligence for practical work';
            $brand['primary_color'] = '#5B5CF6';
            $brand['logo_path'] = $this->defaultPdfLogoPath();
            $brand['logo_source'] = $brand['logo_path'] ? 'platform' : 'none';
            $brand['color_source'] = 'platform';
            $brand['selected'] = true;
        }

        if (! $explicitlyUnbranded && ! $isPlatformBrand && Auth::check()) {
            $company = null;
            if ($requestedBrand !== null) {
                $companies = Company::query()
                    ->where('user_id', Auth::id())
                    ->get()
                    ->filter(fn (Company $candidate): bool => $this->sameBrandName($requestedBrand, (string) $candidate->name))
                    ->sortBy(fn (Company $candidate): int => abs(
                        mb_strlen($this->normalizeBrandName($requestedBrand))
                        - mb_strlen($this->normalizeBrandName((string) $candidate->name))
                    ));
                $company = $companies->first();
            } else {
                $companyId = (int) request()?->input('chat_brand_voice', 0);
                if ($companyId > 0) {
                    $company = Company::query()
                        ->whereKey($companyId)
                        ->where('user_id', Auth::id())
                        ->first();
                }
            }

            if ($company) {
                $brand['name'] = trim((string) $company->name);
                $brand['tagline'] = trim((string) $company->tagline);
                $brand['primary_color'] = $this->safeHexColor((string) $company->brand_color, $brand['primary_color']);
                $brand['logo_path'] = $this->resolveCompanyLogoPath($company);
                $brand['logo_source'] = $brand['logo_path'] ? 'brand_voice' : 'none';
                $brand['color_source'] = trim((string) $company->brand_color) !== '' ? 'brand_voice' : 'neutral';
                $brand['tone_of_voice'] = trim((string) $company->tone_of_voice) ?: null;
                $brand['specific_instructions'] = trim((string) $company->specific_instructions) ?: null;
                $brand['selected'] = true;
            }
        }

        if (! $explicitlyUnbranded) {
            $uploadedLogo = $this->resolveUploadedRequestLogo($prompt, $requestedBrand);
            if ($uploadedLogo !== null) {
                $brand['logo_path'] = null;
                $brand['logo_binary'] = $uploadedLogo['binary'];
                $brand['logo_mime'] = $uploadedLogo['mime'];
                $brand['logo_name'] = $uploadedLogo['name'];
                $brand['logo_attachment_id'] = $uploadedLogo['attachment_id'];
                $brand['logo_source'] = 'user_upload';
                if ($requestedBrand !== null) {
                    $brand['name'] = $displayBrandName ?? $requestedBrand;
                } elseif ($brand['name'] === '' && $uploadedLogo['brand_name'] !== null) {
                    $brand['name'] = $uploadedLogo['brand_name'];
                }
                $visibleLogoBrandingOnly = (bool) preg_match(
                    '/\bonly\s+(?:its|the|their)\s+(?:visible\s+)?(?:branding|brand(?:\s+colou?rs?)?|colou?rs?)\b/iu',
                    $prompt,
                );
                if ($visibleLogoBrandingOnly) {
                    $brand['tagline'] = '';
                }
                if (($visibleLogoBrandingOnly || $brand['color_source'] === 'neutral') && $uploadedLogo['accent_color'] !== null) {
                    $brand['primary_color'] = $uploadedLogo['accent_color'];
                    $brand['color_source'] = 'uploaded_logo_sample';
                }
            }
        }

        return $brand;
    }

    private function documentSourceText(): string
    {
        foreach (['raw_user_input', 'prompt', 'message', 'input', 'chat_message'] as $key) {
            $value = trim((string) request()?->input($key, ''));
            if ($value !== '') {
                return trim((string) preg_split('/\s*Please follow these response preferences:/iu', $value, 2)[0]);
            }
        }

        return '';
    }

    private function requestedBrandName(string $prompt): ?string
    {
        $patterns = [
            '/\bbrand\s+ga\s+([\p{L}\p{N}&][\p{L}\p{N}&.\'\- ]{0,48}?)(?=\s+(?:adoo|adigoo|waxaan|waxaanan|iyo|oo|using|with|and|while|for|logo|color|colour)\b|[,.!?]|$)/iu',
            '/\bbrand\s+(?:ga|ka|of|for)\s+([\p{L}\p{N}&][\p{L}\p{N}&.\'\- ]{0,60})/iu',
            '/\b(?:use|using|apply|follow)\s+(?:the\s+)?([\p{L}\p{N}&][\p{L}\p{N}&.\'\- ]{0,60}?)\s+(?:brand|branding|brand\s+colors?|brand\s+colours?)\b/iu',
            '/\b([\p{L}\p{N}&][\p{L}\p{N}&.\'\- ]{0,60}?)\s+brand\b/iu',
        ];

        foreach ($patterns as $pattern) {
            if (! preg_match($pattern, $prompt, $matches)) {
                continue;
            }

            $name = trim((string) $matches[1], " \t\n\r\0\x0B'\"`.,;:-");
            $name = preg_split('/\s+(?:waxaan|waxaanan|and|with|logo|color|colour|this|please|using|while|for)\b/iu', $name)[0] ?? $name;
            $name = preg_replace('/^(?:(?:the|an?)\s+)?(?:(?:attached|uploaded|original|actual|official|existing)\s+)+/iu', '', $name) ?? $name;
            $name = trim((string) preg_replace('/[\'’]s$/u', '', $name));

            if ($name !== '' && mb_strlen($name) <= 64) {
                return $name;
            }
        }

        return null;
    }

    private function sameBrandName(string $requested, string $company): bool
    {
        $requested = $this->normalizeBrandName($requested);
        $company = $this->normalizeBrandName($company);

        return $requested !== '' && ($requested === $company || str_contains($requested, $company) || str_contains($company, $requested));
    }

    private function normalizeBrandName(string $value): string
    {
        return mb_strtolower(trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value)));
    }

    private function defaultPdfLogoPath(): ?string
    {
        $matches = glob(public_path('garad-ai-chat-pro/dist/assets/garad-ai-wordmark-*.png'));

        return is_array($matches) && isset($matches[0]) ? $matches[0] : null;
    }

    private function resolveUploadedRequestLogo(string $prompt, ?string $requestedBrand = null): ?array
    {
        if (! Auth::check()) {
            return null;
        }

        $references = request()?->input('images', []);
        $names = request()?->input('image_names', []);
        if (is_string($references)) {
            $references = [$references];
        }
        if (is_string($names)) {
            $names = [$names];
        }
        $references = is_array($references) ? $references : [];

        $logoIntent = (bool) preg_match('/\b(?:logo|brand\s+mark|brand\s+identity)\b|astaanta|sumadda|sumad/iu', $prompt);
        $attachments = app(AIChatProAttachmentService::class);

        foreach (array_values($references) as $index => $reference) {
            if (! is_string($reference) || trim($reference) === '') {
                continue;
            }

            $hintName = is_array($names) && is_string($names[$index] ?? null) ? $names[$index] : '';
            $attachment = $attachments->findOwnedReference($reference, (int) Auth::id());
            $name = $attachment?->original_name ?: $hintName;
            $looksLikeLogo = (bool) preg_match('/(?:^|[._\-\s])(logo|brand|identity|mark)(?:$|[._\-\s])/iu', $name);
            $logoBrand = $this->brandNameFromLogoName($name);
            if (! $attachment || $attachment->upload_status !== 'uploaded') {
                continue;
            }
            if ($requestedBrand !== null
                && (($logoBrand !== null && ! $this->sameBrandName($requestedBrand, $logoBrand))
                    || ($logoBrand === null && count($references) > 1))) {
                continue;
            }
            if (! $looksLikeLogo && ! ($logoIntent && count($references) === 1)) {
                continue;
            }
            if (! in_array(strtolower((string) $attachment->mime_type), ['image/png', 'image/jpeg', 'image/webp'], true)) {
                continue;
            }

            $binary = $attachments->readOwnedReference($reference, (int) Auth::id());
            if (! is_string($binary) || $binary === '') {
                continue;
            }

            $brandName = $this->brandNameFromLogoName($name);

            return [
                'binary'        => $binary,
                'mime'          => (string) $attachment->mime_type,
                'name'          => (string) $name,
                'brand_name'    => $brandName,
                'attachment_id' => (string) $attachment->uuid,
                'accent_color'  => $this->sampleLogoAccentColor($binary),
            ];
        }

        $chatId = (int) request()?->input('chat_id', 0);
        if ($chatId <= 0) {
            return null;
        }

        $chat = UserOpenaiChat::query()
            ->whereKey($chatId)
            ->where('user_id', Auth::id())
            ->first();
        if ($chat === null) {
            return null;
        }

        $chatImages = AIChatProDocument::query()
            ->where('user_openai_chat_id', $chatId)
            ->where('user_id', Auth::id())
            ->where('status', 'ready')
            ->where('mime_type', 'like', 'image/%')
            ->latest('id')
            ->get();
        $logoDocuments = $chatImages->filter(
            fn (AIChatProDocument $document): bool => (bool) preg_match(
                '/(?:^|[._\-\s])(logo|brand|identity|mark)(?:$|[._\-\s])/iu',
                (string) $document->original_name,
            )
        );
        if ($requestedBrand !== null) {
            $logoDocument = $logoDocuments->first(function (AIChatProDocument $document) use ($requestedBrand): bool {
                $logoBrand = $this->brandNameFromLogoName((string) $document->original_name);

                return $logoBrand !== null && $this->sameBrandName($requestedBrand, $logoBrand);
            });
            if ($logoDocument === null && $logoDocuments->count() === 1) {
                $onlyLogo = $logoDocuments->first();
                if ($onlyLogo !== null && $this->brandNameFromLogoName((string) $onlyLogo->original_name) === null) {
                    $logoDocument = $onlyLogo;
                }
            }
        } else {
            $logoDocument = $logoDocuments->first();
            if ($logoDocument === null && $logoIntent && $chatImages->count() === 1) {
                $logoDocument = $chatImages->first();
            }
        }
        if ($logoDocument === null) {
            return null;
        }

        $mime = strtolower(trim((string) $logoDocument->mime_type));
        if (! in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            return null;
        }

        $binary = app(DocumentRepository::class)->readBinaryForOwnedDocument(
            $logoDocument,
            (int) Auth::id(),
        );
        $name = (string) $logoDocument->original_name;
        $brandName = $this->brandNameFromLogoName($name);

        return [
            'binary'        => $binary,
            'mime'          => $mime,
            'name'          => $name,
            'brand_name'    => $brandName,
            'attachment_id' => (string) $logoDocument->id,
            'accent_color'  => $this->sampleLogoAccentColor($binary),
        ];

    }

    private function brandNameFromLogoName(string $name): ?string
    {
        $matches = [];
        preg_match_all(
            '/(?:^|[-_\s])([\p{L}][\p{L}\p{N}&.\'’ -]{0,48}?)\s+(?:logo|brand(?:[\s_-]+mark)?)\b/iu',
            pathinfo($name, PATHINFO_FILENAME),
            $matches,
        );

        $brandNames = $matches[1] ?? [];
        $brandName = trim((string) ($brandNames === [] ? '' : end($brandNames)));

        return $brandName !== '' ? $brandName : null;
    }

    private function sampleLogoAccentColor(string $binary): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $image = @imagecreatefromstring($binary);
        if ($image === false) {
            return null;
        }

        try {
            $width = imagesx($image);
            $height = imagesy($image);
            $step = max(1, (int) ceil(max($width, $height) / 80));
            $colors = [];

            for ($y = 0; $y < $height; $y += $step) {
                for ($x = 0; $x < $width; $x += $step) {
                    $color = imagecolorsforindex($image, imagecolorat($image, $x, $y));
                    if (($color['alpha'] ?? 0) > 80) {
                        continue;
                    }

                    $red = (int) $color['red'];
                    $green = (int) $color['green'];
                    $blue = (int) $color['blue'];
                    $maximum = max($red, $green, $blue);
                    $minimum = min($red, $green, $blue);
                    $saturation = $maximum === 0 ? 0 : ($maximum - $minimum) / $maximum;
                    if ($saturation < 0.18 || $maximum > 248 && $minimum > 238) {
                        continue;
                    }

                    $key = sprintf('%02X%02X%02X', $red >> 3 << 3, $green >> 3 << 3, $blue >> 3 << 3);
                    $colors[$key] = ($colors[$key] ?? 0) + 1;
                }
            }

            if ($colors === []) {
                return null;
            }

            arsort($colors);

            return '#' . array_key_first($colors);
        } finally {
            imagedestroy($image);
        }
    }

    private function resolveCompanyLogoPath(Company $company): ?string
    {
        $raw = trim((string) $company->logo);
        if ($raw === '' || str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
            return null;
        }

        $clean = ltrim(str_replace('\\', '/', $raw), '/');
        $storageRelative = preg_replace('#^storage/#', '', $clean) ?: $clean;

        $candidates = [
            public_path($clean),
            public_path('storage/' . $storageRelative),
            storage_path('app/public/' . $storageRelative),
        ];

        try {
            if (Storage::disk('public')->exists($storageRelative)) {
                $candidates[] = Storage::disk('public')->path($storageRelative);
            }
        } catch (Throwable) {
            // Ignore non-local public disks. Remote logos are intentionally not fetched here.
        }

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function pdfLogoHtml(array $brand): string
    {
        if (is_string($brand['logo_binary'] ?? null) && $brand['logo_binary'] !== '') {
            $mime = in_array($brand['logo_mime'] ?? '', ['image/png', 'image/jpeg', 'image/webp'], true)
                ? $brand['logo_mime']
                : 'image/png';

            return '<img class="brand-logo" src="data:' . $mime . ';base64,' . base64_encode($brand['logo_binary'])
                . '" alt="' . $this->escape($brand['name']) . '">';
        }

        $path = $brand['logo_path'];
        if (! $path || ! is_file($path)) {
            return '<div class="brand-name">' . $this->escape($brand['name']) . '</div>';
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($extension, ['png', 'jpg', 'jpeg', 'gif'], true)) {
            return '<div class="brand-name">' . $this->escape($brand['name']) . '</div>';
        }

        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif'   => 'image/gif',
            default => 'image/png',
        };

        $binary = @file_get_contents($path);
        if ($binary === false) {
            return '<div class="brand-name">' . $this->escape($brand['name']) . '</div>';
        }

        $src = 'data:' . $mime . ';base64,' . base64_encode($binary);

        return '<img class="brand-logo" src="' . $src . '" alt="' . $this->escape($brand['name']) . '">';
    }

    private function wordLogoSupported(string $path): bool
    {
        return in_array(
            strtolower(pathinfo($path, PATHINFO_EXTENSION)),
            ['png', 'jpg', 'jpeg', 'gif', 'bmp'],
            true
        );
    }

    private function persist(
        string $title,
        string $fileName,
        string $type,
        string $mimeType,
        string $binary,
        array $metadata = [],
    ): AIChatProArtifact {
        $userId = Auth::id();
        if (! $userId) {
            throw new RuntimeException('Please sign in before creating downloadable documents.');
        }

        $messageId = request()?->attributes->get('garad_chat_message_id');
        $hasMessageLink = Schema::hasColumn('ai_chat_pro_artifacts', 'user_openai_chat_message_id');
        if ($messageId !== null && ! $hasMessageLink) {
            throw new RuntimeException('The artifact message-link migration must be applied before creating chat artifacts.');
        }

        $chatId = (int) request()?->input('chat_id');
        if ($chatId > 0 && ! UserOpenaiChat::query()->whereKey($chatId)->where('user_id', $userId)->exists()) {
            $chatId = 0;
        }

        $path = sprintf(
            'ai-chat-pro-artifacts/u-%d/%s/%s',
            $userId,
            now()->format('Y/m'),
            Str::uuid() . '.' . $type
        );

        if ($binary === '') {
            throw new RuntimeException('The generated document is empty.');
        }

        $storageQuota = app(AIChatProStorageQuotaService::class);

        return $storageQuota->withUserLock((int) $userId, function () use (
            $storageQuota,
            $userId,
            $binary,
            $title,
            $fileName,
            $type,
            $mimeType,
            $metadata,
            $chatId,
            $hasMessageLink,
            $messageId
        ): AIChatProArtifact {
            $user = Auth::user();
            if (! $user) {
                throw new RuntimeException('Please sign in before creating downloadable documents.');
            }

            $storageQuota->assertCanStore((int) $userId, strlen($binary), $user->activePlan());

            $path = sprintf(
                'ai-chat-pro-artifacts/u-%d/%s/%s',
                $userId,
                now()->format('Y/m'),
                Str::uuid() . '.' . $type
            );
            $disk = Storage::disk('local');
            if (! $disk->put($path, $binary)) {
                throw new RuntimeException('Unable to store the generated document.');
            }

            try {
                if (! $disk->exists($path) || $disk->size($path) !== strlen($binary)) {
                    throw new RuntimeException('The generated document could not be verified in storage.');
                }

                $stream = $disk->readStream($path);
                if (! is_resource($stream)) {
                    throw new RuntimeException('The generated document is not readable from storage.');
                }

                $hashContext = hash_init('sha256');
                $verifiedSize = hash_update_stream($hashContext, $stream);
                fclose($stream);
                if ($verifiedSize !== strlen($binary) || ! hash_equals(hash('sha256', $binary), hash_final($hashContext))) {
                    throw new RuntimeException('The generated document failed storage verification.');
                }

                $attributes = [
                    'user_id'             => $userId,
                    'user_openai_chat_id' => $chatId > 0 ? $chatId : null,
                    'type'                => $type,
                    'title'               => $title,
                    'original_name'       => $fileName,
                    'mime_type'           => $mimeType,
                    'storage_disk'        => 'local',
                    'storage_path'        => $path,
                    'file_size'           => $verifiedSize,
                    'metadata'            => $metadata + ['sha256' => hash('sha256', $binary)],
                ];
                if ($hasMessageLink) {
                    $attributes['user_openai_chat_message_id'] = $messageId;
                }

                $artifact = AIChatProArtifact::create($attributes);

                request()?->attributes->set('garad_created_artifact_id', (string) $artifact->id);

                return $this->lastArtifact = $artifact;
            } catch (Throwable $exception) {
                $disk->delete($path);

                throw $exception;
            }
        });
    }

    private function downloadMessage(
        AIChatProArtifact $artifact,
        string $label,
        string $summary,
        array $highlights,
        array $brand,
    ): string {
        $url = route('dashboard.user.openai.chat.pro.artifacts.download', [
            'artifact' => $artifact->id,
        ]);

        $safeLabel = htmlspecialchars($label, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
        $safeName = htmlspecialchars($artifact->original_name, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
        $safeUrl = htmlspecialchars($url, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
        $safeTitle = htmlspecialchars($artifact->title, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
        $safeMimeType = htmlspecialchars((string) $artifact->mime_type, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
        $safeExtension = htmlspecialchars(strtolower(pathinfo((string) $artifact->original_name, PATHINFO_EXTENSION)), ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
        $safeKind = htmlspecialchars((string) $artifact->type, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');

        $message = "I've prepared **{$artifact->title}** as a professional {$label} document.";

        if ($summary !== '') {
            $message .= "\n\n" . $summary;
        }

        if ($highlights !== []) {
            $message .= "\n\n**What's included:**\n";
            foreach (array_slice($highlights, 0, 5) as $highlight) {
                $message .= '- ' . $this->stripMarkdown($highlight) . "\n";
            }
        }

        if ($brand['selected']) {
            $message .= "\n**Branding:** {$brand['name']} logo, brand color, and visual style were applied where available.";
        }

        $message .= "\n\n";
        $message .= '<div class="garad-tool-result-card garad-artifact-result-card" data-mime-type="' . $safeMimeType . '" data-extension="' . $safeExtension . '" data-kind="' . $safeKind . '" data-size="' . (int) $artifact->file_size . '">';
        $message .= '<strong>' . $safeLabel . ' created</strong>';
        $message .= '<span>' . $safeName . '</span>';
        $message .= '<a href="' . $safeUrl . '" target="_blank" rel="noopener">Open / Download ' . $safeLabel . '</a>';
        $message .= '</div>';

        return $message;
    }

    private function removeDuplicateLeadingTitle(string $content, string $title): string
    {
        $lines = preg_split('/\R/u', $content) ?: [];
        if ($lines === []) {
            return $content;
        }

        $firstIndex = null;
        foreach ($lines as $index => $line) {
            if (trim($line) !== '') {
                $firstIndex = $index;

                break;
            }
        }

        if ($firstIndex === null) {
            return $content;
        }

        $first = trim((string) $lines[$firstIndex]);
        $first = preg_replace('/^#{1,6}\s+/u', '', $first) ?? $first;
        $normalize = static function (string $value): string {
            $value = mb_strtolower($value);
            $value = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $value) ?? $value;

            return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        };

        if ($normalize($first) === $normalize($title)) {
            unset($lines[$firstIndex]);
        }

        return trim(implode("\n", $lines));
    }

    private function normalizeHighlights(mixed $value, string $content): array
    {
        $items = is_array($value) ? $value : [];
        $items = array_values(array_filter(array_map(
            fn ($item) => trim((string) $item),
            $items
        ), fn (string $item) => $item !== ''));

        if ($items !== []) {
            return array_slice($items, 0, 6);
        }

        $fallback = [];
        foreach (preg_split('/\R/u', $content) ?: [] as $line) {
            $line = trim($line);
            if (preg_match('/^[-*]\s+(.+)$/u', $line, $match)) {
                $fallback[] = trim($match[1]);
            } elseif (preg_match('/^#{1,3}\s+(.+)$/u', $line, $match)) {
                $fallback[] = trim($match[1]);
            }

            if (count($fallback) >= 5) {
                break;
            }
        }

        return $fallback;
    }

    private function buildSummary(string $content): string
    {
        $paragraphs = preg_split('/\n\s*\n/u', trim($content)) ?: [];
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim(preg_replace('/^#{1,6}\s+/m', '', $paragraph) ?? $paragraph);
            $paragraph = trim(preg_replace('/^[-*]\s+/m', '', $paragraph) ?? $paragraph);
            if (mb_strlen($paragraph) >= 40) {
                return Str::limit($this->stripMarkdown($paragraph), 420, '…');
            }
        }

        return Str::limit($this->stripMarkdown($content), 420, '…');
    }

    private function stripMarkdown(string $text): string
    {
        $text = preg_replace('/\*\*(.+?)\*\*/u', '$1', $text) ?? $text;
        $text = preg_replace('/__(.+?)__/u', '$1', $text) ?? $text;
        $text = preg_replace('/`(.+?)`/u', '$1', $text) ?? $text;
        $text = preg_replace('/^#{1,6}\s+/u', '', $text) ?? $text;

        return trim($text);
    }

    private function safeHexColor(string $value, string $fallback): string
    {
        $value = strtoupper(trim($value));
        if (preg_match('/^#[0-9A-F]{6}$/', $value)) {
            return $value;
        }

        if (preg_match('/^[0-9A-F]{6}$/', $value)) {
            return '#' . $value;
        }

        return $fallback;
    }

    private function mixWithWhite(string $hex, float $ratio): string
    {
        $hex = ltrim($this->safeHexColor($hex, '#5B5CF6'), '#');
        $ratio = max(0, min(1, $ratio));
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $r = (int) round($r + (255 - $r) * $ratio);
        $g = (int) round($g + (255 - $g) * $ratio);
        $b = (int) round($b + (255 - $b) * $ratio);

        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }

    private function normalizeStyle(mixed $value): string
    {
        $style = strtolower(trim((string) $value));

        return in_array($style, ['professional', 'proposal', 'report', 'brief', 'minimal', 'academic', 'corporate', 'formal', 'modern', 'creative'], true)
            ? $style
            : 'professional';
    }

    private function normalizePageSize(mixed $value): string
    {
        $size = strtolower(trim((string) $value));

        return in_array($size, ['a4', 'letter', 'legal'], true) ? $size : 'a4';
    }

    private function normalizeOrientation(mixed $value): string
    {
        $orientation = strtolower(trim((string) $value));

        return in_array($orientation, ['portrait', 'landscape'], true) ? $orientation : 'portrait';
    }

    private function normalizeDirection(mixed $value): string
    {
        return strtolower(trim((string) $value)) === 'rtl' ? 'rtl' : 'ltr';
    }

    private function title(mixed $value): string
    {
        $title = trim((string) $value);
        if ($title === '') {
            $title = 'Document';
        }

        return mb_substr($title, 0, 180);
    }

    private function optionalText(mixed $value): string
    {
        return mb_substr(trim((string) $value), 0, 1200);
    }

    private function content(mixed $value): string
    {
        $content = trim((string) $value);
        if ($content === '') {
            throw new RuntimeException('Document content cannot be empty.');
        }

        if (mb_strlen($content) > self::MAX_TEXT_LENGTH) {
            throw new RuntimeException('Document content is too large for a single generation request.');
        }

        return $content;
    }

    private function fileName(string $title, string $extension): string
    {
        $slug = Str::slug($title);
        if ($slug === '') {
            $slug = 'garad-ai-document';
        }

        return mb_substr($slug, 0, 100) . '.' . $extension;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
    }
}
