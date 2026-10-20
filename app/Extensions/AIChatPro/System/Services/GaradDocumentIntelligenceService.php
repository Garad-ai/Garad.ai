<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Services;

final class GaradDocumentIntelligenceService
{
    public const VERSION = '1.0.0';

    private const EXAMPLES = [
        'weekly_report' => [
            'label' => 'weekly work report',
            'patterns' => ['/\bweekly\s+(?:work\s+)?reports?\b/iu', '/\bwarbixin(?:ta)?\s+toddobaadl\p{L}*/iu'],
            'outline' => ['Purpose and reporting period, if supplied', 'Executive summary', 'Work grouped by area or outcome stated in the sources', 'Meetings or coordination, when supplied', 'Grounded closing summary'],
            'checks' => ['Keep distinct activities distinct', 'Do not add a duplicate activity log', 'Do not infer completion or next-week plans'],
        ],
        'annual_report' => [
            'label' => 'annual report',
            'patterns' => ['/\bannual\s+reports?\b/iu', '/\bwarbixin(?:ta)?\s+sannadl\p{L}*/iu'],
            'outline' => ['Reporting period and organization, if supplied', 'Executive summary', 'Activities and results supported by evidence', 'Financial or operational information, when supplied', 'Challenges and priorities, only when supplied', 'Closing summary'],
            'checks' => ['Keep outcomes distinct from activities', 'Do not infer metrics, approvals, or year-over-year change'],
        ],
        'proposal' => [
            'label' => 'proposal',
            'patterns' => ['/\bproposals?\b/iu', '/\bsoo\s+jeedin\p{L}*/iu'],
            'outline' => ['Purpose and audience', 'Need or opportunity stated by the user', 'Proposed approach', 'Scope and deliverables', 'Schedule and budget, only when supplied', 'Assumptions and open questions'],
            'checks' => ['Label proposed content as proposed', 'Do not invent costs, commitments, approvals, or dates'],
        ],
        'business_plan' => [
            'label' => 'business plan',
            'patterns' => ['/\bbusiness\s+plans?\b/iu', '/\bqorshe(?:ha)?\s+ganacsi\b/iu'],
            'outline' => ['Executive summary', 'Business description and offering', 'Market evidence, only when supplied', 'Operations and staffing, when supplied', 'Financial plan, only from supplied values', 'Risks and assumptions'],
            'checks' => ['Separate evidence from assumptions', 'Do not fabricate market data or financial projections'],
        ],
        'research' => [
            'label' => 'research paper',
            'patterns' => ['/\bresearch\s+papers?\b/iu', '/\bacademic\s+papers?\b/iu'],
            'outline' => ['Abstract', 'Research question and background', 'Methods, only if supplied', 'Findings, only if supported by supplied evidence', 'Limitations', 'References only for verifiable sources supplied or retrieved'],
            'checks' => ['Never invent citations, methodology, sample sizes, or findings'],
        ],
        'financial_report' => [
            'label' => 'financial report',
            'patterns' => ['/\bfinancial\s+reports?\b/iu', '/\bfinance\s+reports?\b/iu'],
            'outline' => ['Reporting period and entity, if supplied', 'Executive summary', 'Financial statements or tables from supplied values', 'Variance or trend analysis only when comparable data exists', 'Notes and limitations'],
            'checks' => ['Preserve units and currency', 'Never calculate from missing inputs or invent financial values'],
        ],
        'cv' => [
            'label' => 'CV or resume',
            'patterns' => ['/\b(?:cv|curriculum\s+vitae|resume|résumé)\b/iu'],
            'outline' => ['Name and contact details, if supplied', 'Professional summary based on supplied experience', 'Experience', 'Education', 'Skills and credentials, when supplied'],
            'checks' => ['Do not invent employers, qualifications, dates, contact details, or achievements'],
        ],
        'technical_manual' => [
            'label' => 'technical manual',
            'patterns' => ['/\btechnical\s+manuals?\b/iu', '/\buser\s+guides?\b/iu', '/\bhandbooks?\b/iu'],
            'outline' => ['Scope and prerequisites', 'Procedures in safe sequence', 'Expected results only when documented', 'Troubleshooting based on supplied evidence', 'Glossary, when useful'],
            'checks' => ['Do not invent commands, system behavior, or safety guarantees'],
        ],
        'training' => [
            'label' => 'training material',
            'patterns' => ['/\btraining\s+(?:materials?|guides?|manuals?)\b/iu', '/\bcurriculum\b/iu'],
            'outline' => ['Audience and learning objectives, if supplied', 'Concepts and demonstrations', 'Practice activities', 'Assessment criteria, if supplied', 'Further resources, only if verifiable'],
            'checks' => ['Distinguish proposed exercises from established policy'],
        ],
        'long_form_report' => [
            'label' => 'long-form report',
            'patterns' => ['/\b(?:detailed|comprehensive|in[- ]depth|long[- ]form)\s+reports?\b/iu', '/\bbook\b/iu'],
            'outline' => ['Purpose and scope', 'Executive summary or introduction', 'Thematic sections grounded in supplied material', 'Evidence, examples, and limitations', 'Conclusion that synthesizes rather than repeats'],
            'checks' => ['Develop only the evidence available', 'Do not add length through repetition or unsupported assumptions'],
        ],
        'generic_report' => [
            'label' => 'report',
            'patterns' => ['/\b(?:reports?|summaries)\b/iu'],
            'outline' => ['Purpose and scope', 'Summary of relevant source information', 'Thematic sections appropriate to the request', 'Limitations or missing information, when relevant', 'Grounded conclusion'],
            'checks' => ['Use only sections supported by the request and evidence', 'Do not force the weekly-report template onto other report types'],
        ],
    ];

    public function referenceForRequest(string $request): ?array
    {
        $request = trim($request);
        if ($request === '') {
            return null;
        }
        if (! preg_match(
            '/\b(?:create|write|draft|prepare|generate|make|produce|export|revise|edit|improve|format|design|translate|proofread|rewrite)\b/iu',
            $request,
        ) && ! preg_match(
            '/\b(?:ii\s+(?:samee|diyaari)\p{L}*|iigu\s+(?:qor|diyaari)\p{L}*|ku\s+(?:diyaari|qor|samee)\p{L}*)\b/iu',
            $request,
        )) {
            return null;
        }

        foreach (self::EXAMPLES as $key => $example) {
            foreach ($example['patterns'] as $pattern) {
                if (preg_match($pattern, $request)) {
                    $syntheticExample = $this->exampleLibrary()['examples'][$key] ?? [];

                    return [
                        'id' => $key,
                        'label' => $example['label'],
                        'outline' => $example['outline'],
                        'checks' => $example['checks'],
                        'example' => $syntheticExample,
                    ];
                }
            }
        }

        return null;
    }

    public function instructionForRequest(string $request, ?string $preferredLanguage = null): string
    {
        $reference = $this->referenceForRequest($request);
        if ($reference === null) {
            return '';
        }

        $outline = implode("\n", array_map(static fn (string $item): string => '- ' . $item, $reference['outline']));
        $checks = implode("\n", array_map(static fn (string $item): string => '- ' . $item, $reference['checks']));
        $depth = preg_match('/\b(?:detailed|comprehensive|in[- ]depth|long[- ]form)\b/iu', $request)
            ? 'Develop the sections using all distinct supported source details; do not substitute generic filler.'
            : 'Match the length to the amount of supplied evidence; concision is appropriate when the source is brief.';
        $language = $this->requestedLanguage($request)
            ?? (in_array($preferredLanguage, ['English', 'Somali'], true) ? $preferredLanguage : null)
            ?? $this->inferSourceLanguage($request);
        $example = $reference['example'] ?? [];
        $sample = $example['localized_examples'][$language] ?? null;
        if (! is_string($sample) && (($language === ($example['language'] ?? null)) || $language === 'English')) {
            $sample = $example['finished_reference'] ?? null;
        }
        $styleNotes = implode("\n", array_map(
            static fn (string $item): string => '- ' . $item,
            $example['style_notes'] ?? [],
        ));
        $qualityCriteria = implode("\n", array_map(
            static fn (string $item): string => '- ' . $item,
            $example['quality_criteria'] ?? [],
        ));
        $syntheticReference = is_string($sample) && $sample !== ''
            ? "\n\nSYNTHETIC — NOT AN APPROVED GOLD STANDARD.\n"
                . "Use the following reference only to observe structure and presentation. Its names, dates, amounts, activities, and conclusions are fictional and MUST NOT be transferred into the user's document.\n"
                . $sample
            : '';

        return "\n\nDocument Intelligence Skill v" . self::VERSION . "\n"
            . "Relevant structural pattern: {$reference['label']}. This pattern guides structure only; it is not a source of facts.\n"
            . ($language !== null ? "Requested document language: {$language}. Follow it even when source notes use another language.\n" : '')
            . "{$depth}\n"
            . "Build a private outline, then draft and review the final deliverable. Do not reveal chain-of-thought or include the outline unless requested.\n"
            . "Structure cues:\n{$outline}\n"
            . "Quality checks:\n{$checks}\n"
            . "Style notes:\n{$styleNotes}\n"
            . "Example-specific quality criteria:\n{$qualityCriteria}\n"
            . "Use only facts from the user request and relevant supplied files. Mark uncertain material as unknown or omit it. Never copy example facts.\n"
            . $syntheticReference;
    }

    public function requestedLanguage(string $request): ?string
    {
        return match (true) {
            (bool) preg_match('/\b(?:english\s+(?:ka\s+dhig|ku\s+qor)|in\s+english|write\s+(?:it\s+)?in\s+english)\b/iu', $request) => 'English',
            (bool) preg_match('/\b(?:somali|soomaali)\s+(?:ka\s+dhig|ku\s+qor)|\bin\s+somali\b/iu', $request) => 'Somali',
            (bool) preg_match('/\b(?:in\s+arabic|write\s+(?:it\s+)?in\s+arabic)\b|(?:العربية|بالعربية|اكتب\s+بالعربية)/iu', $request) => 'Arabic',
            (bool) preg_match('/\b(?:in\s+french|write\s+(?:it\s+)?in\s+french|en\s+français)\b/iu', $request) => 'French',
            (bool) preg_match('/\b(?:in\s+spanish|write\s+(?:it\s+)?in\s+spanish|en\s+español)\b/iu', $request) => 'Spanish',
            default => null,
        };
    }

    private function inferSourceLanguage(string $request): string
    {
        if (preg_match('/(?:[\x{0600}-\x{06FF}])/u', $request)) {
            return 'Arabic';
        }
        if (preg_match('/\b(?:waxaan|waxaa|shaqadayda|warbixin|asbuucan|toddobaad)\b/iu', $request)) {
            return 'Somali';
        }

        return 'English';
    }

    private function exampleLibrary(): array
    {
        static $library = null;
        if ($library === null) {
            $library = require __DIR__ . '/DocumentIntelligence/examples_v1.php';
        }

        return $library;
    }
}
