<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Services;

use Illuminate\Support\Facades\Auth;
use RuntimeException;
use ZipArchive;

final class GaradSpreadsheetToolService
{
    public function create(array $arguments): string
    {
        if (! Auth::check()) throw new RuntimeException('Please sign in before creating Excel files.');
        if (! class_exists(ZipArchive::class)) throw new RuntimeException('PHP ZipArchive is required to create Excel files.');

        $title = $this->cleanTitle((string) ($arguments['title'] ?? 'Garad AI Spreadsheet'));
        $headers = $this->normalizeHeaders($arguments['headers'] ?? []);
        $rows = $this->normalizeRows($arguments['rows'] ?? [], $headers);

        if ($headers === [] && $rows !== []) {
            $max = max(array_map('count', $rows));
            for ($i = 1; $i <= $max; $i++) $headers[] = 'Column ' . $i;
        }
        if ($headers === [] && $rows === []) {
            throw new RuntimeException('Excel data is empty. Provide headers or rows.');
        }

        $binary = $this->buildXlsx($title, $headers, $rows);
        $dir = public_path('uploads/garad-chatpro/u-' . Auth::id() . '/artifacts');
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException('Unable to create the Excel storage directory.');
        }
        $name = $this->slug($title) . '-' . date('Ymd-His') . '.xlsx';
        $path = $dir . DIRECTORY_SEPARATOR . $name;
        if (file_put_contents($path, $binary) === false) throw new RuntimeException('Unable to save the Excel file.');

        $url = url('/uploads/garad-chatpro/u-' . Auth::id() . '/artifacts/' . rawurlencode($name));
        return "✅ **Excel file created successfully.**\n\n[Download {$title}.xlsx]({$url})";
    }

    private function cleanTitle(string $value): string
    {
        $value = trim(strip_tags($value));
        $value = str_replace(["\r", "\n", "\t", '/', '\\', ':', '*', '?', '"', '<', '>', '|', '[', ']'], ' ', $value);
        while (str_contains($value, '  ')) $value = str_replace('  ', ' ', $value);
        return mb_substr($value !== '' ? $value : 'Garad AI Spreadsheet', 0, 120);
    }

    private function normalizeHeaders(mixed $headers): array
    {
        if (! is_array($headers)) return [];
        return array_values(array_map(fn ($v) => mb_substr(trim((string) $v), 0, 250), $headers));
    }

    private function normalizeRows(mixed $rows, array $headers): array
    {
        if (! is_array($rows)) return [];
        $out = [];
        foreach (array_slice($rows, 0, 5000) as $row) {
            if (is_object($row)) $row = (array) $row;
            if (! is_array($row)) $row = [$row];
            if ($headers !== [] && ! array_is_list($row)) {
                $ordered = [];
                foreach ($headers as $header) $ordered[] = $row[$header] ?? $row[mb_strtolower($header)] ?? '';
                $row = $ordered;
            } else {
                $row = array_values($row);
            }
            $out[] = array_slice($row, 0, 200);
        }
        return $out;
    }

    private function buildXlsx(string $title, array $headers, array $rows): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'garad_xlsx_');
        if ($tmp === false) throw new RuntimeException('Unable to allocate Excel temporary file.');
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Unable to create Excel workbook.');

        $sheetRows = [];
        $r = 1;
        if ($headers !== []) {
            $cells = '';
            foreach ($headers as $i => $value) $cells .= $this->cell($i + 1, $r, $value, 1);
            $sheetRows[] = '<row r="' . $r . '">' . $cells . '</row>';
            $r++;
        }
        foreach ($rows as $row) {
            $cells = '';
            foreach ($row as $i => $value) $cells .= $this->cell($i + 1, $r, $value, 0);
            $sheetRows[] = '<row r="' . $r . '">' . $cells . '</row>';
            $r++;
        }

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="18"/>'
            . '<sheetData>' . implode('', $sheetRows) . '</sheetData>'
            . '<autoFilter ref="A1:' . $this->column(max(1, count($headers))) . '1"/>'
            . '</worksheet>';

        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Aptos"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Aptos"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF5E17FF"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFill="1" applyFont="1"/></cellXfs>'
            . '</styleSheet>';

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . $this->xml(mb_substr($title, 0, 31)) . '" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->addFromString('xl/styles.xml', $styles);
        $zip->close();

        $binary = file_get_contents($tmp);
        @unlink($tmp);
        if ($binary === false) throw new RuntimeException('Unable to render Excel workbook.');
        return $binary;
    }

    private function cell(int $col, int $row, mixed $value, int $style): string
    {
        $ref = $this->column($col) . $row;
        if (is_int($value) || is_float($value)) return '<c r="' . $ref . '" s="' . $style . '"><v>' . $value . '</v></c>';
        if (is_bool($value)) return '<c r="' . $ref . '" s="' . $style . '" t="b"><v>' . ($value ? '1' : '0') . '</v></c>';
        if (is_array($value) || is_object($value)) $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        return '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">' . $this->xml(mb_substr((string) $value, 0, 32000)) . '</t></is></c>';
    }

    private function column(int $number): string
    {
        $number = max(1, $number); $letters = '';
        while ($number > 0) { $number--; $letters = chr(65 + ($number % 26)) . $letters; $number = intdiv($number, 26); }
        return $letters;
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function slug(string $value): string
    {
        $value = mb_strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?: 'garad-ai-spreadsheet';
        return trim($value, '-') ?: 'garad-ai-spreadsheet';
    }
}
