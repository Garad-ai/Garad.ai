<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Services;

use App\Extensions\AIChatPro\System\Services\Document\GaradDocumentParser;

final class GaradDocumentQualityReviewService
{
    public const RUBRIC_VERSION = '1.0.0';

    public function review(
        string $title,
        string $content,
        string $request,
        array $verifiedFacts = [],
        bool $evidenceReviewPassed = false,
        array $requiredBlockTypes = [],
    ): array
    {
        $failures = [];
        $revisionRequests = [];
        $content = trim($content);

        if ($title === '' || $content === '') {
            $failures[] = ['code' => 'empty_document', 'severity' => 'critical'];
            $revisionRequests[] = 'Provide a grounded title and substantive document content.';
        }

        $duplicateParagraphs = $this->duplicateParagraphs($content);
        if ($duplicateParagraphs !== []) {
            $failures[] = ['code' => 'repeated_content', 'severity' => 'high'];
            $revisionRequests[] = 'Remove repeated paragraphs or consolidate repeated information into one useful presentation.';
        }

        $requestLeak = $this->containsRequestInstruction($content, $request);
        if ($requestLeak) {
            $failures[] = ['code' => 'request_instruction_leak', 'severity' => 'high'];
            $revisionRequests[] = 'Remove document-generation instructions and title fragments; retain only relevant source facts.';
        }

        $unsupportedClaims = $this->unsupportedClaimIndicators($content, $verifiedFacts, $request);
        if ($unsupportedClaims !== []) {
            $failureCode = in_array('unsupported_training_detail', $unsupportedClaims, true)
                ? 'unsupported_training_detail'
                : 'unsupported_outcome_or_commitment';
            $failures[] = ['code' => $failureCode, 'severity' => 'critical'];
            $revisionRequests[] = 'Remove or qualify claims, procedures, recommendations, roles, and outcomes that are absent from verified source facts.';
        }

        $parser = new GaradDocumentParser;
        $document = $content === '' ? ['blocks' => []] : $parser->parseSemanticBlocks($content);
        $blocks = $document['blocks'] ?? [];
        $blockCounts = array_count_values(array_column($blocks, 'type'));
        $missingBlockTypes = [];
        foreach ($requiredBlockTypes as $type => $requiredCount) {
            $blockType = is_int($type) ? (string) $requiredCount : (string) $type;
            $count = is_int($type) ? 1 : max(1, (int) $requiredCount);
            if (($blockCounts[$blockType] ?? 0) < $count) {
                $missingBlockTypes[] = $blockType;
            }
        }
        if ($missingBlockTypes !== []) {
            $failures[] = ['code' => 'required_semantic_block_missing', 'severity' => 'critical'];
            $revisionRequests[] = 'Restore the required semantic blocks without changing their source-backed content or data.';
        }
        $headings = array_values(array_map(
            static fn (array $block): string => trim((string) ($block['text'] ?? '')),
            array_filter($blocks, static fn (array $block): bool => ($block['type'] ?? null) === 'heading'),
        ));
        $paragraphs = array_values(array_filter(
            $blocks,
            static fn (array $block): bool => in_array($block['type'] ?? null, ['paragraph', 'list', 'table'], true),
        ));
        $wordCount = count(preg_split('/[^\p{L}\p{N}]+/u', $parser->plain($content), -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $structurePassed = $paragraphs !== [] && ($wordCount < 120 || $headings !== []);
        $detailedRequest = $this->requestsDetailedDocument($request);
        $depthNeedsReview = $detailedRequest && ! $evidenceReviewPassed;
        $evidenceReviewRequired = $this->requiresEvidenceReview($request, $content);

        if ($evidenceReviewRequired && ! $evidenceReviewPassed) {
            $failures[] = ['code' => 'model_evidence_review_required', 'severity' => 'high'];
            $revisionRequests[] = 'Review and revise the complete draft for source support, requirement coverage, repetition, appropriate depth, and structure before rendering.';
        }

        if (! $structurePassed) {
            $failures[] = ['code' => 'missing_semantic_structure', 'severity' => 'high'];
            $revisionRequests[] = 'Provide substantive paragraphs, lists, or table content; use meaningful headings for longer documents.';
        }
        if ($depthNeedsReview) {
            $failures[] = ['code' => 'depth_not_reviewed', 'severity' => 'high'];
            $revisionRequests[] = 'Assess whether the document is sufficiently developed for the request without adding unsupported material.';
        }

        $factCoverage = $this->factCoverage($verifiedFacts, $content);
        if (($factCoverage['missing_count'] ?? 0) > 0) {
            $failures[] = ['code' => 'source_fact_coverage_low', 'severity' => 'high'];
            $revisionRequests[] = 'Restore every missing verified source detail without adding unsupported claims.';
        }

        $score = 100;
        $score -= count($failures) * 25;
        $score -= min(20, count($revisionRequests) * 5);
        $score = max(0, $score);

        return [
            'rubric_version' => self::RUBRIC_VERSION,
            'status' => $failures === [] ? 'passed' : 'needs_revision',
            'score' => $score,
            'checks' => [
                'intent_adherence' => $evidenceReviewPassed ? 'model_reviewed_pass' : ($evidenceReviewRequired ? 'review_required' : 'not_scored'),
                'structure' => $structurePassed ? 'passed' : 'failed',
                'source_coverage' => $factCoverage === null ? 'not_scored' : (($factCoverage['missing_count'] ?? 0) === 0 ? 'passed' : 'failed'),
                'factual_accuracy' => $unsupportedClaims !== []
                    ? 'failed'
                    : ($evidenceReviewPassed ? 'model_reviewed_pass' : ($factCoverage === null ? 'not_scored' : 'outcome_rule_checks_passed')),
                'narrative_coherence' => $evidenceReviewPassed ? 'model_reviewed_pass' : 'review_required',
                'non_repetition' => $duplicateParagraphs === [] ? 'passed' : 'failed',
                'instruction_leakage' => $requestLeak ? 'failed' : 'passed',
                'depth' => $depthNeedsReview ? 'review_required' : ($evidenceReviewPassed ? 'model_reviewed_pass' : 'passed'),
                'language_quality' => $evidenceReviewPassed ? 'model_reviewed_pass' : 'not_scored',
                'brand_usage' => 'delegated_to_document_brand_resolver',
                'output_format_readiness' => $structurePassed ? 'semantic_parse_passed' : 'failed',
                'required_semantic_blocks' => $missingBlockTypes === [] ? 'passed' : 'failed',
                'rendered_visual_review' => 'not_run',
            ],
            'failure_codes' => array_values(array_unique(array_column($failures, 'code'))),
            'revision_requests' => array_values(array_unique($revisionRequests)),
            'duplicate_paragraph_count' => count($duplicateParagraphs),
        ];
    }

    public function requiresEvidenceReview(string $request, string $content): bool
    {
        $plain = (new GaradDocumentParser)->plain($content);
        $wordCount = count(preg_split('/[^\p{L}\p{N}]+/u', $plain, -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $detailedRequest = $this->requestsDetailedDocument($request);
        $intent = app(GaradDocumentIntelligenceService::class)->referenceForRequest($request)['id'] ?? null;
        $substantialTypes = [
            'weekly_report',
            'annual_report',
            'proposal',
            'business_plan',
            'research',
            'financial_report',
            'cv',
            'technical_manual',
            'training',
            'long_form_report',
        ];
        $hasStructuredBlocks = (bool) preg_match('/^\s*(?:\|.*\||!\[[^\]]*\]\(|(?:chart|diagram|image)\s*:)/imu', $content);
        $conciseRequest = (bool) preg_match('/\b(?:short|concise|brief)\b/iu', $request);
        if ($conciseRequest && $wordCount <= 180 && ! $hasStructuredBlocks) {
            return false;
        }

        return $detailedRequest
            || $wordCount > 220
            || (in_array($intent, $substantialTypes, true) && $wordCount > 100)
            || $hasStructuredBlocks;
    }

    private function duplicateParagraphs(string $content): array
    {
        $seen = [];
        $duplicates = [];
        foreach (preg_split('/\R\s*\R/u', $content) ?: [] as $block) {
            $block = trim($block);
            $block = trim(preg_replace('/^#{1,6}\s+.*$/mu', '', $block) ?? $block);
            if ($block === '' || preg_match('/^\|/u', $block)) {
                continue;
            }
            $normalized = mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', ' ', strip_tags($block)) ?? $block);
            $normalized = trim(preg_replace('/\s+/u', ' ', $normalized) ?? $normalized);
            if (mb_strlen($normalized) < 12) {
                continue;
            }
            if (isset($seen[$normalized])) {
                $duplicates[] = $normalized;
            }
            $seen[$normalized] = true;
        }

        return $duplicates;
    }

    private function containsRequestInstruction(string $content, string $request): bool
    {
        $contentNormalized = mb_strtolower(preg_replace('/\s+/u', ' ', $content) ?? $content);
        $directives = preg_split('/(?<=[.!?؟])\s+/u', trim($request), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($directives as $directive) {
            $directive = trim($directive);
            if (mb_strlen($directive) < 24 || ! preg_match(
                '/\b(?:please\s+(?:write|create|prepare|generate|make)|(?:write|create|prepare|generate|make)\s+(?:me\s+)?(?:a|an|the)?\s*(?:report|document|pdf|proposal)|'
                . 'use\s+(?:the\s+)?attached\s+(?:logo|file)|write\s+(?:it\s+)?in\s+(?:english|somali)|'
                . 'ku\s+(?:diyaari|qor|samee)\s+(?:warbixin|dukumenti|pdf)|ii\s+(?:diyaari|samee)\s+(?:warbixin|pdf))\b/iu',
                $directive,
            )) {
                continue;
            }
            $normalizedDirective = mb_strtolower(preg_replace('/\s+/u', ' ', $directive) ?? $directive);
            if (str_contains($contentNormalized, $normalizedDirective)) {
                return true;
            }
        }

        return false;
    }

    private function unsupportedClaimIndicators(string $content, array $verifiedFacts, string $request): array
    {
        $supportedSource = mb_strtolower(implode(' ', array_map(
            static fn ($fact): string => trim((string) $fact),
            $verifiedFacts,
        )));
        $patterns = [
            'unsupported_completion' => '/\b(?:completed|finished|finalized|launched|delivered|deployed|resolved)\s+(?:the\s+)?(?:work|activities|tasks?|project|website|report|deliverables?)\b/iu',
            'unsupported_approval' => '/\b(?:approved|approval|authorized|confirmed)\b/iu',
            'unsupported_commitment' => '/\b(?:i|we|the team)\s+(?:am|are|will|plan to|intend to|going to)\s+(?:available|ready|provide|deliver|complete|finish|launch|resolve|continue|start)\b/iu',
        ];
        $detected = [];
        foreach ($patterns as $code => $pattern) {
            preg_match_all($pattern, $content, $matches);
            foreach ($matches[0] ?? [] as $match) {
                if (! str_contains($supportedSource, mb_strtolower((string) $match))) {
                    $detected[] = $code;
                    break;
                }
            }
        }

        $intent = app(GaradDocumentIntelligenceService::class)->referenceForRequest($request)['id'] ?? null;
        if (in_array($intent, ['training', 'technical_manual'], true)) {
            $trainingDetails = [
                '/\b(?:trainer|instructor|supervisor|observer)\b/iu',
                '/\b(?:objective|assessment|certification|certificate|grading|scoring|pass\s+mark|threshold|criteria)\b/iu',
                '/\b(?:safe|safely|safety|risk[- ]free|unsupervised|unaided)\b/iu',
                '/\b(?:restart|repeat\s+as\s+needed|comfortably|consistently)\b/iu',
                '/\b(?:should|must|ought\s+to|recommend(?:ed|ation)?)\b/iu',
                '/\b(?:first\s+time|no\s+prior\s+experience)\b/iu',
            ];
            foreach ($trainingDetails as $pattern) {
                preg_match_all($pattern, $content, $matches);
                foreach ($matches[0] ?? [] as $match) {
                    if (! str_contains($supportedSource, mb_strtolower((string) $match))) {
                        $detected[] = 'unsupported_training_detail';
                        break 2;
                    }
                }
            }
        }

        return $detected;
    }

    private function factCoverage(array $facts, string $content): ?array
    {
        $facts = array_values(array_filter(array_map(static fn ($fact): string => trim((string) $fact), $facts)));
        if ($facts === []) {
            return null;
        }
        $contentTokens = array_fill_keys($this->tokens($content), true);
        $coverages = [];
        $missingCount = 0;
        foreach ($facts as $fact) {
            $tokens = $this->tokens($fact);
            if ($tokens === []) {
                continue;
            }
            $coverage = count(array_filter($tokens, static fn (string $token): bool => isset($contentTokens[$token]))) / count($tokens);
            $coverages[] = $coverage;
            if ($coverage < 0.55) {
                $missingCount++;
            }
        }

        return $coverages === [] ? null : [
            'score' => array_sum($coverages) / count($coverages),
            'missing_count' => $missingCount,
        ];
    }

    private function tokens(string $text): array
    {
        $stopWords = array_fill_keys([
            'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from', 'in', 'is', 'it',
            'of', 'on', 'or', 'the', 'to', 'was', 'were', 'with', 'iyo', 'ayaa', 'waxa', 'waxaa',
            'ee', 'ku', 'ka', 'la', 'oo', 'ah', 'u',
        ], true);
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower(strip_tags($text)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(
            $tokens,
            static fn (string $token): bool => mb_strlen($token) > 1 && ! isset($stopWords[$token]),
        )));
    }

    private function requestsDetailedDocument(string $request): bool
    {
        return (bool) preg_match('/\b(?:detailed|comprehensive|in[- ]depth|well[- ]developed|thorough)\b/iu', $request)
            || (bool) preg_match('/\b(?:faahfaahsan|si\s+wanaagsan\s+u\s+faahfaahsan)\b/iu', $request);
    }
}
