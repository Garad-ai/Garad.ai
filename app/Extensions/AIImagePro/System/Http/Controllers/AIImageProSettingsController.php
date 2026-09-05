<?php

namespace App\Extensions\AIImagePro\System\Http\Controllers;

use App\Extensions\AIImagePro\System\Models\AiImageProModel;
use App\Extensions\AIImagePro\System\Services\AIImageProService;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Services\Common\MenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AIImageProSettingsController extends Controller
{
    public function edit(): View
    {
        $models = AIImageProService::getModelsForTagInput();
        $selectedSlugs = AIImageProService::getSelectedModelSlugs();

        return view('ai-image-pro::settings', compact('models', 'selectedSlugs'));
    }

    public function update(Request $request): RedirectResponse
    {
        if (Helper::appIsDemo()) {
            return back()->with([
                'type'    => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        $request->validate([
            'active_models'                       => 'required|array',
            'active_models.*'                     => 'string|in:' . implode(',', array_column(AIImageProService::getModelsForTagInput(), 'value')),
            'ai-image-pro:show-tools-section'     => 'sometimes|nullable|in:on,off',
            'ai-image-pro:show-community-section' => 'sometimes|nullable|in:on,off',
            'ai_image_pro_display_type'           => 'sometimes|nullable',
            'ai_image_pro:guest_daily_limit'      => 'required|integer|min:-1',
            'ai-image-pro:show-footer'            => 'sometimes|nullable|in:on,off',
            'ai-image-pro:footer-copyright'       => 'sometimes|nullable|string|max:500',
            'ai-image-pro:footer-show-social'     => 'sometimes|nullable|in:on,off',
            'ai-image-pro:footer-columns'         => 'sometimes|nullable|string',
            'ai_image_pro_edit_model'             => 'sometimes|nullable|string',
        ]);

        $activeModels = $request->input('active_models');
        $type = setting('frontend_additional_url_type', 'default');
        $url = setting('frontend_additional_url', '/');
        if (in_array($request->input('ai_image_pro_display_type', 'both_fm'), ['both_fm', 'frontend'])) {
            $url = '/ai-image-pro';
            $type = 'ai-image-pro';
        } elseif ($url === '/ai-image-pro' || $type === 'ai-image-pro') {
            // Reset to default only if currently pointing to chat
            $url = '/';
            $type = 'default';
        }

        setting([
            'frontend_additional_url'             => $url,
            'frontend_additional_url_type'        => $type,
            'ai_image_selected_models'            => json_encode($activeModels),
            'ai-image-pro:show-tools-section'     => $request->has('ai-image-pro:show-tools-section') ? 1 : 0,
            'ai-image-pro:show-community-section' => $request->has('ai-image-pro:show-community-section') ? 1 : 0,
            'ai_image_pro_display_type'           => $request->input('ai_image_pro_display_type', 'both_fm'),
            'ai_image_pro:guest_daily_limit'      => $request->input('ai_image_pro:guest_daily_limit', 2),
            'ai-image-pro:show-footer'            => $request->has('ai-image-pro:show-footer') ? 1 : 0,
            'ai-image-pro:footer-copyright'       => $request->input('ai-image-pro:footer-copyright', ''),
            'ai-image-pro:footer-show-social'     => $request->has('ai-image-pro:footer-show-social') ? 1 : 0,
            'ai-image-pro:footer-columns'         => $request->input('ai-image-pro:footer-columns', '[]'),
            'ai_image_pro_edit_model'             => $request->input('ai_image_pro_edit_model', 'gpt-image-1.5'),
        ]);
        setting()->save();
        app(MenuService::class)->regenerate();

        return redirect()->back()->with(['message' => __('Active AI image models updated.'), 'type' => 'success']);
    }

    /**
     * Display the community images index page (not the publish requests page).
     */
    public function communityReqsIndex(): View
    {
        return view('ai-image-pro::publish-reqs');
    }

    /**
     * Get publish requests with stats (AJAX endpoint).
     */
    public function publishReqs(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'success' => false,
                'message' => __('This feature is disabled in demo mode.'),
            ], 403);
        }

        $status = $request->get('status', 'all');

        // Build query for publish requests
        $query = AiImageProModel::query()
            ->whereNotNull('publish_requested_at')
            ->with('user:id,name,avatar');

        // Filter by status
        if ($status !== 'all') {
            if ($status === 'pending') {
                $query->whereNull('publish_reviewed_at');
            } elseif ($status === 'approved') {
                $query->where('published', true)
                    ->whereNotNull('publish_reviewed_at');
            } elseif ($status === 'rejected') {
                $query->where('published', false)
                    ->whereNotNull('publish_reviewed_at');
            }
        }

        // Get requests
        $requests = $query->orderBy('publish_requested_at', 'desc')->get();

        // Calculate stats
        $stats = [
            'total'   => AiImageProModel::whereNotNull('publish_requested_at')->count(),
            'pending' => AiImageProModel::whereNotNull('publish_requested_at')
                ->whereNull('publish_reviewed_at')
                ->count(),
            'approved' => AiImageProModel::whereNotNull('publish_requested_at')
                ->whereNotNull('publish_reviewed_at')
                ->where('published', true)
                ->count(),
            'rejected' => AiImageProModel::whereNotNull('publish_requested_at')
                ->whereNotNull('publish_reviewed_at')
                ->where('published', false)
                ->count(),
            'approved_today' => AiImageProModel::whereNotNull('publish_requested_at')
                ->whereNotNull('publish_reviewed_at')
                ->where('published', true)
                ->whereDate('publish_reviewed_at', today())
                ->count(),
            'rejected_today' => AiImageProModel::whereNotNull('publish_requested_at')
                ->whereNotNull('publish_reviewed_at')
                ->where('published', false)
                ->whereDate('publish_reviewed_at', today())
                ->count(),
        ];

        // Format requests for frontend
        $formattedRequests = $requests->map(function ($request) {
            // Determine status
            if (is_null($request->publish_reviewed_at)) {
                $status = 'pending';
                $reviewedBy = null;
            } elseif ($request->published) {
                $status = 'approved';
                $reviewedBy = optional($request->reviewedBy)->name ?? 'Admin';
            } else {
                $status = 'rejected';
                $reviewedBy = optional($request->reviewedBy)->name ?? 'Admin';
            }

            // Get first generated image
            $imageUrl = is_array($request->generated_images) && count($request->generated_images) > 0
                ? $request->generated_images[0]
                : null;

            $userAvatar = optional($request->user)->avatar;
            if ($userAvatar && strpos($userAvatar, 'http') === false) {
                $userAvatar = custom_theme_url('/' . $userAvatar);
            }

            return [
                'id'          => $request->id,
                'title'       => $request->prompt,
                'description' => $request->metadata['description'] ?? null,
                'image_url'   => $imageUrl,
                'status'      => $status,
                'user'        => [
                    'name'    => optional($request->user)->name ?? 'Anonymous',
                    'avatar'  => $userAvatar,
                    'initial' => strtoupper(substr(optional($request->user)->name ?? 'A', 0, 1)),
                ],
                'tags'        => $request->metadata['tags'] ?? [],
                'created_at'  => optional($request->publish_requested_at)->diffForHumans(),
                'reviewed_by' => $reviewedBy,
            ];
        });

        return response()->json([
            'requests' => $formattedRequests,
            'stats'    => $stats,
        ]);
    }

    /**
     * Approve a publish request.
     */
    public function approveRequest(Request $request, int $id): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'success' => false,
                'message' => __('This feature is disabled in demo mode.'),
            ], 403);
        }

        $imageRequest = AiImageProModel::findOrFail($id);

        // Approve the request (works for both pending and rejected requests)
        $imageRequest->update([
            'published'           => true,
            'publish_reviewed_at' => now(),
            'publish_reviewed_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => __('Request approved successfully!'),
        ]);
    }

    /**
     * Reject a publish request.
     */
    public function rejectRequest(Request $request, int $id): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'success' => false,
                'message' => __('This feature is disabled in demo mode.'),
            ], 403);
        }

        $imageRequest = AiImageProModel::findOrFail($id);

        // Check if already rejected
        if ($imageRequest->publish_reviewed_at && ! $imageRequest->published) {
            return response()->json([
                'success' => false,
                'message' => __('This request has already been rejected.'),
            ], 400);
        }

        // Reject the request (works for both pending and approved requests)
        $imageRequest->update([
            'published'           => false,
            'publish_reviewed_at' => now(),
            'publish_reviewed_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => __('Request rejected successfully!'),
        ]);
    }
}
