<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Services;

final class GaradPdfTheme
{
    private const TOKENS = [
        'page' => [
            'size'              => 'a4',
            'orientation'       => 'portrait',
            'margin_top'        => '24mm',
            'margin_horizontal' => '17mm',
            'margin_bottom'     => '22mm',
        ],
        'canvas' => [
            'horizontal_margin'      => 48,
            'interior_brand_y'       => 23,
            'interior_title_y'       => 36,
            'interior_rule_y'        => 49,
            'footer_bottom_offset'   => 27,
            'footer_rule_offset'     => 10,
            'footer_font_size'       => 8,
            'header_brand_font_size' => 8,
            'header_title_font_size' => 7,
        ],
        'type' => [
            'body'   => '10.5pt',
            'table'  => '9pt',
            'footer' => '8pt',
            'h1'     => '22pt',
            'h2'     => '16pt',
            'h3'     => '12pt',
        ],
        'space' => [
            'paragraph'  => '0 0 8pt',
            'section'    => '16pt 0 8pt',
            'subsection' => '12pt 0 6pt',
            'table'      => '10pt 0 14pt',
        ],
    ];

    private const PALETTE = [
        'primary'         => '#5B5CF6',
        'heading'         => '#172033',
        'body'            => '#263247',
        'muted'           => '#64748B',
        'border'          => '#D9DFEA',
        'table_alternate' => '#F7F8FC',
        'white'            => '#FFFFFF',
    ];

    public function __construct(
        private readonly string $primaryColor = self::PALETTE['primary'],
        private readonly string $style = 'professional',
        private readonly string $pageSize = 'a4',
        private readonly string $orientation = 'portrait',
        private readonly string $direction = 'ltr',
    ) {}

    public function palette(): array
    {
        $primary = $this->safeHexColor($this->primaryColor);

        return array_replace(self::PALETTE, [
            'primary' => $primary,
            'table_header' => $this->mixWithWhite($primary, 0.92),
            'callout' => $this->mixWithWhite($primary, 0.96),
        ]);
    }

    public function pageConfiguration(): array
    {
        return array_replace(self::TOKENS['page'], [
            'size'        => $this->pageSize,
            'orientation' => $this->orientation,
        ]);
    }

    public function canvasConfiguration(): array
    {
        return self::TOKENS['canvas'];
    }

    public function styles(): string
    {
        $colors = $this->palette();
        $tokens = [
            ...$colors,
            'body'               => self::TOKENS['type']['body'],
            'table'              => self::TOKENS['type']['table'],
            'h1'                 => self::TOKENS['type']['h1'],
            'h2'                 => self::TOKENS['type']['h2'],
            'h3'                 => self::TOKENS['type']['h3'],
            'margin_top'         => self::TOKENS['page']['margin_top'],
            'margin_horizontal'  => self::TOKENS['page']['margin_horizontal'],
            'margin_bottom'      => self::TOKENS['page']['margin_bottom'],
            'paragraph_spacing'  => self::TOKENS['space']['paragraph'],
            'section_spacing'    => self::TOKENS['space']['section'],
            'subsection_spacing' => self::TOKENS['space']['subsection'],
            'table_spacing'      => self::TOKENS['space']['table'],
            'direction'          => $this->direction,
            'body_alignment'     => $this->direction === 'rtl' ? 'right' : 'left',
        ];

        $css = <<<'CSS'
@page {
    margin: {margin_top} {margin_horizontal} {margin_bottom};
}
* { box-sizing: border-box; }
body {
    margin: 0;
    font-family: "DejaVu Sans", Arial, sans-serif;
    font-size: {body};
    line-height: 1.48;
    color: {body_color};
    background: {white};
    direction: {direction};
    text-align: {body_alignment};
}
.cover-brand {
    width: 100%;
    border-collapse: collapse;
    margin: 0 0 9px;
}
.cover-brand td { vertical-align: middle; border: 0; padding: 0; }
.brand-logo-cell { width: 55%; }
.brand-meta-cell { width: 45%; text-align: right; color: {muted}; font-size: 8.5pt; }
.brand-logo { max-height: 42px; max-width: 190px; }
.brand-name { font-size: 17pt; font-weight: 700; color: {primary}; }
.accent-line { height: 2px; background: {primary}; margin: 4px 0 21px; }
.title-block { margin: 0 0 18px; }
.title-block h1 {
    font-size: 24pt;
    line-height: 1.16;
    margin: 0 0 7px;
    color: {heading};
}
.subtitle { font-size: 12pt; font-weight: 700; color: {primary}; margin: 0 0 7px; }
.prepared { font-size: 9pt; color: {muted}; }
.prepared-by {
    display: inline-block;
    margin-top: 9px;
    padding: 7px 10px;
    border-left: 3px solid {primary};
    background: {table_alternate};
    color: {heading};
    font-size: 9pt;
}
.prepared-by span { color: {muted}; margin-right: 7px; }
.prepared-by strong { color: {heading}; }
.summary-box, .key-box, .callout {
    page-break-inside: avoid;
    border: 1px solid {border};
    background: {callout};
    padding: 10px 12px;
    margin: 10px 0 14px;
}
.summary-box { border-left: 3px solid {primary}; }
.summary-title, .key-box-title { font-size: 11pt; font-weight: 700; color: {primary}; margin: 0 0 5px; }
.key-box { background: {table_alternate}; }
.key-box ul { margin-bottom: 0; }
.document-body h1, .document-body h2, .document-body h3,
.document-body h4, .document-body h5, .document-body h6 {
    page-break-after: avoid;
    color: {heading};
    font-weight: 700;
}
.document-body h1 {
    font-size: {h1};
    line-height: 1.2;
    margin: {section_spacing};
    padding: 0 0 5px;
    border-bottom: 1px solid {border};
}
.document-body h2 {
    font-size: {h2};
    line-height: 1.25;
    color: {primary};
    margin: {section_spacing};
    padding: 0 0 5px;
    border-bottom: 1px solid {border};
}
.document-body h3 {
    font-size: {h3};
    line-height: 1.3;
    margin: {subsection_spacing};
    page-break-after: avoid;
}
.document-body h4, .document-body h5, .document-body h6 {
    font-size: 10.5pt;
    margin: 10px 0 5px;
}
p {
    margin: {paragraph_spacing};
    page-break-inside: avoid;
}
.document-body .keep-with-next { page-break-after: avoid; }
ul, ol { margin: 5px 0 11px; padding-left: 18px; }
li { padding-left: 2px; margin: 0 0 4px; }
li, blockquote { page-break-inside: avoid; }
strong { color: {heading}; }
em { font-style: italic; }
code {
    font-family: "DejaVu Sans Mono", monospace;
    font-size: 8.5pt;
    background: {table_alternate};
    padding: 1px 3px;
}
pre {
    white-space: pre-wrap;
    overflow-wrap: break-word;
    border: 1px solid {border};
    background: {table_alternate};
    padding: 8px 10px;
    page-break-inside: avoid;
}
pre code { background: transparent; padding: 0; }
blockquote {
    border-left: 3px solid {primary};
    background: {callout};
    margin: 9px 0 12px;
    padding: 8px 11px;
}
hr { border: 0; border-top: 1px solid {border}; margin: 12px 0; }
a { color: {primary}; text-decoration: underline; }
img { max-width: 100%; height: auto; }
table {
    width: 100%;
    border-collapse: collapse;
    table-layout: auto;
    margin: {table_spacing};
    font-size: {table};
    line-height: 1.35;
}
thead { display: table-header-group; }
tr { page-break-inside: avoid; }
th, td {
    border: 1px solid {border};
    padding: 6px 7px;
    text-align: left;
    vertical-align: top;
    white-space: normal;
    overflow-wrap: break-word;
    word-wrap: break-word;
}
th { background: {table_header}; color: {heading}; font-weight: 700; }
tbody tr:nth-child(even) { background: {table_alternate}; }
td[align="center"], th[align="center"] { text-align: center; }
td[align="right"], th[align="right"] { text-align: right; }
.document-body > :first-child { margin-top: 0; }
.document-body figure { margin: 12px 0; text-align: center; page-break-inside: avoid; }
.document-body figcaption { color: {muted}; font-size: 8pt; margin-top: 4px; }
.document-body img { max-width: 100%; height: auto; }
.document-page-break { page-break-before: always; }
[dir="rtl"] .brand-meta-cell { text-align: left; }
[dir="rtl"] table { direction: rtl; }
CSS;

        $tokens['body_color'] = self::PALETTE['body'];

        $replace = [];
        foreach ($tokens as $key => $value) {
            $replace['{' . $key . '}'] = $value;
        }

        $style = match ($this->style) {
            'academic' => '.document-body.style-academic { font-family: "DejaVu Serif", serif; }',
            'formal'   => '.document-body.style-formal { line-height: 1.55; }',
            'minimal'  => '.document-body.style-minimal { line-height: 1.35; }',
            'modern', 'creative' => '.document-body.style-' . $this->style . ' h1, .document-body.style-' . $this->style . ' h2 { border-bottom-width: 2px; }',
            'corporate' => '.document-body.style-corporate h1, .document-body.style-corporate h2 { letter-spacing: 0.2px; }',
            default     => '',
        };

        return strtr($css, $replace) . $style;
    }

    private function safeHexColor(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (! preg_match('/^[0-9a-f]{6}$/i', $hex)) {
            $hex = ltrim(self::PALETTE['primary'], '#');
        }

        return '#' . strtoupper($hex);
    }

    private function mixWithWhite(string $hex, float $ratio): string
    {
        $hex = ltrim($this->safeHexColor($hex), '#');
        $ratio = max(0, min(1, $ratio));
        $channels = str_split($hex, 2);
        $mixed = array_map(
            static fn (string $channel): int => (int) round(
                hexdec($channel) + (255 - hexdec($channel)) * $ratio,
            ),
            $channels,
        );

        return sprintf('#%02X%02X%02X', ...$mixed);
    }
}
