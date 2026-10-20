<?php

declare(strict_types=1);

use App\Extensions\AIChatPro\System\Services\Document\GaradDocumentParser;
use App\Extensions\AIChatPro\System\Services\GaradDocumentAuthoringReviewService;
use App\Extensions\AIChatPro\System\Services\GaradDocumentGroundingService;
use App\Extensions\AIChatPro\System\Services\GaradDocumentIntelligenceService;
use App\Extensions\AIChatPro\System\Services\GaradDocumentQualityReviewService;
use App\Services\Ai\AiCompletionService;
use App\Services\Ai\ProviderRequestBudgetService;
use App\Services\Stream\StreamService;
use App\Domains\Engine\Enums\EngineEnum;
use App\Domains\Engine\Services\AnthropicService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

$app = require $root . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(static function (Throwable $exception): never {
    fwrite(STDERR, "UNCAUGHT EVALUATION ERROR: {$exception->getMessage()}\n");
    exit(1);
});
$mockCompletion = new class extends AiCompletionService
{
    public ?EngineEnum $lastEngine = null;
    public ?string $lastModel = null;
    public bool $returnPassingReview = true;
    public bool $returnPassingDepth = true;
    public ?string $contentOverride = null;

    public function complete(
        string $systemPrompt,
        string $userContent,
        ?EngineEnum $engine = null,
        ?string $model = null,
    ): string {
        $this->lastEngine = $engine;
        $this->lastModel = $model;
        $input = json_decode($userContent, true, 512, JSON_THROW_ON_ERROR);

        return json_encode([
            'decision' => 'approved',
            'content' => $this->contentOverride ?? ($input['draft_markdown'] ?? ''),
            'checks' => [
                'requirements_covered' => $this->returnPassingReview,
                'source_facts_preserved' => true,
                'claims_supported_or_explicitly_limited' => true,
                'non_repetitive' => true,
                'depth_matches_request_and_evidence' => $this->returnPassingDepth,
                'language_and_structure_preserved' => true,
            ],
            'issues' => [],
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
};
$app->instance(AiCompletionService::class, $mockCompletion);

$fixture = require $root . '/tests/Fixtures/garad_document_evaluation_cases.php';
$exampleLibrary = require $root . '/app/Extensions/AIChatPro/System/Services/DocumentIntelligence/examples_v1.php';
$intelligence = app(GaradDocumentIntelligenceService::class);
$grounding = app(GaradDocumentGroundingService::class);
$reviewer = app(GaradDocumentQualityReviewService::class);
$parser = app(GaradDocumentParser::class);
$phraseIsRepresented = static function (string $phrase, string $content): bool {
    $wordVariants = static function (string $word): array {
        $variants = [$word];
        if (mb_strlen($word) > 4 && preg_match('/(?:ed|ing)$/u', $word)) {
            $stem = preg_replace('/(?:ed|ing)$/u', '', $word) ?? $word;
            $variants[] = $stem;
            $variants[] = $stem . 'e';
        }
        if (mb_strlen($word) > 4 && preg_match('/ies$/u', $word)) {
            $variants[] = mb_substr($word, 0, -3) . 'y';
        }
        if (mb_strlen($word) > 4 && preg_match('/es$/u', $word)) {
            $variants[] = mb_substr($word, 0, -2);
        } elseif (mb_strlen($word) > 3 && preg_match('/s$/u', $word) && ! preg_match('/ss$/u', $word)) {
            $variants[] = mb_substr($word, 0, -1);
        }

        return array_values(array_unique($variants));
    };
    $contentWords = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($content), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $contentVariants = [];
    foreach ($contentWords as $word) {
        $contentVariants = array_merge($contentVariants, $wordVariants($word));
    }

    foreach (preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($phrase), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
        if (array_intersect($wordVariants($word), $contentVariants) === []) {
            return false;
        }
    }

    return true;
};
$failures = [];
$totals = [
    'cases' => 0,
    'examples_retrieved' => 0,
    'intent_pass' => 0,
    'language_pass' => 0,
    'synthetic_example_provenance_pass' => 0,
    'grounding_pass' => 0,
    'quality_pass' => 0,
    'content_coverage_pass' => 0,
    'structure_pass' => 0,
];

foreach ($fixture['cases'] as $name => $case) {
    $facts = [];
    foreach ($case['expected_facts'] as $fact) {
        $section = 'work';
        foreach ($case['sections'] as $heading => $body) {
            if (mb_stripos($body, $fact) !== false) {
                $section = preg_match('/meeting|kulam/iu', $heading) ? 'meeting' : 'work';
                break;
            }
        }
        $facts[] = ['section' => $section, 'quote' => $fact];
    }

    $source = $case['input'] . "\nSynthetic source notes: " . implode(' ', $case['expected_facts']);
    $candidate = '# ' . $case['title'];
    foreach ($case['sections'] as $heading => $body) {
        $candidate .= "\n\n## {$heading}\n\n{$body}";
    }
    $reference = $intelligence->referenceForRequest($case['input']);
    $intentPassed = ($reference['id'] ?? null) === $case['expected_document_type'];
    $languagePassed = $intelligence->requestedLanguage($case['input']) === $case['language'];
    $retrievedExample = is_array($reference['example'] ?? null) && $reference['example'] !== [];
    $provenancePassed = ! $retrievedExample
        || ($reference['example']['synthetic_label'] ?? null) === $exampleLibrary['synthetic_label'];
    if ($retrievedExample) {
        $totals['examples_retrieved']++;
    }

    $evaluationRequest = Request::create('/', 'POST', ['raw_user_input' => $source]);
    $evaluationRequest->attributes->set('garad_selected_model', 'claude-opus-5');
    $evaluationRequest->attributes->set('garad_selected_engine', EngineEnum::ANTHROPIC->value);
    $app->instance('request', $evaluationRequest);
    try {
        $prepared = $grounding->prepare([
            'title' => $case['title'],
            'content' => $candidate,
            'source_facts' => $facts,
        ]);
    } catch (Throwable $exception) {
        $failures[] = "{$name}: production grounding/composition failed: {$exception->getMessage()}";
        $totals['cases']++;
        printf("%s | intent=%s | language=%s | grounding=FAIL | quality=BLOCKED\n", $name, $intentPassed ? 'PASS' : 'FAIL', $languagePassed ? 'PASS' : 'FAIL');
        continue;
    }
    $groundingPassed = ($prepared['grounding']['verified_quotes'] ?? 0) === count($case['expected_facts']);

    $coverageMissing = [];
    foreach ($case['required_phrases'] as $phrase) {
        if (! $phraseIsRepresented($phrase, $prepared['content'])) {
            $coverageMissing[] = $phrase;
        }
    }

    if ($name === 'unsupported_approval_hard_fail') {
        $negativeReview = $reviewer->review(
            $case['title'],
            $candidate,
            $source,
            $case['expected_facts'],
        );
        $hardFailDetected = $negativeReview['status'] === 'needs_revision'
            && in_array('unsupported_outcome_or_commitment', $negativeReview['failure_codes'], true);
        if (! $hardFailDetected) {
            $failures[] = "{$name}: the unsupported approval was not hard-failed.";
        }
        if (str_contains(mb_strtolower($prepared['content']), 'approved')) {
            $failures[] = "{$name}: the unsupported approval remained in the composed document.";
        }
        $coverageMissing = [];
    }

    $qualityPassed = ($prepared['quality_review']['status'] ?? null) === 'passed';
    if ($name === 'unsupported_approval_hard_fail') {
        $qualityPassed = $qualityPassed
            && ($negativeReview['status'] ?? null) === $case['expected_review_status'];
    } else {
        $qualityPassed = $qualityPassed
            && ($prepared['quality_review']['status'] ?? null) === $case['expected_review_status'];
    }

    $blocks = array_column($parser->parseSemanticBlocks($prepared['content'])['blocks'] ?? [], 'type');
    $missingBlockTypes = array_values(array_diff($case['expected_block_types'] ?? [], $blocks));
    $structurePassed = $missingBlockTypes === [];

    foreach ([
        'intent_pass' => $intentPassed,
        'language_pass' => $languagePassed,
        'synthetic_example_provenance_pass' => $provenancePassed,
        'grounding_pass' => $groundingPassed,
        'quality_pass' => $qualityPassed,
        'content_coverage_pass' => $coverageMissing === [],
        'structure_pass' => $structurePassed,
    ] as $metric => $passed) {
        if ($passed) {
            $totals[$metric]++;
        } else {
            $failures[] = "{$name}: {$metric} failed.";
        }
    }

    if ($coverageMissing !== []) {
        $failures[] = "{$name}: missing required content: " . implode(', ', $coverageMissing) . '.';
    }
    if ($missingBlockTypes !== []) {
        $failures[] = "{$name}: missing semantic blocks: " . implode(', ', $missingBlockTypes) . '.';
    }

    $depth = $prepared['quality_review']['checks']['depth'] ?? 'unknown';
    if (! in_array($depth, ['passed', 'model_reviewed_pass'], true)) {
        $failures[] = "{$name}: detailed-document depth check failed.";
    }

    $totals['cases']++;
    printf(
        "%s | intent=%s | language=%s | provenance=%s | grounding=%s (%d/%d) | quality=%s | depth=%s | content=%s | structure=%s\n",
        $name,
        $intentPassed ? 'PASS' : 'FAIL',
        $languagePassed ? 'PASS' : 'FAIL',
        $provenancePassed ? 'PASS' : 'FAIL',
        $groundingPassed ? 'PASS' : 'FAIL',
        $prepared['grounding']['verified_quotes'] ?? 0,
        count($case['expected_facts']),
        $prepared['quality_review']['status'] ?? 'unknown',
        $depth,
        $coverageMissing === [] ? 'PASS' : 'FAIL',
        $structurePassed ? 'PASS' : 'FAIL',
    );
}

$shortRequest = 'Please create a short report. The team reviewed the customer guide.';
$shortRequestContext = Request::create('/', 'POST', ['raw_user_input' => $shortRequest]);
$shortRequestContext->attributes->set('garad_selected_model', 'claude-opus-5');
$shortRequestContext->attributes->set('garad_selected_engine', EngineEnum::ANTHROPIC->value);
$app->instance('request', $shortRequestContext);
$mockCompletion->contentOverride = "## Work\n\nThe team reviewed the customer guide.";
$regression = $grounding->prepare([
    'title' => 'Short Report',
    'content' => "## Work\nPlease create a short report.\n\n"
        . "The team reviewed the customer guide.\n\nThe team reviewed the customer guide.",
    'source_facts' => [['section' => 'work', 'quote' => 'The team reviewed the customer guide.']],
]);
$mockCompletion->contentOverride = null;
$leakRemoved = ! str_contains($regression['content'], 'Please create a short report');
$duplicateRemoved = substr_count($regression['content'], 'The team reviewed the customer guide.') === 1;
$conciseReview = $reviewer->review(
    'Short Report',
    $regression['content'],
    $shortRequest,
    ['The team reviewed the customer guide.'],
);
$concisePassed = $conciseReview['status'] === 'passed'
    && $conciseReview['checks']['depth'] === 'passed';
$detailedGate = $reviewer->requiresEvidenceReview(
    'Create a detailed report.',
    '## Overview' . "\n\n" . 'The source lists three work items.',
);
$originalArkaanRequest = 'sxb waxaan rabaa inaa ii sameeyso report shaqadeyda ee Weekly report ee arkaan adoo adeegsanaya brand ga arkaan waxaan asbucan sameeyey website ka arkaan oo qeebo kala duwan ka kobnaa waxaan kudaray qurxin. waxaan kale ka qeeb galay ilaa 3 meeting mid waa main meeting ka asbuuclaha ah ee arkaan oo agends kala duwan iyo reporting la isku sameeyey midna waa waa mid aan la yeelanay baxnaano oo la rabay in loo sameeyo system lagu tala galay reporting ka ee la socoshada halka ee wax marayaan shaqooyinka midka kalena waa meeting loga hadlaayey daah furka xaflada event ga garad ai pdf ahaan igu soo sameey magaceyga waa Jabir Osman Gedi ttitle kana waa ICT Coordinator. logo da iyo brand geeda color adeegso';
$originalArkaanIntent = $intelligence->referenceForRequest($originalArkaanRequest)['id'] ?? null;
$originalArkaanReviewGate = $reviewer->requiresEvidenceReview(
    $originalArkaanRequest,
    implode("\n\n", [
        '## Dulmar',
        '## Shaqada Mareegta',
        '## Kulamada',
        '## Gunaanad',
        str_repeat('Shaqada iyo kulamada waxaa lagu soo koobay xogta uu isticmaaluhu bixiyay. ', 14),
    ]),
);
$conciseGate = ! $reviewer->requiresEvidenceReview(
    'Create a concise note.',
    'The team reviewed the customer guide.',
);
$shortWeeklyGate = ! $reviewer->requiresEvidenceReview(
    'Create a short weekly report. The team reviewed the customer guide.',
    '## Work' . "\n\n" . 'The team reviewed the customer guide.',
);
$brandInstructionRequest = 'Create a short weekly report. The team reviewed the sample website copy. Logo-da iyo midabbada brand-ka Arkaan adeegso.';
$brandRequestContext = Request::create('/', 'POST', ['raw_user_input' => $brandInstructionRequest]);
$brandRequestContext->attributes->set('garad_selected_model', 'claude-opus-5');
$brandRequestContext->attributes->set('garad_selected_engine', EngineEnum::ANTHROPIC->value);
$app->instance('request', $brandRequestContext);
$brandInstructionRegression = $grounding->prepare([
    'title' => 'Weekly Report',
    'content' => "## Work\nThe team reviewed the sample website copy.\n\n"
        . "## Additional Details\nLogo-da iyo midabbada brand-ka Arkaan.",
    'source_facts' => [
        ['section' => 'work', 'quote' => 'The team reviewed the sample website copy.'],
        ['section' => 'other', 'quote' => 'Logo-da iyo midabbada brand-ka Arkaan'],
    ],
]);
$brandInstructionRemoved = ! preg_match(
    '/logo-da|midabbada brand-ka/iu',
    $brandInstructionRegression['content'],
);
printf(
    "regressions | prompt_leak=%s | duplicate=%s | concise=%s | brand_instruction=%s | status=%s\n",
    $leakRemoved ? 'PASS' : 'FAIL',
    $duplicateRemoved ? 'PASS' : 'FAIL',
    $concisePassed ? 'PASS' : 'FAIL',
    $brandInstructionRemoved ? 'PASS' : 'FAIL',
    $conciseReview['status'],
);

foreach ([
    'prompt instruction leakage' => $leakRemoved,
    'repeated content removal' => $duplicateRemoved,
    'concise-document behavior' => $concisePassed,
    'detailed output review gate' => $detailedGate,
    'Somali Arkaan weekly report intent' => $originalArkaanIntent === 'weekly_report',
    'substantial weekly report evidence review' => $originalArkaanReviewGate,
    'concise output review exemption' => $conciseGate,
    'short weekly report review exemption' => $shortWeeklyGate,
    'Somali brand instruction filtering' => $brandInstructionRemoved,
] as $check => $passed) {
    if (! $passed) {
        $failures[] = "{$check} failed.";
    }
}

$manualRequest = 'Create a concise training manual in English based only on these fictional source notes. Title: Sample Kiosk Procedure. Audience: new volunteers. Procedure: select Start, scan the printed sample card, then select Finish to print a sample receipt. The notes do not describe security behavior. Save as a PDF.';
$manualRequestContext = Request::create('/', 'POST', ['raw_user_input' => $manualRequest]);
$manualRequestContext->attributes->set('garad_selected_model', 'claude-opus-5');
$manualRequestContext->attributes->set('garad_selected_engine', EngineEnum::ANTHROPIC->value);
$app->instance('request', $manualRequestContext);
$mockCompletion->contentOverride = "# Sample Kiosk Procedure\n\n"
    . "## Purpose\n\nThis manual helps new volunteers practise the kiosk sample procedure.\n\n"
    . "## Procedure\n\n1. Select Start.\n2. Scan the printed sample card.\n3. Select Finish to print a sample receipt.\n\n"
    . "## Limits\n\nThe notes do not describe security behavior.";
$manual = $grounding->prepare([
    'title' => 'Sample Kiosk Procedure',
    'content' => "# Sample Kiosk Procedure\n\n## Purpose\n\nThis manual helps new volunteers practise the kiosk sample procedure.\n\n"
        . "## Procedure\n\n1. Select Start.\n2. Scan the printed sample card.\n3. Select Finish to print a sample receipt.\n\n"
        . "## Limits\n\nThe source does not describe security behavior. Supervisor Maria Lopez approved the kiosk in 2024 and it prints 40 receipts per hour.",
    'source_facts' => [
        ['section' => 'identity', 'quote' => 'Audience: new volunteers'],
        ['section' => 'identity', 'quote' => 'Title: Sample Kiosk Procedure'],
        ['section' => 'work', 'quote' => 'Procedure: select Start, scan the printed sample card, then select Finish to print a sample receipt.'],
        ['section' => 'other', 'quote' => 'The notes do not describe security behavior.'],
        ['section' => 'other', 'quote' => 'fictional source notes'],
    ],
]);
$mockCompletion->contentOverride = null;
$manualText = $manual['content'] . ' ' . $manual['prepared_by'];
$authoringChecks = [
    'authored prose retained' => str_contains($manual['content'], 'helps new volunteers practise')
        && str_contains($manual['content'], '1. Select Start.'),
    'unsupported specifics removed' => ! preg_match('/Maria Lopez|2024|40 receipts|approved/u', $manualText),
    'source labels not rendered as content' => ! preg_match('/Title:|fictional source notes/iu', $manualText)
        && $manual['prepared_by'] === '',
    'no report template on a manual' => ! str_contains($manual['content'], 'Work & Activity'),
];
printf("authoring | %s\n", implode(' | ', array_map(
    static fn (string $name, bool $ok): string => $name . '=' . ($ok ? 'PASS' : 'FAIL'),
    array_keys($authoringChecks),
    $authoringChecks,
)));
foreach ($authoringChecks as $check => $passed) {
    if (! $passed) {
        $failures[] = "authoring regression failed: {$check}.";
    }
}

$unsupportedManual = $reviewer->review(
    'Sample Kiosk Procedure',
    "# Sample Kiosk Procedure\n\n## Procedure\n\n1. Select Start.\n2. Scan the printed sample card.\n3. Select Finish to print a sample receipt.\n\n"
        . 'A supervisor provides safe rehearsal and approves completion.',
    'Create a concise training manual from this source.',
    [
        'Select Start.',
        'Scan the printed sample card.',
        'Select Finish to print a sample receipt.',
    ],
);
$unsupportedManualFails = $unsupportedManual['status'] === 'needs_revision'
    && in_array('unsupported_training_detail', $unsupportedManual['failure_codes'], true);
printf("unsupported training detail | %s\n", $unsupportedManualFails ? 'PASS' : 'FAIL');
if (! $unsupportedManualFails) {
    $failures[] = 'Unsupported training procedures were not hard-failed.';
}
$unsupportedAuthoredManualRequest = 'Create a detailed training manual in English for new volunteers using the sample kiosk. Title: Sample Kiosk Procedure. Audience: new volunteers. Procedure: select Start, scan the printed sample card, then select Finish to print a sample receipt. The notes do not describe security behavior or troubleshooting.';
$unsupportedAuthoredManualContext = Request::create('/', 'POST', [
    'raw_user_input' => $unsupportedAuthoredManualRequest,
]);
$unsupportedAuthoredManualContext->attributes->set('garad_selected_model', 'gpt-5.5');
$unsupportedAuthoredManualContext->attributes->set('garad_selected_engine', EngineEnum::OPEN_AI->value);
$app->instance('request', $unsupportedAuthoredManualContext);
$unsupportedAuthoredManualBlocked = false;
try {
    $grounding->prepare([
        'title' => 'Sample Kiosk Procedure',
        'content' => "## Purpose and Audience\n\nThis manual introduces new volunteers to the sample kiosk procedure. It explains the three actions covered by the source notes and states what the notes do not cover.\n\n"
            . "## Before You Begin\n\nYou will need the printed sample card. The procedure is a practice workflow, so the receipt it prints is a sample receipt.\n\n"
            . "## Procedure\n\n1. Select Start on the kiosk.\n2. Scan the printed sample card.\n3. Select Finish to print a sample receipt.\n\n"
            . "## Limits of This Manual\n\nThe source notes do not describe security behavior or troubleshooting steps. Volunteers should not assume any behavior beyond the three actions above.",
        'source_facts' => [
            ['section' => 'other', 'quote' => 'Audience: new volunteers'],
            ['section' => 'work', 'quote' => 'Procedure: select Start, scan the printed sample card, then select Finish to print a sample receipt.'],
            ['section' => 'other', 'quote' => 'The notes do not describe security behavior or troubleshooting.'],
        ],
    ]);
} catch (RuntimeException) {
    $unsupportedAuthoredManualBlocked = true;
}
printf("authored manual unsupported-claim gate | %s\n", $unsupportedAuthoredManualBlocked ? 'PASS' : 'FAIL');
if (! $unsupportedAuthoredManualBlocked) {
    $failures[] = 'The model review could waive unsupported training advice from the authored manual.';
}

$missingTable = $reviewer->review(
    'Supplied Values',
    "## Supplied Values\n\nThe source lists two ledger values.",
    'Create a financial report.',
    [],
    false,
    ['table' => 1],
);
$missingTableFails = $missingTable['status'] === 'needs_revision'
    && in_array('required_semantic_block_missing', $missingTable['failure_codes'], true);
if (! $missingTableFails) {
    $failures[] = 'A required table block could disappear without failing quality review.';
}
$headingsOnlyReview = $reviewer->review(
    'Detailed Report',
    "# Detailed Report\n\n## Summary\n\n## Findings\n\n## Conclusion",
    'Create a detailed report from this source.',
    [],
    true,
);
$headingsOnlyFails = $headingsOnlyReview['status'] === 'needs_revision'
    && $headingsOnlyReview['checks']['structure'] === 'failed'
    && in_array('missing_semantic_structure', $headingsOnlyReview['failure_codes'], true);
if (! $headingsOnlyFails) {
    $failures[] = 'A headings-only document passed structural quality review.';
}

$authoringReview = app(GaradDocumentAuthoringReviewService::class);
$passingModelReview = $authoringReview->reviewAndRevise(
    request: 'Write a concise note.',
    documentType: 'generic_report',
    title: 'Customer Guide Note',
    verifiedSourceFacts: ['The team reviewed the customer guide.'],
    draft: 'The team reviewed the customer guide.',
    modelId: 'claude-opus-5',
    engineId: EngineEnum::ANTHROPIC->value,
);
$modelSelectionPassed = $mockCompletion->lastEngine === EngineEnum::ANTHROPIC
    && $mockCompletion->lastModel === 'claude-opus-5';
$openAiModelReview = $authoringReview->reviewAndRevise(
    request: 'Write a detailed weekly report.',
    documentType: 'weekly_report',
    title: 'Weekly Report',
    verifiedSourceFacts: ['The team reviewed the customer guide.'],
    draft: "## Summary\n\nThe team reviewed the customer guide.",
    modelId: 'gpt-5.5',
    engineId: EngineEnum::OPEN_AI->value,
);
$openAiModelSelectionPassed = $mockCompletion->lastEngine === EngineEnum::OPEN_AI
    && $mockCompletion->lastModel === 'gpt-5.5'
    && $openAiModelReview['metadata']['status'] === 'passed';
$mockCompletion->returnPassingReview = false;
$failedReviewRejected = false;
try {
    $authoringReview->reviewAndRevise(
        request: 'Write a concise note.',
        documentType: 'generic_report',
        title: 'Customer Guide Note',
        verifiedSourceFacts: ['The team reviewed the customer guide.'],
        draft: 'The team reviewed the customer guide.',
        modelId: 'claude-opus-5',
        engineId: EngineEnum::ANTHROPIC->value,
    );
} catch (RuntimeException) {
    $failedReviewRejected = true;
} finally {
    $mockCompletion->returnPassingReview = true;
}
$mockCompletion->returnPassingDepth = false;
$shallowDetailedReviewRejected = false;
try {
    $authoringReview->reviewAndRevise(
        request: 'Write a detailed training manual.',
        documentType: 'training',
        title: 'Sample Kiosk Procedure',
        verifiedSourceFacts: ['Select Start.', 'Scan the printed sample card.'],
        draft: '## Procedure' . "\n\n" . 'Select Start and scan the printed sample card.',
        modelId: 'claude-opus-5',
        engineId: EngineEnum::ANTHROPIC->value,
    );
} catch (RuntimeException) {
    $shallowDetailedReviewRejected = true;
} finally {
    $mockCompletion->returnPassingDepth = true;
}
printf(
    "model-assisted evidence review | selected_model=%s | selected_OpenAI_model=%s | failed_review_blocked=%s | depth_failure_blocked=%s | missing_table_blocked=%s | headings_only_blocked=%s\n",
    $modelSelectionPassed ? 'PASS' : 'FAIL',
    $openAiModelSelectionPassed ? 'PASS' : 'FAIL',
    $failedReviewRejected ? 'PASS' : 'FAIL',
    $shallowDetailedReviewRejected ? 'PASS' : 'FAIL',
    $missingTableFails ? 'PASS' : 'FAIL',
    $headingsOnlyFails ? 'PASS' : 'FAIL',
);
if (! $modelSelectionPassed
    || ! $openAiModelSelectionPassed
    || ! $failedReviewRejected
    || ! $shallowDetailedReviewRejected
    || ! $missingTableFails
    || ! $headingsOnlyFails
    || $passingModelReview['metadata']['status'] !== 'passed') {
    $failures[] = 'Model-assisted evidence review did not preserve selected-model routing or fail closed.';
}

$budgetRequest = Request::create('/', 'POST');
$app->instance('request', $budgetRequest);
$budget = app(ProviderRequestBudgetService::class);
$budget->initializeChatPro($budgetRequest);
$budgetPayload = $budget->preparePayload([
    'model' => 'claude-opus-5',
    'system' => 'evidence review',
    'messages' => [['role' => 'user', 'content' => str_repeat('x', 18000)]],
    'max_tokens' => 9000,
]);
$outputCapPassed = $budgetPayload['max_tokens'] === ProviderRequestBudgetService::MAX_OUTPUT_TOKENS;
$gpt55MaximumEstimatedCost = ProviderRequestBudgetService::MAX_PROVIDER_REQUESTS * (
    ProviderRequestBudgetService::MAX_INPUT_TOKENS * 5
    + ProviderRequestBudgetService::MAX_OUTPUT_TOKENS * 30
) / 1000000;
$costCeilingPassed = $gpt55MaximumEstimatedCost <= 0.6072;
$inputCeilingPassed = strlen(json_encode(
    $budgetPayload,
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
)) <= ProviderRequestBudgetService::MAX_SERIALIZED_INPUT_BYTES;
$oversizedInputBlocked = false;
try {
    $oversizedPayload = $budget->preparePayload([
        'model' => 'claude-opus-5',
        'messages' => [['role' => 'user', 'content' => str_repeat('x', 19500)]],
    ]);
    // Oversized single messages are no longer rejected internally: the provider enforces its real limit.
    $oversizedInputBlocked = ($oversizedPayload['messages'][0]['content'] ?? '') === str_repeat('x', 19500);
} catch (RuntimeException) {
    $oversizedInputBlocked = false;
}
$remoteImageBlocked = false;
try {
    $budget->preparePayload([
        'messages' => [[
            'role' => 'user',
            'content' => [['type' => 'image_url', 'image_url' => 'https://example.invalid/logo.png']],
        ]],
    ]);
} catch (RuntimeException) {
    $remoteImageBlocked = true;
}
$inlineImageAccepted = false;
try {
    $inlineImageBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/x7cAAAAASUVORK5CYII=';
    $inlineImagePayload = $budget->preparePayload([
        'messages' => [[
            'role' => 'user',
            'content' => [[
                'type' => 'input_image',
                'image_url' => 'data:image/png;base64,' . $inlineImageBase64,
            ]],
        ]],
    ]);
    $inlineImageAccepted = str_contains($inlineImagePayload['messages'][0]['content'][0]['image_url'], $inlineImageBase64);
} catch (RuntimeException) {
    $inlineImageAccepted = false;
}
$budget->recordProviderRequest();
$budget->recordProviderRequest();
$budget->recordProviderRequest();
$fourthRequestBlocked = false;
try {
    $budget->recordProviderRequest();
} catch (RuntimeException) {
    $fourthRequestBlocked = true;
}
$history = [
    ['role' => 'system', 'content' => 'Active instructions'],
];
for ($index = 0; $index < 10; $index++) {
    $history[] = ['role' => $index % 2 === 0 ? 'user' : 'assistant', 'content' => "Context {$index}"];
}
$cappedHistory = $budget->capConversationHistory($history);
$historyCapPassed = count($cappedHistory) === 9
    && ($cappedHistory[0]['role'] ?? null) === 'system'
    && ($cappedHistory[1]['content'] ?? null) === 'Context 2';
printf(
    "provider request budget | output_cap=%s | input_cap=%s | within_ceiling=%s | remote_image=%s | inline_image=%s | three_request_limit=%s | history_cap=%s | GPT-5.5 cost ceiling=%s ($%.4f <= $0.6072) | text_input_ceiling=%d JSON bytes + %d-token reserve\n",
    $outputCapPassed ? 'PASS' : 'FAIL',
    $oversizedInputBlocked ? 'PASS' : 'FAIL',
    $inputCeilingPassed ? 'PASS' : 'FAIL',
    $remoteImageBlocked ? 'PASS' : 'FAIL',
    $inlineImageAccepted ? 'PASS' : 'FAIL',
    $fourthRequestBlocked ? 'PASS' : 'FAIL',
    $historyCapPassed ? 'PASS' : 'FAIL',
    $costCeilingPassed ? 'PASS' : 'FAIL',
    $gpt55MaximumEstimatedCost,
    ProviderRequestBudgetService::MAX_SERIALIZED_INPUT_BYTES,
    ProviderRequestBudgetService::TOKENIZER_OVERHEAD_RESERVE,
);
if (! $outputCapPassed
    || ! $oversizedInputBlocked
    || ! $inputCeilingPassed
    || ! $remoteImageBlocked
    || ! $inlineImageAccepted
    || ! $fourthRequestBlocked
    || ! $historyCapPassed
    || ! $costCeilingPassed) {
    $failures[] = 'Provider token or request-count limits were not enforced by the budget service.';
}

$anthropicAdapterRequest = Request::create('/', 'POST');
$app->instance('request', $anthropicAdapterRequest);
$budget->initializeChatPro($anthropicAdapterRequest);
$capturedAnthropicPayload = null;
Http::fake([
    'https://api.anthropic.com/v1/messages' => static function (HttpRequest $request) use (&$capturedAnthropicPayload) {
        $capturedAnthropicPayload = $request->data();

        return Http::response(['content' => [['type' => 'text', 'text' => '{}']]], 200);
    },
]);
$anthropicAdapterResponse = app(AnthropicService::class)
    ->setKey('synthetic-test-key')
    ->setModel('claude-opus-5')
    ->setSystem('Synthetic provider adapter test.')
    ->setMessages([['role' => 'user', 'content' => 'No provider call is made.']])
    ->setStream(false)
    ->stream();
$anthropicAdapterPassed = $anthropicAdapterResponse->successful()
    && ($capturedAnthropicPayload['max_tokens'] ?? null) === ProviderRequestBudgetService::MAX_OUTPUT_TOKENS
    && strlen(json_encode(
        $capturedAnthropicPayload,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
    )) <= ProviderRequestBudgetService::MAX_SERIALIZED_INPUT_BYTES
    && (int) $anthropicAdapterRequest->attributes->get('garad_provider_request_count') === 1;
printf("Anthropic adapter limit enforcement | %s\n", $anthropicAdapterPassed ? 'PASS' : 'FAIL');
if (! $anthropicAdapterPassed) {
    $failures[] = 'The selected Anthropic provider adapter did not enforce output, input, and request-count limits.';
}

$streamReflection = new ReflectionClass(StreamService::class);
$streamInstance = $streamReflection->newInstanceWithoutConstructor();
$historyNormalizer = $streamReflection->getMethod('anthropicMessagesWithImages');
$anthropicHistory = $historyNormalizer->invoke($streamInstance, [
    [
        'role' => 'user',
        'content' => [
            ['type' => 'input_text', 'text' => 'Create a PDF.'],
            ['type' => 'input_image', 'image_url' => 'data:image/png;base64,YWJj'],
        ],
    ],
    ['role' => 'assistant', 'content' => 'I can help with that.'],
]);
$anthropicHistoryPassed = ($anthropicHistory[0]['content'][0]['type'] ?? null) === 'text'
    && ($anthropicHistory[0]['content'][0]['text'] ?? null) === 'Create a PDF.'
    && ($anthropicHistory[0]['content'][1]['type'] ?? null) === 'image'
    && ($anthropicHistory[0]['content'][1]['source']['type'] ?? null) === 'base64'
    && ($anthropicHistory[1]['content'] ?? null) === 'I can help with that.';
printf("anthropic history normalization | %s\n", $anthropicHistoryPassed ? 'PASS' : 'FAIL');
if (! $anthropicHistoryPassed) {
    $failures[] = 'Anthropic history normalization left incompatible message content in the provider payload.';
}

$openAiBudgetRequest = Request::create('/', 'POST');
$app->instance('request', $openAiBudgetRequest);
$budget->initializeChatPro($openAiBudgetRequest);
$openAiPayloadMethod = $streamReflection->getMethod('prepareChatProProviderPayload');
$openAiPayload = $openAiPayloadMethod->invoke($streamInstance, [
    'model' => 'gpt-5.5',
    'messages' => [['role' => 'user', 'content' => 'Synthetic budget regression.']],
    'max_tokens' => 12000,
]);
$openAiAdapterPassed = ($openAiPayload['max_tokens'] ?? null) === ProviderRequestBudgetService::MAX_OUTPUT_TOKENS
    && (int) $openAiBudgetRequest->attributes->get('garad_provider_request_count') === 1
    && strlen(json_encode(
        $openAiPayload,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
    )) <= ProviderRequestBudgetService::MAX_SERIALIZED_INPUT_BYTES;
printf("OpenAI request budget adapter | %s\n", $openAiAdapterPassed ? 'PASS' : 'FAIL');
if (! $openAiAdapterPassed) {
    $failures[] = 'OpenAI request budget adapter did not enforce output, input, and request-count limits.';
}

$originalArkaanBudgetRequest = Request::create('/', 'POST', [
    'raw_user_input' => $originalArkaanRequest,
    'garad_selected_tool' => 'pdf',
]);
$app->instance('request', $originalArkaanBudgetRequest);
$budget->initializeChatPro($originalArkaanBudgetRequest);
\Illuminate\Support\Facades\Auth::loginUsingId(2);
$originalArkaanTools = \App\Extensions\AIChatPro\System\Services\AiChatProService::toolsForPrompt($originalArkaanRequest);
$originalArkaanToolNames = array_values(array_column($originalArkaanTools, 'name'));
$logoData = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/x7cAAAAASUVORK5CYII=';
$originalArkaanPayload = $budget->preparePayload([
    'model' => 'gpt-5.5',
    'messages' => [
        ['role' => 'system', 'content' => \App\Extensions\AIChatPro\System\Services\AiChatProService::systemInstruction()],
        ['role' => 'user', 'content' => [
            ['type' => 'text', 'text' => $originalArkaanRequest],
            ['type' => 'image_url', 'image_url' => $logoData],
        ]],
    ],
    'tools' => $originalArkaanTools,
    'max_tokens' => ProviderRequestBudgetService::MAX_OUTPUT_TOKENS,
]);
$originalArkaanBudgetPassed = $originalArkaanToolNames === ['create_pdf']
    && strlen(json_encode(
        $originalArkaanPayload,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
    )) <= ProviderRequestBudgetService::MAX_SERIALIZED_INPUT_BYTES;
printf("Original Somali PDF request budget fit | %s | tools=%s\n",
    $originalArkaanBudgetPassed ? 'PASS' : 'FAIL',
    implode(',', $originalArkaanToolNames),
);
\Illuminate\Support\Facades\Auth::logout();
if (! $originalArkaanBudgetPassed) {
    $failures[] = 'The original Somali PDF request did not fit its bounded provider budget with a minimal relevant tool set.';
}

// ---- Chat Pro regression: no arbitrary internal budget on ordinary chat ----
$app->instance('request', Request::create('/', 'POST'));
$chatBudget = app(ProviderRequestBudgetService::class);
$shortMessage = [['role' => 'user', 'content' => 'Hello']];
$shortChatPassed = $chatBudget->fitHistoryToContextWindow($shortMessage, 'gpt-5.5') === $shortMessage;
$longText = str_repeat('Long source paragraph. ', 6000);
$longPayload = $chatBudget->preparePayload([
    'model' => 'gpt-5.5',
    'messages' => [['role' => 'user', 'content' => $longText]],
    'max_tokens' => 12000,
]);
$longMessagePassed = $longPayload['messages'][0]['content'] === $longText && $longPayload['max_tokens'] === 12000;
for ($i = 0; $i < 10; $i++) {
    $chatBudget->recordProviderRequest();
}
$unscopedCountPassed = true;
$bigHistory = [['role' => 'system', 'content' => 'sys']];
for ($i = 0; $i < 12; $i++) {
    $bigHistory[] = ['role' => $i % 2 ? 'assistant' : 'user', 'content' => str_repeat("turn{$i} ", 30000)];
}
$bigHistory[] = ['role' => 'user', 'content' => $longText];
$fitted = $chatBudget->fitHistoryToContextWindow($bigHistory, 'claude-opus-5');
$historyAdaptPassed = ($fitted[0]['role'] ?? null) === 'system'
    && end($fitted)['content'] === $longText
    && count($fitted) < count($bigHistory)
    && count($fitted) > 2
    && str_contains($fitted[count($fitted) - 2]['content'], 'turn11');
$unrelated = $chatBudget->fitHistoryToContextWindow($bigHistory, 'gpt-5.5');
$largeWindowKeepsHistory = count($unrelated) >= count($fitted);
printf("chat pro unrestricted | short=%s | long_message=%s | unscoped_requests=%s | history_adapt=%s | large_window=%s\n",
    $shortChatPassed ? 'PASS' : 'FAIL', $longMessagePassed ? 'PASS' : 'FAIL',
    $unscopedCountPassed ? 'PASS' : 'FAIL', $historyAdaptPassed ? 'PASS' : 'FAIL',
    $largeWindowKeepsHistory ? 'PASS' : 'FAIL');
if (! $shortChatPassed || ! $longMessagePassed || ! $historyAdaptPassed || ! $largeWindowKeepsHistory) {
    $failures[] = 'Ordinary Chat Pro requests are still restricted by the internal budget or history was not adapted correctly.';
}

// Scoped evidence-review budget is released after the review call.
$scopeRequest = Request::create('/', 'POST');
$app->instance('request', $scopeRequest);
$chatBudget->initializeChatPro($scopeRequest);
$chatBudget->endScope($scopeRequest);
$scopeReleasedPassed = ! $scopeRequest->attributes->has('garad_provider_request_max');
printf("evidence review budget scope released | %s\n", $scopeReleasedPassed ? 'PASS' : 'FAIL');
if (! $scopeReleasedPassed) {
    $failures[] = 'Evidence-review budget scope leaked beyond the review call.';
}

// A failing provider inside the OpenAI chat stream must produce an explicit SSE error, not a silent end.
$streamErrorPassed = false;
try {
    $failingMessage = new class {
        public function __get($name)
        {
            throw new RuntimeException('Synthetic provider failure');
        }
    };
    $openAiStreamMethod = (new ReflectionClass(StreamService::class))->getMethod('openaiChatStream');
    $failingStream = $openAiStreamMethod->invoke(
        (new ReflectionClass(StreamService::class))->newInstanceWithoutConstructor(),
        'gpt-5-mini', [], $failingMessage, 'chatPro', false, [],
    );
    $streamBody = '';
    ob_start(static function (string $chunk) use (&$streamBody): string {
        $streamBody .= $chunk;

        return '';
    });
    $failingStream->sendContent();
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
    $streamErrorPassed = str_contains($streamBody, 'event: error')
        && str_contains($streamBody, 'provider_stream_failed')
        && str_contains($streamBody, 'Synthetic provider failure')
        && str_contains($streamBody, '[DONE]');
} catch (Throwable $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    fwrite(STDERR, 'stream test harness error: ' . $e->getMessage() . PHP_EOL);
}
printf("stream failure surfaces explicit error | %s\n", $streamErrorPassed ? 'PASS' : 'FAIL');
if (! $streamErrorPassed) {
    $failures[] = 'A provider stream failure did not produce an explicit error event.';
}

// Provider matrix over the real selector list (no network, no provider calls).
$matrixFailures = [];
$matrixRows = [];
\Illuminate\Support\Facades\Auth::loginUsingId(2);
foreach (\App\Domains\Entity\Models\Entity::planModels() as $model) {
    $key = $model->key ?? $model->getAttribute('key') ?? null;
    $enum = $key instanceof \App\Domains\Entity\Enums\EntityEnum ? $key : \App\Domains\Entity\Enums\EntityEnum::tryFrom((string) $key);
    $key = $enum?->value ?? '?';
    $engine = $enum?->engine()?->value;
    $matrixRows[] = ($key ?? '?') . ' -> ' . ($engine ?? 'UNRESOLVED');
    if ($enum === null || $engine === null) {
        $matrixFailures[] = "Selector model {$key} does not resolve to a provider engine.";
    }
}
\Illuminate\Support\Facades\Auth::logout();
printf("selector models resolve to provider | %s (%d) %s\n", $matrixFailures === [] ? 'PASS' : 'FAIL', count($matrixRows), implode('; ', $matrixRows));
$failures = array_merge($failures, $matrixFailures);

// Document-only authoring instructions must not be sent with ordinary chat.
$instructionRequest = static function (string $prompt, string $tool = ''): string {
    $request = Request::create('/chat', 'POST', ['raw_user_input' => $prompt, 'garad_selected_tool' => $tool]);
    app()->instance('request', $request);

    return \App\Extensions\AIChatPro\System\Services\AiChatProService::systemInstruction();
};
$ordinary = $instructionRequest('What is the capital of Kenya?');
$document = $instructionRequest('Create a PDF report about our three meetings.');
$scopedPassed = strlen($document) > strlen($ordinary) + 500;
printf("document instructions scoped to document requests | %s (%d vs %d chars)\n", $scopedPassed ? 'PASS' : 'FAIL', strlen($ordinary), strlen($document));
if (! $scopedPassed) {
    $failures[] = 'Document-only instructions are still sent with ordinary chat or missing for document requests.';
}

// DeepSeek/OpenRouter tool format and requested-tool detection.
app()->instance('request', Request::create('/chat', 'POST', ['raw_user_input' => 'Create a PDF report', 'garad_selected_tool' => 'pdf']));
\Illuminate\Support\Facades\Auth::loginUsingId(2);
$ccTools = \App\Extensions\AIChatPro\System\Services\AiChatProService::chatCompletionsTools('Create a PDF report');
$ccPassed = count($ccTools) === 1 && ($ccTools[0]['function']['name'] ?? '') === 'create_pdf'
    && \App\Extensions\AIChatPro\System\Services\AiChatProService::requestedDocumentTool('Create a PDF report') === 'create_pdf'
    && (function () {
        app()->instance('request', Request::create('/chat', 'POST', ['raw_user_input' => 'Hello there']));

        return \App\Extensions\AIChatPro\System\Services\AiChatProService::requestedDocumentTool('Hello there') === null;
    })();
\Illuminate\Support\Facades\Auth::logout();
printf("chat-completions document tool wiring | %s\n", $ccPassed ? 'PASS' : 'FAIL');
if (! $ccPassed) {
    $failures[] = 'Chat-completions tool wiring for DeepSeek/OpenRouter is incorrect.';
}

// guardProviderStream must convert a throwing provider stream into an explicit SSE error for every engine.
$guardPassed = true;
$guard = (new ReflectionClass(StreamService::class))->getMethod('guardProviderStream');
foreach (['anthropic', 'gemini', 'deepseek', 'xai', 'openrouter'] as $engineName) {
    $inner = new \Symfony\Component\HttpFoundation\StreamedResponse(static function (): void {
        throw new RuntimeException('Synthetic ' . 'upstream failure');
    });
    $guarded = $guard->invoke((new ReflectionClass(StreamService::class))->newInstanceWithoutConstructor(), $inner, $engineName, 'test-model');
    $body = '';
    ob_start(static function (string $chunk) use (&$body): string {
        $body .= $chunk;

        return '';
    });
    $guarded->sendContent();
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
    $guardPassed = $guardPassed && str_contains($body, 'event: error') && str_contains($body, 'Synthetic upstream failure') && str_contains($body, '[DONE]');
}
printf("all provider streams surface explicit errors | %s\n", $guardPassed ? 'PASS' : 'FAIL');
if (! $guardPassed) {
    $failures[] = 'A provider stream guard did not surface an explicit error.';
}

echo 'TOTALS ' . json_encode($totals, JSON_UNESCAPED_UNICODE) . PHP_EOL;
$allExamplesLabeled = count($exampleLibrary['examples'] ?? []) === 10;
foreach ($exampleLibrary['examples'] ?? [] as $example) {
    $allExamplesLabeled = $allExamplesLabeled
        && ($example['synthetic_label'] ?? null) === $exampleLibrary['synthetic_label'];
}
echo 'EXAMPLE_LIBRARY ' . ($allExamplesLabeled ? 'PASS' : 'FAIL') . ' '
    . count($exampleLibrary['examples'] ?? []) . ' synthetic-labeled entries' . PHP_EOL;
if (! $allExamplesLabeled) {
    $failures[] = 'The synthetic example library contains missing or incorrect provenance labels.';
}
if ($failures !== []) {
    fwrite(STDERR, "FAILURES:\n- " . implode("\n- ", $failures) . PHP_EOL);
    exit(1);
}

echo "All evaluation checks passed.\n";
