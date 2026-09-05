<?php

namespace App\Extensions\SocialMediaAgent\System\Services;

use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity;
use App\Extensions\SocialMedia\System\Models\SocialMediaPlatform;
use App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgent;
use App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgentPost;
use App\Helpers\Classes\ApiHelper;
use App\Helpers\Classes\OpenAiParamHelper;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;

class PostGenerationService
{
    protected string $model;

    protected ?ImageGenerationService $imageService = null;

    public function __construct()
    {
        $this->model = EntityEnum::GPT_5_MINI->value;
    }

    protected array $industryKeywords = [
        'Fashion & Apparel'      => ['fashion', 'apparel', 'clothing', 'boutique', 'style', 'couture', 'streetwear', 'wardrobe', 'runway', 'designer'],
        'Beauty & Cosmetics'     => ['beauty', 'cosmetic', 'skincare', 'makeup', 'salon', 'spa', 'haircare', 'fragrance'],
        'Technology & SaaS'      => ['tech', 'technology', 'software', 'saas', 'app', 'platform', 'digital', 'ai', 'cloud', 'startup'],
        'Health & Wellness'      => ['wellness', 'health', 'fitness', 'gym', 'yoga', 'nutrition', 'healthcare', 'mental health'],
        'Food & Beverage'        => ['restaurant', 'cafe', 'food', 'beverage', 'catering', 'bakery', 'culinary', 'chef'],
        'Travel & Hospitality'   => ['travel', 'hotel', 'tourism', 'resort', 'hospitality', 'destination', 'tour'],
        'Finance & Consulting'   => ['finance', 'investment', 'consulting', 'accounting', 'banking', 'wealth'],
        'Education & Coaching'   => ['education', 'course', 'coaching', 'training', 'school', 'learning', 'mentorship'],
        'Real Estate'            => ['real estate', 'property', 'broker', 'realtor', 'housing', 'mortgage'],
        'Automotive & Mobility'  => ['auto', 'automotive', 'car', 'dealership', 'mobility', 'garage', 'vehicle'],
    ];

    protected array $postTypeGuidelines = [
        'announcements'      => [
            'label'        => 'Announcement',
            'instructions' => 'Highlight important company news, launches, or milestones with specific dates and next steps.',
        ],
        'product_promotions' => [
            'label'        => 'Product Promotion',
            'instructions' => 'Spotlight a specific product or offer, focus on benefits, and include a compelling call-to-action or incentive.',
        ],
        'informative'        => [
            'label'        => 'Informative',
            'instructions' => 'Share educational insights, explain key concepts, or provide valuable context that helps the audience learn something new.',
        ],
        'customer_stories'   => [
            'label'        => 'Customer Story',
            'instructions' => 'Tell a realistic customer success story with quotes, before/after results, or metrics that show impact.',
        ],
        'tips_and_tricks'    => [
            'label'        => 'Tips & Tricks',
            'instructions' => 'Offer practical, actionable tips or step-by-step advice the audience can apply immediately.',
        ],
    ];

    /**
     * Generate a social media post based on agent configuration
     */
    public function generatePost(SocialMediaAgent|Builder|Model $agent, array $options = []): array
    {
        try {
            ApiHelper::setOpenAiKey();

            $generationSettings = [
                'tone'             => $options['tone'] ?? $agent->tone,
                'language'         => $options['language'] ?? $agent->language,
                'hashtag_count'    => $options['hashtag_count'] ?? $agent->hashtag_count,
                'include_hashtags' => array_key_exists('include_hashtags', $options) ? (bool) $options['include_hashtags'] : true,
                'include_emojis'   => array_key_exists('include_emojis', $options) ? (bool) $options['include_emojis'] : null,
                'approximate_words'=> $options['approximate_words'] ?? $agent->approximate_words,
            ];

            $customPostTypes = null;
            if (isset($options['post_types']) && is_array($options['post_types'])) {
                $customPostTypes = $this->filterSupportedPostTypes($options['post_types']);
                if (empty($customPostTypes)) {
                    $customPostTypes = null;
                }
            }

            if ($generationSettings['include_hashtags'] === false) {
                $generationSettings['hashtag_count'] = 0;
            }

            $selectedPostType = $options['post_type'] ?? null;
            if ($selectedPostType && ! $this->isSupportedPostType($selectedPostType)) {
                $selectedPostType = null;
            }

            if ($customPostTypes && $selectedPostType && ! in_array($selectedPostType, $customPostTypes, true)) {
                $selectedPostType = null;
            }

            if (! $selectedPostType) {
                if ($customPostTypes) {
                    if ($agent->exists) {
                        $selectedPostType = $this->getNextPostTypeForAgent($agent, $customPostTypes);
                    } else {
                        $selectedPostType = $customPostTypes[0];
                    }
                } else {
                    $selectedPostType = $this->getNextPostTypeForAgent($agent);
                }
            }

            if ($selectedPostType) {
                $generationSettings['post_type'] = $selectedPostType;
            }

            $prompt = $this->buildPrompt($agent, $options, $generationSettings);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . ApiHelper::setOpenAiKey(),
                'Content-Type'  => 'application/json',
            ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', OpenAiParamHelper::sanitizeChatParams([
                'model'           => $this->model,
                'messages'        => [
                    [
                        'role'    => 'system',
                        'content' => $this->getSystemPrompt($agent, $generationSettings),
                    ],
                    [
                        'role'    => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => $this->getTemperature($agent->creativity),
                'max_tokens'  => 1000,
            ]));

            if ($response->failed()) {
                Log::error('PostGenerationService API Error: ' . $response->body());

                return [
                    'success' => false,
                    'error'   => 'Failed to generate post',
                ];
            }

            $content = $response->json('choices.0.message.content');

            if ($content) {
                Entity::driver(EntityEnum::fromSlug($this->model))
                    ->input($content)
                    ->calculateCredit()
                    ->decreaseCredit();
            }

            $post = $this->parsePostContent($content, $agent, $generationSettings);

            if ($post['success'] && $selectedPostType) {
                $post['post_type'] = $selectedPostType;
                $post['metadata']['post_type'] = $selectedPostType;
            }

            // Generate image if agent has_image enabled
            if ($post['success'] && $agent->has_image) {
                $imageSourceContent = $post['content_without_hashtags'] ?? $post['content'];

                $imageResult = $this->getImageService()->generateImageForPost($imageSourceContent, [
                    'tone'     => $agent->tone,
                    'language' => $agent->language,
                ]);

                $imageModel = $this->getImageService()->getModel();

                if ($imageResult['success']) {
                    $imageStatus = $this->normalizeImageStatus($imageResult['status'] ?? null);

                    if (! empty($imageResult['image_url'])) {
                        $post['image_url'] = $imageResult['image_url'];
                        $post['metadata']['image_generated'] = true;
                        $imageStatus = 'completed';
                    } else {
                        $post['metadata']['image_generated'] = false;
                    }

                    $imageRequestId = $imageResult['request_id'] ?? null;

                    $post['metadata']['image_request_id'] = $imageRequestId;
                    $post['metadata']['image_status'] = $imageStatus;
                    $post['metadata']['image_model'] = $imageModel;

                    $post['image_request_id'] = $imageRequestId;
                    $post['image_status'] = $imageStatus ?? 'pending';
                    $post['image_model'] = $imageModel;

                    $post['image_prompt'] = $imageResult['prompt'] ?? null;
                } else {
                    $post['metadata']['image_generated'] = false;
                    $post['metadata']['image_generation_error'] = $imageResult['error'] ?? 'Image generation failed.';
                    $post['metadata']['image_status'] = 'failed';
                    $post['metadata']['image_model'] = $imageModel;
                    $post['image_status'] = 'failed';
                    $post['image_model'] = $imageModel;
                }
            }

            return $post;
        } catch (Exception $e) {
            Log::error('PostGenerationService Error: ' . $e->getMessage());

            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Get or create image service
     */
    protected function getImageService(): ImageGenerationService
    {
        if (! $this->imageService) {
            $this->imageService = new ImageGenerationService;
        }

        return $this->imageService;
    }

    /**
     * Generate multiple posts at once
     */
    public function generateBulkPosts(SocialMediaAgent $agent, int $count = 5): array
    {
        $posts = [];

        for ($i = 0; $i < $count; $i++) {
            $post = $this->generatePost($agent, [
                'variation' => $i + 1,
            ]);

            if (isset($post['success']) && $post['success']) {
                $posts[] = $post;
            }

            // Add small delay to avoid rate limiting
            if ($i < $count - 1) {
                usleep(500000); // 0.5 seconds
            }
        }

        return $posts;
    }

    public function generateOnePost(SocialMediaAgent $agent, ?string $argsString = null): ?SocialMediaAgentPost
    {
        $payload = [];

        if ($argsString) {
            try {
                $decoded = json_decode($argsString, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            } catch (JsonException $e) {
                Log::error('generateOnePost payload error: ' . $e->getMessage());

                return null;
            }
        }

        $options = [];

        foreach (['topic', 'platform', 'tone', 'language', 'call_to_action', 'post_type'] as $key) {
            if (! empty($payload[$key])) {
                $options[$key] = $payload[$key];
            }
        }

        if (isset($payload['include_hashtags'])) {
            $includeHashtags = filter_var($payload['include_hashtags'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($includeHashtags !== null) {
                $options['include_hashtags'] = $includeHashtags;
            }
        }

        if (isset($payload['include_emojis'])) {
            $includeEmojis = filter_var($payload['include_emojis'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($includeEmojis !== null) {
                $options['include_emojis'] = $includeEmojis;
            }
        }

        $result = $this->generatePost($agent, $options);

        if (! data_get($result, 'success')) {
            Log::warning('generateOnePost failed: ' . data_get($result, 'error', 'Unknown error'));

            return null;
        }

        $platformId = $this->resolvePlatformId($agent, $payload['platform'] ?? null);

        if (! $platformId) {
            Log::warning('generateOnePost: missing platform for agent ' . $agent->id);

            return null;
        }

        $mediaUrls = [];
        $imageRequestId = $result['image_request_id'] ?? data_get($result, 'metadata.image_request_id');
        $imageStatus = $result['image_status'] ?? data_get($result, 'metadata.image_status', 'none');

        if (! empty($result['image_url'])) {
            $mediaUrls[] = $result['image_url'];
            $imageStatus = 'completed';
        } elseif ($imageRequestId) {
            $imageStatus = $imageStatus !== 'none' ? $imageStatus : 'pending';
        } else {
            $imageStatus = 'none';
        }

        $metadata = array_filter(array_merge($result['metadata'] ?? [], [
            'source'                => 'chat_tool',
            'requested_topic'       => $payload['topic'] ?? null,
            'requested_platform'    => $payload['platform'] ?? null,
            'requested_cta'         => $payload['call_to_action'] ?? null,
        ]));

        $videoUrls = [];
        $videoRequestId = null;
        $videoStatus = 'none';
        $postType = 'post';

        $platform = SocialMediaPlatform::find($platformId);
        $platformSlug = $platform?->platform ?? null;
        $supportsVideo = $platformSlug && in_array($platformSlug, ['youtube', 'youtube-shorts'], true);

        if ($supportsVideo) {
            try {
                /** @var VideoGenerationService $videoService */
                $videoService = app(VideoGenerationService::class);
                $videoResult = $videoService->generateVideoForPost(
                    $result['content_without_hashtags'] ?? $result['content'] ?? $result['full_text'],
                    [
                        'platform' => $platformSlug,
                    ]
                );

                if ($videoResult['success']) {
                    $videoStatus = $videoResult['status'] ?? 'pending';
                    $videoRequestId = $videoResult['request_id'] ?? null;

                    if (! empty($videoResult['video_url'])) {
                        $videoUrls[] = $videoResult['video_url'];
                        $videoStatus = 'completed';
                    }

                    if (! empty($videoResult['prompt'])) {
                        $metadata['video_prompt'] = $videoResult['prompt'];
                    }
                } else {
                    $videoStatus = 'failed';
                }
            } catch (Exception $e) {
                Log::error('generateOnePost video error: ' . $e->getMessage());
                $videoStatus = 'failed';
            }
        }

        if ($supportsVideo && ($videoRequestId || count($videoUrls) > 0)) {
            $postType = SocialMediaAgentPost::TYPE_VIDEO;
        }

        $post = SocialMediaAgentPost::create([
            'agent_id'         => $agent->id,
            'platform_id'      => $platformId,
            'content'          => $result['full_text'],
            'post_type'        => $postType,
            'publishing_type'  => $agent->publishing_type ?? 'post',
            'media_urls'       => $mediaUrls,
            'image_request_id' => $imageRequestId,
            'image_status'     => $imageStatus,
            'video_urls'       => $videoUrls,
            'video_request_id' => $videoRequestId,
            'video_status'     => $videoStatus,
            'hashtags'         => $result['hashtags'] ?? [],
            'ai_metadata'      => $metadata,
            'status'           => SocialMediaAgentPost::STATUS_DRAFT,
        ]);

        return $post->fresh(['platform']);
    }

    /**
     * Build the generation prompt
     */
    protected function buildPrompt(SocialMediaAgent $agent, array $options = [], array $generationSettings = []): string
    {
        $variation = $options['variation'] ?? 1;
        $baseContent = trim((string) ($options['base_content'] ?? ''));
        $requestedTopic = trim((string) ($options['topic'] ?? ''));
        $requestedPlatform = trim((string) ($options['platform'] ?? ''));
        $requestedCta = trim((string) ($options['call_to_action'] ?? ''));
        $task = $baseContent !== ''
            ? 'Rewrite the existing post content below into a refreshed, higher-performing variation while keeping the core message intact.'
            : 'Create an engaging social media post (variation #' . $variation . ') based on the following information:';

        if ($requestedTopic !== '' && $baseContent === '') {
            $task = 'Create an engaging social media post focused on: "' . $requestedTopic . '". Use the agent context below to stay on-brand.';
        }

        // Build context from agent data
        $context = [];

        if ($agent->site_description) {
            $context[] = "Business Description: {$agent->site_description}";
        }

        if ($agent->scraped_content && isset($agent->scraped_content['summary'])) {
            $context[] = "Website Summary: {$agent->scraped_content['summary']}";
        }

        if ($agent->branding_description) {
            $context[] = "Branding: {$agent->branding_description}";
        }

        if ($agent->target_audience) {
            $audiences = collect($agent->target_audience)->pluck('name')->toArray();
            $context[] = 'Target Audiences: ' . implode(', ', $audiences);
        }

        $industryGuidance = $this->buildIndustryGuidance($agent);

        if ($industryGuidance) {
            $context[] = $industryGuidance;
        }

        if ($agent->goals) {
            $context[] = 'Marketing Goals: ' . implode(', ', $agent->goals);
        }

        $industryGuidance = $this->buildIndustryGuidance($agent);

        if ($industryGuidance) {
            $context[] = $industryGuidance;
        }

        if ($requestedPlatform) {
            $context[] = 'Target Platform: ' . ucfirst($requestedPlatform);
        }

        if ($requestedTopic) {
            $context[] = 'Requested Topic: ' . $requestedTopic;
        }

        $contextString = implode("\n", array_filter($context));

        $ctaTemplates = '';
        if ($agent->cta_templates && count($agent->cta_templates) > 0) {
            $ctaTemplates = "\nAvailable CTA Templates:\n" . implode("\n", array_filter($agent->cta_templates));
        }

        $existingContentBlock = '';

        if ($baseContent !== '') {
            $existingContentBlock = "\nExisting Post Content:\n\"\"\"\n{$baseContent}\n\"\"\"\n\nWhen rewriting, preserve the main value proposition but improve clarity, hook, and engagement. Avoid repeating sentences verbatim unless necessary.";
        }

        $tone = $generationSettings['tone'] ?? $agent->tone;
        $language = $generationSettings['language'] ?? $agent->language;
        $approximateWords = $generationSettings['approximate_words'] ?? $agent->approximate_words;
        $hashtagCount = $generationSettings['hashtag_count'] ?? $agent->hashtag_count;
        $includeHashtags = $generationSettings['include_hashtags'] ?? true;
        $includeEmojis = $generationSettings['include_emojis'] ?? null;
        $selectedPostType = $generationSettings['post_type'] ?? null;

        $requirements = [];

        if (! empty($tone)) {
            $requirements[] = '- Tone: ' . $tone;
        }

        if (! empty($approximateWords)) {
            $requirements[] = '- Target length: approximately ' . $approximateWords . ' words.';
        }

        if (! empty($language)) {
            $requirements[] = '- Language: ' . $language;
        }

        if ($requestedPlatform) {
            $requirements[] = '- Optimize specifically for ' . ucfirst($requestedPlatform) . '.';
        }

        if ($requestedTopic) {
            $requirements[] = '- Keep the message tightly aligned with the requested topic.';
        }

        if ($requestedCta) {
            $requirements[] = '- Include or adapt this call-to-action: ' . $requestedCta;
        }

        if ($includeHashtags) {
            if (! empty($hashtagCount)) {
                $requirements[] = "- Include up to {$hashtagCount} relevant hashtags.";
            } else {
                $requirements[] = '- Include only highly relevant hashtags when they add value.';
            }
        } else {
            $requirements[] = '- Do not include hashtags.';
        }

        if ($includeEmojis === true) {
            $requirements[] = '- Emojis are allowed when they enhance clarity, but avoid overuse.';
        } elseif ($includeEmojis === false) {
            $requirements[] = '- Do not use emojis.';
        }

        if ($selectedPostType && isset($this->postTypeGuidelines[$selectedPostType])) {
            $guideline = $this->postTypeGuidelines[$selectedPostType];
            $requirements[] = '- Post Type: ' . ($guideline['label'] ?? ucfirst(str_replace('_', ' ', $selectedPostType))) . '. ' . ($guideline['instructions'] ?? 'Keep the content aligned with this format.');
        }

        $requirementsString = empty($requirements)
            ? '- Follow the brand voice while keeping the copy concise and valuable.'
            : implode("\n", $requirements);
        $postTypeResponse = $selectedPostType ?? 'post';

        return <<<PROMPT
{$task}

{$contextString}
{$existingContentBlock}

Post Requirements:
{$requirementsString}
{$ctaTemplates}

Respond with ONLY this JSON (no markdown, no code fences, no extra text):
{"content": "The main post text without hashtags", "hashtags": ["hashtag1", "hashtag2"], "post_type": "{$postTypeResponse}", "cta": "Call to action if applicable"}

Keep the JSON compact. Make the content unique and engaging. Focus on value for the target audience.
PROMPT;
    }

    /**
     * Get system prompt
     */
    protected function getSystemPrompt(SocialMediaAgent $agent, array $generationSettings = []): string
    {
        $tone = $generationSettings['tone'] ?? $agent->tone;
        $language = $generationSettings['language'] ?? $agent->language;

        return <<<SYSTEM
You are an expert social media content creator specialized in creating engaging posts for multiple platforms.

Your expertise includes:
- Writing compelling copy that resonates with target audiences
- Creating platform-optimized content
- Using effective hashtag strategies
- Incorporating strong calls-to-action
- Maintaining consistent brand voice
- Writing in multiple languages fluently

Tone to use: {$tone}
Language: {$language}

Guidelines:
- Keep the content authentic and engaging
- Use appropriate emojis sparingly (only if they enhance the message)
- Make every word count
- Focus on audience value
- Create scroll-stopping content
- Include a clear call-to-action when appropriate

CRITICAL: You must respond with ONLY a valid JSON object. No markdown, no code fences, no explanation, no text before or after the JSON. Keep your response short to avoid truncation.
SYSTEM;
    }

    /**
     * Parse the AI response
     */
    protected function parsePostContent(string $content, SocialMediaAgent $agent, array $generationSettings = []): array
    {
        try {
            // Clean the response
            $content = trim($content);
            $content = preg_replace('/```json\s*/', '', $content);
            $content = preg_replace('/```\s*/', '', $content);
            $content = trim($content);
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                // Attempt to repair truncated JSON (e.g. from max_tokens cutoff)
                $data = $this->attemptJsonRepair($content);
            }

            if (! is_array($data) || empty($data['content'])) {
                Log::warning('Failed to parse post content as JSON: ' . json_last_error_msg(), [
                    'raw_content' => mb_substr($content, 0, 500),
                ]);

                return [
                    'success' => false,
                    'error'   => 'Invalid AI response format',
                ];
            }

            // Ensure all required fields are present
            $postContent = $data['content'] ?? '';
            $hashtags = $data['hashtags'] ?? [];
            $postType = $data['post_type'] ?? 'post';
            $cta = $data['cta'] ?? null;
            $postTypeOverride = $generationSettings['post_type'] ?? null;
            if ($postTypeOverride) {
                $postType = $postTypeOverride;
            }

            // Limit hashtags to the configured count
            $includeHashtags = $generationSettings['include_hashtags'] ?? true;
            $hashtagLimit = $generationSettings['hashtag_count'] ?? $agent->hashtag_count;

            if ($includeHashtags === false) {
                $hashtags = [];
            } elseif (count($hashtags) > $hashtagLimit) {
                $hashtags = array_slice($hashtags, 0, $hashtagLimit);
            }

            // Format hashtags properly (ensure they start with #)
            $hashtags = array_map(function ($tag) {
                $tag = trim($tag);

                return str_starts_with($tag, '#') ? $tag : '#' . $tag;
            }, $hashtags);

            $finalContent = $this->formatFullPost($postContent, $hashtags, $cta);

            return [
                'success'                 => true,
                'content'                 => $finalContent,
                'content_without_hashtags'=> $postContent,
                'hashtags'                => $hashtags,
                'post_type'               => $postType,
                'cta'                     => $cta,
                'full_text'               => $finalContent,
                'metadata'                => [
                    'model'           => $this->model,
                    'tone'            => $generationSettings['tone'] ?? $agent->tone,
                    'language'        => $generationSettings['language'] ?? $agent->language,
                    'generated_at'    => now()->toIso8601String(),
                    'include_hashtags'=> $includeHashtags,
                    'include_emojis'  => $generationSettings['include_emojis'] ?? null,
                    'post_type'       => $postType,
                ],
            ];
        } catch (Exception $e) {
            Log::error('Error parsing post content: ' . $e->getMessage());

            return [
                'success' => false,
                'error'   => 'Failed to parse post content',
            ];
        }
    }

    /**
     * Attempt to repair truncated or malformed JSON from AI response.
     *
     * @return array<string, mixed>|null
     */
    protected function attemptJsonRepair(string $raw): ?array
    {
        // Extract the first JSON object if surrounded by extra text
        if (preg_match('/\{[\s\S]*/', $raw, $match)) {
            $json = $match[0];
        } else {
            return null;
        }

        // Trim trailing incomplete value (e.g. truncated string mid-word)
        $json = preg_replace('/,\s*"[^"]*$/', '', $json);      // trailing key without value
        $json = preg_replace('/,\s*$/', '', $json);             // trailing comma
        $json = preg_replace('/"[^"]*$/', '"', $json);          // unterminated string

        // Count unbalanced braces/brackets and close them
        $openBrackets = substr_count($json, '[') - substr_count($json, ']');
        $openBraces = substr_count($json, '{') - substr_count($json, '}');

        $json .= str_repeat(']', max(0, $openBrackets));
        $json .= str_repeat('}', max(0, $openBraces));

        $data = json_decode($json, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($data) && ! empty($data['content'])) {
            Log::info('PostGenerationService: repaired truncated JSON successfully.');

            return $data;
        }

        return null;
    }

    /**
     * Format the full post with content, CTA, and hashtags
     */
    protected function formatFullPost(string $content, array $hashtags, ?string $cta): string
    {
        $parts = [$content];

        if ($cta) {
            $parts[] = "\n\n" . $cta;
        }

        if (! empty($hashtags)) {
            $parts[] = "\n\n" . implode(' ', $hashtags);
        }

        return implode('', $parts);
    }

    /**
     * Get temperature based on creativity level
     */
    protected function getTemperature(?string $creativity): float
    {
        return match ($creativity) {
            'low'    => 0.5,
            'medium' => 0.7,
            'high'   => 0.9,
            default  => 0.7,
        };
    }

    protected function buildIndustryGuidance(SocialMediaAgent $agent): ?string
    {
        $contextSegments = [];

        if (is_array($agent->categories) && count($agent->categories)) {
            $contextSegments[] = implode(' ', $agent->categories);
        }

        $contextSegments[] = $agent->site_description ?? '';
        $contextSegments[] = $agent->branding_description ?? '';
        $contextSegments[] = data_get($agent->scraped_content, 'summary', '');

        if (is_array($agent->target_audience)) {
            foreach ($agent->target_audience as $audience) {
                $contextSegments[] = implode(' ', array_filter([
                    $audience['name'] ?? '',
                    $audience['segment'] ?? '',
                    $audience['description'] ?? '',
                ]));
            }
        }

        $contextText = strtolower(trim(implode(' ', array_filter($contextSegments))));

        $match = $this->matchIndustryFromText($contextText);

        if (! $match) {
            return null;
        }

        [$industryLabel, $keywords] = $match;

        return "Industry Focus: {$industryLabel}. Emphasize themes relevant to {$industryLabel} such as {$keywords}. Avoid topics unrelated to this industry.";
    }

    protected function resolvePlatformId(SocialMediaAgent $agent, ?string $requestedPlatform): ?int
    {
        $platformIds = $agent->platform_ids ?? [];

        $query = SocialMediaPlatform::query()
            ->where('user_id', $agent->user_id);

        if (! empty($platformIds)) {
            $query->whereIn('id', $platformIds);
        }

        if ($requestedPlatform) {
            $query->whereRaw('LOWER(platform) = ?', [strtolower($requestedPlatform)]);
        }

        $platform = $query->first();

        if ($platform) {
            return (int) $platform->id;
        }

        return $platformIds[0] ?? null;
    }

    protected function matchIndustryFromText(?string $text): ?array
    {
        if (! $text) {
            return null;
        }

        foreach ($this->industryKeywords as $label => $keywords) {
            foreach ($keywords as $keyword) {
                $keyword = strtolower($keyword);

                if ($keyword !== '' && str_contains($text, $keyword)) {
                    return [$label, implode(', ', $keywords)];
                }
            }
        }

        return null;
    }

    /**
     * Set the AI model to use
     */
    public function setModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    protected function normalizeImageStatus(?string $status): ?string
    {
        if (! $status) {
            return null;
        }

        $normalized = strtolower($status);

        if (in_array($normalized, ['pending', 'processing', 'generating', 'queued'], true)) {
            return 'pending';
        }

        if (in_array($normalized, ['done', 'completed', 'success'], true)) {
            return 'completed';
        }

        if (in_array($normalized, ['failed', 'error'], true)) {
            return 'failed';
        }

        return $normalized;
    }

    public function getNextPostTypeForAgent(SocialMediaAgent $agent, ?array $overridePostTypes = null): ?string
    {
        $available = $overridePostTypes !== null
            ? $this->filterSupportedPostTypes($overridePostTypes)
            : $this->filterSupportedPostTypes($agent->post_types ?? []);

        if (empty($available)) {
            return null;
        }

        if (! $agent->exists) {
            return $available[0];
        }

        $status = $agent->post_generation_status ?? [];
        $currentIndex = (int) data_get($status, 'next_post_type_index', 0);

        $postType = $available[$currentIndex % count($available)];
        $nextIndex = ($currentIndex + 1) % count($available);

        $agent->update([
            'post_generation_status' => array_merge($status, [
                'next_post_type_index' => $nextIndex,
            ]),
        ]);

        $agent->refresh();

        return $postType;
    }

    protected function filterSupportedPostTypes(array $postTypes): array
    {
        $filtered = array_values(array_filter($postTypes, function ($type) {
            return is_string($type) && $this->isSupportedPostType($type);
        }));

        return $filtered;
    }

    protected function isSupportedPostType(string $postType): bool
    {
        return array_key_exists($postType, $this->postTypeGuidelines);
    }

    /**
     * Generate post with image description for image generation
     */
    public function generateWithImagePrompt(SocialMediaAgent $agent, array $options = []): array
    {
        $post = $this->generatePost($agent, $options);

        if (! $post['success']) {
            return $post;
        }

        // Generate image prompt based on the post content
        try {
            $imageSourceContent = $post['content_without_hashtags'] ?? $post['content'];
            $imagePrompt = "Create a visual description for a social media image that would accompany this post:\n\n{$imageSourceContent}\n\nDescribe an engaging, relevant image in 1-2 sentences. Focus on visual elements, colors, and composition.";

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . ApiHelper::setOpenAiKey(),
                'Content-Type'  => 'application/json',
            ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', OpenAiParamHelper::sanitizeChatParams([
                'model'       => EntityEnum::GPT_5_MINI->value,
                'messages'    => [
                    [
                        'role'    => 'user',
                        'content' => $imagePrompt,
                    ],
                ],
                'temperature' => 0.8,
                'max_tokens'  => 150,
            ]));

            if ($response->successful()) {
                $post['image_prompt'] = $response->json('choices.0.message.content');
            }
        } catch (Exception $e) {
            Log::warning('Failed to generate image prompt: ' . $e->getMessage());
        }

        return $post;
    }
}
