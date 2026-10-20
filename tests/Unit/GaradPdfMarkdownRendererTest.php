<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Extensions\AIChatPro\System\Services\GaradPdfMarkdownRenderer;
use App\Extensions\AIChatPro\System\Services\GaradPdfTheme;
use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use PHPUnit\Framework\TestCase;
use Smalot\PdfParser\Parser;

final class GaradPdfMarkdownRendererTest extends TestCase
{
    public function test_it_renders_document_blocks_as_semantic_html(): void
    {
        $html = (new GaradPdfMarkdownRenderer)->render(<<<'MARKDOWN'
# Introduction

## Capabilities

A paragraph with **bold**, *italic*, `inline code`, and [a link](https://example.com).

- First bullet
- Second bullet

1. First step
2. Second step

> A callout.

---

| Tier | Purpose |
|:-----|--------:|
| Free | **Explore** |
MARKDOWN);

        self::assertStringContainsString('<h1>Introduction</h1>', $html);
        self::assertStringContainsString('<h2>Capabilities</h2>', $html);
        self::assertStringContainsString('<strong>bold</strong>', $html);
        self::assertStringContainsString('<em>italic</em>', $html);
        self::assertStringContainsString('<code>inline code</code>', $html);
        self::assertStringContainsString('<ul>', $html);
        self::assertStringContainsString('<ol>', $html);
        self::assertStringContainsString('<blockquote>', $html);
        self::assertStringContainsString('<hr', $html);
        self::assertStringContainsString('<table>', $html);
        self::assertStringContainsString('align="right"', $html);
        self::assertStringContainsString('<strong>Explore</strong>', $html);
        self::assertStringNotContainsString('| Tier |', $html);
        self::assertStringNotContainsString('---', $html);
    }

    public function test_it_strips_raw_html_and_unsafe_links(): void
    {
        $html = (new GaradPdfMarkdownRenderer)->render(
            '<script>alert(1)</script>' . "\n\n" . '[unsafe](javascript:alert(1))'
        );

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('href="javascript:', $html);
    }

    public function test_it_preserves_multiline_cells_in_markdown_tables(): void
    {
        $html = (new GaradPdfMarkdownRenderer)->render(<<<'MARKDOWN'
| Capability | Description |
| --- | --- |
| Search | Search the web<br>and summarize sources |
MARKDOWN);

        self::assertStringContainsString('Search the web<br />and summarize sources', $html);
        self::assertStringNotContainsString('&lt;br', $html);
    }

    public function test_dompdf_renders_searchable_content_with_dynamic_page_numbers(): void
    {
        $theme = new GaradPdfTheme;
        $renderer = new GaradPdfMarkdownRenderer;
        $lines = ['# Capabilities'];
        for ($section = 1; $section <= 45; $section++) {
            array_push(
                $lines,
                '',
                '## Section ' . $section,
                '',
                'Garad AI supports practical work across research, documents, and team workflows. This repeated paragraph verifies wrapping and pagination.',
            );
        }

        $html = '<!doctype html><html><head><meta charset="utf-8"><style>'
            . $theme->styles()
            . '</style></head><body><div class="document-body">'
            . $renderer->render(implode("\n", $lines))
            . '</div></body></html>';
        $page = $theme->pageConfiguration();
        $layout = $theme->canvasConfiguration();
        $dompdf = new Dompdf;
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($page['size'], $page['orientation']);
        $dompdf->render();
        $dompdf->getCanvas()->page_script(
            static function (int $pageNumber, int $pageCount, Canvas $canvas, FontMetrics $metrics) use ($layout): void {
                $label = 'Page ' . $pageNumber . ' of ' . $pageCount;
                $font = $metrics->getFont('DejaVu Sans');
                if ($pageNumber > 1) {
                    $canvas->text($layout['horizontal_margin'], $layout['interior_brand_y'], 'INTERIOR PAGE HEADER', $font, $layout['header_brand_font_size'], [0.35, 0.36, 0.96]);
                }

                $canvas->text(
                    $canvas->get_width() - $layout['horizontal_margin'] - $metrics->getTextWidth($label, $font, $layout['footer_font_size']),
                    $canvas->get_height() - $layout['footer_bottom_offset'],
                    $label,
                    $font,
                    $layout['footer_font_size'],
                    [0.39, 0.45, 0.55],
                );
            }
        );

        $binary = $dompdf->output();
        $parsed = (new Parser)->parseContent($binary);
        $pages = $parsed->getPages();

        self::assertGreaterThan(1, count($pages));
        self::assertStringContainsString('Capabilities', $pages[0]->getText());
        self::assertStringContainsString('Section 45', $parsed->getText());
        self::assertStringNotContainsString('INTERIOR PAGE HEADER', $pages[0]->getText());
        self::assertStringContainsString('INTERIOR PAGE HEADER', $pages[1]->getText());
        self::assertStringContainsString('Page 1 of ' . count($pages), $pages[0]->getText());
        self::assertStringContainsString('Page 2 of ' . count($pages), $pages[1]->getText());
    }

    public function test_dompdf_repeats_table_headers_when_report_rows_continue_on_another_page(): void
    {
        $rows = [];
        for ($row = 1; $row <= 48; $row++) {
            $rows[] = '| Work item ' . $row . ' | '
                . str_repeat('Verified source detail remains readable in the report. ', 4)
                . ' |';
        }
        $markdown = "# Work Report\n\n| Work item | Verified detail |\n| --- | --- |\n"
            . implode("\n", $rows);
        $theme = new GaradPdfTheme('#40BDFF');
        $html = '<!doctype html><html><head><meta charset="utf-8"><style>'
            . $theme->styles()
            . '</style></head><body><div class="document-body">'
            . (new GaradPdfMarkdownRenderer)->render($markdown)
            . '</div></body></html>';
        $page = $theme->pageConfiguration();
        $dompdf = new Dompdf;
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($page['size'], $page['orientation']);
        $dompdf->render();

        $parsed = (new Parser)->parseContent($dompdf->output());
        $pages = $parsed->getPages();

        self::assertGreaterThan(1, count($pages));
        foreach (array_slice($pages, 1) as $continuedPage) {
            $continuedPageText = preg_replace('/\s+/u', ' ', $continuedPage->getText()) ?? $continuedPage->getText();
            self::assertStringContainsString('Work item', $continuedPageText);
        }
        $allPageText = preg_replace('/\s+/u', ' ', $parsed->getText()) ?? $parsed->getText();
        self::assertStringContainsString('Work item 48', $allPageText);
    }

    public function test_pdf_theme_uses_shared_page_and_brand_tokens(): void
    {
        $theme = new GaradPdfTheme('#123456');
        $brandTintTheme = new GaradPdfTheme('#00AEEF');

        self::assertSame('a4', $theme->pageConfiguration()['size']);
        self::assertSame('portrait', $theme->pageConfiguration()['orientation']);
        self::assertSame('24mm', $theme->pageConfiguration()['margin_top']);
        self::assertSame('17mm', $theme->pageConfiguration()['margin_horizontal']);
        self::assertSame('22mm', $theme->pageConfiguration()['margin_bottom']);
        self::assertSame(48, $theme->canvasConfiguration()['horizontal_margin']);
        self::assertGreaterThanOrEqual(
            $theme->canvasConfiguration()['interior_title_y'] + $theme->canvasConfiguration()['header_title_font_size'] + 4,
            $theme->canvasConfiguration()['interior_rule_y'],
        );
        self::assertSame('#123456', $theme->palette()['primary']);
        self::assertStringContainsString('background: #123456', $theme->styles());
        self::assertStringContainsString('font-size: 10.5pt', $theme->styles());
        self::assertStringContainsString('font-size: 9pt', $theme->styles());
        self::assertStringContainsString('p {', $theme->styles());
        self::assertStringContainsString('page-break-inside: avoid;', $theme->styles());
        self::assertStringContainsString('thead { display: table-header-group; }', $theme->styles());
        self::assertSame('#EBF9FE', $brandTintTheme->palette()['table_header']);
        self::assertSame('#F5FCFE', $brandTintTheme->palette()['callout']);
        self::assertStringNotContainsString('#F3F4FF', $brandTintTheme->styles());
    }
}
