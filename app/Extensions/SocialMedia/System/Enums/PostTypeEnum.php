<?php

namespace App\Extensions\SocialMedia\System\Enums;

enum PostTypeEnum: string
{
    case Post = 'post';

    case Story = 'story';

    public function label(): string
    {
        return match ($this) {
            self::Post  => 'Post',
            self::Story => 'Story',
        };
    }

    /**
     * Platforms that support story publishing.
     *
     * @return array<PlatformEnum>
     */
    public static function storyPlatforms(): array
    {
        return [
            PlatformEnum::facebook,
            PlatformEnum::instagram,
            PlatformEnum::tiktok,
        ];
    }

    /**
     * Check if a platform supports stories.
     */
    public static function platformSupportsStory(PlatformEnum $platform): bool
    {
        return in_array($platform, self::storyPlatforms(), true);
    }
}
