<?php

namespace App\Extensions\SocialMediaAgent\System\Console\Commands;

use App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgent;
use App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgentPost;
use App\Extensions\SocialMediaAgent\System\Notifications\PostGenerationCompletedNotification;
use App\Extensions\SocialMediaAgent\System\Services\PostGenerationService;
use App\Extensions\SocialMediaAgent\System\Services\VideoGenerationService;
use App\Extensions\SocialMediaAgent\System\Support\SocialMediaAgentGenerationCache;
use App\Helpers\Classes\Helper;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GenerateAgentPostsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'social-media-agent:generate-posts
                            {--agent= : Generate posts for a specific agent ID}
                            {--force : Force generation even if buffer is sufficient}';

    /**
     * The console command description.
     */
    protected $description = 'Generate social media posts for active agents to maintain post buffer';

    protected PostGenerationService $postService;

    protected VideoGenerationService $videoService;

    public function __construct(PostGenerationService $postService, VideoGenerationService $videoService)
    {
        parent::__construct();
        $this->postService = $postService;
        $this->videoService = $videoService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (Helper::appIsDemo()) {
            return 1;
        }

        $this->info('🤖 Starting AI Social Media Agent post generation...');

        $agentId = $this->option('agent');
        $force = $this->option('force');

        Log::info('social-media-agent:generate-posts started', [
            'agent_option' => $agentId,
            'force'        => (bool) $force,
        ]);

        // Get agents to process
        $agents = $this->getAgents($agentId);

        if ($agents->isEmpty()) {
            $this->warn('No active agents found.');
            Log::info('social-media-agent:generate-posts finished', [
                'status'        => 'no_agents',
                'agents_found'  => 0,
                'posts_created' => 0,
            ]);

            return self::SUCCESS;
        }

        $this->info("Processing {$agents->count()} agent(s)...");

        $totalGenerated = 0;

        foreach ($agents as $agent) {
            $this->line('');
            $this->info("Processing Agent: {$agent->name} (ID: {$agent->id})");

            try {
                $generated = $this->processAgent($agent, $force);
                $totalGenerated += $generated;

                if ($generated > 0) {
                    $this->info("  ✓ Generated {$generated} posts");
                } else {
                    $this->line('  • Buffer sufficient, no posts generated');
                }

                Log::info('social-media-agent:generate-posts agent finished', [
                    'agent_id'         => $agent->id,
                    'posts_generated'  => $generated,
                    'force'            => (bool) $force,
                ]);
            } catch (Exception $e) {
                $this->error("  ✗ Error: {$e->getMessage()}");
                Log::error("Agent {$agent->id} post generation failed", [
                    'agent_id' => $agent->id,
                    'error'    => $e->getMessage(),
                    'trace'    => $e->getTraceAsString(),
                ]);
            }
        }

        $this->line('');
        $this->info("✅ Completed! Total posts generated: {$totalGenerated}");

        Log::info('social-media-agent:generate-posts finished', [
            'agents_found'  => $agents->count(),
            'posts_created' => $totalGenerated,
            'force'         => (bool) $force,
        ]);

        return self::SUCCESS;
    }

    /**
     * Get agents to process
     */
    protected function getAgents(?string $agentId)
    {
        $query = SocialMediaAgent::query()->active();

        if ($agentId) {
            $query->where('id', $agentId);
        }

        return $query->get();
    }

    /**
     * Process a single agent
     */
    protected function processAgent(SocialMediaAgent $agent, bool $force = false): int
    {
        $planContext = $this->buildScheduleContext($agent);
        $targetPerPlatform = $this->calculateTargetPostsPerPlatform($agent, $planContext['schedule_days']);
        $platformMap = $agent->platforms()->keyBy('id');

        if (! $force && ! $this->needsMorePosts($agent, $targetPerPlatform)) {
            Log::info('social-media-agent:generate-posts skipped agent due to sufficient buffer', [
                'agent_id' => $agent->id,
            ]);

            return 0;
        }

        $postsToGeneratePerPlatform = $this->calculatePostsNeededByPlatform($agent, $targetPerPlatform);

        if (empty($postsToGeneratePerPlatform)) {
            Log::info('social-media-agent:generate-posts no posts needed', [
                'agent_id'         => $agent->id,
                'calculated_posts' => 0,
            ]);

            return 0;
        }

        $totalRequested = array_sum($postsToGeneratePerPlatform);

        // Update status: generation started
        $stats = SocialMediaAgentGenerationCache::computePostStats($agent);

        $this->updateGenerationStatus($agent, 'generating', array_merge([
            'total_requested' => $totalRequested,
            'generated_count' => 0,
            'failed_count'    => 0,
            'started_at'      => now()->toDateTimeString(),
        ], $stats));

        $this->line("  → Generating {$totalRequested} posts...");

        $generated = 0;
        $failed = 0;
        $scheduleCursor = $this->getInitialScheduleCursor($agent);

        foreach ($postsToGeneratePerPlatform as $platformId => $count) {
            $platformModel = $platformMap->get($platformId);
            $platformSlug = $platformModel?->platform;

            for ($i = 0; $i < $count; $i++) {
                $post = $this->postService->generatePost($agent, [
                    'platform' => $platformSlug,
                ]);

                if ($post['success']) {
                    $scheduleCursor = $this->getNextScheduleTimeAfter($scheduleCursor, $planContext['plan_entries']);

                    [$mediaUrls, $imageRequestId, $imageStatus, $imageModel] = $this->prepareImageData($post);
                    [$videoUrls, $videoRequestId, $videoStatus, $videoMetadata] = $this->prepareVideoData($post, $platformSlug);

                    SocialMediaAgentPost::create([
                        'agent_id'         => $agent->id,
                        'platform_id'      => $platformId,
                        'content'          => $post['full_text'],
                        'post_type'        => 'post',
                        'publishing_type'  => $agent->publishing_type ?? 'post',
                        'hashtags'         => $post['hashtags'] ?? [],
                        'ai_metadata'      => array_merge($post['metadata'] ?? [], $videoMetadata),
                        'media_urls'       => $mediaUrls,
                        'image_request_id' => $imageRequestId,
                        'image_status'     => $imageStatus,
                        'image_model'      => $imageModel,
                        'video_urls'       => $videoUrls,
                        'video_request_id' => $videoRequestId,
                        'video_status'     => $videoStatus,
                        'status'           => SocialMediaAgentPost::STATUS_DRAFT,
                        'scheduled_at'     => $scheduleCursor,
                    ]);

                    $generated++;
                    $stats = SocialMediaAgentGenerationCache::computePostStats($agent);

                    $this->line("    • Post {$generated}/{$totalRequested} generated for platform {$platformId}");
                    if (! empty($mediaUrls)) {
                        $this->line("      ↳ Image stored: {$mediaUrls[0]} ({$imageStatus})");
                    } elseif ($imageRequestId) {
                        $this->line("      ↳ Image request queued: {$imageRequestId} ({$imageStatus})");
                    }

                    if (! empty($videoUrls)) {
                        $this->line("      ↳ Video stored: {$videoUrls[0]} ({$videoStatus})");
                    } elseif ($videoRequestId) {
                        $this->line("      ↳ Video request queued: {$videoRequestId} ({$videoStatus})");
                    }

                    // Update progress
                    $this->updateGenerationStatus($agent, 'generating', array_merge([
                        'generated_count' => $generated,
                        'failed_count'    => $failed,
                    ], $stats));

                    usleep(500000); // 0.5 seconds
                } else {
                    $failed++;
                    $this->warn('    ⚠ Failed to generate post: ' . ($post['error'] ?? 'Unknown error'));

                    $this->updateGenerationStatus($agent, 'generating', [
                        'generated_count' => $generated,
                        'failed_count'    => $failed,
                    ]);
                }
            }
        }

        // Update status: generation completed
        $stats = SocialMediaAgentGenerationCache::computePostStats($agent);

        $this->updateGenerationStatus($agent, 'completed', array_merge([
            'generated_count' => $generated,
            'failed_count'    => $failed,
            'completed_at'    => now()->toDateTimeString(),
        ], $stats));

        // Send notification to user
        try {
            $agent->user->notify(new PostGenerationCompletedNotification($agent, $generated, $failed));
        } catch (Exception $e) {
            Log::error('Failed to send post generation notification', [
                'agent_id' => $agent->id,
                'error'    => $e->getMessage(),
            ]);
        }

        return $generated;
    }

    protected function prepareImageData(array $post): array
    {
        $mediaUrls = [];
        $imageUrl = $post['image_url'] ?? null;

        if ($imageUrl) {
            $mediaUrls[] = $imageUrl;
        }

        $imageRequestId = $post['image_request_id'] ?? data_get($post, 'metadata.image_request_id');
        $imageStatus = $post['image_status'] ?? data_get($post, 'metadata.image_status', 'none');
        $imageModel = $post['image_model'] ?? data_get($post, 'metadata.image_model');

        if ($imageUrl) {
            $imageStatus = 'completed';
        } elseif ($imageRequestId) {
            $imageStatus = $imageStatus && $imageStatus !== 'none'
                ? $imageStatus
                : 'pending';
        } else {
            $imageStatus = 'none';
        }

        return [$mediaUrls, $imageRequestId, $imageStatus, $imageModel];
    }

    protected function prepareVideoData(array $post, ?string $platformSlug): array
    {
        $videoUrls = [];
        $videoRequestId = null;
        $videoStatus = 'none';
        $metadata = [];

        if (! $post['success'] || ! in_array($platformSlug, ['youtube', 'youtube-shorts'], true)) {
            return [$videoUrls, $videoRequestId, $videoStatus, $metadata];
        }

        $videoResult = $this->videoService->generateVideoForPost(
            $post['content_without_hashtags'] ?? $post['content'],
            [
                'platform' => $platformSlug,
            ]
        );

        if (! $videoResult['success']) {
            $videoStatus = 'failed';
            $metadata['video_status'] = 'failed';
            $metadata['video_generation_error'] = $videoResult['error'] ?? 'Video generation failed.';

            return [$videoUrls, $videoRequestId, $videoStatus, $metadata];
        }

        $videoStatus = $videoResult['status'] ?? 'pending';
        $videoRequestId = $videoResult['request_id'] ?? null;

        if (! empty($videoResult['video_url'])) {
            $videoUrls[] = $videoResult['video_url'];
            $videoStatus = 'completed';
        }

        $metadata['video_status'] = $videoStatus;
        $metadata['video_request_id'] = $videoRequestId;

        if (! empty($videoResult['prompt'])) {
            $metadata['video_prompt'] = $videoResult['prompt'];
        }

        return [$videoUrls, $videoRequestId, $videoStatus, $metadata];
    }

    /**
     * Update agent's post generation status
     */
    protected function updateGenerationStatus(SocialMediaAgent $agent, string $status, array $data = []): void
    {
        $currentStatus = $agent->post_generation_status ?? [];

        $payload = array_merge($currentStatus, [
            'status' => $status,
        ], $data);

        $agent->update([
            'post_generation_status' => $payload,
        ]);

        if (in_array($status, ['completed', 'failed'], true)) {
            SocialMediaAgentGenerationCache::forgetForUser($agent->user_id);
        } else {
            SocialMediaAgentGenerationCache::mark($agent, $status, $payload);
        }

        // Refresh agent to get updated data
        $agent->refresh();
    }

    protected function buildScheduleContext(SocialMediaAgent $agent): array
    {
        $dailyPostCount = max(1, (int) $agent->daily_post_count);
        $scheduleDays = $this->resolveScheduleDays($agent->schedule_days ?? []);
        $timeSlots = $this->determineTimeSlots($agent, $dailyPostCount);
        $perDayPlan = $this->buildPerDayPlan($timeSlots, $dailyPostCount);
        $planEntries = $this->buildPlanEntries($scheduleDays, $perDayPlan);

        return [
            'schedule_days' => $scheduleDays,
            'time_slots'    => $timeSlots,
            'per_day_plan'  => $perDayPlan,
            'plan_entries'  => $planEntries,
        ];
    }

    protected function calculateTargetPostsPerPlatform(SocialMediaAgent $agent, array $scheduleDays): int
    {
        $dailyPostCount = max(1, (int) $agent->daily_post_count);
        $daysSelected = max(1, count($scheduleDays));
        $planType = strtolower((string) data_get($agent->settings, 'plan_type', 'weekly'));

        $multiplier = $planType === 'monthly' ? 4 : 1;

        return $dailyPostCount * $daysSelected * $multiplier;
    }

    /**
     * Check if agent needs more posts
     */
    protected function needsMorePosts(SocialMediaAgent $agent, int $targetPerPlatform): bool
    {
        foreach ($agent->platform_ids ?? [] as $platformId) {
            if ($this->countExistingPostsForPlatform($agent, $platformId) < $targetPerPlatform) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate how many posts are needed for each platform
     */
    protected function calculatePostsNeededByPlatform(SocialMediaAgent $agent, int $targetPerPlatform): array
    {
        $needs = [];

        foreach ($agent->platform_ids ?? [] as $platformId) {
            $existing = $this->countExistingPostsForPlatform($agent, $platformId);
            $needed = $targetPerPlatform - $existing;

            if ($needed > 0) {
                $needs[$platformId] = $needed;
            }
        }

        return $needs;
    }

    protected function countExistingPostsForPlatform(SocialMediaAgent $agent, int $platformId): int
    {
        return $agent->posts()
            ->where('platform_id', $platformId)
            ->whereIn('status', [
                SocialMediaAgentPost::STATUS_DRAFT,
                SocialMediaAgentPost::STATUS_SCHEDULED,
            ])
            ->where('scheduled_at', '>=', now())
            ->count();
    }

    protected function getInitialScheduleCursor(SocialMediaAgent $agent): ?Carbon
    {
        $lastScheduled = $agent->posts()
            ->whereNotNull('scheduled_at')
            ->latest('scheduled_at')
            ->value('scheduled_at');

        return $lastScheduled ? Carbon::parse($lastScheduled) : null;
    }

    protected function getNextScheduleTimeAfter(?Carbon $after, array $planEntries): Carbon
    {
        $reference = $after ? $after->copy() : now()->subMinute();
        $weekStart = $reference->copy()->startOfWeek(Carbon::MONDAY);
        $next = null;

        foreach ($planEntries as $entry) {
            $candidate = $weekStart->copy()->addDays($entry['day_number'] - 1);
            [$hour, $minute] = explode(':', $entry['time']);
            $candidate->setTime((int) $hour, (int) $minute, 0);

            if ($candidate <= $reference) {
                $candidate->addWeek();
            }

            if ($next === null || $candidate->lt($next)) {
                $next = $candidate->copy();
            }
        }

        return $next ?? $reference->copy()->addDay();
    }

    protected function resolveScheduleDays($rawDays): array
    {
        $map = [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
        ];

        $normalized = [];

        foreach ((array) $rawDays as $day) {
            $label = null;

            if (is_numeric($day)) {
                $label = $map[(int) $day] ?? null;
            } elseif (is_string($day)) {
                $day = trim($day);
                foreach ($map as $name) {
                    if (strcasecmp($name, $day) === 0) {
                        $label = $name;

                        break;
                    }
                }

                if (! $label) {
                    try {
                        $label = Carbon::parse($day)->format('l');
                    } catch (Exception) {
                        $label = null;
                    }
                }
            }

            if ($label) {
                $normalized[] = $label;
            }
        }

        if (empty($normalized)) {
            $normalized[] = 'Monday';
        }

        return array_values(array_unique($normalized));
    }

    protected function determineTimeSlots(SocialMediaAgent $agent, int $dailyPostCount): array
    {
        $slots = $agent->schedule_times ?? [];
        $normalized = [];

        foreach ($slots as $slot) {
            $normalized[] = [
                'key'   => $slot['key'] ?? ('slot_' . Str::random(5)),
                'label' => $slot['label'] ?? ucfirst($slot['key'] ?? 'Slot'),
                'start' => $this->normalizeTimeValue($slot['start'] ?? null, '09:00'),
                'end'   => $this->normalizeTimeValue($slot['end'] ?? null, '11:00'),
            ];
        }

        if (empty($normalized)) {
            $normalized = $this->defaultTimeSlots();
        }

        $allowedSlots = min($this->maxSlotsForDailyCount($dailyPostCount), count($normalized));
        $allowedSlots = max(1, $allowedSlots);

        return array_slice($normalized, 0, $allowedSlots);
    }

    protected function defaultTimeSlots(): array
    {
        return [
            [
                'key'   => 'morning',
                'label' => 'Morning',
                'start' => '08:00',
                'end'   => '12:00',
            ],
            [
                'key'   => 'noon',
                'label' => 'Noon',
                'start' => '12:00',
                'end'   => '16:00',
            ],
            [
                'key'   => 'evening',
                'label' => 'Evening',
                'start' => '17:00',
                'end'   => '22:00',
            ],
        ];
    }

    protected function maxSlotsForDailyCount(int $dailyPostCount): int
    {
        if ($dailyPostCount <= 1) {
            return 1;
        }

        if ($dailyPostCount === 2) {
            return 2;
        }

        return 3;
    }

    protected function distributePostsAcrossSlots(int $dailyPostCount, int $slotCount): array
    {
        $slotCount = max(1, $slotCount);
        $base = intdiv($dailyPostCount, $slotCount);
        $remainder = $dailyPostCount % $slotCount;

        $distribution = array_fill(0, $slotCount, $base);

        for ($i = 0; $i < $remainder; $i++) {
            $distribution[$i]++;
        }

        return $distribution;
    }

    protected function buildPerDayPlan(array $slots, int $dailyPostCount): array
    {
        $slotCount = count($slots);
        $distribution = $this->distributePostsAcrossSlots($dailyPostCount, $slotCount);
        $plan = [];

        foreach ($slots as $index => $slot) {
            $count = $distribution[$index] ?? 0;

            if ($count <= 0) {
                continue;
            }

            for ($i = 0; $i < $count; $i++) {
                $plan[] = [
                    'slot' => $slot,
                    'time' => $this->calculateTimeForSlot($slot, $i, $count),
                ];
            }
        }

        if (empty($plan)) {
            $slot = $slots[0] ?? $this->defaultTimeSlots()[0];
            $plan[] = [
                'slot' => $slot,
                'time' => $this->normalizeTimeValue($slot['start'] ?? '09:00'),
            ];
        }

        return $plan;
    }

    protected function calculateTimeForSlot(array $slot, int $index, int $total): string
    {
        $start = Carbon::createFromFormat('H:i', $slot['start']);
        $end = Carbon::createFromFormat('H:i', $slot['end']);

        if ($total <= 1 || $end->lessThanOrEqualTo($start)) {
            return $start->format('H:i');
        }

        $minutesDiff = max(1, $end->diffInMinutes($start));
        $steps = max(1, $total - 1);
        $interval = (int) floor($minutesDiff / $steps);

        $scheduled = $start->copy()->addMinutes($interval * $index);

        if ($scheduled->gt($end)) {
            $scheduled = $end->copy();
        }

        return $scheduled->format('H:i');
    }

    protected function buildPlanEntries(array $scheduleDays, array $perDayPlan): array
    {
        $entries = [];

        foreach ($scheduleDays as $dayName) {
            $dayNumber = $this->dayNameToNumber($dayName);

            foreach ($perDayPlan as $plan) {
                $entries[] = [
                    'day_number' => $dayNumber,
                    'time'       => $plan['time'],
                ];
            }
        }

        usort($entries, static function ($a, $b) {
            return $a['day_number'] <=> $b['day_number']
                ?: strcmp($a['time'], $b['time']);
        });

        return $entries;
    }

    protected function dayNameToNumber(string $dayName): int
    {
        $map = [
            'monday'    => 1,
            'tuesday'   => 2,
            'wednesday' => 3,
            'thursday'  => 4,
            'friday'    => 5,
            'saturday'  => 6,
            'sunday'    => 7,
        ];

        $key = strtolower($dayName);

        return $map[$key] ?? 1;
    }

    protected function normalizeTimeValue(?string $time, string $fallback = '09:00'): string
    {
        if (is_string($time)) {
            try {
                return Carbon::createFromFormat('H:i', $time)->format('H:i');
            } catch (Exception) {
                // Fall through to fallback
            }
        }

        return $fallback;
    }
}
