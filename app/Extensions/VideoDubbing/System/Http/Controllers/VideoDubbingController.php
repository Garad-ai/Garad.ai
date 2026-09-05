<?php

declare(strict_types=1);

namespace App\Extensions\VideoDubbing\System\Http\Controllers;

use App\Enums\Introduction;
use App\Extensions\VideoDubbing\System\Http\Requests\StoreVideoDubbingRequest;
use App\Extensions\VideoDubbing\System\Jobs\ProcessVideoDubbingJob;
use App\Extensions\VideoDubbing\System\Jobs\RefreshVideoDubbingStatusJob;
use App\Extensions\VideoDubbing\System\Models\VideoDubbing;
use App\Extensions\VideoDubbing\System\Services\VideoDubbingService;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Services\SharedCredit\SharedCreditService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Js;

class VideoDubbingController extends Controller
{
    private VideoDubbingService $service;

    public function __construct()
    {
        $this->service = new VideoDubbingService;
    }

    public function index(): View
    {
        $dubbings = VideoDubbing::query()
            ->where('user_id', auth()->id())
            ->latest()
            ->get();

        $todayDubbings = $dubbings->filter(fn ($d) => $d->created_at->isToday());
        $previousDubbings = $dubbings->filter(fn ($d) => ! $d->created_at->isToday());

        $inProgress = $dubbings->filter(fn ($d) => $d->isPending())->pluck('id')->toArray();

        $languages = $this->service->getLanguagesForProvider();
        $provider = $this->service->getDefaultProvider();

        $usageInfo = $this->getUsageInfo();

        $videosJson = Js::from(
            $dubbings
                ->where('status', 'complete')
                ->values()
                ->map(function (VideoDubbing $dubbing) {
                    $providerLanguages = config('videodubbing.' . ($dubbing->provider ?? 'heygen') . '.languages', []);

                    return [
                        'id'                   => $dubbing->id,
                        'video_url'            => $dubbing->output_url,
                        'title'                => $dubbing->title ?: __('Dubbed to :lang', ['lang' => __($providerLanguages[$dubbing->target_language] ?? $dubbing->target_language)]),
                        'target_language'      => $dubbing->target_language,
                        'target_language_name' => $providerLanguages[$dubbing->target_language] ?? $dubbing->target_language,
                        'provider'             => ucfirst($dubbing->provider ?? 'heygen'),
                        'duration_seconds'     => $dubbing->duration_seconds,
                        'formatted_duration'   => $dubbing->duration_seconds
                            ? sprintf('%02d:%02d', intdiv($dubbing->duration_seconds, 60), $dubbing->duration_seconds % 60)
                            : null,
                        'created_at' => $dubbing->created_at?->toIso8601String(),
                    ];
                })
        );

        return view('video-dubbing::index', compact(
            'dubbings',
            'todayDubbings',
            'previousDubbings',
            'inProgress',
            'languages',
            'provider',
            'usageInfo',
            'videosJson'
        ));
    }

    public function store(StoreVideoDubbingRequest $request): RedirectResponse|JsonResponse
    {
        if (Helper::appIsDemo()) {
            return $this->storeFailure($request, __('This feature is disabled in demo mode.'));
        }

        $planCheck = $this->checkPlanAccess();

        if ($planCheck !== true) {
            return $this->storeFailure($request, $planCheck);
        }

        $validated = $request->validated();
        $availableLanguages = $this->service->getLanguagesForProvider();
        $targetLanguages = array_values(array_filter(
            $validated['target_languages'],
            fn (string $code) => array_key_exists($code, $availableLanguages)
        ));

        if (empty($targetLanguages)) {
            return $this->storeFailure($request, __('Please select at least one valid target language.'));
        }

        $file = $request->file('video_file');
        $storedFilePath = $file ? $this->service->storeUploadedFile($file) : null;
        $estimatedSeconds = $storedFilePath ? $this->service->estimateUploadedDuration($storedFilePath) : null;

        $usageCheck = $this->checkUsageLimit($estimatedSeconds, count($targetLanguages));

        if ($usageCheck !== true) {
            if ($storedFilePath && Storage::disk('uploads')->exists($storedFilePath)) {
                Storage::disk('uploads')->delete($storedFilePath);
            }

            return $this->storeFailure($request, $usageCheck);
        }

        $createdCount = 0;

        foreach ($targetLanguages as $language) {
            $dubbing = $this->service->createPendingDubbing(
                $validated,
                $language,
                $storedFilePath,
                $estimatedSeconds,
                $file?->getClientOriginalName(),
                $file?->getClientMimeType(),
            );

            ProcessVideoDubbingJob::dispatch($dubbing->id);
            $createdCount++;
        }

        $message = __(':count video(s) are being dubbed. They will be ready shortly.', ['count' => $createdCount]);

        if ($request->wantsJson()) {
            return response()->json([
                'success'    => true,
                'message'    => $message,
                'videosHtml' => $this->renderVideosSection(),
            ]);
        }

        return back()->with([
            'message' => $message,
            'type'    => 'success',
        ]);
    }

    private function storeFailure(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['success' => false, 'message' => $message], 422);
        }

        return back()->with(['message' => $message, 'type' => 'error']);
    }

    private function renderVideosSection(): string
    {
        $dubbings = VideoDubbing::query()
            ->where('user_id', auth()->id())
            ->latest()
            ->get();

        $todayDubbings = $dubbings->filter(fn ($d) => $d->created_at->isToday());
        $previousDubbings = $dubbings->filter(fn ($d) => ! $d->created_at->isToday());

        return view('video-dubbing::sections.videos-section', compact(
            'dubbings',
            'todayDubbings',
            'previousDubbings',
        ))->render();
    }

    public function delete(string $id): RedirectResponse
    {
        if (Helper::appIsDemo()) {
            return back()->with(['message' => __('This feature is disabled in demo mode.'), 'type' => 'error']);
        }

        $dubbing = VideoDubbing::query()
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        $this->deleteDubbingFiles($dubbing);
        $dubbing->delete();

        return back()->with(['message' => __('Deleted successfully.'), 'type' => 'success']);
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

        $dubbings = VideoDubbing::query()
            ->where('user_id', auth()->id())
            ->whereIn('id', $validated['ids'])
            ->get();

        foreach ($dubbings as $dubbing) {
            $this->deleteDubbingFiles($dubbing);
            $dubbing->delete();
        }

        return response()->json([
            'message' => __('Deleted :count video(s) successfully.', ['count' => $dubbings->count()]),
        ]);
    }

    public function checkStatus(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return response()->json(['data' => []]);
        }

        $dubbings = VideoDubbing::query()
            ->where('user_id', auth()->id())
            ->whereIn('id', $ids)
            ->get();

        $data = [];

        foreach ($dubbings as $dubbing) {
            if ($dubbing->isPending() && $dubbing->request_id) {
                RefreshVideoDubbingStatusJob::dispatch($dubbing->id);
            }

            $data[$dubbing->id] = [
                'divId'  => 'dubbing-' . $dubbing->id,
                'html'   => view('video-dubbing::partials.video-item', ['entry' => $dubbing])->render(),
                'status' => $dubbing->status,
            ];
        }

        return response()->json([
            'data'   => $data,
            'videos' => $this->buildVideosList(),
        ]);
    }

    private function buildVideosList(): array
    {
        return VideoDubbing::query()
            ->where('user_id', auth()->id())
            ->where('status', 'complete')
            ->latest()
            ->get()
            ->map(function (VideoDubbing $dubbing) {
                $providerLanguages = config('videodubbing.' . ($dubbing->provider ?? 'heygen') . '.languages', []);

                return [
                    'id'                   => $dubbing->id,
                    'video_url'            => $dubbing->output_url,
                    'title'                => $dubbing->title ?: __('Dubbed to :lang', ['lang' => __($providerLanguages[$dubbing->target_language] ?? $dubbing->target_language)]),
                    'target_language'      => $dubbing->target_language,
                    'target_language_name' => $providerLanguages[$dubbing->target_language] ?? $dubbing->target_language,
                    'provider'             => ucfirst($dubbing->provider ?? 'heygen'),
                    'duration_seconds'     => $dubbing->duration_seconds,
                    'formatted_duration'   => $dubbing->duration_seconds
                        ? sprintf('%02d:%02d', intdiv($dubbing->duration_seconds, 60), $dubbing->duration_seconds % 60)
                        : null,
                    'created_at' => $dubbing->created_at?->toIso8601String(),
                ];
            })
            ->all();
    }

    private function deleteDubbingFiles(VideoDubbing $dubbing): void
    {
        if ($dubbing->source_file_path) {
            $stillUsed = VideoDubbing::query()
                ->where('user_id', $dubbing->user_id)
                ->where('id', '!=', $dubbing->id)
                ->where('source_file_path', $dubbing->source_file_path)
                ->exists();

            if (! $stillUsed && Storage::disk('uploads')->exists($dubbing->source_file_path)) {
                Storage::disk('uploads')->delete($dubbing->source_file_path);
            }
        }

        if ($dubbing->output_url) {
            $outputPath = str_replace('/uploads/', '', parse_url($dubbing->output_url, PHP_URL_PATH) ?? '');

            if ($outputPath && Storage::disk('uploads')->exists($outputPath)) {
                Storage::disk('uploads')->delete($outputPath);
            }
        }
    }

    private function checkPlanAccess(): true|string
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            return true;
        }

        $plan = $user->relationPlan;

        if (! $plan) {
            return __('You need an active plan to use video dubbing.');
        }

        if (! $plan->checkOpenAiItem(Introduction::AI_VIDEO_DUBBING->value)) {
            return __('Video dubbing is not available in your current plan.');
        }

        return true;
    }

    private function checkUsageLimit(?int $estimatedSeconds, int $languageCount): true|string
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            return true;
        }

        if (app(SharedCreditService::class)->isEnabled() && $user->isSharedCreditUser()) {
            return true;
        }

        $plan = $user->relationPlan;

        if (! $plan) {
            return true;
        }

        $secondsLimit = (int) ($plan->video_dubbing_seconds_limit ?? -1);

        if ($secondsLimit === -1) {
            return true;
        }

        if ($secondsLimit === 0) {
            return __('Video dubbing is not available in your current plan.');
        }

        $usedSeconds = (int) VideoDubbing::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['complete', 'pending', 'running'])
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('duration_seconds');

        if ($usedSeconds >= $secondsLimit) {
            return __('You have reached your monthly video dubbing limit of :seconds seconds.', [
                'seconds' => $secondsLimit,
            ]);
        }

        if ($estimatedSeconds !== null && $estimatedSeconds > 0) {
            $projected = $usedSeconds + ($estimatedSeconds * max(1, $languageCount));

            if ($projected > $secondsLimit) {
                return __('Dubbing this video into :count language(s) would exceed your monthly limit of :seconds seconds.', [
                    'count'   => $languageCount,
                    'seconds' => $secondsLimit,
                ]);
            }
        }

        return true;
    }

    /**
     * @return array{used: int, limit: int, unlimited: bool}
     */
    private function getUsageInfo(): array
    {
        $user = auth()->user();
        $plan = $user->relationPlan;

        $usedSeconds = (int) VideoDubbing::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['complete', 'pending', 'running'])
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('duration_seconds');

        $limit = -1;

        if ($plan) {
            $limit = (int) ($plan->video_dubbing_seconds_limit ?? -1);
        }

        return [
            'used'      => $usedSeconds,
            'limit'     => $limit,
            'unlimited' => $limit === -1,
        ];
    }
}
