<?php

declare(strict_types=1);

namespace App\Extensions\AiChatProEntityHighlight\System\Services;

use App\Extensions\AiChatProEntityHighlight\System\Models\ChatEntityHighlight;
use App\Helpers\Classes\PlanHelper;
use Illuminate\Support\Facades\Auth;

class EntityHighlightService
{
    /**
     * Check if entity highlighting is enabled via settings and user plan.
     */
    public static function isEnabled(): bool
    {
        $settingEnabled = (bool) setting('ai_chat_pro_entity_highlight_enabled', true);
        $planCheck = PlanHelper::planMenuCheck(
            Auth::user()?->activePlan(),
            'ai_chat_pro_entity_highlight'
        );

        return $settingEnabled && $planCheck;
    }

    /**
     * Build the system prompt addition that instructs the AI to annotate entities.
     *
     * When $withSuggestions is true, the prompt also requires a "suggestions" array
     * inside the same metadata block — this replaces the standalone suggestions JSON
     * to avoid two competing "tail blocks" that confuse the model.
     */
    public static function systemPromptAddition(bool $withSuggestions = false): string
    {
        $max = (int) setting('ai_chat_pro_entity_highlight_max', 3);
        $threshold = (float) setting('ai_chat_pro_entity_highlight_threshold', 0.8);
        $types = implode(', ', config('ai-chat-pro-entity-highlight.supported_entity_types'));

        $entitiesField = '"entities":[{"text":"Example","type":"person","confidence":0.9,"occurrence_index":0,"context_snippet":"short quote"}]';
        $suggestionsField = $withSuggestions
            ? ',"suggestions":["short follow-up","short follow-up","short follow-up","short follow-up"]'
            : '';

        $suggestionsRule = $withSuggestions
            ? "\n- \"suggestions\": EXACTLY 4 diverse 2-6 word follow-up prompts related to the topic. If the last user message is trivial (greetings, ok, thanks, yes/no, bye), output \"suggestions\":[]."
            : '';

        return <<<PROMPT
HIDDEN METADATA BLOCK (machine-parsed, never shown to user):
You MUST end EVERY response with exactly ONE :::meta block. This is REQUIRED, not optional. Do NOT mention it, reference it, or describe it in your visible answer.
The block MUST start on its own line with EXACTLY :::meta and end with EXACTLY ::: on its own line.

Format (single-line JSON inside the block):
:::meta
{{$entitiesField}{$suggestionsField}}
:::

Rules:
- "entities": up to {$max} items (aim for {$max} when applicable). Min confidence {$threshold}. Allowed types: {$types}.
- "text" MUST be a short, exact substring from your visible answer in the SAME language as the answer (a single name or title, never a full sentence).
- "context_snippet" MUST be in the same language as your answer. "type" MUST be one of the English type values listed above.
- Skip generic words, code identifiers, URLs. If no entity from the allowed types fits, output "entities":[] — but STILL output the block.{$suggestionsRule}

Forbidden:
- Do NOT write "Entities:", "Suggestions:", "Here are…", lists, or any prose/label before or after the block.
- Do NOT wrap the block in code fences or backticks.
- Do NOT output the block more than once. Do NOT output any text after the closing :::.
Your answer ends cleanly, then a blank line, then the :::meta block, then nothing.
PROMPT;
    }

    /**
     * Parse and validate entity annotations from the AI response output.
     *
     * Handles both raw newlines and <br/> replacements in the output.
     *
     * @return array<int, array{text: string, type: string, confidence: float, occurrence_index: int, context_snippet: string}>
     */
    public static function parseAnnotations(string $output): array
    {
        // Normalize <br/> to newlines for consistent parsing
        $normalized = str_replace(['<br/>', '<br>'], "\n", $output);

        $entities = self::extractEntitiesFromMeta($normalized);

        if ($entities === null) {
            // Backward-compat fallbacks for the old :::entity-highlights format
            $json = null;
            if (preg_match('/:::\s*entity[- ]?highlights?\s*\n?\s*(.*?)\s*\n?\s*:::/si', $normalized, $matches)) {
                $json = trim($matches[1]);
            } elseif (preg_match('/\n\s*(\[\s*\{[\s\S]*?"text"\s*:\s*"[\s\S]*?\}\s*\])\s*(?::::\s*)?$/s', $normalized, $matches)) {
                $json = trim($matches[1]);
            }

            if ($json === null || $json === '') {
                return [];
            }

            if (! str_starts_with($json, '[')) {
                $bracketPos = strpos($json, '[');
                if ($bracketPos === false) {
                    return [];
                }
                $json = substr($json, $bracketPos);
            }

            $entities = json_decode($json, true);
        }

        if (! is_array($entities)) {
            return [];
        }

        $max = (int) setting('ai_chat_pro_entity_highlight_max', 3);
        $threshold = (float) setting('ai_chat_pro_entity_highlight_threshold', 0.8);
        $supportedTypes = config('ai-chat-pro-entity-highlight.supported_entity_types');

        $cleanOutput = self::stripAnnotationBlock($output);

        $validated = [];
        $seenTexts = [];

        foreach ($entities as $entity) {
            if (empty($entity['text']) || empty($entity['type']) || ! isset($entity['confidence'])) {
                continue;
            }

            if ((float) $entity['confidence'] < $threshold) {
                continue;
            }

            if (! in_array($entity['type'], $supportedTypes, true)) {
                continue;
            }

            $normalizedText = mb_strtolower($entity['text']);
            if (in_array($normalizedText, $seenTexts, true)) {
                continue;
            }

            // Check against both the raw and normalized clean output (case-insensitive fallback)
            $cleanNormalized = str_replace(['<br/>', '<br>'], "\n", $cleanOutput);
            if (mb_strpos($cleanNormalized, $entity['text']) === false
                && mb_stripos($cleanNormalized, $entity['text']) === false) {
                continue;
            }

            if (self::isInsideCodeBlock($cleanNormalized, $entity['text'])) {
                continue;
            }

            $seenTexts[] = $normalizedText;
            $validated[] = [
                'text'             => $entity['text'],
                'type'             => $entity['type'],
                'confidence'       => round((float) $entity['confidence'], 2),
                'occurrence_index' => (int) ($entity['occurrence_index'] ?? 0),
                'context_snippet'  => $entity['context_snippet'] ?? '',
            ];

            if (count($validated) >= $max) {
                break;
            }
        }

        return $validated;
    }

    /**
     * Strip the :::meta (or legacy :::entity-highlights) block from the output.
     */
    public static function stripAnnotationBlock(string $output): string
    {
        // Strip everything from :::meta or :::entity-highlights to end.
        $result = preg_replace('/\s*(?:<br\s*\/?>|\n)*\s*:::\s*(?:meta|entity[- ]?highlights?)[\s\S]*$/i', '', $output);

        // Fallback: AI dropped ::: markers — strip trailing JSON array with entity keys
        $result = preg_replace('/\s*(?:<br\s*\/?>|\n)+\s*\[\s*\{[\s\S]*?"text"\s*:\s*"[\s\S]*?\}\s*\]\s*$/i', '', $result);

        return rtrim($result);
    }

    /**
     * Extract suggestions array from a :::meta block. Returns null if no block or no suggestions key.
     *
     * @return array<int, string>|null
     */
    public static function parseSuggestions(string $output): ?array
    {
        $normalized = str_replace(['<br/>', '<br>'], "\n", $output);
        $decoded = self::decodeMetaJson($normalized);

        if (! is_array($decoded) || ! isset($decoded['suggestions']) || ! is_array($decoded['suggestions'])) {
            return null;
        }

        $suggestions = array_values(array_filter(
            $decoded['suggestions'],
            fn ($s) => is_string($s) && trim($s) !== ''
        ));

        return $suggestions;
    }

    /**
     * Extract entities array from a :::meta block. Returns null when no meta block is present
     * (so callers can fall back to legacy parsing).
     *
     * @return array<int, mixed>|null
     */
    private static function extractEntitiesFromMeta(string $normalized): ?array
    {
        $decoded = self::decodeMetaJson($normalized);

        if (! is_array($decoded)) {
            return null;
        }

        if (! isset($decoded['entities']) || ! is_array($decoded['entities'])) {
            // Meta block exists but no entities key — treat as empty list, do not fall back.
            return [];
        }

        return $decoded['entities'];
    }

    /**
     * Find a :::meta ... ::: block and decode its JSON body.
     */
    private static function decodeMetaJson(string $normalized): ?array
    {
        if (! preg_match('/:::\s*meta\s*\n?\s*(.*?)\s*\n?\s*:::/si', $normalized, $matches)) {
            return null;
        }

        $json = trim($matches[1]);

        // Strip code fences the AI may have wrapped around the JSON
        $json = preg_replace('/^```(?:json)?\s*/i', '', $json);
        $json = preg_replace('/\s*```$/', '', $json);
        $json = trim($json);

        if ($json === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Check if an entity's position falls inside a fenced or inline code block.
     */
    private static function isInsideCodeBlock(string $text, string $entity): bool
    {
        preg_match_all('/```[\s\S]*?```|`[^`]+`/', $text, $codeMatches, PREG_OFFSET_CAPTURE);

        $entityPos = mb_strpos($text, $entity);
        if ($entityPos === false) {
            return false;
        }

        foreach ($codeMatches[0] as $match) {
            $codeStart = $match[1];
            $codeEnd = $codeStart + strlen($match[0]);
            if ($entityPos >= $codeStart && $entityPos < $codeEnd) {
                return true;
            }
        }

        return false;
    }

    /**
     * Persist validated entity highlights to the database for a message.
     */
    public static function saveForMessage(int $userId, int $messageId, array $entities): void
    {
        if (empty($entities)) {
            return;
        }

        ChatEntityHighlight::create([
            'user_id'    => $userId,
            'message_id' => $messageId,
            'entities'   => $entities,
        ]);
    }
}
