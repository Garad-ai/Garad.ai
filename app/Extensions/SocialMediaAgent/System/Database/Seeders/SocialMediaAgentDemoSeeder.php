<?php

namespace App\Extensions\SocialMediaAgent\System\Database\Seeders;

use App\Extensions\SocialMedia\System\Models\SocialMediaPlatform;
use App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgent;
use App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgentPost;
use App\Models\User;
use Exception;
use Faker\Factory as Faker;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SocialMediaAgentDemoSeeder extends Seeder
{
    protected Generator $faker;

    public function __construct()
    {
        $this->faker = Faker::create();
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get first user (or specify a test user)
        $user = User::first();

        if (! $user) {
            Log::warning('No users found. Please create a user first.');
            $this->command->error('No users found. Please create a user first.');

            return;
        }

        // Get user's platforms
        $platforms = SocialMediaPlatform::where('user_id', $user->id)->get();

        if ($platforms->isEmpty()) {
            Log::warning('No social media platforms found for user. Please connect platforms first.');
            $this->command->error('No social media platforms found. Please connect platforms first.');

            return;
        }

        $this->command->info("Creating demo agents and posts for user: {$user->name}");

        // Create demo agents
        $agents = $this->createDemoAgents($user, $platforms);

        // Create demo posts for each agent
        foreach ($agents as $agent) {
            $this->createDemoPosts($agent, $platforms);
        }

        $this->command->info('Demo data created successfully!');
    }

    /**
     * Create demo agents
     */
    protected function createDemoAgents(User $user, $platforms): array
    {
        $agents = [];

        $agentTemplates = $this->getAgentTemplates();

        foreach ($agentTemplates as $template) {
            $agents[] = SocialMediaAgent::create([
                'user_id'          => $user->id,
                'name'             => $template['name'],
                'platform_ids'     => $platforms->pluck('id')->take($template['platform_count'])->toArray(),
                'site_url'         => $template['site_url'],
                'site_description' => $template['site_description'],
                'scraped_content'  => $template['scraped_content'],
                'target_audience'  => $template['target_audience'],
                'post_types'       => $template['post_types'],
                'tone'             => $template['tone'],
                'language'         => 'en',
                'hashtag_count'    => $template['hashtag_count'],
                'schedule_days'    => $template['schedule_days'],
                'schedule_times'   => $template['schedule_times'],
                'daily_post_count' => $template['daily_post_count'],
                'has_image'        => $template['has_image'],
                'is_active'        => true,
                'settings'         => [
                    'plan_type'        => $template['plan_type'],
                    'include_hashtags' => true,
                    'include_emoji'    => true,
                ],
                'post_generation_status' => [
                    'status' => 'idle',
                ],
            ]);
        }

        $this->command->info('Created ' . count($agents) . ' demo agents');

        return $agents;
    }

    /**
     * Get agent templates
     */
    protected function getAgentTemplates(): array
    {
        return [
            [
                'name'             => $this->faker->company . ' - ' . $this->faker->randomElement(['Product Updates', 'Tech News', 'Innovation Hub']),
                'platform_count'   => 2,
                'site_url'         => $this->faker->url,
                'site_description' => $this->faker->catchPhrase . '. ' . $this->faker->bs,
                'scraped_content'  => [
                    'about'    => $this->faker->paragraph(3),
                    'features' => [
                        $this->faker->catchPhrase,
                        $this->faker->bs,
                        $this->faker->catchPhrase,
                        $this->faker->bs,
                    ],
                    'target_market' => $this->faker->randomElement([
                        'Small to medium businesses, startups, and enterprises',
                        'Tech-savvy professionals and business owners',
                        'Forward-thinking companies looking to innovate',
                    ]),
                ],
                'target_audience' => [
                    [
                        'segment'      => 'Tech Enthusiasts',
                        'demographics' => 'Ages ' . $this->faker->numberBetween(25, 35) . '-' . $this->faker->numberBetween(40, 55) . ', ' . $this->faker->jobTitle,
                        'interests'    => implode(', ', $this->faker->words(5)),
                        'pain_points'  => $this->faker->sentence,
                    ],
                    [
                        'segment'      => 'Business Decision Makers',
                        'demographics' => 'Ages ' . $this->faker->numberBetween(30, 40) . '-' . $this->faker->numberBetween(45, 60) . ', ' . $this->faker->jobTitle,
                        'interests'    => implode(', ', $this->faker->words(5)),
                        'pain_points'  => $this->faker->sentence,
                    ],
                ],
                'post_types'     => ['product_update', 'tip', 'engagement', 'educational'],
                'tone'           => 'professional',
                'hashtag_count'  => 5,
                'schedule_days'  => ['Monday', 'Wednesday', 'Friday'],
                'schedule_times' => [
                    ['start' => '09:00', 'end' => '10:00'],
                    ['start' => '14:00', 'end' => '15:00'],
                ],
                'daily_post_count' => 2,
                'has_image'        => true,
                'plan_type'        => 'weekly',
            ],
            [
                'name'             => $this->faker->randomElement(['Luxe', 'Elite', 'Premium', 'Vintage']) . ' ' . $this->faker->randomElement(['Fashion', 'Style', 'Boutique']) . ' - ' . $this->faker->randomElement(['Spring Collection', 'New Arrivals', 'Latest Trends']),
                'platform_count'   => 3,
                'site_url'         => $this->faker->url,
                'site_description' => $this->faker->catchPhrase . ' offering ' . $this->faker->bs . ' for the modern ' . $this->faker->randomElement(['professional', 'individual', 'lifestyle']),
                'scraped_content'  => [
                    'about'       => $this->faker->paragraph(2),
                    'collections' => [
                        $this->faker->randomElement(['Spring', 'Summer', 'Fall', 'Winter']) . ' ' . $this->faker->year . ' Collection',
                        $this->faker->words(2, true) . ' Line',
                        $this->faker->words(2, true) . ' Essentials',
                    ],
                    'values' => implode(', ', $this->faker->words(3)),
                ],
                'target_audience' => [
                    [
                        'segment'      => $this->faker->randomElement(['Professional Women', 'Fashion Forward Men', 'Style Conscious Individuals']),
                        'demographics' => 'Ages ' . $this->faker->numberBetween(25, 35) . '-' . $this->faker->numberBetween(40, 50) . ', ' . $this->faker->city . ' professionals',
                        'interests'    => implode(', ', $this->faker->words(4)),
                        'pain_points'  => $this->faker->sentence,
                    ],
                    [
                        'segment'      => 'Conscious Consumers',
                        'demographics' => 'Ages ' . $this->faker->numberBetween(22, 30) . '-' . $this->faker->numberBetween(35, 45) . ', environmentally aware',
                        'interests'    => implode(', ', $this->faker->words(4)),
                        'pain_points'  => $this->faker->sentence,
                    ],
                ],
                'post_types'     => ['promotional', 'lifestyle', 'engagement', 'behind_the_scenes'],
                'tone'           => 'friendly',
                'hashtag_count'  => 8,
                'schedule_days'  => ['Tuesday', 'Thursday', 'Saturday'],
                'schedule_times' => [
                    ['start' => '10:00', 'end' => '11:00'],
                    ['start' => '17:00', 'end' => '18:00'],
                ],
                'daily_post_count' => 3,
                'has_image'        => true,
                'plan_type'        => 'daily',
            ],
            [
                'name'             => $this->faker->randomElement(['Fit', 'Health', 'Wellness', 'Active']) . $this->faker->randomElement(['Life', 'Pro', 'Zone', 'Hub']) . ' ' . $this->faker->randomElement(['Coach', 'Trainer', 'Guide']) . ' - ' . $this->faker->randomElement(['Daily Motivation', 'Fitness Journey', 'Health Tips']),
                'platform_count'   => 2,
                'site_url'         => $this->faker->url,
                'site_description' => 'Personal ' . $this->faker->randomElement(['fitness', 'health', 'wellness']) . ' coaching and ' . $this->faker->bs . ' to help you achieve your goals.',
                'scraped_content'  => [
                    'about'    => $this->faker->paragraph(2),
                    'services' => [
                        $this->faker->words(3, true),
                        $this->faker->words(3, true),
                        $this->faker->words(3, true),
                        $this->faker->words(4, true),
                    ],
                    'specialties' => implode(', ', $this->faker->words(4)),
                ],
                'target_audience' => [
                    [
                        'segment'      => 'Fitness Beginners',
                        'demographics' => 'Ages ' . $this->faker->numberBetween(25, 35) . '-' . $this->faker->numberBetween(45, 55) . ', looking to start fitness journey',
                        'interests'    => implode(', ', $this->faker->words(4)),
                        'pain_points'  => $this->faker->sentence,
                    ],
                    [
                        'segment'      => 'Busy Professionals',
                        'demographics' => 'Ages ' . $this->faker->numberBetween(30, 35) . '-' . $this->faker->numberBetween(40, 50) . ', limited time for fitness',
                        'interests'    => implode(', ', $this->faker->words(4)),
                        'pain_points'  => $this->faker->sentence,
                    ],
                ],
                'post_types'     => ['motivational', 'tip', 'educational', 'engagement'],
                'tone'           => 'enthusiastic',
                'hashtag_count'  => 6,
                'schedule_days'  => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
                'schedule_times' => [
                    ['start' => '06:00', 'end' => '07:00'],
                    ['start' => '19:00', 'end' => '20:00'],
                ],
                'daily_post_count' => 2,
                'has_image'        => false,
                'plan_type'        => 'daily',
            ],
        ];
    }

    /**
     * Create demo posts for an agent
     */
    protected function createDemoPosts(SocialMediaAgent $agent, $platforms): void
    {
        $platformIds = $agent->platform_ids;
        $postCount = $this->faker->numberBetween(15, 25); // Increased post count for better 30-day spread

        for ($i = 0; $i < $postCount; $i++) {
            $platformId = $platformIds[$i % count($platformIds)];

            // Spread posts evenly across next 30 days
            $daysFromNow = $this->faker->numberBetween(0, 30);
            $hour = $this->faker->numberBetween(8, 20); // Posts between 8 AM and 8 PM
            $minute = $this->faker->randomElement([0, 15, 30, 45]);
            $scheduledAt = now()->addDays($daysFromNow)->setTime($hour, $minute, 0);

            // Generate post type
            $postType = $this->faker->randomElement(['post', 'story']);

            // Generate content based on post type and tone
            $content = $this->generatePostContent($postType, $agent->tone);

            // Generate hashtags
            $hashtagCount = $this->faker->numberBetween(3, $agent->hashtag_count);
            $hashtags = $this->generateHashtags($hashtagCount, $postType);

            // Generate image if agent has_image enabled (90% chance to have image)
            $mediaUrls = [];
            $imageStatus = 'none';
            if ($agent->has_image && $this->faker->boolean(90)) {
                // 40% chance to have multiple images (2-4 images)
                $imageCount = $this->faker->boolean(40) ? $this->faker->numberBetween(2, 4) : 1;

                for ($imgIdx = 0; $imgIdx < $imageCount; $imgIdx++) {
                    $imageUrl = $this->generateDummyImage($postType, $i, $imgIdx);
                    if ($imageUrl) {
                        $mediaUrls[] = $imageUrl;
                    }
                }

                if (! empty($mediaUrls)) {
                    $imageStatus = 'completed';
                }
            }

            // Random status distribution
            $statusDistribution = [
                SocialMediaAgentPost::STATUS_DRAFT     => 50, // 50%
                SocialMediaAgentPost::STATUS_SCHEDULED => 30, // 30%
                SocialMediaAgentPost::STATUS_PUBLISHED => 20, // 20%
            ];
            $status = $this->faker->randomElement(array_merge(
                array_fill(0, $statusDistribution[SocialMediaAgentPost::STATUS_DRAFT], SocialMediaAgentPost::STATUS_DRAFT),
                array_fill(0, $statusDistribution[SocialMediaAgentPost::STATUS_SCHEDULED], SocialMediaAgentPost::STATUS_SCHEDULED),
                array_fill(0, $statusDistribution[SocialMediaAgentPost::STATUS_PUBLISHED], SocialMediaAgentPost::STATUS_PUBLISHED)
            ));

            // Generate unique created_at times spread over past 1-30 days
            $createdDaysAgo = $this->faker->numberBetween(1, 30);
            $createdHour = $this->faker->numberBetween(0, 23);
            $createdMinute = $this->faker->numberBetween(0, 59);
            $createdSecond = $this->faker->numberBetween(0, 59);
            $createdAt = now()
                ->subDays($createdDaysAgo)
                ->setTime($createdHour, $createdMinute, $createdSecond);

            SocialMediaAgentPost::create([
                'agent_id'         => $agent->id,
                'platform_id'      => $platformId,
                'content'          => $content,
                'post_type'        => $postType,
                'media_urls'       => $mediaUrls,
                'image_request_id' => null,
                'image_status'     => $imageStatus,
                'hashtags'         => $hashtags,
                'ai_metadata'      => [
                    'generated_by'    => 'demo_seeder',
                    'tone'            => $agent->tone,
                    'target_audience' => $agent->target_audience[0]['segment'] ?? 'General',
                    'generated_at'    => $createdAt->toDateTimeString(),
                ],
                'status'       => $status,
                'scheduled_at' => $scheduledAt,
                'created_at'   => $createdAt,
                'updated_at'   => $createdAt->copy()->addMinutes($this->faker->numberBetween(1, 120)),
            ]);
        }

        $this->command->info("Created {$postCount} demo posts for agent: {$agent->name}");
    }

    /**
     * Generate a dummy image for post
     */
    protected function generateDummyImage(string $postType, int $index, int $imageIndex = 0): ?string
    {
        try {
            // Image categories based on post type
            $categoryMap = [
                'product_update'    => 'tech',
                'promotional'       => 'business',
                'tip'               => 'education',
                'educational'       => 'books',
                'motivational'      => 'people',
                'engagement'        => 'community',
                'lifestyle'         => 'nature',
                'behind_the_scenes' => 'office',
            ];

            $category = $categoryMap[$postType] ?? 'abstract';

            // Create unique seed for each image
            $seed = $index * 100 + $imageIndex;

            // Use different placeholder services for variety
            $services = [
                'picsum'      => "https://picsum.photos/seed/{$seed}/1200/630",
                'placeholder' => 'https://via.placeholder.com/1200x630/{color1}/{color2}?text={text}',
            ];

            $serviceType = $this->faker->randomElement(array_keys($services));

            if ($serviceType === 'placeholder') {
                $colors = ['3498db', 'e74c3c', '2ecc71', 'f39c12', '9b59b6', '1abc9c', '34495e'];
                $color1 = $this->faker->randomElement($colors);
                $color2 = 'ffffff';
                $text = urlencode(ucfirst($category));
                $imageUrl = str_replace(
                    ['{color1}', '{color2}', '{text}'],
                    [$color1, $color2, $text],
                    $services['placeholder']
                );
            } else {
                $imageUrl = $services['picsum'];
            }

            // Download and store the image
            $response = Http::timeout(10)->get($imageUrl);

            if ($response->successful()) {
                $filename = 'social-media-agent/demo/' . uniqid('post_', true) . '.jpg';
                Storage::disk('uploads')->put($filename, $response->body());

                // Manually construct the URL since uploads disk doesn't have url() method
                return config('app.url') . '/uploads/' . $filename;
            }

            return null;
        } catch (Exception $e) {
            Log::warning('Failed to generate dummy image: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Generate post content based on type and tone
     */
    protected function generatePostContent(string $postType, string $tone): string
    {
        $emojis = $this->getEmojisForTone($tone);
        $emoji = $this->faker->randomElement($emojis);

        $templates = [
            'product_update' => [
                "{$emoji} Exciting news! {sentence} {catchPhrase}.\n\nLearn more: {url}",
                "🚀 We're thrilled to announce: {catchPhrase}! {sentence}",
                '✨ New update alert! {sentence} {bs}.',
                '🎉 Just launched: {catchPhrase}. {sentence}',
            ],
            'promotional' => [
                "{$emoji} Limited time offer! {sentence}\n\n{catchPhrase} - Don't miss out!",
                "🎁 Special deal: {sentence}\n\nUse code: {randomCode}",
                "💫 Exclusive offer: {catchPhrase}!\n\n{sentence}",
                '🔥 Hot deal alert! {sentence}',
            ],
            'tip' => [
                "💡 Pro Tip: {sentence}\n\n{catchPhrase}",
                '🎯 Quick tip: {sentence}',
                "✅ Did you know? {sentence}\n\n{catchPhrase}",
                "{$emoji} Here's a tip: {sentence}",
            ],
            'educational' => [
                "📚 Learning moment: {sentence}\n\n{catchPhrase}",
                '🧠 Knowledge drop: {sentence}',
                "📖 Today's lesson: {catchPhrase}\n\n{sentence}",
                '💭 Understanding {word}: {sentence}',
            ],
            'motivational' => [
                "🔥 {catchPhrase}! {sentence}\n\nYou've got this!",
                "💪 Remember: {sentence}\n\nKeep pushing forward!",
                '⭐ {catchPhrase}. {sentence}',
                '🌟 Daily reminder: {sentence}',
            ],
            'engagement' => [
                "🤔 Question for you: {sentence}?\n\nDrop your thoughts below! 👇",
                "💬 We want to hear from you! {sentence}?\n\nComment your answer!",
                "🗣️ Let's discuss: {sentence}?\n\nShare your experience!",
                '❓ Quick poll: {sentence}?',
            ],
            'lifestyle' => [
                "{$emoji} {catchPhrase}! {sentence}",
                '✨ Living our best life: {sentence}',
                "🌈 {sentence}\n\n{catchPhrase}",
                '💫 {catchPhrase}. {sentence}',
            ],
            'behind_the_scenes' => [
                '👀 Behind the scenes: {sentence}',
                "🎬 Sneak peek: {catchPhrase}!\n\n{sentence}",
                "🔍 Here's how we {verb}: {sentence}",
                '✨ The story behind: {catchPhrase}',
            ],
        ];

        $template = $this->faker->randomElement($templates[$postType] ?? $templates['tip']);

        // Replace placeholders
        $verbs = ['create', 'build', 'develop', 'design', 'improve', 'optimize', 'enhance', 'deliver', 'achieve', 'transform'];

        $content = str_replace(
            ['{sentence}', '{catchPhrase}', '{bs}', '{url}', '{randomCode}', '{word}', '{verb}'],
            [
                ucfirst($this->faker->sentence),
                ucfirst($this->faker->catchPhrase),
                $this->faker->bs,
                $this->faker->url,
                strtoupper($this->faker->lexify('???##')),
                $this->faker->word,
                $this->faker->randomElement($verbs),
            ],
            $template
        );

        return $content;
    }

    /**
     * Get emojis based on tone
     */
    protected function getEmojisForTone(string $tone): array
    {
        $emojiMap = [
            'professional' => ['🎯', '📊', '💼', '🚀', '✅', '📈'],
            'friendly'     => ['😊', '💕', '🌟', '✨', '💫', '🎉'],
            'enthusiastic' => ['🔥', '💪', '⚡', '🎉', '🏆', '🌟'],
            'casual'       => ['👋', '😄', '🤗', '🎈', '✌️', '🙌'],
        ];

        return $emojiMap[$tone] ?? $emojiMap['friendly'];
    }

    /**
     * Generate relevant hashtags
     */
    protected function generateHashtags(int $count, string $postType): array
    {
        $hashtagPools = [
            'product_update'    => ['NewFeature', 'ProductUpdate', 'Innovation', 'TechNews', 'Announcement', 'LaunchDay'],
            'promotional'       => ['Sale', 'Discount', 'LimitedOffer', 'SpecialDeal', 'ShopNow', 'Exclusive'],
            'tip'               => ['ProTip', 'LifeHack', 'TipOfTheDay', 'HowTo', 'DidYouKnow', 'QuickTip'],
            'educational'       => ['LearnMore', 'Education', 'Knowledge', 'Tutorial', 'Guide', 'Insights'],
            'motivational'      => ['Motivation', 'Inspiration', 'YouGotThis', 'KeepGoing', 'BelieveInYourself', 'SuccessMindset'],
            'engagement'        => ['Community', 'LetsTalk', 'YourTurn', 'ShareYourStory', 'JoinTheConversation', 'WeWantToKnow'],
            'lifestyle'         => ['Lifestyle', 'DailyLife', 'LivingWell', 'Wellness', 'Balance', 'Vibes'],
            'behind_the_scenes' => ['BTS', 'BehindTheScenes', 'MakingOf', 'Process', 'HowItsMade', 'InsideLook'],
        ];

        $generalHashtags = [
            $this->faker->word . ucfirst($this->faker->word),
            ucfirst($this->faker->word) . 'Tips',
            ucfirst($this->faker->word) . 'Life',
            $this->faker->company,
        ];

        $pool = array_merge(
            $hashtagPools[$postType] ?? [],
            $generalHashtags
        );

        return $this->faker->randomElements($pool, min($count, count($pool)));
    }
}
