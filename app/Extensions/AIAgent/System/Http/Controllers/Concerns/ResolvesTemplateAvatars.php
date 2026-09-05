<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Http\Controllers\Concerns;

trait ResolvesTemplateAvatars
{
    /**
     * Resolve a workflow template's avatar URL from its title (key).
     */
    protected function templateAvatar(?string $key): string
    {
        $base = config('ai-agent.template_avatar_base');
        $default = config('ai-agent.template_default_avatar');
        $map = config('ai-agent.template_avatars', []);

        return $base . ($map[$key] ?? $default);
    }
}
