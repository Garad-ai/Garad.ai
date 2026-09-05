<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Readers;

use RuntimeException;

class TextReader
{
    public function read(string $path, string $extension): string
    {
        $content = @file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException('Unable to read text document.');
        }

        $text = match (strtolower($extension)) {
            'csv' => $this->readCsv($content),
            'json' => $this->readJson($content),
            'xml' => $this->readXml($content),
            'html', 'htm' => $this->readHtml($content),
            default => $content,
        };

        $text = trim((string) $text);

        if ($text === '') {
            throw new RuntimeException('No text content could be extracted from the document.');
        }

        return $text;
    }

    private function readCsv(string $content): string
    {
        $rows = preg_split('/\R/u', $content) ?: [];
        $lines = [];

        foreach ($rows as $row) {
            $columns = str_getcsv($row);
            $columns = array_map(static fn ($value) => trim((string) $value), $columns ?: []);
            $columns = array_values(array_filter($columns, static fn ($value) => $value !== ''));

            if ($columns !== []) {
                $lines[] = implode(' | ', $columns);
            }
        }

        return implode("\n", $lines);
    }

    private function readJson(string $content): string
    {
        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $content;
        }

        return (string) json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function readXml(string $content): string
    {
        $text = preg_replace('/\s+/u', ' ', strip_tags($content));

        return (string) $text;
    }

    private function readHtml(string $content): string
    {
        $text = preg_replace('/\s+/u', ' ', strip_tags($content));

        return html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}