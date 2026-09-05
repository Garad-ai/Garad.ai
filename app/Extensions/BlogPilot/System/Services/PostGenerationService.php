<?php

namespace App\Extensions\BlogPilot\System\Services;

use App\Domains\Entity\Enums\EntityEnum;
use App\Extensions\BlogPilot\System\Models\BlogPilot;
use App\Helpers\Classes\ApiHelper;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Parsedown;

class PostGenerationService
{
    protected string $model;

    protected ?ImageGenerationService $imageService = null;

    public function __construct()
    {
        $this->model = EntityEnum::GPT_5_MINI->value;
    }

    /**
     * Generate a post based on agent configuration
     */
    public function generatePost(BlogPilot|Builder|Model $agent, int $postIndex = 0): array
    {
        try {
            ApiHelper::setOpenAiKey();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . ApiHelper::setOpenAiKey(),
                'Content-Type'  => 'application/json',
            ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
                'model'       => $this->model,
                'messages'    => $this->buildPrompt($agent, $postIndex),
                'temperature' => $this->getTemperature('high'),
                'max_tokens'  => 1000,
            ]);

            if ($response->failed()) {
                Log::error('PostGenerationService API Error: ' . $response->body());

                return [
                    'success' => false,
                    'error'   => 'Failed to generate post',
                ];
            }

            $content = $response->json('choices.0.message.content');

            // Try to extract JSON from the response if it's not pure JSON
            $jsonStart = strpos($content, '{');
            $jsonEnd = strrpos($content, '}');
            if ($jsonStart !== false && $jsonEnd !== false && $jsonEnd > $jsonStart) {
                $jsonString = substr($content, $jsonStart, $jsonEnd - $jsonStart + 1);
            } else {
                $jsonString = $content;
            }

            $post = json_decode(trim($jsonString), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('Failed to parse post content as JSON: ' . json_last_error_msg() . ' | Content: ' . $content);

                return [
                    'success' => false,
                    'error'   => 'Invalid AI response format',
                ];
            }

            if (isset($post['post_content'])) {
                $Parsedown = new Parsedown;
                $post['post_content'] = $Parsedown->text($post['post_content']);
                $post['success'] = true;

                if ($agent->has_image) {
                    $image = $this->getImageService()->generateImageForPost($post['post_title']);
                    if (isset($image['image_url'])) {
                        $post['image_url'] = ltrim($image['image_url'], '/');
                    }
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
    public function generateBulkPosts(BlogPilot $agent, int $count = 5): array
    {
        $posts = [];

        for ($i = 0; $i < $count; $i++) {
            $post = $this->generatePost($agent, $i);

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

    /**
     * Build the generation prompt
     *
     * Cycles through topics by index so each post covers a different topic,
     * and picks a random article type for variety.
     */
    protected function buildPrompt(BlogPilot $agent, int $postIndex = 0): array
    {
        $topics = $agent->selected_topics;
        $articleTypes = $this->parsePostTypesPrompt($agent->post_types);

        $selectedTopic = ! empty($topics)
            ? $topics[$postIndex % count($topics)]
            : 'general';

        $selectedArticleType = ! empty($articleTypes)
            ? $articleTypes[array_rand($articleTypes)]
            : 'Informative / Educational';

        $json = [
            'topic'          => $selectedTopic,
            'article_type'   => $selectedArticleType,
            'article_length' => $agent->article_length . ' words',
            'tone_of_voice'  => $agent->tone,
            'language'       => $agent->language,
            'include_emojis' => $agent->has_emoji,
        ];

        if ($agent->has_web_search) {
            $json['web_search_results'] = $this->webSearch($selectedTopic);
        }

        Log::info('BlogPilot Post Generation Prompt: ' . json_encode($json));

        $messages = [
            [
                'role'    => 'system',
                'content' => 'You are a blog post writer. Given the JSON data, write a post for the specified topic using the specified article type. Return only valid JSON: {"post_title": string, "post_content": string, "post_tags": string[3], "post_categories": string[1]}. Use "and" not "&" in categories.',
            ],
            [
                'role'    => 'user',
                'content' => json_encode($json),
            ],
        ];

        return $messages;
    }

    /**
     * Parse Post Types prompt
     */
    protected function parsePostTypesPrompt(array $post_type): array
    {
        $post_types = [
            'how-to-guide' => [
                'title'       => '"How-To" Guide',
                'description' => 'Step-by-step tutorials (e.g., "How to Start a Dropshipping Store").',
            ],
            'listicle' => [
                'title'       => 'Listicle (Top X / Best X)',
                'description' => 'Top 10, Top 20, Best Tools, Best Tips.',
            ],
            'informative' => [
                'title'       => 'Informative / Educational',
                'description' => 'Explains a topic in detail (e.g., "What Is SEO?").',
            ],
            'ultimate-guide' => [
                'title'       => 'Ultimate Guide / Comprehensive Guide',
                'description' => 'Long-form, all-in-one resources (e.g., "The Ultimate Guide to AI Marketing").',
            ],
            'beginners-guide' => [
                'title'       => 'Beginner’s Guide',
                'description' => 'Content for newcomers (e.g., "AI for Beginners").',
            ],
            'faq' => [
                'title'       => 'FAQ / Common Questions',
                'description' => 'Short answers to frequently asked questions.',
            ],
            'comparison' => [
                'title'       => 'Comparison / VS Article',
                'description' => 'Tool A vs Tool B.',
            ],
            'best-of-year' => [
                'title'       => '"Best of Year" Article',
                'description' => 'Best AI Tools in 2025, Best Laptops 2025.',
            ],
            'product-review' => [
                'title'       => 'Product Review',
                'description' => 'Single product review.',
            ],
            'product-roundup' => [
                'title'       => 'Product Roundup Review',
                'description' => 'Multiple product reviews in one post.',
            ],
            'buyers-guide' => [
                'title'       => 'Buyer’s Guide',
                'description' => 'How to choose something (e.g., "Buyer’s Guide for Gaming Monitors").',
            ],
            'case-study' => [
                'title'       => 'Case Study',
                'description' => 'Real results and data.',
            ],
            'problem-solution' => [
                'title'       => 'Problem & Solution Post',
                'description' => 'Explains an issue and the fix.',
            ],
            'step-by-step-tutorial' => [
                'title'       => 'Step-by-Step Tutorial',
                'description' => 'More detailed than "how-to."',
            ],
            'checklist' => [
                'title'       => 'Checklist Post',
                'description' => 'Simple checklists for quick tasks.',
            ],
            'tips-tricks' => [
                'title'       => 'Tips & Tricks',
                'description' => 'Fast actionable advice.',
            ],
            'story-driven' => [
                'title'       => 'Story-Driven Article',
                'description' => 'Narrative intro or full storytelling.',
            ],
            'opinion-editorial' => [
                'title'       => 'Opinion / Editorial',
                'description' => 'What I think about AI replacing designers.',
            ],
            'trend-analysis' => [
                'title'       => 'Trend Analysis',
                'description' => 'What’s happening now in the industry.',
            ],
            'predictions' => [
                'title'       => 'Predictions / Future Outlook',
                'description' => 'AI in 2030 — What’s Coming Next.',
            ],
            'myths-misconceptions' => [
                'title'       => 'Myths & Misconceptions',
                'description' => 'Debunking common false beliefs.',
            ],
            'industry-report' => [
                'title'       => 'Industry Report',
                'description' => 'Data-heavy insights.',
            ],
            'news-summary' => [
                'title'       => 'News Summary',
                'description' => 'Summaries of recent events.',
            ],
            'strategy-guide' => [
                'title'       => 'Strategy Guide',
                'description' => 'Marketing strategy, business strategy, etc.',
            ],
            'framework-explanation' => [
                'title'       => 'Framework / Model Explanation',
                'description' => 'Explaining systems like SWOT, AIDA, etc.',
            ],
            'template-example' => [
                'title'       => 'Template / Example Post',
                'description' => 'Includes templates or ready-to-use formats.',
            ],
            'beginners-vs-advanced' => [
                'title'       => 'Beginners vs Advanced Versions',
                'description' => 'Separate content depending on level.',
            ],
            'localized' => [
                'title'       => 'Localized Article',
                'description' => 'City/country specific (e.g., "Best Cafés in Istanbul for Remote Work").',
            ],
            'niche-expert' => [
                'title'       => 'Niche Expert Breakdown',
                'description' => 'Deep dive for specific industries (legal, medical, etc.).',
            ],
        ];

        $prompts = [];
        foreach ($post_type as $key => $type) {
            if (isset($post_types[$type])) {
                $prompts[] = $post_types[$type]['title'];
            }
        }

        return $prompts;
    }

    /**
     * Get webSearch results from OpenAI API
     */
    protected function webSearch(string $input): string
    {
        try {
            ApiHelper::setOpenAiKey();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . ApiHelper::setOpenAiKey(),
                'Content-Type'  => 'application/json',
            ])->timeout(60)->post('https://api.openai.com/v1/responses', [
                'model' => $this->model,
                'input' => $input,
                'tools' => [
                    [
                        'type' => 'web_search',
                    ],
                ],
                'tool_choice' => 'auto',
            ]);

            $data = $response->json();
            $text = collect($data['output'] ?? [])
                ->flatMap(fn ($item) => $item['content'] ?? [])
                ->firstWhere('type', 'output_text')['text'] ?? null;

            return $text;
        } catch (Exception $e) {
            Log::error('PostGenerationService fn_webSearch Error: ' . $e->getMessage());

            return '';
        }
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

    public function generateTopics(string $topic): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . ApiHelper::setOpenAiKey(),
            'Content-Type'  => 'application/json',
        ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
            'model'       => $this->model,
            'messages'    => [
                [
                    'role'    => 'system',
                    'content' => 'You are a blog topic generator assistant. Always provide only the final result without explanations or extra commentary.',
                ],
                [
                    'role'    => 'user',
                    'content' => "Create 10 topic about $topic with comma separated and to not use the ul or ol list. Output plain text only.",
                ],
            ],
            'temperature' => $this->getTemperature('high'),
            'max_tokens'  => 1000,
        ]);

        if ($response->failed()) {
            Log::error('PostGenerationService API Error: ' . $response->body());

            return [
                'success' => false,
                'error'   => 'Failed to generate topics',
            ];
        }

        $content = $response->json('choices.0.message.content');
        $topics = array_map('trim', explode(',', $content));

        return [
            'success'    => true,
            'topic_data' => $topics,
        ];
    }
}
