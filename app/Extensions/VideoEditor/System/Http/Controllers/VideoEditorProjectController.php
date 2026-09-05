<?php

declare(strict_types=1);

namespace App\Extensions\VideoEditor\System\Http\Controllers;

use App\Extensions\VideoEditor\System\Http\Requests\CreateProjectFromUrlRequest;
use App\Extensions\VideoEditor\System\Http\Requests\StoreProjectRequest;
use App\Extensions\VideoEditor\System\Http\Requests\UpdateProjectRequest;
use App\Extensions\VideoEditor\System\Models\VideoEditorMedia;
use App\Extensions\VideoEditor\System\Models\VideoEditorProject;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class VideoEditorProjectController extends Controller
{
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $dimensions = $this->getDimensions($validated['aspect_ratio'] ?? '16:9', $validated['resolution'] ?? '1080p');

        $project = new VideoEditorProject([
            'name'          => $validated['name'],
            'aspect_ratio'  => $validated['aspect_ratio'] ?? '16:9',
            'width'         => $dimensions['width'],
            'height'        => $dimensions['height'],
            'fps'           => $validated['fps'] ?? 30,
            'timeline_data' => [
                'tracks'  => [
                    [
                        'id'     => 'track-video-1',
                        'type'   => 'video',
                        'label'  => __('Video'),
                        'locked' => false,
                        'muted'  => false,
                        'clips'  => [],
                    ],
                    [
                        'id'     => 'track-audio-1',
                        'type'   => 'audio',
                        'label'  => __('Audio'),
                        'locked' => false,
                        'muted'  => false,
                        'clips'  => [],
                    ],
                    [
                        'id'     => 'track-text-1',
                        'type'   => 'text',
                        'label'  => __('Text'),
                        'locked' => false,
                        'muted'  => false,
                        'clips'  => [],
                    ],
                ],
                'version' => 1,
            ],
            'last_saved_at' => now(),
        ]);
        $project->user_id = Auth::id();
        $project->save();

        return response()->json([
            'status'  => 'success',
            'message' => __('Project created successfully'),
            'project' => $project,
        ]);
    }

    public function show(VideoEditorProject $project): JsonResponse
    {
        abort_if($project->user_id !== Auth::id(), 404);

        $project->load('media');

        return response()->json([
            'status'  => 'success',
            'project' => $project,
        ]);
    }

    public function update(UpdateProjectRequest $request, VideoEditorProject $project): JsonResponse
    {
        abort_if($project->user_id !== Auth::id(), 404);

        $validated = $request->validated();

        $updateData = [];

        if (isset($validated['name'])) {
            $updateData['name'] = $validated['name'];
        }

        if (isset($validated['timeline_data'])) {
            $updateData['timeline_data'] = $validated['timeline_data'];
        }

        if (isset($validated['duration_ms'])) {
            $updateData['duration_ms'] = $validated['duration_ms'];
        }

        if (isset($validated['aspect_ratio'])) {
            $updateData['aspect_ratio'] = $validated['aspect_ratio'];
            $dimensions = $this->getDimensions($validated['aspect_ratio'], $validated['resolution'] ?? $project->resolution ?? '1080p');
            $updateData['width'] = $dimensions['width'];
            $updateData['height'] = $dimensions['height'];
        }

        // Save project thumbnail from canvas snapshot (base64 data-URL)
        if (! empty($validated['thumbnail'])) {
            $thumbPath = $this->saveThumbnailFromDataUrl(
                $validated['thumbnail'],
                $project
            );

            if ($thumbPath) {
                $updateData['thumbnail_path'] = $thumbPath;
            }
        }

        $updateData['last_saved_at'] = now();

        $project->update($updateData);

        // Associate orphaned media referenced by timeline clips with this project
        if (isset($validated['timeline_data']['tracks'])) {
            $mediaIds = [];
            foreach ($validated['timeline_data']['tracks'] as $track) {
                foreach ($track['clips'] ?? [] as $clip) {
                    if (! empty($clip['mediaId'])) {
                        $mediaIds[] = (int) $clip['mediaId'];
                    }
                }
            }

            if (! empty($mediaIds)) {
                VideoEditorMedia::query()
                    ->where('user_id', Auth::id())
                    ->whereIn('id', array_unique($mediaIds))
                    ->whereNull('video_editor_project_id')
                    ->update(['video_editor_project_id' => $project->id]);
            }
        }

        return response()->json([
            'status'  => 'success',
            'message' => __('Project saved successfully'),
            'project' => $project->fresh(),
        ]);
    }

    public function destroy(VideoEditorProject $project): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'message' => __('This feature is disabled in demo mode.'),
            ], 403);
        }

        abort_if($project->user_id !== Auth::id(), 404);

        $project->delete();

        return response()->json([
            'status'  => 'success',
            'message' => __('Project deleted successfully'),
        ]);
    }

    public function fromUrl(CreateProjectFromUrlRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $url = $validated['url'];
        $title = trim($validated['title'] ?? '') ?: $this->inferTitleFromUrl($url);
        $durationMs = (int) ($validated['duration_ms'] ?? 0);
        $width = (int) ($validated['width'] ?? 1920);
        $height = (int) ($validated['height'] ?? 1080);

        $aspectRatio = $this->inferAspectRatio($width, $height);
        $dimensions = $this->getDimensions($aspectRatio, '1080p');

        $filename = basename(parse_url($url, PHP_URL_PATH) ?: '') ?: 'video';
        $clipDuration = $durationMs > 0 ? $durationMs : 5000;

        [$media, $project] = DB::transaction(function () use ($url, $title, $filename, $durationMs, $width, $height, $aspectRatio, $dimensions, $clipDuration) {
            $media = new VideoEditorMedia([
                'type'              => 'video',
                'filename'          => $filename,
                'original_filename' => $filename,
                'path'              => $url,
                'mime_type'         => 'video/mp4',
                'duration_ms'       => $durationMs ?: null,
                'width'             => $width ?: null,
                'height'            => $height ?: null,
                'source'            => 'external',
            ]);
            $media->user_id = Auth::id();
            $media->save();

            $project = new VideoEditorProject([
                'name'          => Str::limit($title, 60, ''),
                'aspect_ratio'  => $aspectRatio,
                'width'         => $dimensions['width'],
                'height'        => $dimensions['height'],
                'fps'           => 30,
                'duration_ms'   => $clipDuration,
                'timeline_data' => [
                    'tracks' => [
                        [
                            'id'     => 'track-video-1',
                            'type'   => 'video',
                            'label'  => __('Video'),
                            'locked' => false,
                            'muted'  => false,
                            'clips'  => [
                                [
                                    'id'        => 'clip-' . Str::random(8),
                                    'mediaId'   => $media->id,
                                    'startTime' => 0,
                                    'endTime'   => $clipDuration,
                                    'trimStart' => 0,
                                    'trimEnd'   => $clipDuration,
                                    'volume'    => 1.0,
                                ],
                            ],
                        ],
                        [
                            'id'     => 'track-audio-1',
                            'type'   => 'audio',
                            'label'  => __('Audio'),
                            'locked' => false,
                            'muted'  => false,
                            'clips'  => [],
                        ],
                        [
                            'id'     => 'track-text-1',
                            'type'   => 'text',
                            'label'  => __('Text'),
                            'locked' => false,
                            'muted'  => false,
                            'clips'  => [],
                        ],
                    ],
                    'version' => 1,
                ],
                'last_saved_at' => now(),
            ]);
            $project->user_id = Auth::id();
            $project->save();

            $media->update(['video_editor_project_id' => $project->id]);

            return [$media, $project];
        });

        return response()->json([
            'status'     => 'success',
            'message'    => __('Project created from video'),
            'project_id' => $project->id,
            'editor_url' => route('dashboard.user.video-editor.editor', $project),
        ]);
    }

    public function duplicate(VideoEditorProject $project): JsonResponse
    {
        abort_if($project->user_id !== Auth::id(), 404);

        $newProject = $project->replicate();
        $newProject->name = $project->name . ' (' . __('Copy') . ')';
        $newProject->last_saved_at = now();
        $newProject->thumbnail_path = null;
        $newProject->save();

        // Copy the thumbnail file so the duplicate has its own copy and isn't
        // broken when the original re-saves (which deletes the old file).
        if ($project->thumbnail_path && Storage::disk('public')->exists($project->thumbnail_path)) {
            $ext = pathinfo($project->thumbnail_path, PATHINFO_EXTENSION) ?: 'jpg';
            $dir = 'video-editor/thumbnails/' . Auth::id();
            $newPath = $dir . '/project_' . $newProject->id . '_' . Str::random(8) . '.' . $ext;
            Storage::disk('public')->makeDirectory($dir);
            if (Storage::disk('public')->copy($project->thumbnail_path, $newPath)) {
                $newProject->update(['thumbnail_path' => $newPath]);
            }
        }

        return response()->json([
            'status'  => 'success',
            'message' => __('Project duplicated successfully'),
            'project' => $newProject,
        ]);
    }

    /**
     * Save a project thumbnail from either:
     * - a base64 data-URL (canvas snapshot), or
     * - a "url:https://..." fallback (download media thumbnail).
     * Overwrites any previous thumbnail for this project.
     */
    private function saveThumbnailFromDataUrl(string $dataUrl, VideoEditorProject $project): ?string
    {
        $imageData = null;

        if (str_starts_with($dataUrl, 'data:image/')) {
            // Base64 data-URL from canvas snapshot
            $parts = explode(',', $dataUrl, 2);
            if (count($parts) !== 2) {
                return null;
            }

            $imageData = base64_decode($parts[1], true);
        } elseif (str_starts_with($dataUrl, 'url:')) {
            // Fallback: use media thumbnail URL
            $url = substr($dataUrl, 4);

            if (! filter_var($url, FILTER_VALIDATE_URL)) {
                return null;
            }

            // Try reading from local disk first (faster, avoids network issues)
            $localPath = $this->urlToLocalPath($url);
            if ($localPath && file_exists($localPath)) {
                $imageData = @file_get_contents($localPath);
            }

            // Fall back to HTTP download
            if (! $imageData) {
                try {
                    $imageData = @file_get_contents($url, false, stream_context_create([
                        'http' => ['timeout' => 10],
                    ]));
                } catch (Throwable) {
                    return null;
                }
            }
        }

        if (! $imageData || strlen($imageData) < 100) {
            return null;
        }

        // Validate decoded data is actually a valid image
        $imageInfo = @getimagesizefromstring($imageData);
        if ($imageInfo === false || ! in_array($imageInfo['mime'], ['image/jpeg', 'image/png', 'image/webp'])) {
            return null;
        }

        // Delete old thumbnail if it exists
        if ($project->thumbnail_path && Storage::disk('public')->exists($project->thumbnail_path)) {
            Storage::disk('public')->delete($project->thumbnail_path);
        }

        $dir = 'video-editor/thumbnails/' . Auth::id();
        $filename = 'project_' . $project->id . '_' . Str::random(8) . '.jpg';
        $path = $dir . '/' . $filename;

        Storage::disk('public')->makeDirectory($dir);
        Storage::disk('public')->put($path, $imageData);

        return $path;
    }

    /**
     * Try to resolve a URL to a local file path if it points to this server's uploads.
     */
    private function urlToLocalPath(string $url): ?string
    {
        $appUrl = rtrim(config('app.url'), '/');
        $requestUrl = rtrim(request()->getSchemeAndHttpHost(), '/');

        foreach ([$appUrl, $requestUrl] as $base) {
            if (! $base) {
                continue;
            }

            // Check if URL starts with uploads/ path on this server
            $uploadsPrefix = $base . '/uploads/';
            if (str_starts_with($url, $uploadsPrefix)) {
                $relativePath = substr($url, strlen($uploadsPrefix));

                return public_path('uploads/' . $relativePath);
            }
        }

        return null;
    }

    private function inferTitleFromUrl(string $url): string
    {
        $name = basename(parse_url($url, PHP_URL_PATH) ?: '');
        $name = preg_replace('/\.[a-z0-9]{2,5}$/i', '', $name);
        $name = trim(str_replace(['_', '-'], ' ', (string) $name));

        return $name !== '' ? $name : __('Untitled Video');
    }

    private function inferAspectRatio(int $width, int $height): string
    {
        if ($width <= 0 || $height <= 0) {
            return '16:9';
        }

        $ratio = $width / $height;

        return match (true) {
            $ratio < 0.7 => '9:16',
            $ratio < 1.1 => '1:1',
            $ratio < 1.5 => '4:3',
            default      => '16:9',
        };
    }

    private function getDimensions(string $aspectRatio, string $resolution = '1080p'): array
    {
        $baseHeight = match ($resolution) {
            '720p'  => 720,
            '4k'    => 2160,
            default => 1080,
        };

        return match ($aspectRatio) {
            '9:16'  => ['width' => (int) round($baseHeight * 9 / 16), 'height' => $baseHeight],
            '1:1'   => ['width' => $baseHeight, 'height' => $baseHeight],
            '4:3'   => ['width' => (int) round($baseHeight * 4 / 3), 'height' => $baseHeight],
            default => ['width' => (int) round($baseHeight * 16 / 9), 'height' => $baseHeight],
        };
    }
}
