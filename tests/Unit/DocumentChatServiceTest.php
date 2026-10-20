<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Extensions\AIChatPro\System\Services\DocumentChatService;
use App\Extensions\AIChatPro\System\Services\DocumentRepository;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

final class DocumentChatServiceTest extends TestCase
{
    public function test_document_conversion_context_includes_all_uploaded_source_documents(): void
    {
        $documents = new Collection([
            (object) [
                'id' => 1,
                'original_name' => 'assignment-one.docx',
                'chunks' => [['index' => 0, 'content' => 'The first assignment describes the community health program.']],
            ],
            (object) [
                'id' => 2,
                'original_name' => 'assignment-two.docx',
                'chunks' => [['index' => 0, 'content' => 'The second assignment describes the program evaluation methods.']],
            ],
            (object) [
                'id' => 3,
                'original_name' => 'Arkaan Logo.png',
                'chunks' => [['index' => 0, 'content' => 'Image attachment: Arkaan logo in cyan.']],
            ],
            (object) [
                'id' => 4,
                'original_name' => 'university-logo.png',
                'chunks' => [['index' => 0, 'content' => 'Image attachment: university logo in blue.']],
            ],
        ]);

        $repository = $this->createMock(DocumentRepository::class);
        $repository->expects(self::once())
            ->method('getDocumentsForChat')
            ->with(13643)
            ->willReturn($documents);

        $context = (new DocumentChatService($repository))->buildContextForChat(
            13643,
            'word kaan pdf iiga soo dhig sidookale adoo adeegsanaya logo da iyo brand gaas',
        );

        self::assertNotNull($context);
        self::assertStringContainsString('assignment-one.docx', $context);
        self::assertStringContainsString('assignment-two.docx', $context);
        self::assertStringContainsString('Arkaan Logo.png', $context);
        self::assertStringContainsString('university-logo.png', $context);
    }
}
