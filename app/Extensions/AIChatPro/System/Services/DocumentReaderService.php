<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Services;

use App\Extensions\AIChatPro\System\Readers\ExcelReader;
use App\Extensions\AIChatPro\System\Readers\ImageReader;
use App\Extensions\AIChatPro\System\Readers\PdfReader;
use App\Extensions\AIChatPro\System\Readers\PowerPointReader;
use App\Extensions\AIChatPro\System\Readers\TextReader;
use App\Extensions\AIChatPro\System\Readers\WordReader;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

class DocumentReaderService
{
    public function __construct(
        private readonly PdfReader $pdfReader = new PdfReader,
        private readonly WordReader $wordReader = new WordReader,
        private readonly ExcelReader $excelReader = new ExcelReader,
        private readonly PowerPointReader $powerPointReader = new PowerPointReader,
        private readonly TextReader $textReader = new TextReader,
        private readonly ImageReader $imageReader = new ImageReader,
    ) {}

    public function readUploadedFile(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: '');

        return $this->buildPayload(
            $file->getRealPath(),
            $extension,
            $file->getMimeType() ?: null,
            $file->getClientOriginalName()
        );
    }

    public function readFile(string $path, string $originalName, ?string $mimeType = null): array
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION) ?: pathinfo($path, PATHINFO_EXTENSION));

        return $this->buildPayload($path, $extension, $mimeType, $originalName);
    }

    private function buildPayload(string $path, string $extension, ?string $mimeType, string $originalName): array
    {
        $extension = $this->normalizeExtension($extension);
        $text = $this->extractText($path, $extension, $originalName);
        $normalized = $this->normalizeText($text);

        return [
            'extension' => $extension,
            'mime_type' => $mimeType,
            'original_name' => $originalName,
            'extracted_text' => $normalized,
            'metadata' => [
                'character_count' => mb_strlen($normalized),
            ],
        ];
    }

    private function extractText(string $path, string $extension, string $originalName): string
    {
        return match ($extension) {
            'pdf' => $this->pdfReader->read($path),
            'doc', 'docx' => $this->wordReader->read($path, $extension),
            'xls', 'xlsx' => $this->excelReader->read($path),
            'ppt', 'pptx' => $this->powerPointReader->read($path, $extension),
            'txt', 'csv', 'json', 'xml', 'html', 'htm', 'md', 'markdown' => $this->textReader->read($path, $extension),
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp' => $this->imageReader->read($path, $originalName),
            default => throw new InvalidArgumentException('Unsupported document format: ' . $extension),
        };
    }

    private function normalizeExtension(string $extension): string
    {
        $extension = strtolower(trim($extension));

        if ($extension === '') {
            throw new InvalidArgumentException('Unable to detect the document extension.');
        }

        return match ($extension) {
            'markdown' => 'md',
            'jpeg' => 'jpg',
            default => $extension,
        };
    }

    private function normalizeText(string $text): string
    {
        $encoding = mb_detect_encoding($text, ['UTF-8', 'UTF-16', 'ASCII', 'ISO-8859-1'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $text = mb_convert_encoding($text, 'UTF-8', $encoding);
        }

        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/\t+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\x{00A0}/u', ' ', $text) ?? $text;
        $text = preg_replace('/[ ]{2,}/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }
}