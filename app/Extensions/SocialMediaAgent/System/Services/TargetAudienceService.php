<?php

namespace App\Extensions\SocialMediaAgent\System\Services;

use App\Domains\Entity\Enums\EntityEnum;
use App\Helpers\Classes\ApiHelper;
use App\Helpers\Classes\OpenAiParamHelper;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TargetAudienceService
{
    protected string $model;

    public function __construct()
    {
        $this->model = EntityEnum::GPT_5_MINI->value;
    }

    protected int $maxTargets = 8;

    /**
     * Generate target audience suggestions based on site content or description
     */
    public function generateTargets(?array $scrapedContent = null, ?string $description = null, array $existingTargets = []): array
    {
        $existingTargetNames = $this->extractTargetNames($existingTargets);
        $normalizedExistingTargets = array_map([$this, 'normalizeName'], $existingTargetNames);

        try {
            $prompt = $this->buildPrompt($scrapedContent, $description, $existingTargetNames);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . ApiHelper::setOpenAiKey(),
                'Content-Type'  => 'application/json',
            ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', OpenAiParamHelper::sanitizeChatParams([
                'model'       => $this->model,
                'messages'    => [
                    [
                        'role'    => 'system',
                        'content' => $this->getSystemPrompt(),
                    ],
                    [
                        'role'    => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.7,
                'max_tokens'  => 1500,
            ]));

            if ($response->failed()) {
                Log::error('TargetAudienceService API Error: ' . $response->body());

                return $this->getDefaultTargets($normalizedExistingTargets);
            }

            $content = $response->json('choices.0.message.content');

            $targets = $this->parseTargets($content);
            $targets = $this->filterExistingTargets($targets, $normalizedExistingTargets);

            if (count($targets) < $this->maxTargets) {
                $targets = array_merge(
                    $targets,
                    $this->getDefaultTargets(
                        array_merge(
                            $normalizedExistingTargets,
                            $this->normalizeTargetCollection($targets)
                        ),
                        $this->maxTargets - count($targets)
                    )
                );
            }

            return array_slice($targets, 0, $this->maxTargets);
        } catch (Exception $e) {
            Log::error('TargetAudienceService Error: ' . $e->getMessage());

            return $this->getDefaultTargets($normalizedExistingTargets);
        }
    }

    /**
     * Build the prompt for AI based on available data
     */
    protected function buildPrompt($scrapedContent, $description, array $existingTargets = []): string
    {
        $context = '';

        if ($scrapedContent && isset($scrapedContent['pages'])) {
            $context = "Website Information:\n";

            foreach ($scrapedContent['pages'] as $index => $page) {
                $context .= "\nPage " . ($index + 1) . ":\n";
                $context .= 'Title: ' . ($page['title'] ?? 'N/A') . "\n";
                $context .= 'Meta Description: ' . ($page['meta_description'] ?? 'N/A') . "\n";
                $context .= 'Content Preview: ' . substr($page['content'] ?? '', 0, 500) . "\n";

                if (! empty($page['headings'])) {
                    $context .= 'Main Headings: ' . implode(', ', array_slice($page['headings'], 0, 5)) . "\n";
                }
            }
        } elseif ($description) {
            $context = "Business/Website Description:\n{$description}";
        } else {
            $context = 'No specific information provided. Generate general target audiences for social media marketing.';
        }

        $existingTargetsContext = '';
        if (! empty($existingTargets)) {
            $existingTargetsContext = "\nPreviously generated audience segments (avoid repeating these):\n";
            foreach ($existingTargets as $index => $targetName) {
                $existingTargetsContext .= ($index + 1) . '. ' . $targetName . "\n";
            }
        }

        return <<<PROMPT
Based on the following information, generate {$this->maxTargets} diverse target audience segments that would be interested in this business/website.

{$context}

{$existingTargetsContext}

For each target audience segment, provide:
1. A clear, descriptive name (2-4 words)
2. A brief description (1-2 sentences) explaining who they are and why they would be interested

Return the response in the following JSON format:
{
  "targets": [
    {
      "name": "Target Audience Name",
      "description": "Brief description of this audience segment"
    }
  ]
}
PROMPT;
    }

    /**
     * Get system prompt for consistent AI behavior
     */
    protected function getSystemPrompt(): string
    {
        return <<<'SYSTEM'
You are an expert social media marketing strategist specializing in target audience identification.

Your task is to analyze business information and generate diverse, specific target audience segments.

Guidelines:
- Be specific and actionable
- Focus on demographics, psychographics, and behaviors
- Ensure diversity across segments
- Keep descriptions clear and concise
- Think about social media user behavior
- Consider different stages of customer journey
- Return ONLY valid JSON, no additional text

Always respond with properly formatted JSON.
SYSTEM;
    }

    /**
     * Parse AI response and extract targets
     */
    protected function parseTargets(string $content): array
    {
        try {
            // Try to extract JSON from the response
            $content = trim($content);

            // Remove markdown code blocks if present
            $content = preg_replace('/```json\s*/', '', $content);
            $content = preg_replace('/```\s*/', '', $content);
            $content = trim($content);

            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('Failed to parse AI response as JSON: ' . json_last_error_msg());

                return [];
            }

            if (! isset($data['targets']) || ! is_array($data['targets'])) {
                Log::warning('Invalid AI response structure');

                return [];
            }

            $targets = [];
            foreach ($data['targets'] as $target) {
                if (isset($target['name']) && isset($target['description'])) {
                    $targets[] = [
                        'id'          => uniqid('target_'),
                        'name'        => trim($target['name']),
                        'description' => trim($target['description']),
                    ];
                }
            }

            return array_slice($targets, 0, $this->maxTargets);
        } catch (Exception $e) {
            Log::error('Error parsing targets: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Get default target audiences as fallback
     */
    protected function getDefaultTargets(array $excludedNames = [], ?int $limit = null): array
    {
        $limit ??= $this->maxTargets;

        $pool = [
            [
                'name'        => 'Young Professionals',
                'description' => 'Career-focused individuals aged 25-35 who are active on social media and looking for professional development.',
            ],
            [
                'name'        => 'Tech Enthusiasts',
                'description' => 'Technology-savvy users interested in latest trends, gadgets, and digital innovations.',
            ],
            [
                'name'        => 'Small Business Owners',
                'description' => 'Entrepreneurs and business owners seeking solutions to grow their businesses and improve operations.',
            ],
            [
                'name'        => 'Content Creators',
                'description' => 'Social media influencers, bloggers, and creators looking for tools and inspiration for their content.',
            ],
            [
                'name'        => 'Marketing Professionals',
                'description' => 'Digital marketers and advertising professionals seeking effective strategies and tools.',
            ],
            [
                'name'        => 'Lifestyle Seekers',
                'description' => 'Individuals interested in improving their lifestyle, wellness, and personal growth.',
            ],
            [
                'name'        => 'Eco Conscious Shoppers',
                'description' => 'People who prioritize sustainable brands, ethical sourcing, and environmentally friendly lifestyles.',
            ],
            [
                'name'        => 'Remote Team Leaders',
                'description' => 'Managers running hybrid or remote teams who need tools, tips, and inspiration to keep employees engaged.',
            ],
            [
                'name'        => 'Health & Wellness Fans',
                'description' => 'Fitness-minded followers who look for actionable ideas to stay healthy, energized, and balanced.',
            ],
            [
                'name'        => 'Early Adopter Creators',
                'description' => 'Creators who experiment with new formats, platforms, and AI tools to stay ahead of content trends.',
            ],
            [
                'name'        => 'Side Hustle Builders',
                'description' => 'Ambitious individuals who juggle full-time jobs while developing online businesses or digital products.',
            ],
            [
                'name'        => 'Community Organizers',
                'description' => 'Local leaders and nonprofit advocates who use social channels to mobilize neighbors and donors.',
            ],
        ];

        $normalizedExcluded = array_map([$this, 'normalizeName'], $excludedNames);
        shuffle($pool);

        $targets = [];
        foreach ($pool as $target) {
            $normalizedName = $this->normalizeName($target['name']);

            if (in_array($normalizedName, $normalizedExcluded, true)) {
                continue;
            }

            $targets[] = [
                'id'          => uniqid('target_'),
                'name'        => $target['name'],
                'description' => $target['description'],
            ];

            $normalizedExcluded[] = $normalizedName;

            if (count($targets) >= $limit) {
                break;
            }
        }

        return $targets;
    }

    /**
     * Set the AI model to use
     */
    public function setModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Set maximum number of target audiences to generate
     */
    public function setMaxTargets(int $maxTargets): self
    {
        $this->maxTargets = $maxTargets;

        return $this;
    }

    /**
     * Build a list of prior target names
     */
    protected function extractTargetNames(array $targets): array
    {
        $names = [];

        foreach ($targets as $target) {
            if (is_array($target) && isset($target['name'])) {
                $name = trim((string) $target['name']);
            } elseif (is_string($target)) {
                $name = trim($target);
            } else {
                $name = '';
            }

            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Remove previously generated targets from the new list
     */
    protected function filterExistingTargets(array $targets, array $normalizedExistingTargets): array
    {
        if (empty($normalizedExistingTargets) || empty($targets)) {
            return $targets;
        }

        return array_values(array_filter($targets, function ($target) use ($normalizedExistingTargets) {
            return ! in_array($this->normalizeName($target['name'] ?? ''), $normalizedExistingTargets, true);
        }));
    }

    /**
     * Normalize the target collection to allow comparisons
     */
    protected function normalizeTargetCollection(array $targets): array
    {
        if (empty($targets)) {
            return [];
        }

        return array_map(function ($target) {
            return $this->normalizeName($target['name'] ?? '');
        }, $targets);
    }

    /**
     * Normalize text for comparisons
     */
    protected function normalizeName(?string $name): string
    {
        if ($name === null) {
            return '';
        }

        $value = trim($name);

        if ($value === '') {
            return '';
        }

        return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
    }
}
