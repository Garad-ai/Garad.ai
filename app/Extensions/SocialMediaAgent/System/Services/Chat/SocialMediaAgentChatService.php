<?php

declare(strict_types=1);

namespace App\Extensions\SocialMediaAgent\System\Services\Chat;

use App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgent;
use App\Extensions\SocialMediaAgent\System\Services\PostGenerationService;
use App\Helpers\Classes\Helper;
use App\Helpers\Classes\RateLimiter\RateLimiter;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use JsonException;
use RuntimeException;
use Throwable;

class SocialMediaAgentChatService
{
    private static function availableTools(): array
    {
        return [
            [
                'type'        => 'function',
                'name'        => 'generate_social_post',
                'description' => 'RoleYou are a Social Media AI Assistant.You analyze, advise, and plan social media content and campaigns. You may generate social media posts, captions, scripts, or images only via the generation tool and only when explicitly requested.CRITICAL RULE (HARD)You must NOT write or output any social media post, caption, script, thread, or ready-to-publish content directly in chat.If the user explicitly asks to create, write, draft, or generate a post or caption:You must classify the intent as GENERATEYou must call the appropriate generation toolYou must NOT manually write the post in the responseAll post or caption generation must occur only through the tool.Step 1 — Intent Classification (internal)Classify the user’s intent as:• ANALYZE (analytics, trends, competitors, breakdowns)• ADVISE (strategy, planning, optimization, recommendations)• GENERATE (explicit request for post, caption, script, or image creation)Step 2 — Action RulesIf intent is ANALYZE or ADVISEYou may:provide explanations and insightsgive strategic guidanceshare frameworks, structures, and checklistssuggest topic ideas as concepts (not written copy)You must NOT:output post-like textprovide example captions or scriptsuse emojis or publishable formattingIf intent is GENERATEYou must:call the appropriate generation toolpass all required parameters (platform, tone, format, campaign context, etc.)return only the tool responseYou must NOT:write post text directlypartially draft content outside the toolmix analysis with generated copyIf Intent Is UnclearAsk a clarification question before any action:“Do you want me to generate the post or caption, or only analyze and provide guidance?”Do not generate or call tools until clarified.Default Safety RuleIf there is any ambiguity, default to ANALYZE or ADVISE. Never generate content or call tools by default.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'topic' => [
                            'type'        => 'string',
                            'description' => 'Main topic or brief description of what the post should be about.',
                        ],
                        'tone' => [
                            'type'        => 'string',
                            'description' => 'Tone of voice (e.g. "casual", "professional", "funny", "inspirational").',
                        ],
                        'platform' => [
                            'type'        => 'string',
                            'description' => 'Social media platform (e.g. "Twitter", "Instagram", "LinkedIn").',
                        ],
                    ],
                    'required' => ['topic', 'platform'],
                ],
            ],
        ];
    }

    public static function tools(): array
    {
        return self::availableTools();
    }

    public static function callFunction(?string $functionName, ?string $argsString): ?string
    {
        return match ($functionName) {
            'generate_social_post'  => self::generatePost($argsString),
            default                 => null,
        };
    }

    public static function generatePost(?string $argsString): string
    {
        $clientIp = Helper::getRequestIp();

        $rateLimiter = new RateLimiter('social-media-agent-chat', 5);

        if (Helper::appIsDemo() && ! $rateLimiter->attempt($clientIp)) {
            return trans('This feature is disabled in this demo.');
        }

        try {
            $args = json_decode($argsString, true, 512, JSON_THROW_ON_ERROR);

            if (! isset($args['topic'])) {
                throw new JsonException('Invalid arguments: topic is required');
            }

            $agent = SocialMediaAgent::query()
                ->where('id', request('chat_open_ai_agent_id'))
                ->first();

            if (! $agent) {
                $agent = SocialMediaAgent::query()
                    ->where('user_id', auth()->id())
                    ->first();
            }

            if (! $agent) {
                throw new RuntimeException('No active agent available.');
            }

            $post = app(PostGenerationService::class)->generateOnePost($agent, $argsString);

            if (! $post) {
                throw new RuntimeException('Unable to create post.');
            }

            $response = [
                'success' => true,
                'post'    => Arr::only($post->toArray(), [
                    'id',
                    'agent_id',
                    'platform_id',
                    'content',
                    'hashtags',
                    'media_urls',
                    'image_status',
                    'image_request_id',
                    'status',
                    'created_at',
                ]),
            ];

            // Log::info('test log: ' . print_r($response, true));

            // Sending down the shell only. the rest will be handle on frontend
            $response_md = '::: social-media-agent-chat-post-card {data-platform-id=' . $post->platform->id . ' data-post-id=' . $post->id . '}<br>:::<br>';

            // return json_encode($response, JSON_THROW_ON_ERROR);
            return $response_md;
        } catch (JsonException|Throwable $e) {
            Log::error('SocialMediaAgentChatService::generatePost error: ' . $e->getMessage());

            return json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
