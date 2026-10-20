<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Extensions\AIChatPro\System\Services\GaradDocumentIntelligenceService;
use App\Extensions\AIChatPro\System\Services\GaradDocumentQualityReviewService;
use App\Extensions\AIChatPro\System\Services\Document\GaradDocumentParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GaradDocumentEvaluationSuiteTest extends TestCase
{
    #[DataProvider('evaluationCases')]
    public function test_synthetic_document_case_meets_its_structural_and_grounding_contract(array $case): void
    {
        $intelligence = new GaradDocumentIntelligenceService;
        $reference = $intelligence->referenceForRequest($case['input']);
        $content = "# {$case['title']}";
        foreach ($case['sections'] as $section => $body) {
            $content .= "\n\n## {$section}\n\n{$body}";
        }

        self::assertSame($case['expected_document_type'], $reference['id'] ?? null);
        self::assertSame($case['language'], $intelligence->requestedLanguage($case['input']));
        self::assertSame('1.0.0', $case['quality_rubric_version']);
        self::assertNotEmpty($case['expected_facts']);
        self::assertNotEmpty($case['required_phrases']);
        self::assertNotEmpty($case['known_failure_conditions']);
        self::assertContains($case['format'], ['pdf', 'docx', 'xlsx', 'pptx']);

        foreach (array_keys($case['sections']) as $section) {
            self::assertStringContainsString("## {$section}", $content);
        }
        foreach ($case['required_phrases'] as $phrase) {
            self::assertStringContainsString(mb_strtolower($phrase), mb_strtolower($content));
        }
        foreach ($case['forbidden_phrases'] as $phrase) {
            self::assertStringNotContainsString(mb_strtolower($phrase), mb_strtolower($content));
        }

        $reviewer = new GaradDocumentQualityReviewService;
        $review = $reviewer->review(
            $case['title'],
            $content,
            $case['input'],
            $case['expected_facts'],
        );
        if ($reviewer->requiresEvidenceReview($case['input'], $content)) {
            // Substantial documents must never pass on rule checks alone; the model evidence review is mandatory.
            self::assertSame('needs_revision', $review['status']);
            self::assertContains('model_evidence_review_required', $review['failure_codes']);
            $review = $reviewer->review(
                $case['title'],
                $content,
                $case['input'],
                $case['expected_facts'],
                true,
            );
        }
        self::assertSame($case['expected_review_status'], $review['status']);

        if (in_array('table', $case['expected_block_types'] ?? [], true)
            || in_array('image', $case['expected_block_types'] ?? [], true)) {
            $semantic = (new GaradDocumentParser)->parseSemanticBlocks($content);
            $blockTypes = array_column($semantic['blocks'], 'type');
            foreach ($case['expected_block_types'] as $type) {
                self::assertContains($type, $blockTypes);
            }
        }
    }

    public static function evaluationCases(): iterable
    {
        $fixture = require dirname(__DIR__, 2) . '/tests/Fixtures/garad_document_evaluation_cases.php';
        foreach ($fixture['cases'] as $name => $case) {
            $case['quality_rubric_version'] = $fixture['quality_rubric']['version'];
            yield $name => [$case];
        }
    }
}
