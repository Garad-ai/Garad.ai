<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Services;

use App\Domains\Engine\Enums\EngineEnum;
use App\Domains\Entity\Enums\EntityEnum;
use App\Services\Ai\AiCompletionService;
use RuntimeException;

final class GaradDocumentAuthoringReviewService
{
    public const RUBRIC_VERSION = '1.0.0';

    private const SYSTEM_PROMPT = <<<'PROMPT'
You are the evidence reviewer and reviser for a user-facing document.
Treat the supplied verified source quotations as a closed-world factual record. Review the complete draft against the user's requirements and the document intent. Do not use general knowledge, conventional practice, or plausible assumptions as factual support.

Remove unsupported names, roles, dates, outcomes, procedures, recommendations, assessment criteria, safety claims, examples, and statements about what a source does not say. Do not add details to make the document longer. Preserve grounded explanations, every required fact and required semantic block, the requested language, and useful structure. Remove repeated explanations and redundant sections. Match the requested depth to the evidence; when the source is sparse, say so briefly and keep the document concise rather than inventing detail.

Return only one JSON object with exactly these fields:
{
  "decision": "approved" or "revised",
  "content": "the complete final Markdown document",
  "checks": {
    "requirements_covered": true,
    "source_facts_preserved": true,
    "claims_supported_or_explicitly_limited": true,
    "non_repetitive": true,
    "depth_matches_request_and_evidence": true,
    "language_and_structure_preserved": true
  },
  "issues": ["short issue labels"]
}
If any check cannot be truthfully marked true after revision, return false for that check. Do not include chain-of-thought or commentary.
PROMPT;

    public function reviewAndRevise(
        string $request,
        string $documentType,
        string $title,
        array $verifiedSourceFacts,
        string $draft,
        string $modelId,
        string $engineId,
        array $requiredBlocks = [],
    ): array {
        $entity = EntityEnum::tryFrom($modelId);
        $engine = EngineEnum::tryFrom($engineId);
        if ($entity === null || $engine === null) {
            throw new RuntimeException('The selected model is unavailable for document evidence review.');
        }

        if (! in_array($engine, [
            EngineEnum::OPEN_AI,
            EngineEnum::ANTHROPIC,
            EngineEnum::DEEP_SEEK,
            EngineEnum::GEMINI,
        ], true)) {
            throw new RuntimeException('The selected provider does not support document evidence review.');
        }
        if ($entity->engine() !== $engine) {
            throw new RuntimeException('The selected model and provider do not match the active chat route. No alternate provider was used.');
        }

        $input = json_encode([
            'request' => $request,
            'document_type' => $documentType,
            'title' => $title,
            'verified_source_facts' => array_values(array_unique(array_filter(
                array_map(static fn ($fact): string => trim((string) $fact), $verifiedSourceFacts),
            ))),
            'draft_markdown' => $draft,
            'required_semantic_blocks' => $requiredBlocks,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $budget = app(\App\Services\Ai\ProviderRequestBudgetService::class);
        $httpRequest = request();
        $scoped = $httpRequest !== null && ! $httpRequest->attributes->has('garad_provider_request_max');
        if ($scoped) {
            $budget->initializeChatPro($httpRequest);
        }
        try {
            $response = app(AiCompletionService::class)->complete(
                self::SYSTEM_PROMPT,
                $input,
                $engine,
                $entity->value,
            );
        } finally {
            if ($scoped) {
                $budget->endScope($httpRequest);
            }
        }
        $result = $this->decodeResult($response);
        $checks = $result['checks'] ?? null;
        $requiredChecks = [
            'requirements_covered',
            'source_facts_preserved',
            'claims_supported_or_explicitly_limited',
            'non_repetitive',
            'depth_matches_request_and_evidence',
            'language_and_structure_preserved',
        ];
        $allChecksPassed = is_array($checks);
        foreach ($requiredChecks as $check) {
            $allChecksPassed = $allChecksPassed && ($checks[$check] ?? false) === true;
        }

        $content = trim((string) ($result['content'] ?? ''));
        if (! in_array(($result['decision'] ?? null), ['approved', 'revised'], true)
            || $content === ''
            || ! $allChecksPassed) {
            $failedChecks = is_array($checks)
                ? array_keys(array_filter($requiredChecks ? array_combine($requiredChecks, array_map(
                    static fn (string $name): bool => ($checks[$name] ?? false) !== true,
                    $requiredChecks,
                )) : []))
                : $requiredChecks;
            \Illuminate\Support\Facades\Log::warning('Document evidence review rejected the draft', [
                'model' => $entity->value,
                'decision' => $result['decision'] ?? null,
                'failed_checks' => $failedChecks,
                'issues' => array_slice(array_map('strval', (array) ($result['issues'] ?? [])), 0, 10),
                'empty_content' => $content === '',
            ]);
            throw new RuntimeException('The document did not pass model-assisted evidence review'
                . ($failedChecks !== [] ? ' (' . implode(', ', $failedChecks) . ')' : '')
                . '. No artifact was created.');
        }

        return [
            'content' => $content,
            'metadata' => [
                'rubric_version' => self::RUBRIC_VERSION,
                'status' => 'passed',
                'model' => $entity->value,
                'decision' => $result['decision'],
                'revised' => $result['decision'] === 'revised',
                'checks' => array_intersect_key($checks, array_fill_keys($requiredChecks, true)),
                'issues' => array_values(array_filter(
                    array_map(static fn ($issue): string => trim((string) $issue), (array) ($result['issues'] ?? [])),
                )),
            ],
        ];
    }

    private function decodeResult(string $response): array
    {
        $response = trim($response);
        if (str_starts_with($response, '```')) {
            $response = preg_replace('/^```(?:json)?\s*|\s*```$/iu', '', $response) ?? $response;
        }

        $start = strpos($response, '{');
        $end = strrpos($response, '}');
        if ($start === false || $end === false || $end < $start) {
            throw new RuntimeException('The model-assisted evidence review returned an invalid response. No artifact was created.');
        }

        try {
            $decoded = json_decode(substr($response, $start, $end - $start + 1), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('The model-assisted evidence review returned invalid JSON. No artifact was created.', 0, $exception);
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('The model-assisted evidence review returned an invalid result. No artifact was created.');
        }

        return $decoded;
    }
}
