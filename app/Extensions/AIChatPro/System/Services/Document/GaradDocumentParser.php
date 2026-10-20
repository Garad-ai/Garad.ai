<?php

namespace App\Extensions\AIChatPro\System\Services\Document;

/**
 * Turns an assistant answer (Markdown with possible backend leftovers) into a
 * structured document model that the Word and PDF renderers share.
 *
 * Block types: heading, paragraph, list, table, quote, code, callout, image.
 * Inline markup (bold / italic / code / links) stays in text and is turned into
 * runs by {@see self::inlineRuns()}.
 */
class GaradDocumentParser
{
    public const SCHEMA_VERSION = 1;

    private const SUMMARY_HEADINGS = ['executive summary', 'summary'];

    private const CALLOUT_HEADINGS = [
        'key finding', 'key findings', 'key takeaways', 'important note', 'recommendation', 'recommendations', 'conclusion', 'conclusions',
    ];

    private const MAX_IMAGE_BYTES = 4000000;

    /**
     * @return array{title:string,subtitle:string,kind:string,summary:array,blocks:array,cover:bool,landscape:bool,date:string,warnings:array}
     */
    public function parse(string $markdown, ?string $titleHint = null, ?string $prompt = null): array
    {
        $warnings = [];
        $blocks = $this->parseBlocks($this->preClean($markdown), $warnings);

        $title = $this->takeTitle($blocks, $titleHint, $prompt);
        $blocks = $this->pruneEmptySections($this->dedupe($blocks));
        $blocks = $this->groupCallouts($blocks);

        $hasTable = (bool) array_filter($blocks, fn ($b) => $b['type'] === 'table');
        $hasHeading = (bool) array_filter($blocks, fn ($b) => $b['type'] === 'heading');
        $paragraphs = count(array_filter($blocks, fn ($b) => $b['type'] === 'paragraph'));
        $chars = mb_strlen($markdown);
        $kind = ($hasTable && ! $hasHeading && $paragraphs <= 3 && $chars < 3500) ? 'data' : 'report';

        [$summary, $blocks] = $this->extractSummary($blocks, $kind);

        if ($title === '') {
            $title = $this->titleFromTables($blocks) ?: $this->titleFromText($blocks) ?: 'Garad AI Document';
        }

        if ($kind === 'data') {
            $blocks = $this->addDataHeading($blocks);
            if ($summary === []) {
                $generated = $this->generatedSummary($blocks);
                $summary = $generated !== '' ? [['type' => 'paragraph', 'text' => $generated]] : [];
            }
        }

        $sections = count(array_filter($blocks, fn ($b) => $b['type'] === 'heading' && $b['level'] <= 2));
        $cover = $kind === 'report' && ($sections >= 4 || $chars > 5000);
        $maxCols = 0;
        foreach ($blocks as $b) {
            if ($b['type'] === 'table') {
                $maxCols = max($maxCols, count($b['headers']));
            }
        }

        return [
            'title' => $title,
            'subtitle' => $this->subtitleFromPrompt($prompt),
            'kind' => $kind,
            'summary' => $summary,
            'blocks' => $blocks,
            'cover' => $cover,
            'landscape' => $maxCols >= 7,
            'date' => now()->format('j F Y'),
            'warnings' => $warnings,
        ];
    }

    public function inferTitle(string $markdown, ?string $titleHint = null, ?string $prompt = null): string
    {
        return $this->parse($markdown, $titleHint, $prompt)['title'];
    }

    /**
     * Parse Markdown into the shared, renderer-neutral block model.
     *
     * @return array{schema_version:int,blocks:array,warnings:array}
     */
    public function parseSemanticBlocks(string $markdown): array
    {
        $warnings = [];
        $blocks = $this->parseBlocks($this->preClean($markdown), $warnings);
        $blocks = $this->groupCallouts($this->pruneEmptySections($this->dedupe($blocks)));

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'blocks' => $blocks,
            'warnings' => $warnings,
        ];
    }

    /** Splits inline Markdown into formatted runs. */
    public function inlineRuns(string $text): array
    {
        $pattern = '/(\*\*[^*\n]+?\*\*|__[^_\n]+?__|`[^`\n]+`|\[[^\]\n]+\]\([^)\s]+\)|(?<![\w*])\*[^*\s][^*\n]*?\*(?![\w*])|(?<![\w_])_[^_\s][^_\n]*?_(?![\w_])|~~[^~\n]+~~)/u';
        $parts = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [$text];
        $runs = [];

        foreach ($parts as $part) {
            $run = ['text' => $part, 'bold' => false, 'italic' => false, 'code' => false, 'href' => null];
            if (preg_match('/^(\*\*|__)(.+)\1$/us', $part, $m)) {
                $run['text'] = $m[2];
                $run['bold'] = true;
            } elseif (preg_match('/^`(.+)`$/us', $part, $m)) {
                $run['text'] = $m[1];
                $run['code'] = true;
            } elseif (preg_match('/^\[([^\]]+)\]\(([^)\s]+)\)$/u', $part, $m)) {
                $run['text'] = $m[1];
                $run['href'] = preg_match('#^(https?://|mailto:)#i', $m[2]) ? $m[2] : null;
            } elseif (preg_match('/^~~(.+)~~$/us', $part, $m)) {
                $run['text'] = $m[1];
            } elseif (preg_match('/^([*_])(.+)\1$/us', $part, $m) && mb_strlen($part) > 2) {
                $run['text'] = $m[2];
                $run['italic'] = true;
            }
            // Bold text wrapping a link or italics keeps its inner text readable.
            $run['text'] = str_replace(['**', '__'], '', $run['text']);
            $runs[] = $run;
        }

        return $runs;
    }

    public function plain(string $text): string
    {
        return trim(implode('', array_map(fn ($r) => $r['text'], $this->inlineRuns($text))));
    }

    /* ----------------------------------------------------------------- */

    private function preClean(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\u{00A0}", "\u{200B}", "\u{FEFF}"], ["\n", "\n", ' ', '', ''], $text);
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text) ?? $text;
        $text = preg_replace('/\[\[GARAD_[A-Z_]+:[^\]]*\]\]/', '', $text) ?? $text;

        // Internal metadata appended by the backend: cut everything from the marker on.
        if (preg_match('/(\.{3}\s*)?:{0,3}meta\b[^\n]*\n?\s*(\{"|\[\{")/i', $text, $m, PREG_OFFSET_CAPTURE)) {
            $text = substr($text, 0, $m[0][1]);
        }
        $text = preg_replace('/^\s*\.{3}\s*:{0,3}meta\s*$/mi', '', $text) ?? $text;

        $lines = [];
        foreach (explode("\n", $text) as $line) {
            $t = trim($line);
            if ($t !== '' && $t[0] === '{' && str_ends_with($t, '}')) {
                $json = json_decode($t, true);
                if (is_array($json) && array_intersect(array_keys($json), ['entities', 'suggestions', 'text', 'type', 'meta', 'context_snippet'])) {
                    continue;
                }
            }
            $lines[] = rtrim($line);
        }

        return trim(implode("\n", $lines));
    }

    private function stripHtml(string $line): string
    {
        return preg_replace('/<\/?[a-zA-Z][^>\n]*>/', '', $line) ?? $line;
    }

    private function parseBlocks(string $text, array &$warnings): array
    {
        $lines = explode("\n", $text);
        $n = count($lines);
        $blocks = [];
        $para = [];

        $flush = function () use (&$para, &$blocks): void {
            if ($para !== []) {
                $t = trim(preg_replace('/\s+/u', ' ', implode(' ', $para)) ?? '');
                if ($t !== '') {
                    $blocks[] = ['type' => 'paragraph', 'text' => $t];
                }
                $para = [];
            }
        };

        for ($i = 0; $i < $n; $i++) {
            $raw = $lines[$i];
            $trim = trim($raw);

            if (preg_match('/^(```|~~~)\s*([\w+#.-]*)/', $trim, $m)) {
                $flush();
                $fence = $m[1];
                $code = [];
                for ($i++; $i < $n && ! str_starts_with(trim($lines[$i]), $fence); $i++) {
                    $code[] = $lines[$i];
                }
                $blocks[] = ['type' => 'code', 'lang' => $m[2], 'text' => rtrim(implode("\n", $code))];
                continue;
            }

            $trim = trim($this->stripHtml($raw));
            if ($trim === '') {
                $flush();
                continue;
            }

            if (preg_match('/^(-{3,}|\*{3,}|_{3,})$/', $trim)) {
                $flush();
                $blocks[] = ['type' => 'horizontal_rule'];
                continue;
            }

            if (preg_match('/^(?:\\\\pagebreak|<!--\s*pagebreak\s*-->)$/i', $trim)) {
                $flush();
                $blocks[] = ['type' => 'page_break'];
                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.+?)\s*#*$/u', $trim, $m)) {
                $flush();
                $text = $this->plain($m[2]);
                if ($text !== '') {
                    $blocks[] = ['type' => 'heading', 'level' => min(3, strlen($m[1])), 'text' => $text];
                }
                continue;
            }

            if ($this->isTableStart($lines, $i)) {
                $flush();
                [$table, $i] = $this->readTable($lines, $i, $warnings);
                $blocks[] = $table;
                continue;
            }

            if (preg_match('/^!\[([^\]]*)\]\(([^)\s]+)(?:\s+"[^"]*")?\)$/u', $trim, $m)) {
                $flush();
                $blocks[] = ['type' => 'image', 'alt' => $this->plain($m[1]), 'src' => $m[2]];
                continue;
            }

            if (preg_match('/^>\s?(.*)$/u', $trim)) {
                $flush();
                $quote = [];
                for (; $i < $n && preg_match('/^>\s?(.*)$/u', trim($this->stripHtml($lines[$i])), $q); $i++) {
                    $quote[] = $q[1];
                }
                $i--;
                $blocks[] = ['type' => 'quote', 'text' => trim(preg_replace('/\s+/u', ' ', implode(' ', $quote)) ?? '')];
                continue;
            }

            if (preg_match('/^(\s*)([-*+]|\d+[.)])\s+(.+)$/u', $this->stripHtml($raw), $m)) {
                $flush();
                $ordered = ctype_digit($m[2][0]);
                $items = [];
                for (; $i < $n; $i++) {
                    $cur = $this->stripHtml($lines[$i]);
                    if (preg_match('/^(\s*)([-*+]|\d+[.)])\s+(.+)$/u', $cur, $im)) {
                        if (ctype_digit($im[2][0]) !== $ordered && strlen($im[1]) < 2) {
                            break;
                        }
                        $items[] = ['text' => trim($im[3]), 'depth' => min(2, intdiv(strlen(str_replace("\t", '  ', $im[1])), 2))];
                    } elseif (trim($cur) !== '' && preg_match('/^\s{2,}\S/u', $cur) && $items !== []) {
                        $items[count($items) - 1]['text'] .= ' ' . trim($cur);
                    } else {
                        break;
                    }
                }
                $i--;
                $blocks[] = ['type' => 'list', 'ordered' => $ordered, 'items' => $items];
                continue;
            }

            $para[] = $trim;
        }

        $flush();

        return $blocks;
    }

    private function isTableStart(array $lines, int $i): bool
    {
        if (! isset($lines[$i + 1]) || ! str_contains($lines[$i], '|')) {
            return false;
        }

        return (bool) preg_match('/^\s*\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?\s*$/', $lines[$i + 1]) && str_contains($lines[$i + 1], '-');
    }

    private function splitRow(string $line): array
    {
        $line = trim($this->stripHtml($line));
        $line = preg_replace('/^\||\|$/', '', $line) ?? $line;
        $cells = preg_split('/(?<!\\\\)\|/', $line) ?: [];

        return array_map(fn ($c) => trim(str_replace('\|', '|', $c)), $cells);
    }

    private function readTable(array $lines, int $i, array &$warnings): array
    {
        $headers = $this->splitRow($lines[$i]);
        $align = array_map(function ($c) {
            $c = trim($c);

            return str_starts_with($c, ':') && str_ends_with($c, ':') ? 'center' : (str_ends_with($c, ':') ? 'right' : 'left');
        }, $this->splitRow($lines[$i + 1]));

        $rows = [];
        for ($j = $i + 2; $j < count($lines) && str_contains($lines[$j], '|') && trim($lines[$j]) !== ''; $j++) {
            $rows[] = $this->splitRow($lines[$j]);
        }

        $cols = max(1, count($headers));
        foreach ($rows as $k => $row) {
            if (count($row) !== $cols) {
                $warnings[] = 'Normalised a malformed table row.';
                $rows[$k] = array_slice(array_pad($row, $cols, ''), 0, $cols);
            }
        }
        $align = array_slice(array_pad($align, $cols, 'left'), 0, $cols);

        return [['type' => 'table', 'headers' => array_map(fn ($h) => $this->plain($h), $headers), 'rows' => $rows, 'align' => $align], $j - 1];
    }

    /** Uses the first H1 (or a clean hint) as the title and removes it from the body. */
    private function takeTitle(array &$blocks, ?string $hint, ?string $prompt): string
    {
        $hint = trim((string) $hint);
        if ($hint !== '' && ! str_contains($hint, '|') && ! preg_match('/^[-:\s|]+$/', $hint)) {
            $hint = $this->cleanTitle($hint);
            if ($hint !== '') {
                if (($blocks[0]['type'] ?? '') === 'heading' && ($blocks[0]['level'] ?? 0) === 1) {
                    array_shift($blocks);
                }

                return $hint;
            }
        }

        $h1 = null;
        foreach ($blocks as $k => $b) {
            if ($b['type'] === 'heading' && $b['level'] === 1) {
                $h1 = $k;
                break;
            }
            if ($b['type'] !== 'paragraph') {
                break;
            }
        }
        if ($h1 !== null) {
            $title = $this->cleanTitle($blocks[$h1]['text']);
            array_splice($blocks, $h1, 1);

            return $title;
        }

        return $this->titleFromPrompt($prompt);
    }

    private function cleanTitle(string $title): string
    {
        $title = $this->plain($title);
        $title = trim(preg_replace('/\s+/u', ' ', $title) ?? $title, " \t.:;,-–—");
        if (mb_strlen($title) > 90) {
            $title = rtrim(mb_substr($title, 0, 90));
            $title = preg_replace('/\s+\S*$/u', '', $title) ?? $title;
        }

        return $title;
    }

    private function titleFromPrompt(?string $prompt): string
    {
        $prompt = trim((string) $prompt);
        if ($prompt === '') {
            return '';
        }

        $re = '/\b(?:prepare|create|make|write|draft|generate|build|produce|give me)\b\s+(?:me\s+)?(?:a|an|the|my)?\s*'
            . '(?:(?:professional|detailed|comprehensive|short|brief|complete|full|simple|formal|executive|long)\s+)*'
            . '((?:business|project|market|financial|technical|research|annual|sales|marketing)\s+)?'
            . '(report|plan|proposal|white ?paper|case study|analysis|strategy|policy|memo|brief)\b'
            . '(?:\s+(?:on|about|for|regarding|of)\s+(.+))?/iu';

        if (! preg_match($re, $prompt, $m)) {
            return '';
        }

        $type = trim(mb_strtolower(trim($m[1] ?? '') . ' ' . $m[2]));
        $label = ucwords($type);
        $subject = trim((string) ($m[3] ?? ''));
        $subject = preg_split('/[.!?\n]|\s+(?:and then|then|in (?:word|pdf|docx)|as (?:a )?(?:word|pdf))\b/iu', $subject)[0] ?? '';
        $subject = trim($subject, " \t,;:-");

        if ($subject === '' || mb_strlen($subject) > 60) {
            return $label;
        }

        $subject = mb_strtoupper(mb_substr($subject, 0, 1)) . mb_substr($subject, 1);

        return $subject . ' - ' . ($type === 'report' ? 'Overview Report' : $label);
    }

    private function subtitleFromPrompt(?string $prompt): string
    {
        return '';
    }

    private function titleFromTables(array $blocks): string
    {
        foreach ($blocks as $b) {
            if ($b['type'] === 'table') {
                $h = array_values(array_filter(array_map(fn ($x) => $x !== '' ? $x : null, $b['headers'])));
                $h = array_slice($h, 0, 3);
                if (count($h) >= 2) {
                    return implode(', ', array_slice($h, 0, -1)) . ' and ' . end($h);
                }

                return $h[0] ?? '';
            }
        }

        return '';
    }

    private function titleFromText(array $blocks): string
    {
        foreach ($blocks as $b) {
            if (in_array($b['type'], ['heading', 'paragraph'], true)) {
                $first = preg_split('/(?<=[.!?])\s/u', $b['text'])[0] ?? $b['text'];

                return $this->cleanTitle($first);
            }
        }

        return '';
    }

    private function dedupe(array $blocks): array
    {
        $out = [];
        $seen = [];
        foreach ($blocks as $b) {
            if ($b['type'] === 'paragraph' && mb_strlen($b['text']) > 60) {
                $key = mb_strtolower($b['text']);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
            }
            $out[] = $b;
        }

        return $out;
    }

    private function pruneEmptySections(array $blocks): array
    {
        $out = [];
        $count = count($blocks);
        for ($i = 0; $i < $count; $i++) {
            $b = $blocks[$i];
            if ($b['type'] === 'heading') {
                $next = $blocks[$i + 1] ?? null;
                if ($next === null || ($next['type'] === 'heading' && $next['level'] <= $b['level'])) {
                    continue;
                }
            }
            if ($b['type'] === 'paragraph' && $b['text'] === '') {
                continue;
            }
            $out[] = $b;
        }

        return $out;
    }

    private function headingKey(string $text): string
    {
        return trim(mb_strtolower(preg_replace('/^[\d.\s)]+|[:\s]+$/u', '', $text) ?? $text));
    }

    /** Callout headings (Conclusion, Recommendation, ...) and labelled quotes become callout blocks. */
    private function groupCallouts(array $blocks): array
    {
        $out = [];
        $count = count($blocks);
        for ($i = 0; $i < $count; $i++) {
            $b = $blocks[$i];

            if ($b['type'] === 'quote' && preg_match('/^(Key finding|Important note|Important|Note|Recommendation|Tip|Warning|Takeaway)s?\s*:\s*(.+)$/iu', $b['text'], $m)) {
                $label = ucfirst(mb_strtolower($m[1])) === 'Note' ? 'Note' : ucfirst(mb_strtolower($m[1]));
                $out[] = ['type' => 'callout', 'label' => $label, 'blocks' => [['type' => 'paragraph', 'text' => $m[2]]]];
                continue;
            }

            if ($b['type'] === 'heading' && in_array($this->headingKey($b['text']), self::CALLOUT_HEADINGS, true)) {
                $inner = [];
                $j = $i + 1;
                while ($j < $count && in_array($blocks[$j]['type'], ['paragraph', 'list'], true)) {
                    $inner[] = $blocks[$j];
                    $j++;
                }
                if ($inner !== []) {
                    $out[] = ['type' => 'callout', 'label' => $b['text'], 'blocks' => $inner];
                    $i = $j - 1;
                    continue;
                }
            }

            $out[] = $b;
        }

        return $out;
    }

    /** @return array{0:array,1:array} summary blocks and the remaining body */
    private function extractSummary(array $blocks, string $kind): array
    {
        // An explicit "Executive Summary" section becomes the summary box.
        foreach ($blocks as $k => $b) {
            if ($b['type'] === 'heading' && in_array($this->headingKey($b['text']), self::SUMMARY_HEADINGS, true)) {
                $inner = [];
                $j = $k + 1;
                while ($j < count($blocks) && in_array($blocks[$j]['type'], ['paragraph', 'list'], true)) {
                    $inner[] = $blocks[$j++];
                }
                if ($inner !== []) {
                    array_splice($blocks, $k, $j - $k);

                    return [$inner, array_values($blocks)];
                }
            }
            if ($b['type'] === 'callout' && in_array($this->headingKey($b['label']), self::SUMMARY_HEADINGS, true)) {
                $inner = $b['blocks'];
                array_splice($blocks, $k, 1);

                return [$inner, array_values($blocks)];
            }
        }

        $lead = [];
        foreach ($blocks as $b) {
            if ($b['type'] !== 'paragraph') {
                break;
            }
            $lead[] = $b;
        }
        $lead = array_slice($lead, 0, 2);
        if ($lead !== [] && mb_strlen($lead[0]['text']) >= 40 && mb_strlen($lead[0]['text']) <= 700) {
            return [$lead, array_slice($blocks, count($lead))];
        }

        if ($kind === 'data') {
            $last = end($blocks);
            if ($last && $last['type'] === 'paragraph' && mb_strlen($last['text']) >= 40 && mb_strlen($last['text']) <= 400) {
                array_pop($blocks);
                $generated = $this->generatedSummary($blocks);
                $summary = [];
                if ($generated !== '') {
                    $summary[] = ['type' => 'paragraph', 'text' => $generated];
                }
                $summary[] = $last;

                return [$summary, $blocks];
            }
        }

        return [[], $blocks];
    }

    private function generatedSummary(array $blocks): string
    {
        foreach ($blocks as $b) {
            if ($b['type'] !== 'table') {
                continue;
            }
            $items = array_values(array_filter(array_map(fn ($r) => $this->plain($r[0] ?? ''), $b['rows'])));
            $cols = array_values(array_filter(array_map('mb_strtolower', array_slice($b['headers'], 1))));
            $join = fn (array $x) => count($x) > 1 ? implode(', ', array_slice($x, 0, -1)) . ' and ' . end($x) : ($x[0] ?? '');
            $across = $cols !== [] ? ' across ' . $join($cols) : '';
            $rowCount = count($b['rows']);

            if ($rowCount === 0) {
                return '';
            }
            if ($rowCount <= 4) {
                return 'This comparison examines ' . $join($items) . $across . '.';
            }

            return sprintf('This document presents %d entries, including %s%s.', $rowCount, $join(array_slice($items, 0, 3)), $across);
        }

        return '';
    }

    private function addDataHeading(array $blocks): array
    {
        foreach ($blocks as $k => $b) {
            if ($b['type'] === 'table') {
                if (($blocks[$k - 1]['type'] ?? '') === 'heading') {
                    return $blocks;
                }
                $first = trim($b['headers'][0] ?? '');
                $label = $first !== '' ? $first . ' ' . (count($b['rows']) >= 2 ? 'Comparison' : 'Overview') : 'Data Overview';
                array_splice($blocks, $k, 0, [['type' => 'heading', 'level' => 2, 'text' => $label]]);

                return $blocks;
            }
        }

        return $blocks;
    }

    /**
     * Loads an embeddable image: data URIs, or files inside this site's public directory.
     * Remote URLs are never fetched.
     *
     * @return array{path:string,width:int,height:int,mime:string}|null  path is a temp file the caller must unlink
     */
    public function loadImage(string $src): ?array
    {
        $binary = null;
        if (preg_match('#^data:image/(png|jpe?g|gif);base64,([A-Za-z0-9+/=\s]+)$#', $src, $m)) {
            $binary = base64_decode($m[2], true) ?: null;
        } elseif (! preg_match('#^[a-z][a-z0-9+.-]*:#i', $src)) {
            $root = realpath(public_path());
            $file = realpath(public_path(ltrim($src, '/')));
            if ($root && $file && str_starts_with($file, $root . DIRECTORY_SEPARATOR) && is_file($file)) {
                $binary = @file_get_contents($file) ?: null;
            }
        }

        if ($binary === null || strlen($binary) > self::MAX_IMAGE_BYTES) {
            return null;
        }

        $info = @getimagesizefromstring($binary);
        if (! $info || ! in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF], true)) {
            return null;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'garad_img_');
        file_put_contents($tmp, $binary);

        return ['path' => $tmp, 'width' => $info[0], 'height' => $info[1], 'mime' => $info['mime']];
    }
}
