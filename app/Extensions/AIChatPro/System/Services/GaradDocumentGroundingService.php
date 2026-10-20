<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Services;

use RuntimeException;

final class GaradDocumentGroundingService
{
    /**
     * Retain grounded model prose while ensuring verified source facts remain represented.
     */
    public function prepare(array $arguments): array
    {
        $userRequest = $this->requestText();
        $uploadedDocumentContext = $this->uploadedDocumentContext($userRequest);
        $source = $this->sourceText($userRequest, $uploadedDocumentContext);
        $facts = $arguments['source_facts'] ?? [];

        if ($source === '' || ! is_array($facts)) {
            throw new RuntimeException('The document request could not be verified against its source.');
        }

        $sourceNormalized = $this->normalize($source);
        $verified = [];

        foreach ($facts as $fact) {
            if (! is_array($fact)) {
                continue;
            }

            $quote = trim((string) ($fact['quote'] ?? ''));
            if ($quote === '' || ! str_contains($sourceNormalized, $this->normalize($quote))) {
                continue;
            }

            $section = in_array(($fact['section'] ?? ''), ['identity', 'work', 'meeting', 'other'], true)
                ? $fact['section']
                : 'other';

            $verified[$section][] = $quote;
        }

        if ($verified === []) {
            throw new RuntimeException('No document facts could be verified against the user request. Please retry with source-grounded content.');
        }

        $title = trim((string) ($arguments['title'] ?? ''));
        if ($title === '' || ! str_contains($sourceNormalized, $this->normalize($title))) {
            $title = $this->groundTitle($title, $source);
        }
        $title = $this->titleInRequestedLanguage($title, $userRequest);
        $candidateBlocks = (new \App\Extensions\AIChatPro\System\Services\Document\GaradDocumentParser)
            ->parseSemanticBlocks((string) ($arguments['content'] ?? ''))['blocks'] ?? [];
        $requiredBlockCounts = array_filter(
            array_count_values(array_column($candidateBlocks, 'type')),
            static fn (int $count, string $type): bool => in_array($type, ['table', 'image', 'code'], true),
            ARRAY_FILTER_USE_BOTH,
        );

        $composition = app(GaradDocumentCompositionService::class)->compose(
            $verified,
            $source,
            $title,
            (string) ($arguments['content'] ?? ''),
        );
        $reviewer = app(GaradDocumentQualityReviewService::class);
        $qualityReview = $reviewer->review(
            $title,
            $composition['content'] . ($composition['prepared_by'] !== '' ? "\n\n" . $composition['prepared_by'] : ''),
            $source,
            $composition['quality_facts'],
            requiredBlockTypes: $requiredBlockCounts,
        );
        $evidenceReview = null;
        if ($qualityReview['status'] !== 'passed'
            || $reviewer->requiresEvidenceReview($userRequest, $composition['content'])) {
            $modelId = trim((string) request()?->attributes->get('garad_selected_model', ''));
            $engineId = trim((string) request()?->attributes->get('garad_selected_engine', ''));
            if ($modelId === '' || $engineId === '') {
                throw new RuntimeException('The selected model could not be resolved for the required document evidence review. No artifact was created.');
            }

            $reference = app(GaradDocumentIntelligenceService::class)->referenceForRequest($userRequest);
            // Tables are checked through source-fact coverage after the reviewed revision: the reviewer may
            // legitimately condense a table into prose for a short document, but images and code must remain.
            $postReviewBlocks = array_diff_key($requiredBlockCounts, ['table' => true]);
            // One additional revision pass is allowed when the first reviewed draft dropped verified source details.
            for ($attempt = 1; $attempt <= 2; $attempt++) {
                $reviewResult = app(GaradDocumentAuthoringReviewService::class)->reviewAndRevise(
                    request: $userRequest,
                    documentType: (string) ($reference['id'] ?? 'general_document'),
                    title: $title,
                    verifiedSourceFacts: array_merge(...array_values($verified)),
                    draft: $composition['content'],
                    modelId: $modelId,
                    engineId: $engineId,
                    requiredBlocks: $requiredBlockCounts,
                );
                $composition['content'] = $reviewResult['content'];
                $qualityReview = $reviewer->review(
                    $title,
                    $composition['content'] . ($composition['prepared_by'] !== '' ? "\n\n" . $composition['prepared_by'] : ''),
                    $source,
                    $composition['quality_facts'],
                    true,
                    $postReviewBlocks,
                );
                $evidenceReview = $reviewResult['metadata'];
                if ($qualityReview['status'] === 'passed') {
                    break;
                }
                \Illuminate\Support\Facades\Log::warning('Garad document review attempt failed quality gate', [
                    'attempt' => $attempt,
                    'failures' => $qualityReview['failure_codes'] ?? [],
                    'quality_facts' => $composition['quality_facts'],
                ]);
            }
        }
        if ($qualityReview['status'] !== 'passed') {
            $failureCodes = implode(', ', $qualityReview['failure_codes'] ?? []);
            throw new RuntimeException(
                'The document did not pass source-grounded evidence review'
                . ($failureCodes !== '' ? " ({$failureCodes})" : '')
                . '. No artifact was created; please revise the draft or provide more source material.',
            );
        }
        if ($evidenceReview !== null) {
            $qualityReview['evidence_review'] = $evidenceReview;
        }
        $requestedPageCount = $this->requestedPageCount($userRequest);
        return [
            ...$arguments,
            'title' => $title,
            'content' => $composition['content'],
            'page_count' => $requestedPageCount,
            'subtitle' => '',
            'prepared_for' => '',
            'prepared_by' => $composition['prepared_by'],
            'summary' => $composition['summary'],
            'summary_title' => $composition['summary_title'],
            'highlights' => [],
            'grounding' => [
                'source' => $uploadedDocumentContext !== '' ? 'user_request_and_uploaded_documents' : 'user_request',
                'uploaded_document_context' => $uploadedDocumentContext !== '',
                'verified_quotes' => $composition['verified_quotes'],
            ],
            'quality_review' => $qualityReview,
            'evidence_review' => $evidenceReview,
        ];
    }

    private function requestText(): string
    {
        $request = request();
        if (! $request) {
            return '';
        }

        foreach (['raw_user_input', 'prompt', 'message', 'input', 'chat_message'] as $key) {
            $value = trim((string) $request->input($key, ''));
            if ($value !== '') {
                return trim((string) preg_split('/\s*Please follow these response preferences:/iu', $value, 2)[0]);
            }
        }

        return '';
    }

    private function sourceText(string $userRequest, string $documentContext): string
    {
        return trim($userRequest . ($documentContext !== '' ? "\n\nUploaded source material:\n" . $documentContext : ''));
    }

    private function uploadedDocumentContext(string $userRequest): string
    {
        $chatId = (int) request()?->input('chat_id', 0);
        if ($chatId <= 0) {
            return '';
        }

        return app(DocumentChatService::class)->buildContextForChat($chatId, $userRequest) ?? '';
    }

    private function normalize(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return mb_strtolower(trim($text));
    }

    private function groundTitle(string $title, string $source): string
    {
        $titleTokens = preg_split('/[^\p{L}\p{N}]+/u', trim($title), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $sourceTokens = preg_split('/[^\p{L}\p{N}]+/u', $this->normalize($source), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $sourceTokenSet = array_fill_keys($sourceTokens, true);
        $verifiedTokens = array_values(array_filter(
            $titleTokens,
            fn (string $token): bool => isset($sourceTokenSet[mb_strtolower($token)]),
        ));

        return count($verifiedTokens) >= 2 ? implode(' ', $verifiedTokens) : 'Document';
    }

    private function titleInRequestedLanguage(string $title, string $source): string
    {
        if (preg_match('/\b(?:english\s+(?:ka\s+dhig|ku\s+qor)|in\s+english)\b/iu', $source)
            && preg_match('/\b(?:report|warbixin)\b/iu', $source)) {
            if (preg_match('/\b(?:weekly|asbuuc|asbuucan|toddobaadl\p{L}*)\b/iu', $source)) {
                return preg_match('/\barkaan\b/iu', $source)
                    ? 'Arkaan Weekly Work Report'
                    : 'Weekly Work Report';
            }

            return preg_match('/\barkaan\b/iu', $source) ? 'Arkaan Work Report' : 'Work Report';
        }

        if (! preg_match('/\b(?:ku\s+qor|qor)\s+(?:af\s+)?(?:somali|soomaali)\b/iu', $source)
            || ! preg_match('/\b(?:report|warbixin)\b/iu', $source)) {
            return $title;
        }

        if (preg_match('/\b(?:weekly|asbuuc|asbuucan|toddobaadl\p{L}*)\b/iu', $source)) {
            return 'Warbixinta Shaqada Toddobaadlaha ah';
        }

        return 'Warbixinta Shaqada';
    }

    private function requestedPageCount(string $source): ?int
    {
        if (! preg_match(
            '/\b(one|two|three|four|five|six|seven|eight|nine|ten|\d{1,2})\s*-?\s*pages?\b/iu',
            $source,
            $match,
        )) {
            return null;
        }

        $count = match (mb_strtolower($match[1])) {
            'one' => 1,
            'two' => 2,
            'three' => 3,
            'four' => 4,
            'five' => 5,
            'six' => 6,
            'seven' => 7,
            'eight' => 8,
            'nine' => 9,
            'ten' => 10,
            default => (int) $match[1],
        };
        return $count >= 1 && $count <= 20 ? $count : null;
    }
}