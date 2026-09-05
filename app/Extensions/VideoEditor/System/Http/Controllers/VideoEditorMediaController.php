<?php

declare(strict_types=1);

namespace App\Extensions\VideoEditor\System\Http\Controllers;

use App\Extensions\AiVideoPro\System\Models\UserFall;
use App\Extensions\VideoEditor\System\Http\Requests\UploadMediaRequest;
use App\Extensions\VideoEditor\System\Models\VideoEditorMedia;
use App\Extensions\VideoEditor\System\Models\VideoEditorProject;
use App\Extensions\VideoEditor\System\Services\MediaProcessingService;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\UserOpenai;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class VideoEditorMediaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = VideoEditorMedia::query()
            ->where('user_id', Auth::id())
            ->orderByDesc('created_at');

        if ($request->has('type') && $request->input('type') !== 'all') {
            $type = $request->input('type');
            if (in_array($type, ['video', 'audio', 'image'], true)) {
                $query->where('type', $type);
            }
        }

        if ($request->has('project_id')) {
            $query->where('video_editor_project_id', (int) $request->input('project_id'));
        }

        if ($request->filled('search')) {
            $search = str_replace(['%', '_'], ['\\%', '\\_'], $request->input('search'));
            $query->where('original_filename', 'like', '%' . $search . '%');
        }

        $media = $query->paginate(24);

        return response()->json([
            'status' => 'success',
            'media'  => $media,
        ]);
    }

    public function upload(UploadMediaRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $mimeType = $file->getMimeType();
        $type = $this->detectMediaType($mimeType);

        if (! $type) {
            return response()->json([
                'status'  => 'error',
                'message' => __('Unsupported file type'),
            ], 422);
        }

        // Derive safe extension from MIME type, not client-supplied extension
        $safeExtension = $this->getSafeExtension($mimeType, ($file->guessExtension() ?? $file->getClientOriginalExtension()));
        $filename = Str::random(40) . '.' . $safeExtension;
        $path = 'video-editor/media/' . Auth::id() . '/' . $filename;

        Storage::disk('public')->putFileAs(dirname($path), $file, basename($path));

        $metadata = MediaProcessingService::extractMetadata(
            Storage::disk('public')->path($path),
            $type
        );

        $thumbnailPath = null;
        if ($type === 'video' || $type === 'image') {
            $thumbnailPath = MediaProcessingService::generateThumbnail(
                Storage::disk('public')->path($path),
                $type,
                Auth::id()
            );
        }

        // Sanitize original filename for display
        $originalFilename = Str::limit(
            preg_replace('/[^\w\s\-\.\(\)]/', '_', pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)),
            100
        ) . '.' . $safeExtension;

        $media = new VideoEditorMedia([
            'video_editor_project_id'  => $request->input('project_id'),
            'type'                     => $type,
            'filename'                 => $filename,
            'original_filename'        => $originalFilename,
            'path'                     => $path,
            'mime_type'                => $mimeType,
            'file_size'                => $file->getSize(),
            'duration_ms'              => $metadata['duration_ms'] ?? null,
            'width'                    => $metadata['width'] ?? null,
            'height'                   => $metadata['height'] ?? null,
            'thumbnail_path'           => $thumbnailPath,
            'source'                   => 'upload',
        ]);
        $media->user_id = Auth::id();
        $media->save();

        return response()->json([
            'status'  => 'success',
            'message' => __('File uploaded successfully'),
            'media'   => $media,
        ]);
    }

    public function destroy(VideoEditorMedia $media): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'message' => __('This feature is disabled in demo mode.'),
            ], 403);
        }

        abort_if($media->user_id !== Auth::id(), 404);

        if ($media->path && Storage::disk('public')->exists($media->path)) {
            Storage::disk('public')->delete($media->path);
        }

        if ($media->thumbnail_path && Storage::disk('public')->exists($media->thumbnail_path)) {
            Storage::disk('public')->delete($media->thumbnail_path);
        }

        $media->delete();

        return response()->json([
            'status'  => 'success',
            'message' => __('Media deleted successfully'),
        ]);
    }

    /**
     * Import files selected from MagicAI's Content Manager.
     * Accepts the items array from the mediaSelected Livewire event.
     *
     * Security: validates that URLs point to files the current user owns
     * (user-specific upload folder or user-owned AI-generated content).
     */
    public function importFromContentManager(Request $request): JsonResponse
    {
        $request->validate([
            'items'             => ['required', 'array', 'min:1', 'max:20'],
            'items.*.url'       => ['required', 'string', 'max:2048'],
            'items.*.title'     => ['nullable', 'string', 'max:255'],
            'items.*.type'      => ['nullable', 'string', 'in:image,video,audio,stockImage,stockVideo,other'],
            'items.*.extension' => ['nullable', 'string', 'max:10', 'regex:/^[a-zA-Z0-9]+$/'],
            'project_id'        => ['nullable', 'integer', 'exists:video_editor_projects,id'],
        ]);

        if ($request->input('project_id')) {
            $project = VideoEditorProject::findOrFail($request->input('project_id'));
            abort_if($project->user_id !== Auth::id(), 404);
        }

        $userId = Auth::id();
        $allowedExtensions = ['mp4', 'webm', 'mov', 'avi', 'flv', 'wmv', 'mp3', 'wav', 'ogg', 'aac', 'm4a', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
        $imported = [];

        foreach ($request->input('items') as $item) {
            $url = $item['url'];
            $title = $item['title'] ?? 'Untitled';
            $extension = strtolower($item['extension'] ?? pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
            $cmType = $item['type'] ?? 'image';

            // ── Security: validate file extension ──
            if (! in_array($extension, $allowedExtensions, true)) {
                continue; // skip unsupported file types
            }

            // ── Security: validate URL ownership ──
            // Only allow URLs that belong to the current user:
            // - Local uploads: must contain the user's folder pattern (u-{userId}/)
            // - AI-generated content from MagicAI: must reference user-owned records
            // - External URLs (S3/R2/CDN): only from known storage disks
            if (! $this->isUrlOwnedByUser($url, $userId)) {
                continue; // skip files the user doesn't own
            }

            // ── Security: verify the file actually exists ──
            $path = $this->resolveContentManagerPath($url, $userId);
            if (! $path) {
                continue;
            }

            // Determine media type
            $type = match (true) {
                in_array($cmType, ['video', 'stockVideo'])                         => 'video',
                in_array($cmType, ['image', 'stockImage'])                         => 'image',
                in_array($extension, ['mp4', 'webm', 'mov', 'avi', 'flv', 'wmv'])  => 'video',
                in_array($extension, ['mp3', 'wav', 'ogg', 'aac', 'm4a'])          => 'audio',
                in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']) => 'image',
                default                                                            => 'image',
            };

            // Determine MIME type from extension
            $mimeType = match ($extension) {
                'mp4'  => 'video/mp4',
                'webm' => 'video/webm',
                'mov'  => 'video/quicktime',
                'avi'  => 'video/x-msvideo',
                'mp3'  => 'audio/mpeg',
                'wav'  => 'audio/wav',
                'ogg'  => 'audio/ogg',
                'aac', 'm4a' => 'audio/aac',
                'jpg', 'jpeg' => 'image/jpeg',
                'png'   => 'image/png',
                'gif'   => 'image/gif',
                'webp'  => 'image/webp',
                'svg'   => 'image/svg+xml',
                default => $type === 'video' ? 'video/mp4' : ($type === 'audio' ? 'audio/mpeg' : 'image/png'),
            };

            $originalFilename = Str::limit(strip_tags($title), 100);
            if ($extension && ! str_ends_with(strtolower($originalFilename), '.' . $extension)) {
                $originalFilename .= '.' . $extension;
            }

            $media = new VideoEditorMedia([
                'video_editor_project_id' => $request->input('project_id'),
                'type'                    => $type,
                'filename'                => basename($path),
                'original_filename'       => $originalFilename,
                'path'                    => $path,
                'mime_type'               => $mimeType,
                'file_size'               => 0,
                'source'                  => 'content_manager',
            ]);
            $media->user_id = $userId;
            $media->save();

            $imported[] = $media;
        }

        if (empty($imported)) {
            return response()->json([
                'status'  => 'error',
                'message' => __('No valid media files found to import.'),
            ], 422);
        }

        return response()->json([
            'status'  => 'success',
            'message' => __(':count file(s) imported.', ['count' => count($imported)]),
            'media'   => $imported,
        ]);
    }

    /**
     * Check if a Content Manager URL belongs to the current user.
     * Validates against user-specific upload folders and AI-generated content patterns.
     */
    private function isUrlOwnedByUser(string $url, int $userId): bool
    {
        // Extract path from full URL (strip domain)
        $path = $url;
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            $parsedPath = parse_url($url, PHP_URL_PATH);
            if (! $parsedPath) {
                return false;
            }
            $path = ltrim($parsedPath, '/');
        }

        // Normalize: strip leading "uploads/" or "public/" prefixes
        $path = preg_replace('#^(uploads/|public/)#', '', ltrim($path, '/'));

        // Content Manager stores user uploads in: media/{type}/u-{userId}/
        // Pattern: media/(images|videos|other)/u-{userId}/filename
        $userFolderPattern = '/^media\/[a-z]+\/u-' . preg_quote((string) $userId, '/') . '\//';
        if (preg_match($userFolderPattern, $path)) {
            return true;
        }

        // AI-generated images stored by MagicAI (e.g. "images/user-{userId}/..." or similar)
        // These are from UserOpenai records which are already user-scoped
        if (preg_match('/\buser[-_]?' . preg_quote((string) $userId, '/') . '\b/', $path)) {
            return true;
        }

        // External storage (S3/R2) — allow if domain matches configured storage URLs
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            foreach (['s3', 'r2'] as $disk) {
                try {
                    $diskUrl = rtrim(Storage::disk($disk)->url(''), '/');
                    if ($diskUrl && str_starts_with($url, $diskUrl)) {
                        return true;
                    }
                } catch (Throwable) {
                    // Disk not configured — skip
                }
            }
        }

        return false;
    }

    /**
     * Resolve a Content Manager URL to a storable path.
     * Returns a path suitable for VideoEditorMedia, or null if the file cannot be verified.
     */
    private function resolveContentManagerPath(string $url, int $userId): ?string
    {
        // External URLs (S3/R2/CDN) — store the full URL
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        // Local path: normalize and verify file exists
        $path = ltrim($url, '/');

        // Strip "uploads/" prefix to get the Storage::disk('public') relative path
        $storagePath = preg_replace('#^uploads/#', '', $path);

        // Verify the file actually exists on disk
        if (! Storage::disk('public')->exists($storagePath)) {
            return null;
        }

        // Store with "uploads/" prefix for getUrlAttribute() compatibility
        if (! str_starts_with($path, 'uploads/')) {
            $path = 'uploads/' . $storagePath;
        }

        return $path;
    }

    public function importFromLibrary(Request $request): JsonResponse
    {
        $request->validate([
            'source_type' => ['required', 'string', 'in:ai_video,ai_image'],
            'source_id'   => ['nullable'],
            'project_id'  => ['nullable', 'integer', 'exists:video_editor_projects,id'],
        ]);

        if ($request->input('project_id')) {
            $project = VideoEditorProject::findOrFail($request->input('project_id'));
            abort_if($project->user_id !== Auth::id(), 404);
        }

        $sourceType = $request->input('source_type');
        $sourceId = $request->input('source_id');
        $projectId = $request->input('project_id');

        // Single import (with source_id)
        if ($sourceId) {
            if ($sourceType === 'ai_video') {
                return $this->importAiVideo($sourceId, $projectId);
            }

            if ($sourceType === 'ai_image') {
                return $this->importAiImage($sourceId, $projectId);
            }
        }

        // Bulk import — import all recent AI-generated items not already in the editor
        return $this->bulkImport($sourceType, $projectId);
    }

    /**
     * Bulk import recent AI-generated videos or images that haven't been imported yet.
     */
    private function bulkImport(string $sourceType, ?int $projectId): JsonResponse
    {
        $alreadyImported = VideoEditorMedia::query()
            ->where('user_id', Auth::id())
            ->whereNotNull('source_id')
            ->pluck('source_id')
            ->toArray();

        $imported = 0;

        if ($sourceType === 'ai_video') {
            $items = UserFall::query()
                ->where('user_id', Auth::id())
                ->where('status', 'complete')
                ->whereNotNull('video_url')
                ->whereNotIn('id', $alreadyImported)
                ->orderByDesc('created_at')
                ->limit(20)
                ->get();

            foreach ($items as $item) {
                $media = new VideoEditorMedia([
                    'video_editor_project_id' => $projectId,
                    'type'                    => 'video',
                    'filename'                => basename($item->video_url),
                    'original_filename'       => 'AI Video - ' . Str::limit($item->prompt, 30),
                    'path'                    => $item->video_url,
                    'mime_type'               => 'video/mp4',
                    'file_size'               => 0,
                    'source'                  => 'ai_generated',
                    'source_id'               => (string) $item->id,
                ]);
                $media->user_id = Auth::id();
                $media->save();
                $imported++;
            }
        } elseif ($sourceType === 'ai_image') {
            $items = UserOpenai::query()
                ->where('user_id', Auth::id())
                ->whereNotNull('output')
                ->where('output', '!=', '')
                ->whereNotIn('id', $alreadyImported)
                ->orderByDesc('created_at')
                ->limit(20)
                ->get();

            foreach ($items as $item) {
                $storagePath = $this->resolveImportPath($item);
                if (! $storagePath) {
                    continue;
                }

                // Detect mime type from extension
                $ext = strtolower(pathinfo($item->output, PATHINFO_EXTENSION));
                $mimeType = match ($ext) {
                    'jpg', 'jpeg' => 'image/jpeg',
                    'webp'  => 'image/webp',
                    'gif'   => 'image/gif',
                    default => 'image/png',
                };

                $media = new VideoEditorMedia([
                    'video_editor_project_id' => $projectId,
                    'type'                    => 'image',
                    'filename'                => basename($item->output),
                    'original_filename'       => 'AI Image - ' . Str::limit($item->input ?? 'Untitled', 30),
                    'path'                    => $storagePath,
                    'mime_type'               => $mimeType,
                    'file_size'               => 0,
                    'source'                  => 'imported',
                    'source_id'               => (string) $item->id,
                ]);
                $media->user_id = Auth::id();
                $media->save();
                $imported++;
            }
        }

        return response()->json([
            'status'   => 'success',
            'message'  => $imported > 0
                ? __(':count items imported.', ['count' => $imported])
                : __('No new items to import.'),
            'imported' => $imported,
        ]);
    }

    private function importAiVideo(string $sourceId, ?int $projectId): JsonResponse
    {
        $userFall = UserFall::query()
            ->where('id', $sourceId)
            ->where('user_id', Auth::id())
            ->where('status', 'complete')
            ->first();

        if (! $userFall || ! $userFall->video_url) {
            return response()->json([
                'status'  => 'error',
                'message' => __('Video not found or not ready'),
            ], 404);
        }

        $media = new VideoEditorMedia([
            'video_editor_project_id'  => $projectId,
            'type'                     => 'video',
            'filename'                 => basename($userFall->video_url),
            'original_filename'        => 'AI Video - ' . Str::limit($userFall->prompt, 30),
            'path'                     => $userFall->video_url,
            'mime_type'                => 'video/mp4',
            'file_size'                => 0,
            'source'                   => 'ai_generated',
            'source_id'                => (string) $sourceId,
        ]);
        $media->user_id = Auth::id();
        $media->save();

        return response()->json([
            'status'  => 'success',
            'message' => __('Video imported successfully'),
            'media'   => $media,
        ]);
    }

    private function importAiImage(string $sourceId, ?int $projectId): JsonResponse
    {
        $userImage = UserOpenai::query()
            ->where('id', $sourceId)
            ->where('user_id', Auth::id())
            ->first();

        if (! $userImage || ! $userImage->output) {
            return response()->json([
                'status'  => 'error',
                'message' => __('Image not found'),
            ], 404);
        }

        $storagePath = $this->resolveImportPath($userImage);

        $media = new VideoEditorMedia([
            'video_editor_project_id'  => $projectId,
            'type'                     => 'image',
            'filename'                 => basename($userImage->output),
            'original_filename'        => 'AI Image - ' . Str::limit($userImage->input ?? 'Untitled', 30),
            'path'                     => $storagePath,
            'mime_type'                => 'image/png',
            'file_size'                => 0,
            'source'                   => 'imported',
            'source_id'                => (string) $sourceId,
        ]);
        $media->user_id = Auth::id();
        $media->save();

        return response()->json([
            'status'  => 'success',
            'message' => __('Image imported successfully'),
            'media'   => $media,
        ]);
    }

    /**
     * Resolve the storable path for an imported UserOpenai image.
     *
     * - If output is already a full URL (S3/R2/CDN) → store as-is
     * - If local storage (uploads/public) → store the relative web path (e.g. "uploads/image.png")
     *   so getUrlAttribute() can resolve it with url() using the current request domain.
     */
    private function resolveImportPath(UserOpenai $item): ?string
    {
        $output = $item->output;
        if (! $output) {
            return null;
        }

        // Already a full URL — store directly
        if (str_starts_with($output, 'http://') || str_starts_with($output, 'https://')) {
            return $output;
        }

        // S3 / R2 — these need absolute URLs from their respective disk
        if ($item->storage === 's3') {
            return Storage::disk('s3')->url($output);
        }
        if ($item->storage === 'r2') {
            return Storage::disk('r2')->url($output);
        }

        // Local storage (uploads / public) — store the relative web path.
        // The output typically looks like "uploads/image_xxx.png"
        // Ensure it starts with "uploads/" for getUrlAttribute() to recognize it.
        $relativePath = ltrim($output, '/');
        if (! str_starts_with($relativePath, 'uploads/')) {
            $relativePath = 'uploads/' . $relativePath;
        }

        return $relativePath;
    }

    private function detectMediaType(string $mimeType): ?string
    {
        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        if (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        }

        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        return null;
    }

    /**
     * Get a safe file extension from the MIME type rather than trusting the client-supplied extension.
     * Falls back to a whitelist check of the client extension.
     */
    private function getSafeExtension(string $mimeType, string $clientExtension): string
    {
        $mimeToExt = [
            'video/mp4'        => 'mp4',
            'video/webm'       => 'webm',
            'video/quicktime'  => 'mov',
            'video/x-msvideo'  => 'avi',
            'video/avi'        => 'avi',
            'audio/mpeg'       => 'mp3',
            'audio/mp3'        => 'mp3',
            'audio/wav'        => 'wav',
            'audio/x-wav'      => 'wav',
            'audio/ogg'        => 'ogg',
            'audio/aac'        => 'aac',
            'audio/mp4'        => 'aac',
            'image/jpeg'       => 'jpg',
            'image/png'        => 'png',
            'image/gif'        => 'gif',
            'image/webp'       => 'webp',
        ];

        if (isset($mimeToExt[$mimeType])) {
            return $mimeToExt[$mimeType];
        }

        // Fallback: only allow whitelisted extensions
        $allowed = array_merge(
            config('video-editor.allowed_video_types', []),
            config('video-editor.allowed_audio_types', []),
            config('video-editor.allowed_image_types', []),
        );

        $ext = strtolower($clientExtension);

        return in_array($ext, $allowed, true) ? $ext : 'bin';
    }
}
