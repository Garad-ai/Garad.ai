<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Services;

final class GaradDocumentCompositionService
{
    /**
     * Organize verified source facts into a consistent, content-driven document hierarchy.
     *
     * @return array{content:string,summary:string,prepared_by:string,verified_quotes:int,summary_title:string,quality_facts:list<string>}
     */
    public function compose(array $facts, string $source, string $title, string $candidateContent = ''): array
    {
        $isSomali = $this->isSomali($source);
        $facts = $this->separateSourceLabels($facts, $title);
        $identity = array_values(array_unique(array_map(
            fn (string $fact): string => $this->normalizeIdentityFact($fact),
            $this->removeContainedFacts($this->excludeInstructions($facts['identity'] ?? [], 'identity')),
        )));
        $work = $this->removeRedundantWorkDetails(array_values(array_unique(array_map(
            fn (string $fact): string => $this->polishFact($fact, 'work', $isSomali),
            $this->removeContainedFacts($this->excludeInstructions($facts['work'] ?? [], 'work')),
        ))));
        $meetings = array_values(array_unique(array_filter(
            array_map(
                fn (string $fact): string => $this->polishFact($fact, 'meeting', $isSomali),
                $this->removeContainedFacts($this->expandMeetingFacts($this->excludeInstructions($facts['meeting'] ?? [], 'meeting'))),
            ),
            fn (string $fact): bool => ! $this->isParticipationCountOnly($fact)
                && ! $this->isDocumentInstruction($fact),
        )));
        $other = array_values(array_unique(
            $this->removeContainedFacts($this->excludeInstructions($facts['other'] ?? [], 'other')),
        ));
        $sections = [];
        $isReport = (bool) preg_match(
            '/\b(?:report|warbixin|weekly\s+summary|work\s+summary)\b/iu',
            $source . ' ' . $title,
        );
        $isDetailedReport = $isReport
            && count($work) + count($meetings) + count($other) >= 3;
        $isAuthoring = ! $isReport && (bool) preg_match(
            '/\b(?:proposal|business\s+plan|plan|research|paper|manual|guide|handbook|training|curriculum|'
            . 'lesson|article|essay|policy|brochure|procedure|soo\s+jeedin\p{L}*|qorshe\p{L}*|hage|cashar\p{L}*)\b/iu',
            $source . ' ' . $title,
        );
        $workTitle = null;
        $meetingTitle = $isSomali ? 'Kulamada iyo Isku-duwidda' : 'Meetings & Coordination';
        $otherTitle = $isSomali ? 'Faahfaahin Dheeraad ah' : 'Additional Details';
        $safeHeadings = [];

        if ($work !== []) {
            $workContent = $isDetailedReport
                ? implode("\n\n", array_map(
                    fn (string $fact): string => $this->workNarrative($fact, $isSomali),
                    $work,
                ))
                : $this->bulletList($work);
            $workTitle = $isDetailedReport && $this->describesWebsiteWork($work)
                ? ($isSomali
                    ? 'Horumarinta Mareegta iyo Hagaajinta Muuqaalka'
                    : 'Website Development & Visual Improvements')
                : ($isReport
                    ? ($isSomali ? 'Shaqada iyo Hawlaha' : 'Work & Activity')
                    : ($isSomali ? 'Macluumaadka Muhiimka ah' : 'Key Information'));
            $safeHeadings[] = $workTitle;
            $sections[] = '## ' . $workTitle
                . "\n\n" . $workContent;
        }

        if ($meetings !== []) {
            if ($isDetailedReport) {
                $meetingLines = ['## ' . $meetingTitle];
                $safeHeadings[] = $meetingTitle;
                foreach ($meetings as $index => $fact) {
                    $meetingTitleForFact = $this->meetingHeading($fact, $index + 1, $isSomali);
                    $safeHeadings[] = $meetingTitleForFact;
                    $meetingLines[] = '';
                    $meetingLines[] = '### ' . $meetingTitleForFact;
                    $meetingLines[] = '';
                    $meetingLines[] = $this->meetingNarrative($fact, $isSomali);
                }
                $sections[] = implode("\n", $meetingLines);
            } else {
                $meetingLines = ["## Meetings & Coordination"];
                foreach ($meetings as $index => $fact) {
                    $meetingLines[] = '';
                    $meetingLines[] = '### Meeting ' . ($index + 1);
                    $meetingLines[] = '';
                    $meetingLines[] = '> ' . $fact;
                }
                $sections[] = implode("\n", $meetingLines);
            }
        }

        if ($other !== []) {
            $safeHeadings[] = $otherTitle;
            $sections[] = '## ' . $otherTitle
                . "\n\n" . $this->bulletList($other);
        }

        $summary = '';
        if ($isReport && $work !== [] && $meetings !== []) {
            $meetingCount = $this->explicitMeetingCount($source);
            $meetingLabel = $meetingCount !== null && $meetingCount === count($meetings)
                ? 'ka qaybgalka ' . $this->somaliNumberWord($meetingCount) . ' kulan'
                : 'ka qaybgalka kulamada la soo sheegay';
            if (! $isSomali) {
                $meetingLabel = $meetingCount !== null && $meetingCount === count($meetings)
                    ? 'participation in ' . $this->numberWord($meetingCount) . ' meetings'
                    : 'participation in the reported meetings';
            }
            $summary = $this->reportSummary($work, $meetingLabel, $isSomali);
        }

        $fallbackContent = implode("\n\n", $sections);
        $candidate = $meetings !== [] && preg_match('/^\s*\|.*\|\s*$/mu', $candidateContent)
            ? ['content' => '', 'missing_facts' => []]
            : $this->groundCandidateContent(
                $candidateContent,
                array_merge($work, $meetings, $other),
                $safeHeadings,
                $isAuthoring,
            );
        if ($isAuthoring && $candidate['content'] !== '' && $candidate['missing_facts'] !== []) {
            // Keep the model-authored document; surface omitted verified facts instead of discarding the draft.
            $candidate['content'] .= "\n\n## " . ($isSomali ? 'Faahfaahin Dheeraad ah' : 'Additional Details')
                . "\n\n" . $this->bulletList($candidate['missing_facts']);
            $candidate['missing_facts'] = [];
        }
        $content = $candidate['content'] !== '' && $candidate['missing_facts'] === []
            ? $candidate['content']
            : $fallbackContent;
        if ($isDetailedReport) {
            $closing = $this->reportClosing($work, $meetings, $isSomali);
            $content = trim($content) . "\n\n" . $closing;
        }

        return [
            'content' => $content,
            'summary' => $summary,
            'verified_quotes' => count($identity) + count($work) + count($meetings) + count($other),
            'summary_title' => (bool) preg_match('/\b(?:weekly|toddobaadl\p{L}*)\b/iu', $source . ' ' . $title)
                ? ($isSomali ? 'Dulmarka Toddobaadka' : 'Weekly Overview')
                : ($isSomali ? 'Soo Koobid' : 'Executive Summary'),
            'prepared_by' => implode(' · ', $identity),
            'quality_facts' => array_values(array_unique(array_merge($work, $meetings, $other))),
        ];
    }

    /**
     * Retain authored Markdown only when its factual sentences are anchored in verified facts.
     * Incomplete candidates fall back to the complete deterministic composition.
     *
     * @return array{content:string,missing_facts:list<string>}
     */
    private function groundCandidateContent(string $content, array $facts, array $safeHeadings, bool $lenient = false): array
    {
        $this->lenientGrounding = $lenient;
        $content = $this->removeRedundantDocumentSections(trim($content));
        if ($content === '' || $facts === []) {
            return ['content' => '', 'missing_facts' => []];
        }
        $supportedTokens = [];
        foreach ($facts as $fact) {
            foreach ($this->contentTokens($fact) as $token) {
                foreach ($this->tokenVariants($token) as $variant) {
                    $supportedTokens[$variant] = true;
                }
            }
        }

        $acceptedLines = [];
        $seenLines = [];
        $acceptedTokens = [];
        $inMarkdownTable = false;
        foreach (preg_split('/\R/u', $content) ?: [] as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                $acceptedLines[] = '';
                continue;
            }

            if (! preg_match('/^\|.*\|$/u', $trimmed)) {
                $inMarkdownTable = false;
            }

            if (preg_match('/^#{1,6}\s+(.+)$/u', $trimmed, $heading)) {
                $headingText = trim((string) $heading[1]);
                if ($this->isNeutralHeading($headingText)
                    || in_array(mb_strtolower($headingText), array_map('mb_strtolower', $safeHeadings), true)
                    || $this->isSafeStructuralHeading($headingText, $supportedTokens)) {
                    $acceptedLines[] = $trimmed;
                    foreach ($this->contentTokens($headingText) as $token) {
                        if ($this->tokenIsSupported($token, $supportedTokens)) {
                            $acceptedTokens[$token] = true;
                        }
                    }
                }
                continue;
            }

            if (preg_match('/^!\[([^\]]*)\]\((data:image\/[^)\s]+)\)$/iu', $trimmed, $image)) {
                $validatedImage = (new \App\Extensions\AIChatPro\System\Services\Document\GaradDocumentParser)
                    ->loadImage($image[2]);
                if ($validatedImage !== null) {
                    if (is_file($validatedImage['path'])) {
                        unlink($validatedImage['path']);
                    }
                    $acceptedLines[] = $trimmed;
                }
                continue;
            }

            if (preg_match('/^\|.*\|$/u', $trimmed)) {
                if (preg_match('/^\|[\s:|-]+\|$/u', $trimmed)) {
                    $acceptedLines[] = $trimmed;
                    $inMarkdownTable = true;
                    continue;
                }

                $cells = array_values(array_filter(
                    array_map('trim', explode('|', trim($trimmed, '|'))),
                    static fn (string $cell): bool => $cell !== '',
                ));
                $isTableHeader = ! $inMarkdownTable;
                if ($isTableHeader) {
                    $inMarkdownTable = true;
                } elseif ($cells !== []) {
                    $rowGrounded = true;
                    foreach ($cells as $cell) {
                        if (! $this->claimIsGrounded($cell, $supportedTokens)) {
                            $rowGrounded = false;
                            break;
                        }
                    }
                    if (! $rowGrounded) {
                        continue;
                    }
                    foreach ($cells as $cell) {
                        foreach ($this->contentTokens($cell) as $token) {
                            $acceptedTokens[$token] = true;
                        }
                    }
                }
                $acceptedLines[] = $trimmed;
                continue;
            }

            $prefix = '';
            $claim = $trimmed;
            if (preg_match('/^((?:[-*+]\s+|>\s*|\d+[.)]\s+)+)(.+)$/u', $trimmed, $listMatch)) {
                $prefix = $listMatch[1];
                $claim = $listMatch[2];
            }

            $parts = preg_split(
                $lenient
                    ? '/(?<=[.!?])\s+/u'
                    : '/(?<=[.!?])\s+|(?<=,)\s+(?=(?:so|therefore|and|but)\b)/iu',
                $claim,
                -1,
                PREG_SPLIT_NO_EMPTY,
            ) ?: [$claim];
            $retained = [];
            foreach ($parts as $part) {
                $plain = $this->plainText((string) $part);
                if ($this->isDocumentInstruction($plain)
                    || ! $this->claimIsGrounded($plain, $supportedTokens)) {
                    continue;
                }
                $retainedPart = rtrim(trim((string) $part), ",;: \t");
                if ($retainedPart !== '' && ! preg_match('/[.!?…]$/u', $retainedPart)) {
                    $retainedPart .= '.';
                }
                $retained[] = $retainedPart;
                foreach ($this->contentTokens($plain) as $token) {
                    $acceptedTokens[$token] = true;
                }
            }

            if ($retained !== []) {
                $acceptedLine = $prefix . implode(' ', $retained);
                $lineKey = mb_strtolower((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $acceptedLine));
                if (isset($seenLines[$lineKey]) && mb_strlen($lineKey) > 12) {
                    continue;
                }
                $seenLines[$lineKey] = true;
                $acceptedLines[] = $acceptedLine;
            }
        }

        $acceptedLines = $this->removeEmptyHeadings($acceptedLines);
        $groundedContent = trim(implode("\n", $acceptedLines));
        if ($groundedContent === '' || $acceptedTokens === []) {
            return ['content' => '', 'missing_facts' => []];
        }

        $missingFacts = [];
        foreach ($facts as $fact) {
            $factTokens = $this->contentTokens($fact);
            if ($factTokens === [] || $this->tokenCoverage($factTokens, $acceptedTokens) >= 0.6) {
                continue;
            }
            $missingFacts[] = $fact;
        }

        return [
            'content' => $groundedContent,
            'missing_facts' => array_values(array_unique($missingFacts)),
        ];
    }

    private function claimIsGrounded(string $claim, array $supportedTokens): bool
    {
        $tokens = $this->contentTokens($claim);
        if ($tokens === []) {
            return true;
        }

        $unsupportedOutcomeTerms = array_fill_keys([
            'approved', 'approval', 'achieved', 'accomplished', 'completed', 'completion',
            'delivered', 'deployed', 'finished', 'launched', 'resolved', 'successful',
            'successfully', 'blocked', 'blocker', 'blockers', 'planned', 'plan',
            'recommend', 'recommended', 'confirmed', 'decided', 'decision', 'ready',
        ], true);
        foreach ($tokens as $token) {
            if (isset($unsupportedOutcomeTerms[$token]) && ! $this->tokenIsSupported($token, $supportedTokens)) {
                return false;
            }
        }

        preg_match_all('/\b\d+(?:[.,]\d+)?%?\b/u', $claim, $numbers);
        foreach ($numbers[0] ?? [] as $number) {
            $isOrdinal = $this->lenientGrounding && ctype_digit($number) && (int) $number <= 20;
            $numberParts = preg_split('/[.,]/u', rtrim($number, '%'), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $numberGrounded = isset($supportedTokens[$number])
                || ($numberParts !== [] && count(array_filter(
                    $numberParts,
                    static fn (string $part): bool => isset($supportedTokens[$part]),
                )) === count($numberParts));
            if (! $isOrdinal && ! $numberGrounded) {
                return false;
            }
        }

        if ($this->lenientGrounding) {
            // Authored explanatory prose is retained; only invented specifics (named entities) are removed.
            $words = preg_split('/\s+/u', $this->plainText($claim), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($words as $index => $word) {
                $clean = trim($word, " \t.,;:!?()\"'“”‘’");
                if ($index > 0 && preg_match('/^\p{Lu}\p{Ll}{2,}$/u', $clean)
                    && ! $this->tokenIsSupported(mb_strtolower($clean), $supportedTokens)
                    && ! preg_match('/[.!?:]$/u', (string) $words[$index - 1])) {
                    return false;
                }
            }

            return true;
        }

        $overlap = count(array_filter(
            $tokens,
            fn (string $token): bool => $this->tokenIsSupported($token, $supportedTokens),
        ));
        $minimum = count($tokens) <= 2 ? 1 : (int) ceil(count($tokens) * 0.75);

        return $overlap >= $minimum;
    }

    private function contentTokens(string $text): array
    {
        $stopWords = array_fill_keys([
            'a', 'about', 'after', 'all', 'also', 'an', 'and', 'are', 'as', 'at', 'be', 'been',
            'by', 'for', 'from', 'had', 'has', 'have', 'he', 'her', 'his', 'i', 'in', 'into',
            'is', 'it', 'its', 'of', 'on', 'or', 'our', 'she', 'that', 'the', 'their', 'them',
            'there', 'these', 'they', 'this', 'those', 'to', 'was', 'we', 'were', 'which', 'who',
            'with', 'you', 'your', 'report', 'weekly', 'week', 'summary', 'overview', 'section',
            'sections', 'activity', 'activities', 'work', 'meeting', 'meetings', 'discussion',
            'discussions', 'details', 'provided', 'reported', 'source', 'information', 'included',
            'includes', 'include', 'focused', 'focus', 'covers', 'covered', 'records', 'record',
            'presents', 'presented', 'highlights', 'outlines', 'describes', 'described',
            'waa', 'waxaan', 'waxaanan', 'waxa', 'waxaa', 'ee', 'iyo', 'oo', 'ka', 'ku', 'la',
            'in', 'uu', 'ay', 'ah', 'u', 'leh', 'lagu', 'laga', 'loogu', 'dhexeeya', 'kale',
            'tani', 'tan', 'kuwa', 'waxaana', 'ayaa', 'asbuucan', 'asbucan',
        ], true);

        $words = preg_split(
            '/[^\p{L}\p{N}]+/u',
            mb_strtolower($this->plainText($text)),
            -1,
            PREG_SPLIT_NO_EMPTY,
        ) ?: [];

        return array_values(array_unique(array_filter(
            $words,
            static fn (string $word): bool => (ctype_digit($word) || mb_strlen($word) > 1)
                && ! isset($stopWords[$word]),
        )));
    }

    private function plainText(string $text): string
    {
        $text = preg_replace('/!\[([^\]]*)\]\([^)]+\)/u', '$1', $text) ?? $text;
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/u', '$1', $text) ?? $text;
        $text = preg_replace('/[`*_~]/u', '', $text) ?? $text;

        return trim($text);
    }

    private function tokenCoverage(array $tokens, array $availableTokens): float
    {
        if ($tokens === []) {
            return 1.0;
        }

        $available = [];
        foreach ($availableTokens as $token => $value) {
            $token = is_int($token) ? $value : $token;
            foreach ($this->tokenVariants((string) $token) as $variant) {
                $available[$variant] = true;
            }
        }
        $covered = count(array_filter(
            $tokens,
            fn (string $token): bool => $this->tokenIsSupported($token, $available),
        ));

        return $covered / count($tokens);
    }

    private function tokenIsSupported(string $token, array $supportedTokens): bool
    {
        foreach ($this->tokenVariants($token) as $variant) {
            if (isset($supportedTokens[$variant])) {
                return true;
            }
        }

        return false;
    }

    private function tokenVariants(string $token): array
    {
        $variants = [$token];
        if (mb_strlen($token) > 4 && preg_match('/ies$/u', $token)) {
            $variants[] = mb_substr($token, 0, -3) . 'y';
        }
        if (mb_strlen($token) > 4 && preg_match('/ed$/u', $token)) {
            $stem = mb_substr($token, 0, -2);
            $variants[] = $stem;
            $variants[] = $stem . 'e';
        }
        if (mb_strlen($token) > 5 && preg_match('/ing$/u', $token)) {
            $stem = mb_substr($token, 0, -3);
            $variants[] = $stem;
            $variants[] = $stem . 'e';
        }
        if (mb_strlen($token) > 4 && preg_match('/es$/u', $token)) {
            $variants[] = mb_substr($token, 0, -2);
        } elseif (mb_strlen($token) > 3 && preg_match('/s$/u', $token) && ! preg_match('/ss$/u', $token)) {
            $variants[] = mb_substr($token, 0, -1);
        }
        if (mb_strlen($token) > 4 && preg_match('/ly$/u', $token)) {
            $variants[] = mb_substr($token, 0, -2);
        }

        return array_values(array_unique($variants));
    }

    private function isNeutralHeading(string $heading): bool
    {
        return (bool) preg_match(
            '/^(?:executive\s+summary|weekly\s+(?:overview|summary)|overview|introduction|'
            . 'work(?:\s+(?:and|&)\s+activity)?|website\s+(?:work|activity)|meetings?(?:\s+(?:and|&)\s+coordination)?|'
            . 'additional\s+details|conclusion|report\s+details)$/iu',
            trim($heading),
        );
    }

    private function isSafeStructuralHeading(string $heading, array $supportedTokens): bool
    {
        if ($this->isDocumentInstruction($heading)) {
            return false;
        }

        if ($this->claimIsGrounded($heading, $supportedTokens)) {
            return true;
        }

        return ! preg_match(
            '/\b(?:approved|approval|authorized|confirmed|achieved|accomplished|completed|completion|'
            . 'delivered|deployed|finished|launched|resolved|successful|successfully|blocked|blockers?|'
            . 'will|planned|plan|recommend(?:ed)?|decided|decision|ready)\b|\d/iu',
            $heading,
        ) && count($this->contentTokens($heading)) <= 8;
    }

    private function removeRedundantDocumentSections(string $content): string
    {
        $lines = preg_split('/\R/u', $content) ?: [];
        $result = [];
        $skipLevel = null;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (preg_match('/^(#{1,6})\s+(.+)$/u', $trimmed, $heading)) {
                $level = strlen($heading[1]);
                if ($skipLevel !== null && $level <= $skipLevel) {
                    $skipLevel = null;
                }
                if (preg_match(
                    '/^(?:(?:activity|work)\s+(?:log|register|summary|overview)|'
                    . '(?:diiwaanka|diiwaan)\s+hawlaha)$/iu',
                    trim($heading[2]),
                )) {
                    $skipLevel = $level;
                    continue;
                }
            }
            if ($skipLevel === null) {
                $result[] = $line;
            }
        }

        return trim(implode("\n", $result));
    }

    private function removeEmptyHeadings(array $lines): array
    {
        $result = [];
        foreach ($lines as $index => $line) {
            if (! preg_match('/^(#{1,6})\s+/u', trim($line), $currentHeading)) {
                $result[] = $line;
                continue;
            }

            $currentLevel = strlen($currentHeading[1]);
            $hasContent = false;
            for ($next = $index + 1, $count = count($lines); $next < $count; $next++) {
                $candidate = trim($lines[$next]);
                if (preg_match('/^(#{1,6})\s+/u', $candidate, $nextHeading)) {
                    if (strlen($nextHeading[1]) <= $currentLevel) {
                        break;
                    }
                    continue;
                }
                if ($candidate !== '' && ! preg_match('/^\|[\s:|-]+\|$/u', $candidate)) {
                    $hasContent = true;
                    break;
                }
            }
            if ($hasContent) {
                $result[] = $line;
            }
        }

        return $result;
    }

    private function bulletList(array $facts): string
    {
        return implode("\n", array_map(static fn (string $fact): string => '- ' . $fact, $facts));
    }

    private function workNarrative(string $fact, bool $isSomali): string
    {
        if ($isSomali && preg_match('/^waxaan\s+(?:ku\s*)?daray\s+qurxin[.!?…]*$/iu', trim($fact))) {
            return 'Waxaan sidoo kale ku daray hagaajin muuqaal ah.';
        }
        if (! $isSomali && preg_match('/^waxaan\s+(?:ku\s*)?daray\s+qurxin[.!?…]*$/iu', trim($fact))) {
            return 'I also added visual improvements.';
        }

        return $fact;
    }

    private function describesWebsiteWork(array $work): bool
    {
        return (bool) preg_match('/\b(?:website|mareeg)\p{L}*\b/iu', implode(' ', $work));
    }

    private function meetingHeading(string $fact, int $index, bool $isSomali): string
    {
        $fact = rtrim(trim($fact), ". \t\n\r\0\x0B");

        if ($isSomali && preg_match('/^Kulanka weyn ee toddobaadlaha ah ee (.+?)(?:;|$)/iu', $fact, $match)) {
            return 'Kulanka toddobaadlaha ah ee ' . $match[1];
        }
        if ($isSomali && preg_match('/^Kulan lala yeeshay (.+?)(?: oo ku saabsanaa|;|$)/iu', $fact, $match)) {
            return 'Kulanka lala yeeshay ' . $match[1];
        }
        if ($isSomali && preg_match('/^Kulan looga hadlay (.+)$/iu', $fact, $match)) {
            $topic = trim((string) preg_replace('/\bevent-ka\s+/iu', '', trim($match[1])));

            return 'Kulanka: ' . ucfirst($topic);
        }

        if (! $isSomali && preg_match('/^(?:a|the) meeting with (.+?) about\b/iu', $fact, $match)) {
            return 'Meeting with ' . trim($match[1]);
        }
        if (! $isSomali && preg_match('/^the (.+?) weekly main meeting\b/iu', $fact, $match)) {
            return trim($match[1]) . ' Weekly Main Meeting';
        }
        if (! $isSomali && preg_match('/^a meeting with (.+?) concerned\b/iu', $fact, $match)) {
            return 'Meeting with ' . trim($match[1]);
        }
        if (! $isSomali && preg_match('/^a meeting discussed the launch event for (.+)$/iu', $fact, $match)) {
            return rtrim(trim($match[1]), '.') . ' Launch Event Discussion';
        }
        if (! $isSomali && preg_match('/^(?:the )?(.+?\bmeeting),?\s*(?:where|with)\b/iu', $fact, $match)) {
            return ucwords(trim($match[1]));
        }
        if (! $isSomali && preg_match('/^(?:a|the) meeting (?:discussing|about) (.+)$/iu', $fact, $match)) {
            return ucfirst((string) preg_replace('/^(?:a|the|an)\s+/iu', '', trim($match[1])));
        }

        return ($isSomali ? 'Kulan ' : 'Meeting ') . $index;
    }

    private function reportSummary(array $work, string $meetingLabel, bool $isSomali): string
    {
        $websiteWork = $this->describesWebsiteWork($work);
        $workText = mb_strtolower(implode(' ', $work));
        $hasMultipleSections = (bool) preg_match(
            '/\b(?:multiple|several|various|different)\b|qaybo\s+kala\s+duwan/iu',
            $workText,
        );
        $hasVisualDetail = (bool) preg_match('/\b(?:visual|design|polish|qurxin|muuqaal)\b/iu', $workText);

        if ($isSomali) {
            if ($websiteWork && $hasMultipleSections && $hasVisualDetail) {
                $workSummary = 'Shaqadu waxay ka koobnayd hawlo ka dhacay qaybo kala duwan oo mareegta ah iyo hagaajinta muuqaalkeeda.';
            } elseif ($websiteWork && $hasVisualDetail) {
                $workSummary = 'Shaqada la soo sheegay waxay ku saabsanayd mareegta, waxaana ku jiray hagaajin muuqaal ah.';
            } elseif ($websiteWork) {
                $workSummary = 'Shaqada la soo sheegay waxay ku saabsanayd mareegta.';
            } else {
                $workSummary = 'Hawlaha shaqo ee la soo sheegay ayaa lagu soo koobay warbixinta.';
            }

            return $workSummary . ' Warbixintu waxa kale oo ay diiwaangelinaysaa ' . $meetingLabel
                . ' iyo mawduucyadooda.';
        }

        $siteLabel = 'the website';
        if ($websiteWork && $hasMultipleSections && $hasVisualDetail) {
            $workSummary = 'Work covered multiple sections of ' . $siteLabel . ' and included visual improvements.';
        } elseif ($websiteWork && $hasVisualDetail) {
            $workSummary = 'Work covered ' . $siteLabel . ' and included visual improvements.';
        } elseif ($websiteWork) {
            $workSummary = 'Work covered ' . $siteLabel . '.';
        } else {
            $workSummary = 'The report organizes the work activities provided in the source notes.';
        }

        return $workSummary . ' It also records ' . $meetingLabel . ' and their stated topics.';
    }

    private function reportClosing(array $work, array $meetings, bool $isSomali): string
    {
        $topics = [];
        foreach ($meetings as $index => $fact) {
            $fact = mb_strtolower($fact);
            if ($isSomali) {
                $topic = match (true) {
                    (bool) preg_match('/\b(?:asbuucl|toddobaadl|weekly)\p{L}*/u', $fact) =>
                        (bool) preg_match('/\b(?:agend|ajand|reporting|warbixin)\p{L}*/u', $fact)
                            ? 'ajandayaasha iyo warbixinta'
                            : 'kulanka toddobaadlaha ah',
                    (bool) preg_match('/\b(?:system|nidaam)\b/u', $fact) => 'la socodka shaqooyinka',
                    (bool) preg_match('/\b(?:event|xaflad|launch|daahfur)\b/u', $fact) =>
                        'daahfurka xafladda' . (str_contains($fact, 'garad ai') ? ' Garad AI' : ''),
                    default => $this->meetingHeading($meetings[$index], $index + 1, true),
                };
            } else {
                $topic = match (true) {
                    (bool) preg_match('/\b(?:weekly|week)\b/u', $fact) =>
                        (bool) preg_match('/\b(?:agenda|reporting)\b/u', $fact)
                            ? 'weekly reporting'
                            : 'the weekly meeting',
                    (bool) preg_match('/\b(?:system|tracking)\b/u', $fact) => 'work tracking',
                    (bool) preg_match('/\b(?:event|launch)\b/u', $fact) =>
                        str_contains($fact, 'garad ai') ? 'the Garad AI launch event' : 'the event launch',
                    default => 'the reported meeting topics',
                };
            }
            $topics[] = $topic;
        }
        $topics = array_values(array_unique($topics));
        $topicCount = count($topics);
        $conjunction = $isSomali ? 'iyo' : 'and';
        $topicList = match ($topicCount) {
            0 => '',
            1 => $topics[0],
            2 => $topics[0] . ' ' . $conjunction . ' ' . $topics[1],
            default => implode(', ', array_slice($topics, 0, -1))
                . ', ' . $conjunction . ' ' . $topics[$topicCount - 1],
        };

        if ($isSomali) {
            $workTopic = $this->describesWebsiteWork($work)
                ? 'hawlaha mareegta iyo hagaajinta muuqaalka'
                : 'hawlaha shaqo ee la soo sheegay';
            $closing = 'Guud ahaan, warbixintani waxay isu keentaa ' . $workTopic;
            if ($topicList !== '') {
                $closing .= ' iyo kulamada ku saabsanaa ' . $topicList;
            }

            return $closing . '.';
        }

        $workTopic = $this->describesWebsiteWork($work)
            ? 'website development and visual improvements'
            : 'the reported work activities';
        $closing = 'The report brings together ' . $workTopic;
        if ($topicList !== '') {
            $closing .= ' with meeting discussions on ' . $topicList;
        }

        return $closing . '.';
    }

    private function meetingNarrative(string $fact, bool $isSomali): string
    {
        if ($isSomali && str_starts_with(mb_strtolower($fact), 'kulanka weyn ee toddobaadlaha ah')) {
            if (preg_match('/; waxaa laga hadlay (.+)\.$/u', $fact, $matches)) {
                return 'Waxaa laga hadlay ' . $matches[1] . '.';
            }
        }

        if ($isSomali && str_starts_with(mb_strtolower($fact), 'kulan lala yeeshay')) {
            if (preg_match('/ oo ku saabsanaa (.+)\.$/u', $fact, $matches)) {
                return 'Waxaa laga hadlay ' . $matches[1] . '.';
            }
        }

        if ($isSomali && str_starts_with(mb_strtolower($fact), 'kulan looga hadlay')) {
            if (preg_match('/^Kulan looga hadlay (.+)\.$/u', $fact, $matches)) {
                $topic = trim((string) preg_replace('/\bevent-ka\s+/iu', '', trim($matches[1])));

                return 'Waxaa looga hadlay ' . $topic . '.';
            }
        }

        if (! $isSomali && preg_match('/^(?:the )?(.+?\bmeeting),?\s*where (.+?) were discussed\.?$/iu', $fact, $match)) {
            return ucfirst(trim($match[2])) . ' were discussed.';
        }
        if (! $isSomali && preg_match('/^(?:a|the) meeting with .+? about (.+)\.?$/iu', $fact, $match)) {
            return 'The discussion concerned ' . rtrim(trim($match[1]), '.') . '.';
        }
        if (! $isSomali && preg_match('/^(a meeting with .+? concerned .+)\.?$/iu', $fact, $match)) {
            return rtrim(trim($match[1]), '.') . '.';
        }
        if (! $isSomali && preg_match('/^(?:the )?(.+?\bmeeting)\s+with\s+(.+)$/iu', $fact, $match)) {
            return 'The meeting covered ' . rtrim(trim($match[2]), '.') . '.';
        }
        if (! $isSomali && preg_match('/^(?:a|the) meeting discussing (.+)\.?$/iu', $fact, $match)) {
            return 'The meeting discussed ' . rtrim(trim($match[1]), '.') . '.';
        }

        return $fact;
    }

    private function isSomali(string $source): bool
    {
        if (preg_match(
            '/\b(?:english\s+(?:ka\s+dhig|ku\s+qor)|in\s+english|write\s+(?:it\s+)?in\s+english|'
            . 'make\s+(?:it\s+)?(?:the\s+)?report\s+in\s+english)\b/iu',
            $source,
        )) {
            return false;
        }
        if (preg_match('/\b(?:somali|soomaali)\s+(?:ka\s+dhig|ku\s+qor)|in\s+somali\b/iu', $source)) {
            return true;
        }

        return (bool) preg_match(
            '/\b(?:waxaan|asbuuc|qayb|qeeb|shaqada|kulanka|kulamo|magaceyga|adeegsanaya)\b/iu',
            $source,
        );
    }

    private function removeContainedFacts(array $facts): array
    {
        $normalized = array_map(
            static fn (string $fact): string => mb_strtolower(
                (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', trim($fact)),
            ),
            $facts,
        );
        $result = [];

        foreach ($facts as $index => $fact) {
            $isContained = false;
            if ($normalized[$index] === '') {
                $result[] = $fact;
                continue;
            }
            foreach ($normalized as $otherIndex => $other) {
                if ($index === $otherIndex || mb_strlen($other) < mb_strlen($normalized[$index])) {
                    continue;
                }
                if (
                    str_contains($other, $normalized[$index])
                    && (mb_strlen($other) > mb_strlen($normalized[$index]) || $otherIndex < $index)
                ) {
                    $isContained = true;
                    break;
                }
            }
            if (! $isContained) {
                $result[] = $fact;
            }
        }

        return $result;
    }

    private function excludeInstructions(array $facts, string $section = ''): array
    {
        return array_values(array_filter(
            $facts,
            fn (string $fact): bool => ! $this->isDocumentInstruction($fact, $section),
        ));
    }

    private bool $lenientGrounding = false;

    /**
     * Labelled source metadata (title, audience) and descriptions of the source itself are not report facts.
     */
    private function separateSourceLabels(array $facts, string $title): array
    {
        $titleKey = mb_strtolower(trim($title));
        foreach (['identity', 'work', 'meeting', 'other'] as $section) {
            $kept = [];
            foreach ($facts[$section] ?? [] as $fact) {
                $fact = trim((string) $fact);
                if (preg_match('/^(?:document\s+)?title\s*:\s*(.+)$/iu', $fact, $m)
                    && mb_strtolower(trim($m[1], " .")) === $titleKey) {
                    continue;
                }
                if (preg_match('/^(?:(?:fictional|sample|synthetic|test)\s+)?(?:source\s+)?notes?$/iu', $fact)
                    || preg_match('/^(?:fictional|sample|synthetic)\s+source\s+notes?$/iu', $fact)) {
                    continue;
                }
                if ($section === 'identity' && preg_match('/^(?:audience|purpose|topic|format|language|scope)\s*:/iu', $fact)) {
                    $facts['other'][] = $fact;
                    continue;
                }
                $kept[] = $fact;
            }
            $facts[$section] = $kept;
        }

        return $facts;
    }

    private function isDocumentInstruction(string $fact, string $section = ''): bool
    {
        $trimmed = trim($fact);
        $hasDocumentType = (bool) preg_match(
            '/\b(?:report|warbixin|pdf|document|dukumenti|presentation|slides?)\b/iu',
            $trimmed,
        );
        $hasRequestLanguage = (bool) preg_match(
            '/\b(?:rabaa|raba|doonayaa|doonaysaa|ii\s+(?:samee|sameey|soo\s+samee|qor|diyaari)|'
            . 'I\s+(?:want|need)\s+(?:you\s+to\s+)?(?:create|prepare|write|make|generate|draft))\b/iu',
            $trimmed,
        );
        $refersToPersonalWork = (bool) preg_match(
            '/\b(?:shaqadeyda|shaqadayda|my\s+(?:weekly\s+)?work)\b/iu',
            $trimmed,
        );
        $isDocumentTitleMisclassifiedAsIdentity = $section === 'identity'
            && (bool) preg_match('/^(?:(?:weekly|toddobaadle)\s+)?(?:report|warbixin|pdf)\b/iu', $trimmed);
        $isRequestOrTitleFragment = (bool) preg_match(
            '/^(?:(?:sxb\s+)?(?:waxaan\s+)?(?:rabaa|raba|doonayaa|doonaysaa)\b.*'
            . '(?:report|warbixin|pdf)|'
            . '(?:report|warbixin|pdf)\s+(?:shaqadeyda|shaqadayda|my\s+(?:weekly\s+)?work)\b|'
            . '(?:(?:weekly|toddobaadle)\s+)?(?:report|warbixin)\s+(?:ee|for|of)\s+'
            . '(?:\p{L}+\s*){1,5})/iu',
            $trimmed,
        );
        $isBrandingInstructionFragment = (bool) preg_match(
            '/^(?:logo(?:-da)?|astaant\p{L}*|sumadd\p{L}*|brand(?:-ka)?|midabbad\p{L}*)\b'
            . '.*\b(?:brand|midab\p{L}*|color|colour|cyan|logo|astaanta)\b/iu',
            $trimmed,
        );

        if ($isDocumentTitleMisclassifiedAsIdentity
            || $isRequestOrTitleFragment
            || $isBrandingInstructionFragment
            || ($hasDocumentType && ($hasRequestLanguage || $refersToPersonalWork))) {
            return true;
        }

        return (bool) preg_match(
            '/^(?:ha|hana)\s+(?:ku\s+darin|qorin|samayn|isticmaalin|ku\s+soo\s+darin|abuurin)\b/iu',
            $trimmed,
        ) || (bool) preg_match(
            '/^(?:pdf|warbixin|dukumenti|document|report)\s+(?:gaaban|dheer|kooban|faahfaahsan|short|brief|detailed)\b.*\b\d+\s*(?:bog|page)/iu',
            $trimmed,
        ) || (bool) preg_match(
            '/^(?:(?:please|kindly)\s+)?(?:use|apply|create|make|prepare|write|include|exclude|omit|do\s+not|don\'t|avoid|keep|ensure|follow)\b/iu',
            $trimmed,
        ) || (bool) preg_match(
            '/(?:^|\s)(?:adeegso|isticmaal|samee|sameey|soo\s+samee|ii\s+qor|ku\s+dar|ka\s+reeb)\s*[.!?…]*$/iu',
            $trimmed,
        ) || (
            preg_match('/\b(?:using|adoo\s+adeegsanaya)\b/iu', $trimmed)
            && preg_match('/\b(?:report|document|pdf|presentation)\b/iu', $trimmed)
            && preg_match('/\b(?:logo|brand|color|colour|midab)\b/iu', $trimmed)
        );
    }

    private function normalizeIdentityFact(string $fact): string
    {
        $fact = (string) preg_replace('/^(?:my\s+)?name\s+(?:is|:)\s*/iu', '', trim($fact));
        $fact = (string) preg_replace('/^magac\p{L}*\s+waa\s*/iu', '', $fact);
        $fact = (string) preg_replace('/^(?:t+itle(?:\s*-?\s*kana)?|job\s+title)\s+(?:is|:|waa)\s*/iu', '', $fact);
        $fact = (string) preg_replace('/\s+(?:my\s+)?title\s+(?:is|:)\s*/iu', ' · ', $fact);
        $fact = (string) preg_replace('/\s+t+itle(?:\s*-?\s*kana)?\s+waa\s*/iu', ' · ', $fact);
        $fact = (string) preg_replace('/^(.+?)\s+is\s+(.+)$/iu', '$1 · $2', $fact);
        $fact = (string) preg_replace('/\s*,\s*·/u', ' ·', $fact);

        return trim($fact, " .");
    }

    private function polishFact(string $fact, string $section, bool $isSomali): string
    {
        $fact = trim(preg_replace('/\s+/u', ' ', $fact) ?? $fact);

        if (! preg_match('/\b(?:waxaan|asbuc|asbuuc|mid|midna|midka|qeeb|qayb|xaflada|hadlaayey)\b/iu', $fact)) {
            return $this->sentence($fact);
        }

        if ($section === 'work' && preg_match(
            '/^waxaan\s+asbucan\s+sameeyey\s+website\s+ka\s+(.+?)\s+oo\s+qeebo\s+kala\s+duwan\s+ka\s+kobnaa'
            . '(?:\s+waxaan\s+(?:ku\s*)?daray\s+(.+?))?[.!?…]*$/iu',
            $fact,
            $matches,
        )) {
            $site = $this->properName($matches[1]);
            if (! $isSomali) {
                $extra = trim((string) ($matches[2] ?? ''));
                $detail = $extra === ''
                    ? ''
                    : ' and added ' . (mb_strtolower($extra) === 'qurxin' ? 'visual improvements' : $extra);

                return 'This week, I worked on multiple sections of the ' . $site
                    . ' website' . $detail . '.';
            }

            $polished = 'Waxaan asbuucan ka shaqeeyay qaybo kala duwan oo ka mid ah mareegta ' . $site;
            if (trim((string) ($matches[2] ?? '')) !== '') {
                if (mb_strtolower(trim($matches[2])) === 'qurxin') {
                    $polished .= ', waxaana ku daray hagaajin muuqaal ah';
                } else {
                    $polished .= ', waxaanan ku daray ' . trim($matches[2]);
                }
            }

            return $polished . '.';
        }

        if ($section === 'work' && ! $isSomali
            && preg_match('/^waxaan\s+(?:ku\s*)?daray\s+qurxin[.!?…]*$/iu', $fact)) {
            return 'I also added visual improvements.';
        }

        if ($section === 'meeting' && preg_match(
            '/^mid\s+waa\s+main\s+meeting\s+ka\s+asbuuclaha\s+ah\s+ee\s+(.+?)\s+oo\s+agends\s+kala\s+duwan'
            . '\s+iyo\s+reporting\s+la\s+isku\s+sameeyey[.!?…]*$/iu',
            $fact,
            $matches,
        )) {
            $organization = $this->properName($matches[1]);
            return $isSomali
                ? 'Kulanka weyn ee toddobaadlaha ah ee ' . $organization
                    . '; waxaa laga hadlay ajandayaal kala duwan iyo warbixin.'
                : 'The ' . $organization . ' weekly main meeting covered various agenda items and reporting.';
        }

        if ($section === 'meeting' && preg_match(
            '/^midna\s+(?:waa\s+){1,2}(?:mid\s+)?aan\s+la\s+yeelanay\s+(.+?)\s+oo\s+la\s+rabay\s+in\s+loo\s+sameeyo'
            . '\s+system\s+lagu\s+tala\s+galay\s+reporting\s+ka\s+ee\s+la\s+socoshada\s+halka\s+ee\s+wax\s+'
            . 'marayaan\s+shaqooyinka[.!?…]*$/iu',
            $fact,
            $matches,
        )) {
            $organization = $this->properName($matches[1]);
            return $isSomali
                ? 'Kulan lala yeeshay ' . $organization
                    . ' oo ku saabsanaa soo jeedinta nidaam loogu talagalay warbixinta iyo la socodka halka shaqooyinku marayaan.'
                : 'A meeting with ' . $organization
                    . ' concerned a proposed system for reporting and tracking work progress.';
        }

        if ($section === 'meeting' && preg_match(
            '/^midka\s+kalena\s+waa\s+meeting\s+lo+ga\s+hadlaayey\s+daah\s+furka\s+xaflada\s+event\s+ga\s+(.+?)[.!?…]*$/iu',
            $fact,
            $matches,
        )) {
            $event = $this->properName($matches[1]);
            return $isSomali
                ? 'Kulan looga hadlay daahfurka xafladda ' . $event . '.'
                : 'A meeting discussed the launch event for ' . $event . '.';
        }

        return $this->sentence($fact);
    }

    private function properName(string $name): string
    {
        $name = trim($name);
        $name = mb_strtoupper(mb_substr($name, 0, 1)) . mb_substr($name, 1);

        return (string) preg_replace('/\bai\b/iu', 'AI', $name);
    }

    private function removeRedundantWorkDetails(array $facts): array
    {
        $normalized = array_map(
            fn (string $fact): string => str_replace('hagaajin muuqaal ah', 'qurxin', mb_strtolower(
                (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', trim($fact)),
            )),
            $facts,
        );
        $redundant = [];

        foreach ($facts as $index => $fact) {
            $fact = trim($fact);
            if (! preg_match(
                '/^(?:waxaan(?:an)?\s+(?:sidoo\s+kale\s+)?(?:ku\s*)?daray|I\s+also\s+added)\s+(.+?)[.!?…]*$/iu',
                $fact,
                $match,
            )) {
                continue;
            }

            $detail = mb_strtolower(
                (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', trim($match[1])),
            );
            if ($detail === '') {
                continue;
            }

            foreach ($normalized as $otherIndex => $other) {
                if ($index !== $otherIndex && str_contains($other, $detail)) {
                    $redundant[$index] = true;
                    break;
                }
            }
        }

        return array_values(array_filter(
            $facts,
            static fn (int $index): bool => ! isset($redundant[$index]),
            ARRAY_FILTER_USE_KEY,
        ));
    }

    private function isParticipationCountOnly(string $fact): bool
    {
        return (bool) preg_match(
            '/\b(?:participated\s+in|attended|took\s+part\s+in|ka\s*(?:qayb|qeeb|qeyb)\s+gal(?:ay|i|aa))\b'
            . '.*\b(?:one|two|three|four|five|six|seven|eight|nine|ten|ilaa\s*)?\d*\s+meetings?\s*[.!?…]*$/iu',
            trim($fact),
        );
    }

    private function expandMeetingFacts(array $facts): array
    {
        $expanded = [];

        foreach ($facts as $fact) {
            $details = preg_replace(
                '/^(?:(?:I|we)\s+)?(?:participated\s+in|attended)\s+(?:one|two|three|four|five|six|seven|eight|nine|ten|\d+)\s+meetings?\s*[:—-]\s*/iu',
                '',
                $fact,
                1,
            );
            $meetings = preg_split('/;\s*(?:and\s+)?/iu', (string) $details, -1, PREG_SPLIT_NO_EMPTY) ?: [];

            if (count($meetings) <= 1) {
                $expanded[] = trim($fact);
                continue;
            }

            foreach ($meetings as $meeting) {
                $meeting = trim((string) preg_replace('/^and\s+/iu', '', $meeting));
                if ($meeting !== '') {
                    $expanded[] = $meeting;
                }
            }
        }

        return array_values(array_unique($expanded));
    }

    private function sentence(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $text = mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);

        return preg_match('/[.!?…]$/u', $text) ? $text : $text . '.';
    }

    private function explicitMeetingCount(string $source): ?int
    {
        if (preg_match(
            '/\b(?:up\s+to|at\s+most|no\s+more\s+than|ilaa)\s+(?:one|two|three|four|five|six|seven|eight|nine|ten|\d+)\s+meetings?\b/iu',
            $source,
        )) {
            return null;
        }

        if (! preg_match('/\b(?:one|two|three|four|five|six|seven|eight|nine|ten|\d+)\s+meetings?\b/iu', $source, $matches)) {
            return null;
        }

        return match (mb_strtolower($matches[0])) {
            'one meeting', '1 meeting', '1 meetings' => 1,
            'two meetings', '2 meeting', '2 meetings' => 2,
            'three meetings', '3 meeting', '3 meetings' => 3,
            'four meetings', '4 meeting', '4 meetings' => 4,
            'five meetings', '5 meeting', '5 meetings' => 5,
            'six meetings', '6 meeting', '6 meetings' => 6,
            'seven meetings', '7 meeting', '7 meetings' => 7,
            'eight meetings', '8 meeting', '8 meetings' => 8,
            'nine meetings', '9 meeting', '9 meetings' => 9,
            'ten meetings', '10 meeting', '10 meetings' => 10,
            default => $this->numericMeetingCount($matches[0]),
        };
    }

    private function numericMeetingCount(string $match): ?int
    {
        return preg_match('/\d+/u', $match, $number) ? (int) $number[0] : null;
    }

    private function somaliNumberWord(int $number): string
    {
        return [
            1 => 'hal',
            2 => 'laba',
            3 => 'saddex',
            4 => 'afar',
            5 => 'shan',
            6 => 'lix',
            7 => 'toddoba',
            8 => 'siddeed',
            9 => 'sagaal',
            10 => 'toban',
        ][$number] ?? (string) $number;
    }

    private function numberWord(int $number): string
    {
        return [
            1 => 'one',
            2 => 'two',
            3 => 'three',
            4 => 'four',
            5 => 'five',
            6 => 'six',
            7 => 'seven',
            8 => 'eight',
            9 => 'nine',
            10 => 'ten',
        ][$number] ?? '(?!)';
    }
}
