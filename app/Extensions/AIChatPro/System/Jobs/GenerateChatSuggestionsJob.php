<?php

namespace App\Extensions\AIChatPro\System\Jobs;

use App\Models\UserOpenaiChatMessage;
use App\Services\Ai\AiCompletionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class GenerateChatSuggestionsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const SYSTEM_PROMPT = <<<'PROMPT'
        You are an expert AI chat assistant.

        You will receive a user message and an AI response. You must decide whether the user's message
        deserves follow-up suggestions.

        IMPORTANT: Base your decision ONLY on the user's message in isolation — ignore the AI response content
        when deciding. Only use the AI response to generate relevant suggestions if you decide to proceed.

        The user's message does NOT deserve suggestions if it is any of the following (in ANY language):
        - A greeting or salutation: hi, hello, hey, hey man, what's up, مرحبا, bonjour, hola, etc.
        - A short reaction or acknowledgement: ok, thanks, cool, nice, wow, lol, great, awesome, etc.
        - A yes/no or minimal answer: yes, no, yep, nope, sure, etc.
        - A farewell: bye, goodbye, see you, etc.
        - Filler or vague: hmm, idk, um, etc.
        - Any message that does NOT contain a clear question, request, or topic to explore.

        If the message does NOT deserve suggestions, return ONLY: {"skip": true}

        If the message DOES deserve suggestions, generate:
        1. A short friendly brief of what the suggestions are about (1 sentence).
        2. An array of 4 short, actionable follow-up suggestions the user might want to ask next.

        STRICT OUTPUT RULES:
        - Return ONLY valid JSON
        - No markdown, no explanations, no extra text, no additional keys

        JSON FORMAT (must match exactly):
        {
          "brief": "short friendly sentence about the suggestions",
          "suggestions": ["Suggestion 1","Suggestion 2","Suggestion 3","Suggestion 4"]
        }

        Suggestions rules:
        - 2–6 words each
        - Relevant follow-up questions or topics
        - Concrete and actionable
        - Diverse range of follow-up directions
        - Written as short prompts the user would type
        PROMPT;

    private const DEFAULT_PAYLOAD = [
        'brief'       => 'If you want, I can:',
        'suggestions' => [
            'Make a shorter version',
            'Rewrite for email',
            'Make it more dramatic',
            'Adapt it to your Brand',
        ],
    ];

    public function __construct(protected int $messageId) {}

    public function handle(): void
    {
        $message = UserOpenaiChatMessage::find($this->messageId);

        if (! $message) {
            Log::warning('Chat suggestion generation: message not found', [
                'message_id' => $this->messageId,
            ]);

            return;
        }

        $this->generateSuggestions($message);
    }

    private function generateSuggestions(UserOpenaiChatMessage $message): void
    {
        $userInput = $message->input ?? '';
        $aiResponse = mb_substr($message->output ?? '', 0, 500);
        $userContent = "User message: {$userInput}.\n\n AI response (truncated): {$aiResponse}";

        try {
            $responseText = app(AiCompletionService::class)->complete(self::SYSTEM_PROMPT, $userContent);

            $payload = json_decode($responseText, true, 512, JSON_THROW_ON_ERROR);

            // AI determined the message is trivial — skip suggestions
            if (! empty($payload['skip'])) {
                return;
            }

            if (
                ! isset($payload['brief'], $payload['suggestions']) ||
                ! is_array($payload['suggestions']) ||
                count($payload['suggestions']) !== 4
            ) {
                throw new RuntimeException('Invalid AI response structure.');
            }
        } catch (Throwable $e) {
            Log::warning('Chat suggestion generation failed', [
                'message_id' => $message->id,
                'error'      => $e->getMessage(),
            ]);

            return;
        }

        $message->update([
            'suggestions_response' => $payload,
        ]);
    }
}
