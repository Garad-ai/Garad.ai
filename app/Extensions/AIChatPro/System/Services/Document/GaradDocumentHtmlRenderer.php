<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Services\Document;

use RuntimeException;

final class GaradDocumentHtmlRenderer
{
    public function render(array $blocks, GaradDocumentParser $parser, string $direction = 'ltr'): string
    {
        $html = '';

        foreach ($blocks as $index => $block) {
            $type = $block['type'] ?? null;
            $html .= match ($type) {
                'heading'         => $this->heading($block, $parser),
                'paragraph'       => $this->paragraph($blocks, $index, $block, $parser),
                'list'            => $this->list($block, $parser),
                'table'           => $this->table($block, $parser, $direction),
                'quote'           => '<blockquote><p>' . $this->inline((string) ($block['text'] ?? ''), $parser) . '</p></blockquote>',
                'callout'         => $this->callout($block, $parser, $direction),
                'code'            => $this->code($block),
                'image'           => $this->image($block, $parser),
                'horizontal_rule' => '<hr>',
                'page_break'      => '<div class="document-page-break"></div>',
                default           => throw new RuntimeException('Unsupported semantic document block: ' . (string) $type),
            };
        }

        return $html;
    }

    private function heading(array $block, GaradDocumentParser $parser): string
    {
        $level = max(1, min(6, (int) ($block['level'] ?? 2)));

        return '<h' . $level . '>' . $this->inline((string) ($block['text'] ?? ''), $parser) . '</h' . $level . '>';
    }

    private function paragraph(array $blocks, int $index, array $block, GaradDocumentParser $parser): string
    {
        $text = (string) ($block['text'] ?? '');
        $nextType = $blocks[$index + 1]['type'] ?? null;
        $keepWithNext = mb_strlen($text) <= 180
            && in_array($nextType, ['list', 'table', 'image', 'code'], true);
        $class = $keepWithNext ? ' class="keep-with-next"' : '';

        return '<p' . $class . '>' . $this->inline($text, $parser) . '</p>';
    }

    private function list(array $block, GaradDocumentParser $parser): string
    {
        $tag = ! empty($block['ordered']) ? 'ol' : 'ul';
        $items = '';

        foreach ($block['items'] ?? [] as $item) {
            $depth = max(0, min(2, (int) ($item['depth'] ?? 0)));
            $style = $depth > 0 ? ' style="margin-left:' . ($depth * 18) . 'px"' : '';
            $items .= '<li' . $style . '>' . $this->inline((string) ($item['text'] ?? ''), $parser) . '</li>';
        }

        return '<' . $tag . '>' . $items . '</' . $tag . '>';
    }

    private function table(array $block, GaradDocumentParser $parser, string $direction): string
    {
        $html = '<table><thead><tr>';
        $alignments = $block['align'] ?? [];

        foreach ($block['headers'] ?? [] as $index => $header) {
            $align = $this->alignment($alignments[$index] ?? 'left', $direction);
            $html .= '<th align="' . $align . '">' . $this->inline((string) $header, $parser) . '</th>';
        }

        $html .= '</tr></thead><tbody>';
        foreach ($block['rows'] ?? [] as $row) {
            $html .= '<tr>';
            foreach ($block['headers'] ?? [] as $index => $_header) {
                $align = $this->alignment($alignments[$index] ?? 'left', $direction);
                $html .= '<td align="' . $align . '">' . $this->inline((string) ($row[$index] ?? ''), $parser) . '</td>';
            }
            $html .= '</tr>';
        }

        return $html . '</tbody></table>';
    }

    private function callout(array $block, GaradDocumentParser $parser, string $direction): string
    {
        $label = htmlspecialchars((string) ($block['label'] ?? ''), ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
        $body = $this->render($block['blocks'] ?? [], $parser, $direction);

        return '<aside class="callout"><strong>' . $label . '</strong>' . $body . '</aside>';
    }

    private function code(array $block): string
    {
        $language = trim((string) ($block['lang'] ?? ''));
        $class = $language === '' ? '' : ' class="language-' . htmlspecialchars($language, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') . '"';

        return '<pre><code' . $class . '>' . htmlspecialchars((string) ($block['text'] ?? ''), ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') . '</code></pre>';
    }

    private function image(array $block, GaradDocumentParser $parser): string
    {
        $image = $parser->loadImage((string) ($block['src'] ?? ''));
        if ($image === null) {
            $alt = htmlspecialchars((string) ($block['alt'] ?? ''), ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');

            return $alt === '' ? '' : '<p class="image-unavailable">' . $alt . '</p>';
        }

        try {
            $binary = file_get_contents($image['path']);
            if ($binary === false) {
                throw new RuntimeException('Unable to read a validated document image.');
            }

            $src = 'data:' . $image['mime'] . ';base64,' . base64_encode($binary);
            $alt = htmlspecialchars((string) ($block['alt'] ?? ''), ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');

            return '<figure><img src="' . $src . '" alt="' . $alt . '">'
                . ($alt !== '' ? '<figcaption>' . $alt . '</figcaption>' : '')
                . '</figure>';
        } finally {
            if (is_file($image['path'])) {
                unlink($image['path']);
            }
        }
    }

    private function inline(string $text, GaradDocumentParser $parser): string
    {
        $html = '';
        foreach ($parser->inlineRuns($text) as $run) {
            $value = htmlspecialchars($run['text'], ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
            if ($run['code']) {
                $value = '<code>' . $value . '</code>';
            }
            if ($run['italic']) {
                $value = '<em>' . $value . '</em>';
            }
            if ($run['bold']) {
                $value = '<strong>' . $value . '</strong>';
            }
            if ($run['href'] !== null) {
                $href = htmlspecialchars($run['href'], ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
                $value = '<a href="' . $href . '">' . $value . '</a>';
            }
            $html .= $value;
        }

        return $html;
    }

    private function alignment(string $alignment, string $direction): string
    {
        if ($direction === 'rtl' && $alignment === 'left') {
            return 'right';
        }

        return in_array($alignment, ['left', 'center', 'right'], true) ? $alignment : 'left';
    }
}
