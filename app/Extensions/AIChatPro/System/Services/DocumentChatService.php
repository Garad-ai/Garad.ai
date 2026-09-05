<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Services;

use Illuminate\Support\Str;

class DocumentChatService
{
    private const MAX_CHUNKS = 6;

    private const MAX_CONTEXT_CHARACTERS = 12000;

    private const STOP_WORDS = [
        'a', 'an', 'and', 'are', 'as', 'at', 'be', 'but', 'by', 'for', 'from', 'how', 'i', 'if', 'in', 'into', 'is', 'it',
        'of', 'on', 'or', 'that', 'the', 'their', 'them', 'this', 'to', 'was', 'what', 'when', 'where', 'which', 'who', 'why', 'with', 'you', 'your',
    ];

    public function __construct(private readonly DocumentRepository $documents) {}

    public function buildContextForChat(int $chatId, string $prompt, int $maxChunks = self::MAX_CHUNKS): ?string
    {
        $documents = $this->documents->getDocumentsForChat($chatId);
        if ($documents->isEmpty()) {
            return null;
        }

        $terms = $this->extractTerms($prompt);
        $scoredChunks = [];

        foreach ($documents as $document) {
            $chunks = is_array($document->chunks) ? $document->chunks : [];
            foreach ($chunks as $chunk) {
                $content = trim((string) ($chunk['content'] ?? ''));
                if ($content === '') {
                    continue;
                }

                $score = $this->scoreChunk($content, $prompt, $terms);
                $scoredChunks[] = [
                    'document' => $document->original_name,
                    'content' => $content,
                    'score' => $score,
                    'index' => (int) ($chunk['index'] ?? 0),
                ];
            }
        }

        usort($scoredChunks, static function (array $left, array $right): int {
            return $right['score'] <=> $left['score'];
        });

        $selected = array_filter(array_slice($scoredChunks, 0, $maxChunks), static fn (array $chunk): bool => $chunk['score'] > 0);

        if ($selected === []) {
            $selected = array_slice($scoredChunks, 0, min($maxChunks, 3));
        }

        $contextParts = [];
        $characterCount = 0;

        foreach ($selected as $chunk) {
            $part = sprintf("Document: %s\nChunk %d:\n%s", $chunk['document'], $chunk['index'] + 1, $chunk['content']);
            $characterCount += mb_strlen($part);
            if ($characterCount > self::MAX_CONTEXT_CHARACTERS) {
                break;
            }
            $contextParts[] = $part;
        }

        if ($contextParts === []) {
            return null;
        }

        return implode("\n\n---\n\n", $contextParts);
    }

    private function extractTerms(string $prompt): array
    {
        $normalized = Str::of(Str::lower($prompt))
            ->replaceMatches('/[^\pL\pN\s]+/u', ' ')
            ->squish()
            ->toString();

        $terms = array_filter(explode(' ', $normalized), function (string $term): bool {
            return mb_strlen($term) > 2 && ! in_array($term, self::STOP_WORDS, true);
        });

        return array_values(array_unique($terms));
    }

    private function scoreChunk(string $content, string $prompt, array $terms): int
    {
        $haystack = Str::lower($content);
        $normalizedPrompt = trim(Str::lower($prompt));
        $score = 0;
        $matchedTerms = 0;

        if ($normalizedPrompt !== '' && str_contains($haystack, $normalizedPrompt)) {
            $score += 20;
        }

        foreach ($terms as $term) {
            if (str_contains($haystack, $term)) {
                $matchedTerms++;
                $score += 4;
                $score += min(substr_count($haystack, $term), 5);
            }
        }

        if ($matchedTerms > 1) {
            $score += $matchedTerms * 2;
        }

        if ($matchedTerms === count($terms) && $matchedTerms > 0) {
            $score += 10;
        }

        if ($score === 0 && $terms === []) {
            $score = 1;
        }

        return $score;
    }
}