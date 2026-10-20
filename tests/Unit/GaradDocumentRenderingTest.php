<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Extensions\AIChatPro\System\Services\Document\GaradDocumentHtmlRenderer;
use App\Extensions\AIChatPro\System\Services\Document\GaradDocumentParser;
use App\Extensions\AIChatPro\System\Services\Document\GaradDocumentWordRenderer;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class GaradDocumentRenderingTest extends TestCase
{
    #[DataProvider('documentExamples')]
    public function test_document_examples_use_the_shared_semantic_pipeline(string $markdown, array $expected): void
    {
        $parser = new GaradDocumentParser;
        $document = $parser->parseSemanticBlocks($markdown);
        $html = (new GaradDocumentHtmlRenderer)->render($document['blocks'], $parser);

        self::assertSame(GaradDocumentParser::SCHEMA_VERSION, $document['schema_version']);
        foreach ($expected as $fragment) {
            self::assertStringContainsString($fragment, $html);
        }
    }

    public function test_word_renderer_preserves_rtl_direction_and_repeating_table_headers(): void
    {
        $markdown = "# تقرير مالي\n\nItems follow:\n\n| المؤشر | القيمة |\n| --- | ---: |\n| النمو | ١٢٪ |";
        $parser = new GaradDocumentParser;
        $document = $parser->parseSemanticBlocks($markdown);
        $word = new PhpWord;
        $section = $word->addSection();
        (new GaradDocumentWordRenderer)->render(
            $section,
            $document['blocks'],
            $parser,
            '123456',
            '0F172A',
            'EEF0FF',
            'rtl',
        );

        $path = tempnam(sys_get_temp_dir(), 'garad_docx_test_');
        self::assertNotFalse($path);

        try {
            IOFactory::createWriter($word, 'Word2007')->save($path);
            $archive = new ZipArchive;
            self::assertSame(true, $archive->open($path));
            $xml = $archive->getFromName('word/document.xml');
            $archive->close();

            self::assertIsString($xml);
            self::assertStringContainsString('bidiVisual', $xml);
            self::assertStringContainsString('tblHeader', $xml);
            self::assertStringContainsString('keepNext', $xml);
            self::assertStringContainsString('تقرير مالي', $xml);
            self::assertStringContainsString('١٢٪', $xml);
        } finally {
            unlink($path);
        }
    }

    public static function documentExamples(): iterable
    {
        yield 'simple report' => ["# Report\n\nA short business summary.\n\nKey actions:\n\n- Review the workflow", ['<h1>Report</h1>', '<p>A short business summary.</p>', '<p class="keep-with-next">Key actions:</p>']];
        yield 'long report' => [self::longReport(), ['<h2>Section 12</h2>', '<p>Evidence supports the recommended actions.</p>']];
        yield 'CV' => ["# Amina Yusuf\n\n## Experience\n\n- Engineer at Example Co\n- Led a delivery team", ['<h2>Experience</h2>', '<ul>', 'Led a delivery team']];
        yield 'business proposal' => ["# Proposal\n\n## Investment\n\n| Item | Cost |\n| --- | ---: |\n| Research | 1250 |", ['<table>', '<th align="left">Item</th>', '<td align="right">1250</td>']];
        yield 'research paper' => ["# Research Paper\n\n## Abstract\n\nThis study reviews adoption.\n\n## Methodology\n\nSurvey analysis.", ['<h2>Abstract</h2>', '<h2>Methodology</h2>']];
        yield 'financial report' => ["# Financial Report\n\n| Quarter | Revenue | Margin |\n| --- | ---: | ---: |\n| Q1 | $100 | 14% |", ['<table>', '$100', '14%']];
        yield 'invoice' => ["# Invoice\n\n| Description | Qty | Amount |\n| --- | ---: | ---: |\n| Service | 2 | $80 |", ['<table>', '<th align="right">Qty</th>', '$80']];
        yield 'certificate' => ["# Certificate\n\nPresented to **Hodan Ali** for completing the program.", ['<strong>Hodan Ali</strong>']];
        yield 'technical manual' => ["# Manual\n\n## Install\n\n```bash\ncomposer install\n```", ['<h2>Install</h2>', '<pre><code class="language-bash">composer install</code></pre>']];
        yield 'book' => ["# Field Guide\n\n## Chapter 1\n\nOpening text.\n\n## Chapter 2\n\nClosing text.", ['<h2>Chapter 1</h2>', '<h2>Chapter 2</h2>']];
        yield 'Somali and French' => ["# Warbixin\n\nAdeeggu wuxuu taageeraa isticmaalayaasha.\n\n## Résumé\n\nLe service est disponible.", ['Adeeggu wuxuu taageeraa', '<h2>Résumé</h2>']];
        yield 'Arabic' => ["# تقرير سنوي\n\n## النتائج\n\nحقق الفريق تقدماً.", ['تقرير سنوي', 'حقق الفريق تقدماً']];
        yield 'wide table' => [self::wideTable(), ['<table>', '<th align="left">Column 10</th>', 'Value 10']];
        yield 'multiple tables' => ["# Comparison\n\n| A | B |\n| --- | --- |\n| one | two |\n\n| C | D |\n| --- | --- |\n| three | four |", ['one', 'three', '<table>']];
        yield 'image block' => ['![One pixel](data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a1WQAAAAASUVORK5CYII=)', ['<figure>', '<img src="data:image/png;base64,', '<figcaption>One pixel</figcaption>']];
        yield 'mixed Markdown' => ["# Mixed\n\n**Bold**, *italic*, `code`, and [link](https://example.com).\n\n> Important note: review the result.\n\n---", ['<strong>Bold</strong>', '<em>italic</em>', '<code>code</code>', 'href="https://example.com"', '<aside class="callout">', '<hr>']];
        yield 'long list' => [self::longList(), ['<li>Item 40 with detail</li>']];
        yield 'branded style input' => ["# Brand Guide\n\n## Colors\n\nPrimary: **#123456**.", ['<h2>Colors</h2>', '<strong>#123456</strong>']];
        yield 'Garad capabilities regression sample' => ["# Garad AI Capabilities\n\n## Overview\n\nGarad AI brings chat, research, and document tools together.\n\n## Capabilities\n\n| Capability | Status |\n| --- | --- |\n| Chat | Available |", ['<h2>Overview</h2>', '<table>', 'Available']];
        yield 'page and section structure' => ["# Procedure\n\nFirst phase.\n\n\\pagebreak\n\n## Second phase\n\nFinal steps.", ['<div class="document-page-break"></div>', '<h2>Second phase</h2>']];
    }

    private static function longReport(): string
    {
        $content = '# Long Report';
        for ($section = 1; $section <= 12; $section++) {
            $content .= "\n\n## Section {$section}\n\nEvidence supports the recommended actions.";
        }

        return $content;
    }

    private static function longList(): string
    {
        $items = [];
        for ($item = 1; $item <= 40; $item++) {
            $items[] = "- Item {$item} with detail";
        }

        return implode("\n", $items);
    }

    private static function wideTable(): string
    {
        $columns = range(1, 10);
        $headers = array_map(static fn (int $column): string => 'Column ' . $column, $columns);
        $values = array_map(static fn (int $column): string => 'Value ' . $column, $columns);

        return '| ' . implode(' | ', $headers) . " |\n|"
            . implode('|', array_fill(0, count($columns), ' --- ')) . "|\n| "
            . implode(' | ', $values) . ' |';
    }
}
