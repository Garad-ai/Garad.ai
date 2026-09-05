<?php

declare(strict_types=1);

namespace App\Extensions\AiChatProSmartImage\System\Services;

use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity as EntityFacade;
use App\Extensions\AiChatProSmartImage\System\Models\ChatSmartImage;
use App\Helpers\Classes\Helper;
use App\Helpers\Classes\PlanHelper;
use App\Models\Usage;
use Illuminate\Support\Facades\Log;
use Throwable;

class SmartImageService
{
    public static function isEnabled(): bool
    {
        if (! (bool) setting('ai_chat_pro_smart_image_enabled', true)) {
            return false;
        }

        $plan = PlanHelper::userPlan();

        if ($plan && ! PlanHelper::planMenuCheck($plan, 'ai_chat_pro_smart_image')) {
            return false;
        }

        return true;
    }

    public static function getMaxImages(): int
    {
        return (int) setting('ai_chat_pro_smart_image_max_images', 5);
    }

    /**
     * Search for images and return formatted result for the stream.
     * Called by AiChatProService::callFunction('search_images', ...).
     */
    public static function searchAndFormat(string $query, int $num = 5): string
    {
        if (! self::isEnabled()) {
            return '';
        }

        if (Helper::appIsDemo()) {
            return '';
        }

        try {
            $provider = setting('default_realtime', 'serper');

            // Check credit balance before searching
            if ($provider === 'serper') {
                $driver = EntityFacade::driver(EntityEnum::SERPER);
                // $driver->redirectIfNoCreditBalance(); leave the credit check off for now
            } elseif ($provider === 'openai') {
                $model = setting('openai_realtime_model', EntityEnum::GPT_4_O_SEARCH_PREVIEW->value);
                $driver = EntityFacade::driver(EntityEnum::fromSlug($model));
                // $driver->redirectIfNoCreditBalance(); leave the credit check off for now
            }

            $images = app(ImageSearchService::class)->search($query, $num);

            if (empty($images)) {
                return '';
            }

            // Deduct credits after successful search
            if (isset($driver)) {
                // $driver->input($query)->calculateCredit()->decreaseCredit();
                // Usage::getSingle()->updateWordCounts($driver->calculate());
            }

            return ':::smart-images' . "\n" . json_encode($images) . "\n" . ':::';
        } catch (Throwable $e) {
            Log::error('SmartImage: searchAndFormat failed', ['error' => $e->getMessage()]);

            return '';
        }
    }

    /**
     * Save smart images to the database for a given message.
     */
    public static function saveForMessage(int $messageId, ?int $userId, string $query, array $images): ?ChatSmartImage
    {
        if (empty($images)) {
            return null;
        }

        return ChatSmartImage::create([
            'user_id'    => $userId,
            'message_id' => $messageId,
            'query'      => $query,
            'images'     => $images,
        ]);
    }

    /**
     * Tool definition for the search_images function calling (Gemini format).
     */
    private static function toolDescriptionText(): string
    {
        return 'Search for and display relevant images alongside your response. '
            . 'Call this when the user mentions or asks about a named visual subject — a place, city, country, landmark, hotel, restaurant; a movie, film, TV show, or series; a song, album, or music artist; a person, actor, celebrity, athlete; a product, brand, car, or gadget; an animal breed, plant, or food dish; or visual topics like travel, architecture, interior design, fashion, art, nature, landscapes, historical events. '
            . 'Also call when the user says "show me", "what does X look like", "examples", "inspiration", "photos of", or similar. '
            . 'CRITICAL EXCEPTION — do NOT call for writing/business tasks even if they contain a proper noun. If the user is asking you to WRITE, DRAFT, COMPOSE, GENERATE TEXT, or PRODUCE copy (cold emails, sales outreach, marketing copy, blog posts, essays, scripts, social posts, product descriptions, ads, resumes, cover letters), the task is a writing task — do NOT call search_images. Example: "write a cold email to Tesla" is a writing task, skip it. "Tell me about Tesla" or "Tesla Model S" is a visual subject, call it. '
            . 'Also do NOT call for: coding, math, debugging, translation, summarization of user-provided text, definitions of abstract concepts, or purely textual/logical tasks. '
            . 'Do NOT call if the user asks to CREATE/GENERATE/DRAW/MAKE a new image (use generate_image for that). '
            . 'Do NOT call if the user says "no images" or "text only".';
    }

    public static function geminiToolDefinition(): array
    {
        return [
            'functionDeclarations' => [
                [
                    'name'        => 'search_images',
                    'description' => self::toolDescriptionText(),
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'query' => [
                                'type'        => 'string',
                                'description' => 'The search query to find relevant images. Should be concise and descriptive.',
                            ],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Tool definition for the search_images function calling (OpenAI format).
     */
    public static function toolDefinition(): array
    {
        return [
            'type'        => 'function',
            'name'        => 'search_images',
            'description' => self::toolDescriptionText(),
            'parameters'  => [
                'type'       => 'object',
                'properties' => [
                    'query' => [
                        'type'        => 'string',
                        'description' => 'The search query to find relevant images. Should be concise and descriptive.',
                    ],
                ],
                'required' => ['query'],
            ],
        ];
    }

    /**
     * System prompt addition when smart image is enabled.
     */
    public static function systemPromptAddition(): string
    {
        return 'RULE — search_images: Call the search_images function whenever the user mentions a named visual subject. This includes: '
            . 'a specific movie or film ("The Godfather", "Inception"), TV show ("Breaking Bad"), song, album, or music artist; '
            . 'a person, celebrity, actor, athlete ("Elon Musk", "Marlon Brando"); '
            . 'a place, city, country, landmark, hotel, restaurant ("Paris", "Tokyo", "Eiffel Tower"); '
            . 'a product, brand, car, or gadget ("iPhone", "Tesla Model S"); '
            . 'an animal breed, plant, or food dish ("Golden Retriever", "sushi"); '
            . 'or general visual topics: travel, architecture, interior design, fashion, art, nature, landscapes, historical events. '
            . 'Also call when the user says "show me", "examples", "inspiration", "what does it look like", or "photos of". '
            . 'CRITICAL EXCEPTION — if the user is asking you to WRITE, DRAFT, COMPOSE, or PRODUCE text (cold emails, sales outreach, marketing copy, blog posts, essays, scripts, ads, social posts, product descriptions, resumes, cover letters), the task is a WRITING task — do NOT call search_images even if a brand or proper noun appears in the prompt. '
            . 'Example: "write a cold email to Tesla" → writing task, do NOT call. "Tell me about Tesla" or just "Tesla Model S" → visual subject, DO call. "Cold Mail" or "Cold Email" are writing/business topics, NOT visual — do NOT call. '
            . 'Also do NOT call for: coding, math, debugging, translation, summarization, definitions of abstract concepts, or purely textual tasks. '
            . 'Do NOT call if the user asks to CREATE/GENERATE/DRAW/MAKE a new image (use generate_image for that). '
            . 'Do NOT call if the user says "no images" or "text only". '
            . 'When the user clearly mentions a visual named entity and is NOT asking you to write text about it, you SHOULD call search_images. '
            . 'ABSOLUTELY NEVER include any trace of function calls in your text response. Your response must NEVER contain text like "search_images(...)", "search_images({...})", "(query=...)", "function_call", or any JSON/code referencing the search_images function. '
            . 'The function call is handled invisibly by the system — the user must never see it. If you call search_images, your visible text response should only contain your normal answer with no reference to searching, querying, or calling any function. Images are displayed automatically by the system.';
    }
}
