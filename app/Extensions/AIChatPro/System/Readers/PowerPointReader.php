<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Readers;

use DOMDocument;
use DOMXPath;
use RuntimeException;
use ZipArchive;

class PowerPointReader
{
    public function read(string $path, string $extension): string
    {
        return match (strtolower($extension)) {
            'pptx' => $this->readPptx($path),
            'ppt' => $this->readPpt($path),
            default => throw new RuntimeException('Unsupported PowerPoint format: ' . $extension),
        };
    }

    private function readPptx(string $path): string
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Unable to open PPTX file.');
        }

        try {
            $slidePaths = $this->resolveSlidePaths($zip);
            if ($slidePaths === []) {
                throw new RuntimeException('No slides found in the PPTX file.');
            }

            $parts = [];
            foreach ($slidePaths as $index => $slidePath) {
                $xml = $zip->getFromName($slidePath);
                if (! is_string($xml) || trim($xml) === '') {
                    continue;
                }

                $text = $this->extractXmlText($xml);
                if ($text !== '') {
                    $parts[] = sprintf('Slide %d' . "\n" . '%s', $index + 1, $text);
                }
            }

            $joined = trim(implode("\n\n---\n\n", $parts));
            if ($joined === '') {
                throw new RuntimeException('The PPTX file does not contain readable text.');
            }

            return $joined;
        } finally {
            $zip->close();
        }
    }

    private function readPpt(string $path): string
    {
        $binary = @file_get_contents($path);
        if (! is_string($binary) || $binary === '') {
            throw new RuntimeException('Unable to read PPT file.');
        }

        // Legacy PPT is binary. Extract printable runs as a best-effort fallback.
        preg_match_all('/[\x20-\x7E]{4,}/', $binary, $matches);
        $segments = $matches[0] ?? [];
        if ($segments === []) {
            throw new RuntimeException('No readable text found in PPT file.');
        }

        $cleaned = [];
        foreach ($segments as $segment) {
            $line = trim(preg_replace('/\s+/u', ' ', $segment) ?? $segment);
            if ($line !== '' && mb_strlen($line) >= 4) {
                $cleaned[] = $line;
            }
        }

        $cleaned = array_values(array_unique($cleaned));
        if ($cleaned === []) {
            throw new RuntimeException('No readable text found in PPT file.');
        }

        return implode("\n", array_slice($cleaned, 0, 2000));
    }

    private function resolveSlidePaths(ZipArchive $zip): array
    {
        $presentationXml = $zip->getFromName('ppt/presentation.xml');
        $relsXml = $zip->getFromName('ppt/_rels/presentation.xml.rels');

        if (! is_string($presentationXml) || ! is_string($relsXml)) {
            return $this->fallbackSlideOrder($zip);
        }

        $presentation = new DOMDocument;
        $relationships = new DOMDocument;

        if (! @$presentation->loadXML($presentationXml) || ! @$relationships->loadXML($relsXml)) {
            return $this->fallbackSlideOrder($zip);
        }

        $presXpath = new DOMXPath($presentation);
        $presXpath->registerNamespace('p', 'http://schemas.openxmlformats.org/presentationml/2006/main');
        $presXpath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

        $relXpath = new DOMXPath($relationships);
        $relXpath->registerNamespace('rel', 'http://schemas.openxmlformats.org/package/2006/relationships');

        $ridToTarget = [];
        foreach ($relXpath->query('//rel:Relationship') ?: [] as $relationship) {
            $id = $relationship->attributes?->getNamedItem('Id')?->nodeValue;
            $target = $relationship->attributes?->getNamedItem('Target')?->nodeValue;

            if ($id && $target && str_contains($target, 'slides/')) {
                $ridToTarget[$id] = 'ppt/' . ltrim($target, '/');
            }
        }

        $slidePaths = [];
        foreach ($presXpath->query('//p:sldId') ?: [] as $slideIdNode) {
            $rid = $slideIdNode->attributes?->getNamedItem('r:id')?->nodeValue;
            if (! $rid || ! isset($ridToTarget[$rid])) {
                continue;
            }

            $slidePath = $ridToTarget[$rid];
            if ($zip->locateName($slidePath) !== false) {
                $slidePaths[] = $slidePath;
            }
        }

        return $slidePaths === [] ? $this->fallbackSlideOrder($zip) : $slidePaths;
    }

    private function fallbackSlideOrder(ZipArchive $zip): array
    {
        $slides = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (! is_string($name)) {
                continue;
            }

            if (preg_match('#^ppt/slides/slide(\d+)\.xml$#', $name, $matches) === 1) {
                $slides[(int) $matches[1]] = $name;
            }
        }

        ksort($slides);

        return array_values($slides);
    }

    private function extractXmlText(string $xml): string
    {
        $dom = new DOMDocument;
        if (! @$dom->loadXML($xml)) {
            return '';
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');

        $chunks = [];
        foreach ($xpath->query('//a:t') ?: [] as $node) {
            $value = trim((string) $node->nodeValue);
            if ($value !== '') {
                $chunks[] = $value;
            }
        }

        return trim(implode("\n", $chunks));
    }
}
