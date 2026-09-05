<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProSkills\System\Http\Controllers;

use App\Extensions\AIChatProSkills\System\Http\Requests\ImportGithubSkillRequest;
use App\Extensions\AIChatProSkills\System\Http\Requests\StoreSkillRequest;
use App\Extensions\AIChatProSkills\System\Models\Skill;
use App\Extensions\AIChatProSkills\System\Services\SkillParserService;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

class UserSkillController extends Controller
{
    public function __construct(
        private SkillParserService $parser
    ) {}

    /**
     * Check if the user's plan allows skills usage.
     */
    private function userCanUseSkills($user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $plan = $user->relationPlan;

        return $plan && $plan->checkOpenAiItem('ai_chat_pro_skills');
    }

    /**
     * Auto-add seeded skills (e.g. Skill Creator) for the user if not already added.
     */
    private function ensureSeededSkillsAttached($user): void
    {
        $seededSkillIds = Skill::query()->active()->where('source', 'seeded')->pluck('id');

        if ($seededSkillIds->isEmpty()) {
            return;
        }

        $existingSeededIds = $user->belongsToMany(Skill::class, 'user_skills')
            ->whereIn('skills.id', $seededSkillIds)
            ->pluck('skills.id');
        $missingSeeded = $seededSkillIds->diff($existingSeededIds);

        if ($missingSeeded->isNotEmpty()) {
            $user->belongsToMany(Skill::class, 'user_skills')->attach($missingSeeded);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $tab = $request->get('tab', 'all');
        $search = $request->get('search', '');

        $this->ensureSeededSkillsAttached($user);

        // Single query to get user's skills with auto_use pivot data
        $userSkillPivots = $user->belongsToMany(Skill::class, 'user_skills')
            ->withPivot('auto_use')
            ->get()
            ->keyBy('id');

        $userSkillIds = $userSkillPivots->keys()->toArray();

        $query = Skill::query()->active();

        if ($tab === 'public') {
            $query->public();
        } elseif ($tab === 'personal') {
            $query->personal($user->id);
        } else {
            // "all" tab: public skills + user's personal skills
            $query->where(function ($q) use ($user) {
                $q->where('is_public', true)
                    ->orWhere('user_id', $user->id);
            });
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $skills = $query->get()->map(function (Skill $skill) use ($userSkillIds, $userSkillPivots, $user) {
            $isAdded = in_array($skill->id, $userSkillIds);
            $autoUse = false;

            if ($isAdded && $userSkillPivots->has($skill->id)) {
                $autoUse = (bool) ($userSkillPivots->get($skill->id)->pivot->auto_use ?? false);
            }

            return [
                'id'          => $skill->id,
                'name'        => $skill->name,
                'slug'        => $skill->slug,
                'description' => $skill->description,
                'source'      => $skill->source,
                'is_public'   => $skill->is_public,
                'is_added'    => $isAdded,
                'auto_use'    => $autoUse,
                'is_owner'    => $skill->user_id === $user->id,
            ];
        });

        $counts = [
            'all'      => Skill::query()->active()->where(function ($q) use ($user) {
                $q->where('is_public', true)->orWhere('user_id', $user->id);
            })->count(),
            'public'   => Skill::query()->active()->public()->count(),
            'personal' => Skill::query()->active()->personal($user->id)->count(),
        ];

        return response()->json([
            'skills' => $skills,
            'counts' => $counts,
        ]);
    }

    public function addSkill(Request $request, Skill $skill): JsonResponse
    {
        $user = $request->user();

        if (! $this->userCanUseSkills($user)) {
            return response()->json(['message' => __('Your current plan does not include Skills. Please upgrade your plan.')], 403);
        }

        if ($skill->status !== 'active') {
            return response()->json(['message' => __('This skill is not available.')], 404);
        }

        if (! $skill->is_public && $skill->user_id !== $user->id) {
            return response()->json(['message' => __('This skill is not available.')], 403);
        }

        if ($user->belongsToMany(Skill::class, 'user_skills')->where('skills.id', $skill->id)->exists()) {
            return response()->json(['message' => __('Skill already added.')], 409);
        }

        $user->belongsToMany(Skill::class, 'user_skills')->attach($skill->id);

        return response()->json(['message' => __('Skill added successfully.')]);
    }

    public function removeSkill(Request $request, Skill $skill): JsonResponse
    {
        $request->user()->belongsToMany(Skill::class, 'user_skills')->detach($skill->id);

        return response()->json(['message' => __('Skill removed.')]);
    }

    public function toggleAutoUse(Request $request, Skill $skill): JsonResponse
    {
        $user = $request->user();

        if (! $this->userCanUseSkills($user)) {
            return response()->json(['message' => __('Your current plan does not include Skills. Please upgrade your plan.')], 403);
        }

        $pivot = $user->belongsToMany(Skill::class, 'user_skills')
            ->withPivot('auto_use')
            ->find($skill->id);

        if (! $pivot) {
            return response()->json(['message' => __('Skill not added to your collection.')], 404);
        }

        $newValue = ! $pivot->pivot->auto_use;

        $user->belongsToMany(Skill::class, 'user_skills')
            ->updateExistingPivot($skill->id, ['auto_use' => $newValue]);

        return response()->json([
            'message'  => $newValue ? __('Auto-use enabled.') : __('Auto-use disabled.'),
            'auto_use' => $newValue,
        ]);
    }

    public function upload(StoreSkillRequest $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json(['message' => __('This feature is disabled in Demo version.')], 403);
        }

        $user = $request->user();

        if (! $this->userCanUseSkills($user)) {
            return response()->json(['message' => __('Your current plan does not include Skills. Please upgrade your plan.')], 403);
        }

        if ($request->hasFile('file')) {
            $uploadedFile = $request->file('file');

            $parsed = $this->parser->parseSkillFile($uploadedFile);

            if (! $this->parser->validateSchema($parsed)) {
                return response()->json(['message' => __('Invalid skill file. SKILL.md must contain a name and instructions.')], 422);
            }

            $parsed['instructions'] = $this->parser->sanitizeInstructions($parsed['instructions']);
            $slug = Skill::uniqueSlug($parsed['name'], $user->id);

            // Extract and store files on disk
            $bundledResources = $parsed['bundled_resources'];
            $isZip = $this->parser->isZipFile($uploadedFile);

            if ($isZip) {
                $bundledResources = $this->parser->extractAndStoreZipFiles($uploadedFile, $user->id, $slug);
            } else {
                $this->parser->storeSkillMdFile(
                    file_get_contents($uploadedFile->getRealPath()),
                    $user->id,
                    $slug
                );
            }

            $skill = Skill::query()->create([
                'user_id'            => $user->id,
                'name'               => $parsed['name'],
                'slug'               => $slug,
                'description'        => $parsed['description'] ?: $parsed['name'],
                'instructions'       => $parsed['instructions'],
                'bundled_resources'  => ! empty($bundledResources) ? $bundledResources : null,
                'source'             => 'uploaded',
                'is_public'          => false,
                'status'             => 'active',
            ]);

            // Auto-add to user's collection
            $user->belongsToMany(Skill::class, 'user_skills')->attach($skill->id);

            return response()->json([
                'message' => __('Skill uploaded successfully.'),
                'skill'   => $skill,
            ]);
        }

        return response()->json(['message' => __('No file provided.')], 422);
    }

    public function importFromGithub(ImportGithubSkillRequest $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json(['message' => __('This feature is disabled in Demo version.')], 403);
        }

        $user = $request->user();

        if (! $this->userCanUseSkills($user)) {
            return response()->json(['message' => __('Your current plan does not include Skills. Please upgrade your plan.')], 403);
        }

        try {
            $parsed = $this->parser->parseGithubUrl($request->input('url'));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if (! $this->parser->validateSchema($parsed)) {
            return response()->json(['message' => __('Invalid skill file. SKILL.md must contain a name and instructions.')], 422);
        }

        $parsed['instructions'] = $this->parser->sanitizeInstructions($parsed['instructions']);
        $slug = Skill::uniqueSlug($parsed['name'], $user->id);

        // Store all fetched files to disk
        $bundledResources = $parsed['bundled_resources'];
        if (! empty($parsed['files'])) {
            $bundledResources = $this->parser->storeSkillFiles($parsed['files'], $user->id, $slug);
        }

        $skill = Skill::query()->create([
            'user_id'            => $user->id,
            'name'               => $parsed['name'],
            'slug'               => $slug,
            'description'        => $parsed['description'] ?: $parsed['name'],
            'instructions'       => $parsed['instructions'],
            'bundled_resources'  => ! empty($bundledResources) ? $bundledResources : null,
            'source'             => 'github',
            'source_url'         => $request->input('url'),
            'is_public'          => false,
            'status'             => 'active',
        ]);

        // Auto-add to user's collection
        $user->belongsToMany(Skill::class, 'user_skills')->attach($skill->id);

        return response()->json([
            'message' => __('Skill imported from GitHub successfully.'),
            'skill'   => $skill,
        ]);
    }

    public function scanGithub(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json(['message' => __('This feature is disabled in Demo version.')], 403);
        }

        $request->validate(['url' => ['required', 'url']]);

        try {
            $files = $this->parser->scanGithubRepo($request->input('url'));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if (empty($files)) {
            return response()->json(['message' => __('No skill files (.md) found in this repository.')], 422);
        }

        return response()->json(['files' => $files]);
    }

    public function importGithubBulk(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json(['message' => __('This feature is disabled in Demo version.')], 403);
        }

        $request->validate([
            'url'     => ['required', 'url'],
            'files'   => ['required', 'array', 'min:1'],
            'files.*' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! $this->userCanUseSkills($user)) {
            return response()->json(['message' => __('Your current plan does not include Skills. Please upgrade your plan.')], 403);
        }

        $url = $request->input('url');
        $skillFolders = $request->input('files');
        $imported = 0;
        $errors = [];

        foreach ($skillFolders as $skillFolder) {
            try {
                // Each entry is a skill folder path (containing SKILL.md)
                $folderPath = dirname($skillFolder);
                if ($folderPath === '.') {
                    $folderPath = $skillFolder;
                }

                $parsed = $this->parser->fetchGithubSkillFolder($url, $folderPath);

                if (! $this->parser->validateSchema($parsed)) {
                    $errors[] = $folderPath . ': ' . __('Missing name or instructions.');

                    continue;
                }

                $parsed['instructions'] = $this->parser->sanitizeInstructions($parsed['instructions']);
                $slug = Skill::uniqueSlug($parsed['name'], $user->id);

                // Store all fetched files to disk
                $bundledResources = null;
                if (! empty($parsed['files'])) {
                    $bundledResources = $this->parser->storeSkillFiles($parsed['files'], $user->id, $slug);
                }

                $skill = Skill::query()->create([
                    'user_id'            => $user->id,
                    'name'               => $parsed['name'],
                    'slug'               => $slug,
                    'description'        => $parsed['description'] ?: $parsed['name'],
                    'instructions'       => $parsed['instructions'],
                    'bundled_resources'  => ! empty($bundledResources) ? $bundledResources : null,
                    'source'             => 'github',
                    'source_url'         => $url,
                    'is_public'          => false,
                    'status'             => 'active',
                ]);

                $user->belongsToMany(Skill::class, 'user_skills')->attach($skill->id);
                $imported++;
            } catch (RuntimeException $e) {
                $errors[] = $skillFolder . ': ' . $e->getMessage();
            }
        }

        $message = __(':count skill(s) imported successfully.', ['count' => $imported]);
        if (! empty($errors)) {
            $message .= ' ' . __(':count file(s) skipped.', ['count' => count($errors)]);
        }

        return response()->json([
            'message'  => $message,
            'imported' => $imported,
            'errors'   => $errors,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $user = $request->user();
        $q = $request->get('q', '');

        $this->ensureSeededSkillsAttached($user);

        $userSkillIds = $user->belongsToMany(Skill::class, 'user_skills')
            ->pluck('skills.id')
            ->toArray();

        // Only show skills that the user has added to their collection
        $skills = Skill::query()
            ->active()
            ->whereIn('id', $userSkillIds)
            ->when($q, function ($query, $q) {
                $query->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%");
                });
            })
            ->get(['id', 'name', 'description', 'slug'])
            ->map(function (Skill $skill) {
                return [
                    'id'          => $skill->id,
                    'name'        => $skill->name,
                    'slug'        => $skill->slug,
                    'description' => Str::limit($skill->description, 80),
                ];
            });

        return response()->json(['skills' => $skills]);
    }

    public function deleteSkill(Request $request, Skill $skill): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json(['message' => __('This feature is disabled in Demo version.')], 403);
        }

        $user = $request->user();

        if ($skill->user_id !== $user->id) {
            return response()->json(['message' => __('You can only delete your own skills.')], 403);
        }

        $skill->users()->detach();
        $skill->delete();

        return response()->json([
            'message' => __('Skill deleted successfully.'),
        ]);
    }

    public function publicSearch(Request $request): JsonResponse
    {
        $q = $request->get('q', '');

        $skills = Skill::query()
            ->active()
            ->public()
            ->when($q, function ($query, $q) {
                $query->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%");
                });
            })
            ->get(['id', 'name', 'description', 'slug'])
            ->map(function (Skill $skill) {
                return [
                    'id'          => $skill->id,
                    'name'        => $skill->name,
                    'slug'        => $skill->slug,
                    'description' => Str::limit($skill->description, 80),
                ];
            });

        return response()->json(['skills' => $skills]);
    }

    public function publicList(Request $request): JsonResponse
    {
        $search = $request->get('search', '');

        $skills = Skill::query()
            ->active()
            ->public()
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->get(['id', 'name', 'slug', 'description', 'source', 'is_public'])
            ->map(function (Skill $skill) {
                return [
                    'id'          => $skill->id,
                    'name'        => $skill->name,
                    'slug'        => $skill->slug,
                    'description' => $skill->description,
                    'source'      => $skill->source,
                    'is_public'   => $skill->is_public,
                    'is_added'    => false,
                    'auto_use'    => false,
                    'is_owner'    => false,
                ];
            });

        $counts = [
            'all'      => Skill::query()->active()->public()->count(),
            'public'   => Skill::query()->active()->public()->count(),
            'personal' => 0,
        ];

        return response()->json([
            'skills' => $skills,
            'counts' => $counts,
        ]);
    }

    public function show(Request $request, Skill $skill): JsonResponse
    {
        $user = $request->user();

        // Allow if skill is public, belongs to user, or user has it in their collection
        if (! $skill->is_public && $skill->user_id !== $user->id) {
            $hasSkill = $user->belongsToMany(Skill::class, 'user_skills')
                ->where('skills.id', $skill->id)
                ->exists();

            if (! $hasSkill) {
                return response()->json(['message' => __('Skill not found.')], 404);
            }
        }

        return $this->skillDetailResponse($skill);
    }

    public function publicShow(Skill $skill): JsonResponse
    {
        if (! $skill->is_public) {
            return response()->json(['message' => __('Skill not found.')], 404);
        }

        return $this->skillDetailResponse($skill);
    }

    /**
     * Create a skill from raw content (used by Skill Creator "Add" button in chat).
     */
    public function createFromContent(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json(['message' => __('This feature is disabled in Demo version.')], 403);
        }

        $user = $request->user();

        if (! $this->userCanUseSkills($user)) {
            return response()->json(['message' => __('Your current plan does not include Skills. Please upgrade your plan.')], 403);
        }

        $files = $request->input('files', []);

        if (! is_array($files) || empty($files)) {
            return response()->json(['message' => __('No skill content provided.')], 422);
        }

        // Validate file entries have required keys
        foreach ($files as $file) {
            if (! is_array($file) || empty($file['path']) || ! isset($file['content'])) {
                return response()->json(['message' => __('Invalid file format.')], 422);
            }
        }

        // Find SKILL.md content
        $skillMdContent = null;
        foreach ($files as $file) {
            if (strtoupper(basename($file['path'] ?? '')) === 'SKILL.MD') {
                $skillMdContent = $file['content'] ?? '';

                break;
            }
        }

        if ($skillMdContent === null) {
            return response()->json(['message' => __('SKILL.md is required.')], 422);
        }

        $parsed = Skill::parseSkillMd($skillMdContent);

        if (! $this->parser->validateSchema($parsed)) {
            return response()->json(['message' => __('Invalid skill. Name and instructions are required.')], 422);
        }

        $parsed['instructions'] = $this->parser->sanitizeInstructions($parsed['instructions']);
        $slug = Skill::uniqueSlug($parsed['name'], $user->id);

        // Store all files to disk
        $bundledResources = $this->parser->storeSkillFiles($files, $user->id, $slug);

        $skill = Skill::query()->create([
            'user_id'            => $user->id,
            'name'               => $parsed['name'],
            'slug'               => $slug,
            'description'        => $parsed['description'] ?: $parsed['name'],
            'instructions'       => $parsed['instructions'],
            'bundled_resources'  => ! empty($bundledResources) ? $bundledResources : null,
            'source'             => 'created',
            'is_public'          => false,
            'status'             => 'active',
        ]);

        // Auto-add to user's collection
        $user->belongsToMany(Skill::class, 'user_skills')->attach($skill->id);

        return response()->json([
            'message' => __('Skill added successfully.'),
            'skill'   => $skill,
        ]);
    }

    /**
     * Export/download a skill as a .skill file (zip format with .skill extension).
     * Compatible with Claude, OpenAI, and other skill platforms.
     */
    public function export(Skill $skill): Response
    {
        $storagePath = $skill->absoluteStoragePath();
        $zipPath = tempnam(sys_get_temp_dir(), 'skill_') . '.skill';
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            return response()->json(['message' => __('Failed to create export file.')], 500);
        }

        // Add SKILL.md
        $skillMdPath = $storagePath . '/SKILL.md';
        if (file_exists($skillMdPath)) {
            $zip->addFile($skillMdPath, 'SKILL.md');
        } else {
            $zip->addFromString('SKILL.md', $this->reconstructSkillMd($skill));
        }

        // Add bundled resource files
        if (is_dir($storagePath) && ! empty($skill->bundled_resources)) {
            foreach ($skill->bundled_resources as $folder => $files) {
                foreach ($files as $file) {
                    $fileName = is_array($file) ? ($file['path'] ?? $file['name']) : $file;
                    $filePath = $storagePath . '/' . $fileName;

                    if (file_exists($filePath)) {
                        $zip->addFile($filePath, $fileName);
                    }
                }
            }
        }

        $zip->close();

        return response()->download($zipPath, $skill->slug . '.skill')->deleteFileAfterSend(true);
    }

    /**
     * Get the content of a specific bundled resource file.
     */
    public function fileContent(Request $request, Skill $skill): JsonResponse
    {
        $user = $request->user();

        // Authorization: same check as show()
        if (! $skill->is_public && $skill->user_id !== $user->id) {
            $hasSkill = $user->belongsToMany(Skill::class, 'user_skills')
                ->where('skills.id', $skill->id)
                ->exists();

            if (! $hasSkill) {
                return response()->json(['content' => ''], 403);
            }
        }

        $filePath = $request->get('path', '');

        if ($filePath === '' || str_contains($filePath, '..') || str_starts_with($filePath, '/')) {
            return response()->json(['content' => ''], 400);
        }

        $storagePath = $skill->absoluteStoragePath();
        $fullPath = realpath($storagePath . '/' . $filePath);

        // Ensure resolved path is within the skill's storage directory
        if (! $fullPath || ! str_starts_with($fullPath, realpath($storagePath) ?: $storagePath)) {
            return response()->json(['content' => '']);
        }

        if (! is_file($fullPath)) {
            return response()->json(['content' => '']);
        }

        return response()->json([
            'content' => file_get_contents($fullPath),
        ]);
    }

    private function reconstructSkillMd(Skill $skill): string
    {
        $name = str_replace(['"', "\n"], ['\"', ' '], $skill->name);
        $description = str_replace(['"', "\n"], ['\"', ' '], $skill->description);

        return "---\nname: \"{$name}\"\ndescription: \"{$description}\"\n---\n\n{$skill->instructions}";
    }

    private function skillDetailResponse(Skill $skill): JsonResponse
    {
        return response()->json([
            'skill' => [
                'id'                => $skill->id,
                'name'              => $skill->name,
                'slug'              => $skill->slug,
                'description'       => $skill->description,
                'instructions'      => $skill->instructions,
                'bundled_resources' => $skill->bundled_resources,
                'source'            => $skill->source,
                'source_url'        => $skill->source_url,
                'is_public'         => $skill->is_public,
                'created_at'        => $skill->created_at->toDateTimeString(),
            ],
        ]);
    }
}
