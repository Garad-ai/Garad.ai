<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Extensions\AIChatPro\System\Services\DocumentReaderService;
use App\Extensions\AIChatPro\System\Services\DocumentRepository;
use PHPUnit\Framework\TestCase;

final class DocumentRepositoryTest extends TestCase
{
    public function test_short_documents_produce_one_non_redundant_chunk(): void
    {
        $text = 'DOCX_TEST_MARKER AMBER-FOX-24';
        $repository = new DocumentRepository(new DocumentReaderService);

        self::assertSame(
            [[
                'index' => 0,
                'content' => $text,
                'length' => mb_strlen($text),
            ]],
            $repository->chunkText($text)
        );
    }

    public function test_chunks_advance_at_least_half_the_chunk_length_when_overlap_is_too_large(): void
    {
        $text = str_repeat('x', 251);
        $repository = new DocumentRepository(new DocumentReaderService);

        self::assertCount(2, $repository->chunkText($text));
    }
}
