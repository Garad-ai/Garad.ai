<?php

namespace App\Extensions\SocialMedia\System\Console\Commands;

use App\Extensions\SocialMedia\System\Enums\PlatformEnum;
use App\Extensions\SocialMedia\System\Models\SocialMediaPlatform;
use App\Extensions\SocialMedia\System\Services\Metrics\SocialMediaPostDailyMetricService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SocialMediaDailyMetricsCommand extends Command
{
    protected $signature = 'app:social-media-daily-metrics {platform? : Limit the run to a single platform (facebook, instagram, tiktok, x)}';

    protected $description = 'Aggregate cumulative post metrics into per-post daily deltas.';

    public function handle(SocialMediaPostDailyMetricService $service): int
    {
        $platformFilter = $this->normalizePlatform($this->argument('platform'));

        if ($this->argument('platform') && ! $platformFilter) {
            $this->error('Unsupported platform. Allowed values: ' . collect($this->supportedPlatforms())->join(', '));

            return self::INVALID;
        }

        $query = SocialMediaPlatform::query()
            ->connected()
            ->whereIn('platform', $this->supportedPlatforms())
            ->when($platformFilter, function ($builder, $platform) {
                return $builder->where('platform', $platform);
            })
            ->orderBy('id');

        $platformsProcessed = 0;
        $postsProcessed = 0;

        $query->lazyById()->each(function (SocialMediaPlatform $platform) use ($service, &$platformsProcessed, &$postsProcessed) {
            try {
                $postsProcessed += $service->recordForPlatform($platform);
                $platformsProcessed++;
            } catch (Throwable $exception) {
                Log::error('Failed to record daily metrics', [
                    'platform_id' => $platform->id,
                    'platform'    => $platform->platform,
                    'message'     => $exception->getMessage(),
                ]);
            }
        });

        $this->info("Daily metric aggregation completed for {$platformsProcessed} platform(s) and {$postsProcessed} post(s).");

        return self::SUCCESS;
    }

    private function supportedPlatforms(): array
    {
        return [
            PlatformEnum::facebook->value,
            PlatformEnum::instagram->value,
            PlatformEnum::tiktok->value,
            PlatformEnum::x->value,
        ];
    }

    private function normalizePlatform(?string $platform): ?string
    {
        if (! $platform) {
            return null;
        }

        $platform = Str::lower($platform);

        return in_array($platform, $this->supportedPlatforms(), true) ? $platform : null;
    }
}
