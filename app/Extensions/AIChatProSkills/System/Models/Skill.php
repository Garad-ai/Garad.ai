<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProSkills\System\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class Skill extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'description',
        'instructions',
        'bundled_resources',
        'source',
        'source_url',
        'is_public',
        'status',
    ];

    protected $casts = [
        'is_public'         => 'boolean',
        'bundled_resources' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_skills')
            ->withPivot('auto_use')
            ->withTimestamps();
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    public function scopePersonal(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId)->where('is_public', false);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Get the relative storage path for this skill's files.
     */
    public function storagePath(): string
    {
        $owner = $this->user_id ?? 'public';

        return "skills/{$owner}/{$this->slug}";
    }

    /**
     * Get the absolute storage path for this skill's files.
     */
    public function absoluteStoragePath(): string
    {
        return storage_path('app/' . $this->storagePath());
    }

    /**
     * Generate a unique slug for the given name and user_id scope.
     */
    public static function uniqueSlug(string $name, ?int $userId = null): string
    {
        $slug = Str::slug($name);

        if ($slug === '') {
            $slug = 'skill';
        }

        $original = $slug;
        $counter = 1;

        $query = static::query()->where('slug', $slug);
        $exists = $userId === null
            ? $query->whereNull('user_id')->exists()
            : $query->where('user_id', $userId)->exists();

        while ($exists) {
            $slug = $original . '-' . $counter;
            $counter++;

            $query = static::query()->where('slug', $slug);
            $exists = $userId === null
                ? $query->whereNull('user_id')->exists()
                : $query->where('user_id', $userId)->exists();
        }

        return $slug;
    }

    /**
     * Parse a SKILL.md content string into structured data.
     *
     * @return array{name: string, description: string, instructions: string, bundled_resources: array|null}
     */
    public static function parseSkillMd(string $content): array
    {
        $parsed = [
            'name'              => '',
            'description'       => '',
            'instructions'      => '',
            'bundled_resources' => null,
        ];

        // Check for YAML frontmatter
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)/s', $content, $matches)) {
            try {
                $frontmatter = Yaml::parse($matches[1]);
            } catch (ParseException) {
                $parsed['instructions'] = trim($content);

                return $parsed;
            }

            $parsed['name'] = $frontmatter['name'] ?? '';
            $parsed['description'] = $frontmatter['description'] ?? '';
            $parsed['instructions'] = trim($matches[2]);

            if (isset($frontmatter['bundled_resources'])) {
                $parsed['bundled_resources'] = $frontmatter['bundled_resources'];
            }
        } else {
            // No frontmatter, treat entire content as instructions
            $parsed['instructions'] = trim($content);
        }

        return $parsed;
    }
}
