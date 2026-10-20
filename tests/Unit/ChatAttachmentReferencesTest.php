<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\Classes\ChatAttachmentReferences;
use PHPUnit\Framework\TestCase;

final class ChatAttachmentReferencesTest extends TestCase
{
    public function test_it_serializes_multiple_attachment_paths_for_text_columns(): void
    {
        self::assertSame(
            'uploads/first.pdf,uploads/second.docx',
            ChatAttachmentReferences::normalize(['uploads/first.pdf', 'uploads/second.docx'])
        );
    }

    public function test_it_preserves_existing_scalar_paths(): void
    {
        self::assertSame('uploads/report, final.pdf', ChatAttachmentReferences::normalize(' uploads/report, final.pdf '));
    }

    public function test_it_discards_empty_and_non_string_entries(): void
    {
        self::assertSame('uploads/report.pdf', ChatAttachmentReferences::normalize([null, '', 12, ' uploads/report.pdf ']));
        self::assertNull(ChatAttachmentReferences::normalize(['', null, 12]));
        self::assertNull(ChatAttachmentReferences::normalize(null));
    }

    public function test_it_preserves_commas_in_remote_file_names_while_splitting_multiple_references(): void
    {
        self::assertSame(
            [
                'https://image.garad.ai/report, final.pdf',
                '/uploads/media/other/u-1/video.mp4',
            ],
            ChatAttachmentReferences::parse('https://image.garad.ai/report, final.pdf,/uploads/media/other/u-1/video.mp4')
        );
    }

    public function test_it_splits_multiple_private_chat_attachment_references(): void
    {
        $references = [
            '/dashboard/user/ai-chat-pro/attachments/11111111-1111-4111-8111-111111111111',
            '/dashboard/user/ai-chat-pro/attachments/22222222-2222-4222-8222-222222222222',
        ];

        self::assertSame(
            $references,
            ChatAttachmentReferences::parse(ChatAttachmentReferences::normalize($references))
        );
    }
}
