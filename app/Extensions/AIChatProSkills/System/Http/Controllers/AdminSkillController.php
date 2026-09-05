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
use Illuminate\View\View;
use RuntimeException;

class AdminSkillController extends Controller
{
    public function __construct(
        private SkillParserService $parser
    ) {}

    public function index(): View
    {
        $skills = Skill::query()
            ->with('creator')
            ->latest()
            ->paginate(20);

        return view('ai-chat-pro-skills::admin.index', compact('skills'));
    }

    public function store(StoreSkillRequest $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json(['message' => __('This feature is disabled in Demo version.')], 403);
        }

        if ($request->hasFile('file')) {
            $parsed = $this->parser->parseSkillFile($request->file('file'));

            if (! $this->parser->validateSchema($parsed)) {
                return response()->json(['message' => __('Invalid skill file. SKILL.md must contain a name and instructions.')], 422);
            }

            $parsed['instructions'] = $this->parser->sanitizeInstructions($parsed['instructions']);
            $slug = Skill::uniqueSlug($parsed['name']);

            // Extract and store files on disk
            $bundledResources = $parsed['bundled_resources'];

            if ($this->parser->isZipFile($request->file('file'))) {
                $bundledResources = $this->parser->extractAndStoreZipFiles($request->file('file'), null, $slug);
            } else {
                $this->parser->storeSkillMdFile(
                    file_get_contents($request->file('file')->getRealPath()),
                    null,
                    $slug
                );
            }

            $skill = Skill::query()->create([
                'user_id'            => null,
                'name'               => $parsed['name'],
                'slug'               => $slug,
                'description'        => $parsed['description'] ?: $parsed['name'],
                'instructions'       => $parsed['instructions'],
                'bundled_resources'  => ! empty($bundledResources) ? $bundledResources : null,
                'source'             => 'uploaded',
                'is_public'          => true,
                'status'             => 'active',
            ]);

            return response()->json([
                'message' => __('Skill uploaded successfully.'),
                'skill'   => $skill,
            ]);
        }

        // Manual creation
        $skill = Skill::query()->create([
            'user_id'      => null,
            'name'         => $request->input('name'),
            'slug'         => Skill::uniqueSlug($request->input('name')),
            'description'  => $request->input('description'),
            'instructions' => $this->parser->sanitizeInstructions($request->input('instructions')),
            'source'       => 'created',
            'is_public'    => true,
            'status'       => 'active',
        ]);

        return response()->json([
            'message' => __('Skill created successfully.'),
            'skill'   => $skill,
        ]);
    }

    public function importFromGithub(ImportGithubSkillRequest $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json(['message' => __('This feature is disabled in Demo version.')], 403);
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
        $slug = Skill::uniqueSlug($parsed['name']);

        // Store all fetched files to disk
        $bundledResources = $parsed['bundled_resources'];
        if (! empty($parsed['files'])) {
            $bundledResources = $this->parser->storeSkillFiles($parsed['files'], null, $slug);
        }

        $skill = Skill::query()->create([
            'user_id'            => null,
            'name'               => $parsed['name'],
            'slug'               => $slug,
            'description'        => $parsed['description'] ?: $parsed['name'],
            'instructions'       => $parsed['instructions'],
            'bundled_resources'  => ! empty($bundledResources) ? $bundledResources : null,
            'source'             => 'github',
            'source_url'         => $request->input('url'),
            'is_public'          => true,
            'status'             => 'active',
        ]);

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
            return response()->json(['message' => __('No skills found in this repository. Skills must contain a SKILL.md file.')], 422);
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

        $url = $request->input('url');
        $skillFolders = $request->input('files');
        $imported = 0;
        $errors = [];

        foreach ($skillFolders as $skillFolder) {
            try {
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
                $slug = Skill::uniqueSlug($parsed['name']);

                $bundledResources = null;
                if (! empty($parsed['files'])) {
                    $bundledResources = $this->parser->storeSkillFiles($parsed['files'], null, $slug);
                }

                Skill::query()->create([
                    'user_id'            => null,
                    'name'               => $parsed['name'],
                    'slug'               => $slug,
                    'description'        => $parsed['description'] ?: $parsed['name'],
                    'instructions'       => $parsed['instructions'],
                    'bundled_resources'  => ! empty($bundledResources) ? $bundledResources : null,
                    'source'             => 'github',
                    'source_url'         => $url,
                    'is_public'          => true,
                    'status'             => 'active',
                ]);

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

    public function update(Request $request, Skill $skill): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json(['message' => __('This feature is disabled in Demo version.')], 403);
        }

        $validated = $request->validate([
            'name'        => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:2000'],
            'status'      => ['sometimes', 'in:active,inactive'],
        ]);

        if (isset($validated['name'])) {
            $newSlug = Skill::uniqueSlug($validated['name'], $skill->user_id);
            if ($newSlug !== $skill->slug) {
                $validated['slug'] = $newSlug;
            }
        }

        $skill->update($validated);

        return response()->json([
            'message' => __('Skill updated successfully.'),
            'skill'   => $skill->fresh(),
        ]);
    }

    public function show(Skill $skill): JsonResponse
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

    public function destroy(Skill $skill): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json(['message' => __('This feature is disabled in Demo version.')], 403);
        }

        if ($skill->source === 'seeded') {
            return response()->json(['message' => __('Built-in skills cannot be deleted. You can deactivate them instead.')], 403);
        }

        $skill->users()->detach();
        $skill->delete();

        return response()->json([
            'message' => __('Skill deleted successfully.'),
        ]);
    }
}
