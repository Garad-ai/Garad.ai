<?php

declare(strict_types=1);

namespace App\Extensions\VideoEditor\System\Http\Controllers;

use App\Extensions\VideoEditor\System\Http\Requests\ExportProjectRequest;
use App\Extensions\VideoEditor\System\Jobs\ProcessVideoExportJob;
use App\Extensions\VideoEditor\System\Models\VideoEditorExportJob;
use App\Extensions\VideoEditor\System\Models\VideoEditorProject;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VideoEditorExportController extends Controller
{
    public function start(ExportProjectRequest $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            $ip = (string) ($request->ip() ?? 'unknown');
            $cacheKey = 'video-editor-demo-export:' . sha1($ip);

            if (Cache::has($cacheKey)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => __('Demo mode allows only one export per day. Please try again tomorrow.'),
                ], 429);
            }

            Cache::put($cacheKey, 1, now()->endOfDay());
        }

        $validated = $request->validated();

        $project = VideoEditorProject::findOrFail($validated['project_id']);
        abort_if($project->user_id !== Auth::id(), 404);

        $format = 'mp4';
        $resolution = $project->width && $project->height
            ? $project->width . 'x' . $project->height
            : '1080p';

        $lock = Cache::lock('video-editor-export-start:' . $project->id, 10);

        try {
            $lock->block(2);
        } catch (LockTimeoutException) {
            return response()->json([
                'status'  => 'error',
                'message' => __('Another export request is being processed. Please try again.'),
            ], 409);
        }

        try {
            $activeExport = VideoEditorExportJob::query()
                ->where('video_editor_project_id', $project->id)
                ->whereIn('status', ['pending', 'processing'])
                ->first();

            if ($activeExport) {
                return response()->json([
                    'status'    => 'error',
                    'message'   => __('An export is already in progress for this project.'),
                    'export_id' => $activeExport->id,
                ], 409);
            }

            $exportJob = new VideoEditorExportJob([
                'video_editor_project_id' => $project->id,
                'status'                  => 'pending',
                'format'                  => $format,
                'resolution'              => $resolution,
                'progress'                => 0,
            ]);
            $exportJob->user_id = Auth::id();
            $exportJob->save();
        } finally {
            $lock->release();
        }

        ProcessVideoExportJob::dispatch($exportJob->id);

        return response()->json([
            'status'    => 'success',
            'message'   => __('Export started'),
            'export_id' => $exportJob->id,
        ]);
    }

    public function status(VideoEditorExportJob $exportJob): JsonResponse
    {
        abort_if($exportJob->user_id !== Auth::id(), 404);

        return response()->json([
            'status'       => 'success',
            'export'       => [
                'id'            => $exportJob->id,
                'status'        => $exportJob->status,
                'progress'      => $exportJob->progress,
                'format'        => $exportJob->format,
                'resolution'    => $exportJob->resolution,
                'error_message' => $exportJob->error_message,
                'completed_at'  => $exportJob->completed_at?->toIso8601String(),
            ],
        ]);
    }

    public function download(VideoEditorExportJob $exportJob): StreamedResponse|JsonResponse
    {
        abort_if($exportJob->user_id !== Auth::id(), 404);

        if ($exportJob->status !== 'completed' || ! $exportJob->output_path) {
            return response()->json([
                'status'  => 'error',
                'message' => __('Export is not ready for download'),
            ], 400);
        }

        if (! Storage::disk('public')->exists($exportJob->output_path)) {
            return response()->json([
                'status'  => 'error',
                'message' => __('Export file not found'),
            ], 404);
        }

        $project = $exportJob->project;
        $filename = Str::slug($project->name) . '_' . $exportJob->resolution . '.' . $exportJob->format;

        return Storage::disk('public')->download($exportJob->output_path, $filename);
    }

    public function rename(Request $request, VideoEditorExportJob $exportJob): JsonResponse
    {
        abort_if($exportJob->user_id !== Auth::id(), 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $project = $exportJob->project;

        if ($project === null) {
            return response()->json([
                'status'  => 'error',
                'message' => __('Project not found'),
            ], 404);
        }

        $project->name = trim($validated['name']);
        $project->save();

        $title = $project->name . ' — ' . __('Export #:id', ['id' => $exportJob->id]);

        return response()->json([
            'status' => 'success',
            'title'  => $title,
            'name'   => $project->name,
        ]);
    }

    public function destroy(VideoEditorExportJob $exportJob): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'message' => __('This feature is disabled in demo mode.'),
            ], 403);
        }

        abort_if($exportJob->user_id !== Auth::id(), 404);

        if ($exportJob->output_path && Storage::disk('public')->exists($exportJob->output_path)) {
            Storage::disk('public')->delete($exportJob->output_path);
        }

        $exportJob->delete();

        return response()->json([
            'status'  => 'success',
            'message' => __('Export deleted'),
        ]);
    }
}
