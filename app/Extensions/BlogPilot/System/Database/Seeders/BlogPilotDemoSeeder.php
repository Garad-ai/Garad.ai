<?php

namespace App\Extensions\BlogPilot\System\Database\Seeders;

use App\Extensions\BlogPilot\System\Models\BlogPilot;
use App\Extensions\BlogPilot\System\Models\BlogPilotPost;
use App\Models\User;
use Faker\Factory as Faker;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class BlogPilotDemoSeeder extends Seeder
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

        $this->command->info("Creating demo agents and posts for user: {$user->name}");

        // Create demo agents
        $agents = $this->createDemoAgents($user);

        // Create demo posts for each agent
        foreach ($agents as $agent) {
            $this->createDemoPosts($agent);
        }

        $this->command->info('Demo data created successfully!');
    }

    /**
     * Create demo agents
     */
    protected function createDemoAgents(User $user): array
    {
        $agents = [];

        $agentTemplates = $this->getAgentTemplates();

        foreach ($agentTemplates as $template) {
            $agents[] = BlogPilot::create([
                'user_id'                => $user->id,
                'name'                   => $template['name'],
                'topic_options'          => $template['topic_options'],
                'selected_topics'        => $template['selected_topics'],
                'post_types'             => $template['post_types'],
                'has_image'              => $template['has_image'],
                'has_emoji'              => $template['has_emoji'],
                'has_web_search'         => $template['has_web_search'],
                'has_keyword_search'     => $template['has_keyword_search'],
                'language'               => $template['language'],
                'article_length'         => $template['article_length'],
                'tone'                   => $template['tone'],
                'frequency'              => $template['frequency'],
                'daily_post_count'       => $template['daily_post_count'],
                'schedule_days'          => $template['schedule_days'],
                'schedule_times'         => $template['schedule_times'],
                'is_active'              => true,
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
                'name'               => 'ChatGPT Posts',
                'tone'               => 'professional',
                'hashtag_count'      => 5,
                'frequency'          => 'weekly',
                'language'           => 'en-US',
                'article_length'     => '800-1200',
                'daily_post_count'   => 2,
                'has_image'          => true,
                'has_emoji'          => false,
                'has_web_search'     => false,
                'has_keyword_search' => true,
                'schedule_days'      => ['Monday', 'Wednesday', 'Friday'],
                'schedule_times'     => [
                    ['start' => '09:00', 'end' => '10:00'],
                    ['start' => '14:00', 'end' => '15:00'],
                ],
                'topic_options'      => [
                    "Exploring ChatGPT's Impact on Language Learning",
                    'How ChatGPT is Revolutionizing Customer Service',
                    'The Ethical Implications of Using ChatGPT in Business',
                    "ChatGPT vs Traditional Chatbots: What's the Difference?",
                    'Ways ChatGPT Can Enhance Your Writing Process',
                    'Understanding the Technology Behind ChatGPT',
                    'ChatGPT and the Future of Human-Computer Interaction',
                    'How ChatGPT Can Assist in Creative Writing Projects',
                    'The Role of ChatGPT in Modern Education',
                    'ChatGPT as a Tool for Mental Health Support.',
                ],
                'selected_topics'    => [
                    'How ChatGPT is Revolutionizing Customer Service',
                    'The Ethical Implications of Using ChatGPT in Business',
                    "Exploring ChatGPT's Impact on Language Learning",
                    "ChatGPT vs Traditional Chatbots: What's the Difference?",
                ],
                'post_types'         => [
                    'how-to-guide',
                    'faq',
                    'best-of-year',
                ],
            ],
        ];
    }

    /**
     * Create demo posts for an agent
     */
    protected function createDemoPosts(BlogPilot $agent): void
    {
        $postCount = 1;

        for ($i = 0; $i < $postCount; $i++) {

            // Spread posts evenly across next 30 days
            $daysFromNow = $this->faker->numberBetween(0, 30);
            $hour = $this->faker->numberBetween(8, 20); // Posts between 8 AM and 8 PM
            $minute = $this->faker->randomElement([0, 15, 30, 45]);
            $scheduledAt = now()->addDays($daysFromNow)->setTime($hour, $minute, 0);

            // Generate unique created_at times spread over past 1-30 days
            $createdDaysAgo = $this->faker->numberBetween(1, 30);
            $createdHour = $this->faker->numberBetween(0, 23);
            $createdMinute = $this->faker->numberBetween(0, 59);
            $createdSecond = $this->faker->numberBetween(0, 59);
            $createdAt = now()
                ->subDays($createdDaysAgo)
                ->setTime($createdHour, $createdMinute, $createdSecond);

            BlogPilotPost::create([
                'user_id'       => $agent->user_id,
                'agent_id'      => $agent->id,
                'title'         => 'Transforming Your WordPress Site: Harnessing AI for Superior Performance and Security',
                'feature_image' => '',
                'content'       => $this->getPostContent(),
                'tag'           => ['AI in WordPress', 'SEO Optimization', 'WordPress Security'],
                'category'      => ['Technology'],
                'status'        => BlogPilotPost::STATUS_DRAFT,
                'scheduled_at'  => $scheduledAt,
                'created_at'    => $createdAt,
                'updated_at'    => $createdAt->copy()->addMinutes($this->faker->numberBetween(1, 120)),
            ]);
        }

        $this->command->info("Created {$postCount} demo posts for agent: {$agent->name}");
    }

    /**
     * Get Static Post Content
     */
    protected function getPostContent(): string
    {
        $content = <<<'HTML'
        <p>In today's digital landscape, integrating Artificial Intelligence (AI) into WordPress is revolutionizing the way we create, manage, and secure our websites. This comprehensive guide explores how AI is redefining user experience, enhancing SEO optimization, fortifying security, and providing insightful analytics on WordPress. 📈</p>
        <p><strong>1. AI-Enhanced User Experience</strong></p>
        <p>AI can dramatically improve user personalization and engagement on your WordPress site. By analyzing user behavior, AI tools can recommend content, suggest products, and even chat with users in real-time to provide a tailored experience. This not only keeps your visitors engaged but also increases conversion rates. 🤖</p>
        <p><strong>2. SEO Optimization with AI</strong></p>
        <p>AI has the power to transform your SEO strategy. Tools enhanced with machine learning can analyze vast amounts of data to predict trends, identify keywords, and optimize content for better search engine rankings. As a result, your WordPress site can achieve greater visibility and attract more organic traffic. 🌐</p>
        <p><strong>3. Content Creation and Automation</strong></p>
        <p>Gone are the days when content creation was a labor-intensive process. AI can assist in generating content, suggest topics, and even automate publishing schedules. This ensures a steady flow of fresh and relevant content, keeping your site active and engaging without the constant manual input. 📅</p>
        <p><strong>4. Safeguarding Your WordPress Site with AI</strong></p>
        <p>With the rise in cyber threats, AI plays a crucial role in enhancing WordPress security. AI-driven security plugins can detect and neutralize threats in real-time, identify vulnerabilities, and adapt to new types of attacks. This proactive approach ensures that your website remains secure against potential breaches. 🔒</p>
        <p><strong>5. AI-Powered Analytics and Insights</strong></p>
        <p>AI offers powerful analytic tools that can provide deep insights into user behavior, site performance, and marketing effectiveness. By leveraging these insights, you can make informed decisions to improve your site’s performance, boost user satisfaction, and ultimately increase your return on investment. 📊</p>
        <p>In conclusion, integrating AI into your WordPress site is not just a trend but a necessity in the modern digital space. By harnessing the power of AI, you can create a more secure, user-friendly, and profitable website.</p>
        HTML;

        return $content;
    }
}
