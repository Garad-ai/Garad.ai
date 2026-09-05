<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Readers;

use RuntimeException;
use Smalot\PdfParser\Parser;
use Spatie\PdfToText\Pdf;
use Throwable;

class PdfReader
{
    public function __construct(private readonly ImageReader $imageReader = new ImageReader) {}

    public function read(string $path): string
    {
        if (! is_file($path)) {
            throw new RuntimeException('PDF file not found for reading.');
        }

        if (class_exists(Pdf::class)) {
            try {
                $text = trim((string) Pdf::getText($path));
                if ($text !== '') {
                    return $text;
                }
            } catch (Throwable) {
            }
        }

        $text = trim((string) (new Parser)->parseFile($path)->getText());

        if ($text !== '') {
            return $text;
        }

        $visionText = $this->extractScannedPdfText($path);
        if ($visionText !== '') {
            return $visionText;
        }

        throw new RuntimeException('Unable to extract text from the PDF document.');
    }

    private function extractScannedPdfText(string $path): string
    {
        if (! class_exists('Imagick')) {
            return '';
        }

        try {
            $imagick = new \Imagick;
            $imagick->setResolution(180, 180);
            $imagick->readImage($path . '[0-2]');

            $chunks = [];
            $page = 1;

            foreach ($imagick as $frame) {
                if ($page > 3) {
                    break;
                }

                $frame->setImageFormat('png');
                $tempImage = tempnam(sys_get_temp_dir(), 'aicp_pdf_');
                $pngPath = $tempImage . '.png';
                $frame->writeImage($pngPath);
                @unlink($tempImage);

                try {
                    $text = trim($this->imageReader->read($pngPath, basename($path) . ' page ' . $page));
                    if ($text !== '') {
                        $chunks[] = 'Page ' . $page . "\n" . $text;
                    }
                } finally {
                    @unlink($pngPath);
                }

                $page++;
            }

            $imagick->clear();
            $imagick->destroy();

            return trim(implode("\n\n---\n\n", $chunks));
        } catch (Throwable) {
            return '';
        }
    }
}