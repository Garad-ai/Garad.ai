<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Readers;

use PhpOffice\PhpWord\IOFactory;
use RuntimeException;
use Throwable;
use ZipArchive;

class WordReader
{
    public function read(string $path, string $extension): string
    {
        return match (strtolower($extension)) {
            'docx' => $this->readDocx($path),
            'doc' => $this->readDoc($path),
            default => throw new RuntimeException('Unsupported Word document format.'),
        };
    }

    private function readDocx(string $path): string
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('Unable to open DOCX document.');
        }

        $xml = $zip->getFromName('word/document.xml') ?: '';
        $zip->close();

        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($xml)));

        if ($text === '') {
            throw new RuntimeException('Unable to extract text from the DOCX document.');
        }

        return $text;
    }

    private function readDoc(string $path): string
    {
        if (! class_exists(IOFactory::class)) {
            throw new RuntimeException('DOC reader is not available.');
        }

        try {
            $phpWord = IOFactory::load($path);
        } catch (Throwable $e) {
            throw new RuntimeException('Unable to read DOC document: ' . $e->getMessage(), 0, $e);
        }

        $text = $this->extractTextFromElement($phpWord);

        if ($text === '') {
            throw new RuntimeException('Unable to extract text from the DOC document.');
        }

        return $text;
    }

    private function extractTextFromElement(mixed $element): string
    {
        $parts = [];

        if (is_object($element) && method_exists($element, 'getText')) {
            $text = trim((string) $element->getText());
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        if (is_object($element) && method_exists($element, 'getElements')) {
            foreach ($element->getElements() as $child) {
                $childText = $this->extractTextFromElement($child);
                if ($childText !== '') {
                    $parts[] = $childText;
                }
            }
        }

        if (is_object($element) && method_exists($element, 'getSections')) {
            foreach ($element->getSections() as $section) {
                $sectionText = $this->extractTextFromElement($section);
                if ($sectionText !== '') {
                    $parts[] = $sectionText;
                }
            }
        }

        return trim(implode("\n", $parts));
    }
}