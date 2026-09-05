<?php

declare(strict_types=1);

namespace App\Extensions\AiCaptions\System\Http\Controllers;

use App\Extensions\AiCaptions\System\Http\Requests\StoreAiCaptionsRequest;
use App\Extensions\AiCaptions\System\Jobs\ProcessCaptionsJob;
use App\Extensions\AiCaptions\System\Models\AiCaptionVideo;
use App\Extensions\AiCaptions\System\Services\CaptionsApiService;
use App\Extensions\AiCaptions\System\Services\VideoDurationService;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Services\SharedCredit\SharedCreditService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AiCaptionsController extends Controller
{
    private const UPLOAD_DISK = 'uploads';

    public function __construct(private readonly CaptionsApiService $captions) {}

    public function index(): View
    {
        $userId = (int) auth()->id();

        $videos = AiCaptionVideo::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get();

        $todayVideos = $videos->filter(fn (AiCaptionVideo $v) => $v->created_at?->isToday())->values();
        $previousVideos = $videos->filter(fn (AiCaptionVideo $v) => ! $v->created_at?->isToday())->values();
        $inProgress = $videos->filter(fn (AiCaptionVideo $v) => $v->isPending())->pluck('id')->toArray();

        $completed = $videos->where('status', 'completed')->values();

        $videosJson = $completed->map(fn (AiCaptionVideo $v) => [
            'id'                 => $v->id,
            'title'              => $v->title,
            'video_url'          => $v->output_url,
            'template_id'        => $v->template_id,
            'template_name'      => $v->template_name,
            'duration_seconds'   => $v->duration_seconds,
            'formatted_duration' => $v->formatted_duration,
            'created_at'         => $v->created_at?->toIso8601String(),
        ])->values();

        $reusableSources = AiCaptionVideo::query()
            ->where('user_id', $userId)
            ->whereNotNull('source_file_path')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn (AiCaptionVideo $v) => [
                'id'               => $v->id,
                'title'            => $v->title ?: ('video-' . $v->id),
                'duration_seconds' => $v->duration_seconds,
                'source_url'       => $v->source_url,
            ])
            ->values();

        $templates = $this->captions->templates();
        $usageInfo = $this->resolveUsageInfo();
        $isDemo = Helper::appIsDemo();

        return view('ai-captions::index', compact(
            'videos',
            'todayVideos',
            'previousVideos',
            'inProgress',
            'videosJson',
            'reusableSources',
            'templates',
            'usageInfo',
            'isDemo',
        ));
    }

    public function store(StoreAiCaptionsRequest $request): RedirectResponse|JsonResponse
    {
        $isAjax = $request->expectsJson() || $request->ajax();

        if (Helper::appIsDemo()) {
            return $this->respond($isAjax, false, __('This feature is disabled in demo mode.'));
        }

        if (! $this->captions->hasApiKey()) {
            return $this->respond($isAjax, false, __('Captions API key is not configured by the administrator.'));
        }

        $planAccess = $this->checkPlanAccess();
        if ($planAccess !== true) {
            return $this->respond($isAjax, false, $planAccess);
        }

        $usageCheck = $this->checkUsageLimit();
        if ($usageCheck !== true) {
            return $this->respond($isAjax, false, $usageCheck);
        }

        $validated = $request->validated();

        $source = $this->resolveSource($request->file('video_file'), $validated['existing_video_id'] ?? null);

        if ($source === null) {
            return $this->respond($isAjax, false, __('Source video could not be resolved.'));
        }

        if (! empty($source['absolute_path'])) {
            if (is_int($source['duration']) && $source['duration'] > 300) {
                if (! empty($source['path'])) {
                    Storage::disk(self::UPLOAD_DISK)->delete($source['path']);
                }

                return $this->respond($isAjax, false, __('Video must be 5 minutes or shorter.'));
            }

            $dims = VideoDurationService::dimensionsFromPath($source['absolute_path']);
            if ($dims !== null && $dims['width'] > 0 && $dims['height'] > 0) {
                $ratio = $dims['width'] / $dims['height'];
                if (abs($ratio - (9 / 16)) > 0.05) {
                    if (! empty($source['path'])) {
                        Storage::disk(self::UPLOAD_DISK)->delete($source['path']);
                    }

                    return $this->respond($isAjax, false, __('Video must be portrait 9:16 aspect ratio.'));
                }
            }
        }

        $templates = collect($this->captions->templates());
        $templateName = (string) ($templates->firstWhere('id', $validated['template_id'])['name'] ?? $validated['template_id']);

        $entry = AiCaptionVideo::create([
            'user_id'          => auth()->id(),
            'is_demo'          => false,
            'title'            => $validated['title'] ?? null,
            'template_id'      => $validated['template_id'],
            'template_name'    => $templateName,
            'source_url'       => $source['url'],
            'source_file_path' => $source['path'],
            'duration_seconds' => $source['duration'],
            'status'           => 'processing',
        ]);

        $submission = $this->captions->submit(
            templateId: $validated['template_id'],
            absoluteFilePath: $source['absolute_path'] ?? null,
            existingVideoId: $source['remote_video_id'] ?? null,
        );

        if (! $submission['success']) {
            $entry->update([
                'status'        => 'failed',
                'error_message' => Str::limit((string) ($submission['error'] ?? __('Submission failed.')), 1000),
            ]);

            return $this->respond($isAjax, false, (string) ($submission['error'] ?? __('Submission failed.')));
        }

        $entry->update([
            'captions_video_id' => $submission['video_id'],
            'status'            => 'processing',
        ]);

        ProcessCaptionsJob::dispatch($entry->id)->delay(now()->addSeconds(5));

        return $this->respond($isAjax, true, __('Your captioned video is being generated.'), [
            'video_id' => $entry->id,
        ]);
    }

    public function all(Request $request): View
    {
        $userId = (int) auth()->id();

        $videos = AiCaptionVideo::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->paginate(24)
            ->withQueryString();

        return view('ai-captions::all', compact('videos'));
    }

    public function checkStatus(Request $request): JsonResponse
    {
        $userId = (int) auth()->id();
        $ids = (array) $request->get('ids', []);

        $entries = AiCaptionVideo::query()
            ->where('user_id', $userId)
            ->whereIn('id', $ids)
            ->get();

        $data = [];

        foreach ($entries as $entry) {
            $payload = [
                'divId' => 'caption-video-' . $entry->id,
                'html'  => view('ai-captions::partials.video-item', ['entry' => $entry])->render(),
            ];

            if ($entry->isComplete()) {
                $payload['videoData'] = [
                    'id'                 => $entry->id,
                    'title'              => $entry->title,
                    'video_url'          => $entry->output_url,
                    'template_id'        => $entry->template_id,
                    'template_name'      => $entry->template_name,
                    'duration_seconds'   => $entry->duration_seconds,
                    'formatted_duration' => $entry->formatted_duration,
                    'created_at'         => $entry->created_at?->toIso8601String(),
                ];
            }

            $data[] = $payload;
        }

        return response()->json(['data' => $data]);
    }

    public function delete(string $id): RedirectResponse
    {
        if (Helper::appIsDemo()) {
            return back()->with(['message' => __('This feature is disabled in demo mode.'), 'type' => 'error']);
        }

        $entry = AiCaptionVideo::query()
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        if ($entry->source_file_path && Storage::disk(self::UPLOAD_DISK)->exists($entry->source_file_path)) {
            Storage::disk(self::UPLOAD_DISK)->delete($entry->source_file_path);
        }

        if ($entry->local_path && Storage::disk(self::UPLOAD_DISK)->exists($entry->local_path)) {
            Storage::disk(self::UPLOAD_DISK)->delete($entry->local_path);
        }

        $entry->delete();

        return back()->with(['message' => __('Deleted successfully.'), 'type' => 'success']);
    }

    public function rename(Request $request, string $id): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json(['success' => false, 'message' => __('This feature is disabled in demo mode.')], 403);
        }

        $entry = AiCaptionVideo::query()
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $entry->update(['title' => trim($validated['title'])]);

        return response()->json([
            'success' => true,
            'title'   => $entry->title,
        ]);
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json(['message' => __('This feature is disabled in demo mode.')], 403);
        }

        $validated = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer'],
        ]);

        $count = AiCaptionVideo::query()
            ->where('user_id', auth()->id())
            ->whereIn('id', $validated['ids'])
            ->delete();

        return response()->json([
            'message' => __('Deleted :count video(s) successfully.', ['count' => $count]),
        ]);
    }

    /**
     * @return array{path: ?string, absolute_path: ?string, url: string, duration: ?int, remote_video_id: ?string}|null
     */
    private function resolveSource(?UploadedFile $file, ?int $existingId): ?array
    {
        if ($file instanceof UploadedFile) {
            $relativePath = 'media/videos/u-' . auth()->id() . '/captions-source/' . Str::random(16) . '.' . $file->guessExtension();
            $absoluteTarget = Storage::disk(self::UPLOAD_DISK)->path($relativePath);

            if (! is_dir(dirname($absoluteTarget))
                && ! mkdir($dir = dirname($absoluteTarget), 0755, true)
                && ! is_dir($dir)
            ) {
                return null;
            }

            $file->move(dirname($absoluteTarget), basename($absoluteTarget));

            return [
                'path'            => $relativePath,
                'absolute_path'   => $absoluteTarget,
                'url'             => Storage::disk(self::UPLOAD_DISK)->url($relativePath),
                'duration'        => VideoDurationService::fromPath($absoluteTarget),
                'remote_video_id' => null,
            ];
        }

        if ($existingId !== null) {
            $existing = AiCaptionVideo::query()
                ->where('user_id', auth()->id())
                ->find($existingId);

            if ($existing && $existing->source_file_path) {
                $absolutePath = Storage::disk(self::UPLOAD_DISK)->path($existing->source_file_path);

                return [
                    'path'            => $existing->source_file_path,
                    'absolute_path'   => is_file($absolutePath) ? $absolutePath : null,
                    'url'             => $existing->source_url ?? '',
                    'duration'        => $existing->duration_seconds,
                    'remote_video_id' => null,
                ];
            }
        }

        return null;
    }

    /**
     * @return true|string true if access allowed, error message otherwise
     */
    private function checkPlanAccess(): true|string
    {
        $user = auth()->user();

        if ($user === null) {
            return __('You must be logged in to use AI Captions.');
        }

        if ($user->isAdmin()) {
            return true;
        }

        $plan = $user->relationPlan;

        if ($plan === null) {
            return __('You need an active plan to use AI Captions.');
        }

        if (! (bool) ($plan->ai_captions_access ?? false)) {
            return __('AI Captions is not available in your current plan.');
        }

        return true;
    }

    /**
     * @return true|string true if within limit, error message otherwise
     */
    private function checkUsageLimit(): true|string
    {
        $user = auth()->user();

        if ($user === null) {
            return __('You must be logged in to use AI Captions.');
        }

        if ($user->isAdmin()) {
            return true;
        }

        if (app(SharedCreditService::class)->isEnabled() && $user->isSharedCreditUser()) {
            return true;
        }

        $plan = $user->relationPlan;
        $limitMinutes = (int) ($plan->ai_captions_minutes ?? config('aicaptions.default_monthly_minutes', 30));

        if ($limitMinutes === -1) {
            return true;
        }

        if ($limitMinutes === 0) {
            return __('AI Captions is not available in your current plan.');
        }

        $usedSeconds = (int) AiCaptionVideo::query()
            ->where('user_id', $user->id)
            ->where('usage_deducted', true)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('duration_seconds');

        if ($usedSeconds >= $limitMinutes * 60) {
            return __('You have reached your monthly AI Captions limit of :minutes minutes.', [
                'minutes' => $limitMinutes,
            ]);
        }

        return true;
    }

    /**
     * @return array{used_seconds: int, limit_minutes: int, unlimited: bool}
     */
    private function resolveUsageInfo(): array
    {
        $user = auth()->user();
        $plan = $user?->relationPlan;

        $limitMinutes = (int) ($plan->ai_captions_minutes ?? config('aicaptions.default_monthly_minutes', 30));

        $usedSeconds = (int) AiCaptionVideo::query()
            ->where('user_id', $user?->id ?? 0)
            ->where('usage_deducted', true)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('duration_seconds');

        return [
            'used_seconds'  => $usedSeconds,
            'limit_minutes' => $limitMinutes,
            'unlimited'     => $limitMinutes === -1,
        ];
    }

    private function respond(bool $isAjax, bool $success, string $message, array $extra = []): RedirectResponse|JsonResponse
    {
        if ($isAjax) {
            return response()->json(array_merge([
                'success' => $success,
                'message' => $message,
            ], $extra), $success ? 200 : 422);
        }

        return back()->with([
            'message' => $message,
            'type'    => $success ? 'success' : 'error',
        ]);
    }
}
