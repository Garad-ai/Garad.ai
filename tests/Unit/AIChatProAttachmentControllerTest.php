<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Extensions\AIChatPro\System\Http\Controllers\AIChatProAttachmentController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ZipArchive;

final class AIChatProAttachmentControllerTest extends TestCase
{
    public function test_valid_open_xml_documents_are_accepted_by_package_structure(): void
    {
        foreach ([
            'docx' => [
                'word/document.xml',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ],
            'xlsx' => [
                'xl/workbook.xml',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
            'pptx' => [
                'ppt/presentation.xml',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            ],
        ] as $extension => [$mainPart, $contentType, $expectedMime]) {
            $binary = $this->openXmlDocument($extension, $mainPart, $contentType);
            self::assertSame(
                [$binary, $expectedMime],
                $this->invoke('decodeAndValidate', [
                    'data:application/octet-stream;base64,' . base64_encode($binary),
                    $extension,
                ])
            );
        }
    }

    public function test_attachment_extensions_match_the_document_reader_formats(): void
    {
        $extensions = [
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'txt', 'csv', 'json', 'xml', 'html', 'htm', 'md', 'markdown',
            'png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp',
            'mp3', 'mpeg', 'mpga', 'm4a', 'wav', 'ogg', 'flac', 'aac',
            'mp4', 'mov', 'mkv', 'avi', 'm4v', 'webm',
        ];

        foreach ($extensions as $extension) {
            self::assertSame(
                ['report.' . $extension, $extension],
                $this->invoke('normalizeFilename', ['report.' . $extension])
            );
        }
    }

    public function test_office_extension_with_non_office_package_is_rejected(): void
    {
        self::assertFalse($this->invoke('validOfficeDocument', ['not a DOCX package', 'docx']));
    }

    private function openXmlDocument(string $extension, string $mainPart, string $contentType): string
    {
        $path = tempnam(sys_get_temp_dir(), 'aicp_test_');
        self::assertNotFalse($path);

        $archive = new ZipArchive;
        self::assertTrue($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $archive->addFromString(
            '[Content_Types].xml',
            '<Types><Override PartName="/' . $mainPart . '" ContentType="' . $contentType . '"/></Types>'
        );
        $archive->addFromString($mainPart, '<document><body>Attachment test</body></document>');
        self::assertTrue($archive->close());

        $binary = file_get_contents($path);
        unlink($path);
        self::assertIsString($binary);

        return $binary;
    }

    private function invoke(string $method, array $arguments): mixed
    {
        $reflection = new ReflectionMethod(AIChatProAttachmentController::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs(new AIChatProAttachmentController, $arguments);
    }
}
