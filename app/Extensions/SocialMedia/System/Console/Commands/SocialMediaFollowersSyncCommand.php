<?php

namespace App\Extensions\SocialMedia\System\Console\Commands;

use App\Extensions\SocialMedia\System\Enums\PlatformEnum;
use App\Extensions\SocialMedia\System\Models\SocialMediaPlatform;
use App\Extensions\SocialMedia\System\Services\SocialMediaFollowerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SocialMediaFollowersSyncCommand extends Command
{
    protected $signature = 'app:social-media-sync-followers {platform? : Limit sync to a single platform (facebook, instagram, linkedin, tiktok, x)}';

    protected $description = 'Refresh follower counts for connected social media platforms.';

    public function handle(SocialMediaFollowerService $service): int
    {
        $platformFilter = $this->normalizePlatform($this->argument('platform'));

        if ($this->argument('platform') && ! $platformFilter) {
            $this->error('Unsupported platform. Allowed values: ' . collect($this->supportedPlatforms())->join(', '));

            return self::INVALID;
        }

        $query = SocialMediaPlatform::query()
            ->connected()
            ->when($platformFilter, fn ($builder, $platform) => $builder->where('platform', $platform))
            ->orderBy('id');

        $updated = 0;

        $query->lazyById(100)->each(function (SocialMediaPlatform $platform) use ($service, &$updated) {
            try {
                $result = $service->sync($platform);

                if ($result !== null) {
                    $updated++;
                }
            } catch (Throwable $exception) {
                Log::warning('Failed to refresh follower count', [
                    'platform_id' => $platform->id,
                    'platform'    => $platform->platform,
                    'message'     => $exception->getMessage(),
                ]);
            }
        });

        $this->info("Follower counts refreshed for {$updated} platform(s).");

        return self::SUCCESS;
    }

    private function supportedPlatforms(): array
    {
        return array_map(fn (PlatformEnum $enum) => $enum->value, PlatformEnum::all());
    }

    private function normalizePlatform(?string $platform): ?string
    {
        if (! $platform) {
            return null;
        }

        $platform = strtolower($platform);

        return in_array($platform, $this->supportedPlatforms(), true) ? $platform : null;
    }
}
