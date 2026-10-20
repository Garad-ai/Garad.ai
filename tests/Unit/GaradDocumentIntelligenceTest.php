<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Extensions\AIChatPro\System\Services\GaradDocumentIntelligenceService;
use App\Extensions\AIChatPro\System\Services\GaradDocumentQualityReviewService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GaradDocumentIntelligenceTest extends TestCase
{
    #[DataProvider('documentRequests')]
    public function test_document_request_selects_a_relevant_structural_pattern(
        string $request,
        string $expectedId,
        string $expectedCue,
    ): void {
        $service = new GaradDocumentIntelligenceService;
        $reference = $service->referenceForRequest($request);

        self::assertSame($expectedId, $reference['id'] ?? null);
        self::assertContains($expectedCue, $reference['outline'] ?? []);
        self::assertStringContainsString('structure only', $service->instructionForRequest($request));
    }

    public function test_structural_guidance_does_not_inject_example_facts_into_requests(): void
    {
        $instruction = (new GaradDocumentIntelligenceService)
            ->instructionForRequest('Create a detailed annual report in English.');

        self::assertStringContainsString('Document Intelligence Skill v1.0.0', $instruction);
        self::assertStringContainsString('Requested document language: English', $instruction);
        self::assertStringContainsString('Do not infer metrics', $instruction);
        self::assertStringContainsString('SYNTHETIC — NOT AN APPROVED GOLD STANDARD.', $instruction);
        self::assertStringNotContainsString('Pine Desk Studio', $instruction);
        self::assertStringNotContainsString('Revenue increased', $instruction);
    }

    public function test_versioned_example_library_labels_every_reference_and_retrieves_only_relevant_content(): void
    {
        $library = require dirname(__DIR__, 2)
            . '/app/Extensions/AIChatPro/System/Services/DocumentIntelligence/examples_v1.php';
        self::assertSame('1.0.0', $library['version']);
        self::assertSame(10, count($library['examples']));
        foreach ($library['examples'] as $example) {
            self::assertSame('SYNTHETIC — NOT AN APPROVED GOLD STANDARD.', $example['synthetic_label']);
        }

        $somaliInstruction = (new GaradDocumentIntelligenceService)
            ->instructionForRequest('Create a weekly work report in Somali.');
        self::assertStringContainsString('Warbixinta Shaqada Toddobaadlaha ah', $somaliInstruction);
        self::assertStringNotContainsString('Orchard Cooperative', $somaliInstruction);
        self::assertStringNotContainsString('Pine Desk Studio', $somaliInstruction);
    }

    public function test_quality_review_flags_repetition_and_verbatim_instructions(): void
    {
        $request = 'Please create a report for the weekly review.';
        $content = "# Weekly Report\n\nPlease create a report for the weekly review.\n\n"
            . "The team reviewed the website layout and documented the visual changes made during the week.\n\n"
            . "The team reviewed the website layout and documented the visual changes made during the week.";

        $review = (new GaradDocumentQualityReviewService)->review(
            'Weekly Report',
            $content,
            $request,
            ['The team reviewed the website layout and documented the visual changes made during the week.'],
        );

        self::assertSame('needs_revision', $review['status']);
        self::assertContains('request_instruction_leak', $review['failure_codes']);
        self::assertContains('repeated_content', $review['failure_codes']);
        self::assertNotEmpty($review['revision_requests']);
        self::assertSame('not_run', $review['checks']['rendered_visual_review']);
    }

    public function test_concise_request_is_not_flagged_as_underdeveloped(): void
    {
        $content = "# Work Note\n\nThe team reviewed the website layout.";
        $review = (new GaradDocumentQualityReviewService)->review(
            'Work Note',
            $content,
            'Create a short work note.',
            ['The team reviewed the website layout.'],
        );

        self::assertSame('passed', $review['status']);
        self::assertSame('passed', $review['checks']['depth']);
    }

    public function test_detailed_document_depth_review_blocks_acceptance(): void
    {
        $review = (new GaradDocumentQualityReviewService)->review(
            'Detailed Report',
            "## Executive Summary\n\nThe team reviewed the customer guide and updated the checklist.",
            'Create a comprehensive report.',
            [
                'The team reviewed the customer guide.',
                'The team updated the checklist.',
                'The support group discussed the handoff process.',
            ],
        );

        self::assertSame('needs_revision', $review['status']);
        self::assertSame('review_required', $review['checks']['depth']);
        self::assertContains('depth_not_reviewed', $review['failure_codes']);

        $reviewed = (new GaradDocumentQualityReviewService)->review(
            'Detailed Report',
            "## Executive Summary\n\nThe team reviewed the customer guide and updated the checklist.",
            'Create a comprehensive report.',
            [
                'The team reviewed the customer guide.',
                'The team updated the checklist.',
            ],
            true,
        );

        self::assertSame('model_reviewed_pass', $reviewed['checks']['depth']);
        self::assertNotContains('depth_not_reviewed', $reviewed['failure_codes']);
    }

    public function test_quality_review_rejects_unsupported_outcomes_and_future_commitments(): void
    {
        $review = (new GaradDocumentQualityReviewService)->review(
            'Weekly Report',
            "# Weekly Report\n\nThe report lists completed activities.\n\nI am available to provide additional materials.",
            'Create a weekly report.',
            ['The operations group discussed the handoff process.'],
        );

        self::assertSame('needs_revision', $review['status']);
        self::assertContains('unsupported_outcome_or_commitment', $review['failure_codes']);
        self::assertSame('failed', $review['checks']['factual_accuracy']);
    }

    public static function documentRequests(): iterable
    {
        yield 'weekly report' => ['Create a weekly work report.', 'weekly_report', 'Executive summary'];
        yield 'annual report' => ['Create an annual report.', 'annual_report', 'Executive summary'];
        yield 'proposal' => ['Draft a project proposal.', 'proposal', 'Proposed approach'];
        yield 'business plan' => ['Prepare a business plan.', 'business_plan', 'Executive summary'];
        yield 'research paper' => ['Write a research paper.', 'research', 'Abstract'];
        yield 'financial report' => ['Create a financial report.', 'financial_report', 'Executive summary'];
        yield 'CV' => ['Prepare a CV.', 'cv', 'Experience'];
        yield 'technical manual' => ['Write a technical manual.', 'technical_manual', 'Scope and prerequisites'];
        yield 'training material' => ['Create training materials.', 'training', 'Practice activities'];
        yield 'long-form report' => ['Write a comprehensive report.', 'long_form_report', 'Purpose and scope'];
    }
}
