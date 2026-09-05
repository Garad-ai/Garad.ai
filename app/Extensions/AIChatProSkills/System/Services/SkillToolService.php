<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProSkills\System\Services;

use App\Extensions\AIChatProSkills\System\Models\Skill;
use Illuminate\Support\Collection;
use stdClass;

class SkillToolService
{
    /**
     * Build tool definitions in OpenAI Responses API format.
     *
     * @param  Collection<int, Skill>  $skills
     *
     * @return array<int, array{type: string, name: string, description: string, parameters: array}>
     */
    public static function openAiTools(Collection $skills): array
    {
        return $skills->map(fn (Skill $skill): array => [
            'type'        => 'function',
            'name'        => self::slugToFunctionName($skill->slug),
            'description' => $skill->description,
            'parameters'  => [
                'type'       => 'object',
                'properties' => new stdClass,
            ],
        ])->values()->all();
    }

    /**
     * Build tool definitions in Anthropic format.
     *
     * @param  Collection<int, Skill>  $skills
     *
     * @return array<int, array{name: string, description: string, input_schema: array}>
     */
    public static function anthropicTools(Collection $skills): array
    {
        return $skills->map(fn (Skill $skill): array => [
            'name'         => self::slugToFunctionName($skill->slug),
            'description'  => $skill->description,
            'input_schema' => [
                'type'       => 'object',
                'properties' => new stdClass,
            ],
        ])->values()->all();
    }

    /**
     * Build tool definitions in Gemini format.
     *
     * All function declarations are wrapped in a single functionDeclarations array.
     *
     * @param  Collection<int, Skill>  $skills
     *
     * @return array<int, array{functionDeclarations: array}>
     */
    public static function geminiTools(Collection $skills): array
    {
        $declarations = $skills->map(fn (Skill $skill): array => [
            'name'        => self::slugToFunctionName($skill->slug),
            'description' => $skill->description,
            'parameters'  => [
                'type'       => 'object',
                'properties' => new stdClass,
            ],
        ])->values()->all();

        return [
            [
                'functionDeclarations' => $declarations,
            ],
        ];
    }

    /**
     * Handle a skill tool call by returning the matching skill's instructions.
     *
     * @param  Collection<int, Skill>  $skills
     */
    public static function handleSkillCall(string $functionName, Collection $skills): ?string
    {
        $slug = self::functionNameToSlug($functionName);

        /** @var Skill|null $skill */
        $skill = $skills->first(fn (Skill $skill): bool => $skill->slug === $slug);

        return $skill?->instructions;
    }

    /**
     * Get display metadata for a skill function call.
     *
     * @param  Collection<int, Skill>  $skills
     *
     * @return array{name: string, slug: string}|null
     */
    public static function getSkillMeta(string $functionName, Collection $skills): ?array
    {
        $slug = self::functionNameToSlug($functionName);

        /** @var Skill|null $skill */
        $skill = $skills->first(fn (Skill $skill): bool => $skill->slug === $slug);

        if (! $skill) {
            return null;
        }

        return [
            'name' => $skill->name,
            'slug' => $skill->slug,
        ];
    }

    /**
     * Convert a function name like `use_skill_tiktok_social_media` to a slug like `tiktok-social-media`.
     */
    public static function functionNameToSlug(string $functionName): string
    {
        $withoutPrefix = preg_replace('/^use_skill_/', '', $functionName);

        return str_replace('_', '-', $withoutPrefix);
    }

    /**
     * Convert a slug like `tiktok-social-media` to a function name like `use_skill_tiktok_social_media`.
     */
    public static function slugToFunctionName(string $slug): string
    {
        return 'use_skill_' . str_replace('-', '_', $slug);
    }
}
