<?php

namespace App\Extensions\AIImagePro\System\Http\Controllers;

use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity;
use App\Enums\AiImageStatusEnum;
use App\Extensions\AdvancedImage\System\Helpers\Tool;
use App\Extensions\AiChatProImageChat\System\Services\AIChatImageService;
use App\Extensions\AIImagePro\System\Http\Requests\GenerateAIImageRequest;
use App\Extensions\AIImagePro\System\Http\Requests\GenerateRealtimeImageRequest;
use App\Extensions\AIImagePro\System\Models\AiImageProModel;
use App\Extensions\AIImagePro\System\Services\AIImageProService;
use App\Extensions\AIImagePro\System\Services\RealtimeGenerationService;
use App\Helpers\Classes\Helper;
use App\Helpers\Classes\MarketplaceHelper;
use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\FrontendGenerators;
use App\Models\OpenaiGeneratorChatCategory;
use App\Models\SettingTwo;
use App\Models\Usage;
use App\Models\UserOpenaiChat;
use App\Models\UserOpenaiChatMessage;
use Exception;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use JsonException;

class AIImageProController extends Controller
{
    /**
     * Display the main AI Image Pro page.
     */
    public function __invoke(Request $request)
    {
        // Redirect authenticated users to dashboard
        if (auth()->check() && ! $request->routeIs('dashboard.user.ai-image-pro.*')) {
            return redirect()->route('dashboard.user.ai-image-pro.index');
        }

        $activeImageModels = AIImageProService::getActiveImageModels();
        $imageStats = $this->getUserImageStats($request);
        $tools = MarketplaceHelper::isRegistered('advanced-image') ? Tool::get() : [];
        $faq = Faq::all();
        $generatorsList = FrontendGenerators::orderBy('created_at', 'desc')->get();

        return view('ai-image-pro::index', compact('activeImageModels', 'imageStats', 'tools', 'faq', 'generatorsList'));
    }

    /**
     * Handle image generation requests.
     * Now returns immediately after queuing the job.
     * Supports both regular form submissions (redirect) and AJAX requests (JSON).
     */
    public function generateImage(GenerateAIImageRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();
        $requestedImageCount = (int) ($validated['image_count'] ?? 1);
        $isAjax = $request->expectsJson() || $request->ajax();

        // Check daily limit for guests
        if (Helper::appIsDemo() || ! auth()->check()) {
            $limitCheck = $this->checkGuestDailyLimit($request, $requestedImageCount);

            if (! $limitCheck['allowed']) {
                if ($isAjax) {
                    return response()->json([
                        'success' => false,
                        'message' => $limitCheck['message'],
                    ], 403);
                }

                return redirect()
                    ->back()
                    ->with([
                        'message' => $limitCheck['message'],
                        'type'    => 'error',
                    ]);
            }
        }

        $model = EntityEnum::fromSlug($validated['model'] ?? EntityEnum::fromSlug(SettingTwo::getCache()->dalle ?? EntityEnum::DALL_E_2->value)->value) ?? EntityEnum::DALL_E_2;
        $driver = auth()->check() ? Entity::driver($model)->inputImageCount($requestedImageCount)->calculateCredit() : null;
        if ($driver && ! $driver->hasCreditBalanceForInput()) {
            $errorMessage = __('You have no credits left. Please consider upgrading your plan.');
            if ($isAjax) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                ], 403);
            }

            return redirect()
                ->back()
                ->with([
                    'message' => $errorMessage,
                    'type'    => 'error',
                ]);
        }

        $isFullUrl = ! str_starts_with($model->engine()->slug(), 'stable_');
        if ($request->hasFile('style_reference')) {
            $file = $request->file('style_reference');
            $validated['style_reference'] = getSelectedOrUploadedFile($file, $isFullUrl);
        }

        if ($request->hasFile('image_reference')) {
            $files = $request->file('image_reference');

            // Check if it's (multiple files) or single file
            if (is_array($files)) {
                // Multiple files - process each one
                $validated['image_reference'] = array_map(static function ($file) use ($isFullUrl) {
                    return getSelectedOrUploadedFile($file, $isFullUrl);
                }, $files);
            } else {
                // Single file - process it directly
                $validated['image_reference'] = getSelectedOrUploadedFile($files, $isFullUrl);
            }
        }

        $recordId = AIImageProService::dispatchImageGenerationJob($validated, auth()?->id(), $driver);

        if ($isAjax) {
            return response()->json([
                'success'   => true,
                'message'   => __('Image generation request submitted. Processing will start shortly.'),
                'record_id' => $recordId,
            ]);
        }

        return redirect()
            ->back()
            ->with([
                'message'   => __('Image generation request submitted. Processing will start shortly.'),
                'type'      => 'success',
                'record_id' => $recordId,
            ]);
    }

    /**
     * Get user image statistics including in-progress count and generated images.
     */
    protected function getUserImageStats(Request $request): array
    {
        if (auth()->check()) {
            $userId = auth()->id();
            $query = AiImageProModel::with('user')->where('user_id', $userId);
        } else {
            $userIp = $request->header('cf-connecting-ip') ?? $request->ip();
            $query = AiImageProModel::with('user')->where('guest_ip', $userIp);
        }

        // Get in-progress images
        $inProgressImages = (clone $query)
            ->whereIn('status', [
                AiImageStatusEnum::PENDING->value,
                AiImageStatusEnum::PROCESSING->value,
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        // Get total count of completed images for pagination info
        $completedTotalCount = (clone $query)
            ->where('status', AiImageStatusEnum::COMPLETED->value)
            ->count();

        // Get first page of completed images (20 items)
        $completedImages = (clone $query)
            ->where('status', AiImageStatusEnum::COMPLETED->value)
            ->orderBy('completed_at', 'desc')
            ->take(20)
            ->get();

        $userId = auth()->check() ? auth()->id() : null;
        $guestIp = $userId ? null : ($request->header('cf-connecting-ip') ?? $request->ip());

        $formattedInProgressImages = $inProgressImages->map(function ($image) use ($userId, $guestIp) {
            return $this->formatImageData($image, $userId, $guestIp);
        })->toArray();

        $formattedCompletedImages = $completedImages->map(function ($image) use ($userId, $guestIp) {
            return $this->formatImageData($image, $userId, $guestIp);
        })->toArray();

        return [
            'in_progress_count'  => $inProgressImages->count(),
            'in_progress_images' => $formattedInProgressImages,
            'completed_images'   => $formattedCompletedImages,
            'completed_count'    => $completedTotalCount,
        ];
    }

    /**
     * Generate a private share link for an image
     */
    public function generateShareLink(Request $request): JsonResponse
    {
        $request->validate([
            'image_id' => 'required|string',
        ]);

        // Parse the image ID (format: "id" or "id-index")
        $imageId = explode('-', $request->image_id)[0];

        $image = AiImageProModel::find($imageId);

        if (! $image) {
            return response()->json([
                'success' => false,
                'message' => __('Image not found'),
            ], 404);
        }

        $userId = auth()->check() ? auth()->id() : null;
        $guestIp = $userId ? null : ($request->header('cf-connecting-ip') ?? $request->ip());

        $canPublish = $this->userCanPublishImage($image, $userId, $guestIp);
        // Check if user owns this image
        if (! $canPublish) {
            return response()->json([
                'success' => false,
                'message' => __('You can only share your own images'),
            ], 403);
        }

        // Generate or retrieve share token
        if (! $image->share_token) {
            $image->update([
                'share_token' => Str::random(32),
            ]);
        }

        // Generate share URL
        $shareUrl = route('ai-image-pro.share.view', ['token' => $image->share_token]);

        return response()->json([
            'success'   => true,
            'share_url' => $shareUrl,
            'message'   => __('Share link generated successfully'),
        ]);
    }

    /**
     * View a shared image by token
     */
    public function viewSharedImage(Request $request, string $token): View
    {
        $sharedImage = AiImageProModel::with('user')->where('share_token', $token)->first();

        if (! $sharedImage) {
            abort(404, __('Image not found or link expired'));
        }

        // Increment views
        $sharedImage->incrementViews();

        $userId = auth()->check() ? auth()->id() : null;
        $guestIp = $userId ? null : ($request->header('cf-connecting-ip') ?? $request->ip());

        $image = $this->formatImageData($sharedImage, $userId, $guestIp);
        $image['urls'] = $image['generated_images'];
        $image['title'] = $image['prompt'];

        // Return view with formatted image data
        return view('ai-image-pro::shared-image', compact('image'));
    }

    /**
     * Retrieve public community images with pagination.
     *
     * @throws JsonException
     */
    public function getCommunityImages(Request $request): JsonResponse
    {
        $perPage = 12;
        $page = $request->get('page', 1);
        $filterUserId = $request->get('user_id'); // New parameter

        $query = AiImageProModel::with('user')
            ->where('published', true)
            ->whereNotNull('generated_images')
            ->latest('completed_at');

        // Apply user filter if provided
        if ($filterUserId) {
            $query->where('user_id', $filterUserId);
        }

        $images = $query->paginate($perPage, ['*'], 'page', $page);

        $userId = auth()->check() ? auth()->id() : null;
        $guestIp = $userId ? null : ($request->header('cf-connecting-ip') ?? $request->ip());

        $formattedImages = [];
        foreach ($images as $image) {
            if (! empty($image->generated_images)) {
                $baseData = $this->formatImageData($image, $userId, $guestIp);

                foreach ($image->generated_images as $index => $generatedImage) {
                    $formattedImages[] = array_merge($baseData, [
                        'id'        => $image->id . '-' . $index,
                        'url'       => $generatedImage,
                        'thumbnail' => $this->getThumbnailUrl($generatedImage),
                    ]);
                }
            }
        }

        return response()->json([
            'images'      => $formattedImages,
            'hasMore'     => $images->hasMorePages(),
            'page'        => $images->currentPage(),
            'total'       => $images->lastPage(),
            'filtered_by' => $filterUserId ? 'user' : null,
        ]);
    }

    /**
     * Get user image stats via AJAX.
     */
    public function getImageStats(Request $request): JsonResponse
    {
        $stats = $this->getUserImageStats($request);

        return response()->json($stats);
    }

    /**
     * Backward-compatible endpoint for legacy "images" route.
     */
    public function getImages(Request $request): JsonResponse
    {
        return $this->getCompletedImages($request);
    }

    /**
     * Get paginated completed images for infinite scroll.
     */
    public function getCompletedImages(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 20), 50);
        $page = $request->get('page', 1);

        if (auth()->check()) {
            $userId = auth()->id();
            $query = AiImageProModel::with('user')->where('user_id', $userId);
        } else {
            $userIp = $request->header('cf-connecting-ip') ?? $request->ip();
            $query = AiImageProModel::with('user')->where('guest_ip', $userIp);
        }

        $images = $query
            ->where('status', AiImageStatusEnum::COMPLETED->value)
            ->orderBy('completed_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        $userId = auth()->check() ? auth()->id() : null;
        $guestIp = $userId ? null : ($request->header('cf-connecting-ip') ?? $request->ip());

        $formattedImages = $images->map(function ($image) use ($userId, $guestIp) {
            return $this->formatImageData($image, $userId, $guestIp);
        })->toArray();

        return response()->json([
            'images'   => $formattedImages,
            'has_more' => $images->hasMorePages(),
            'page'     => $images->currentPage(),
            'total'    => $images->total(),
        ]);
    }

    protected function userCanPublishImage(?AiImageProModel $image, ?int $userId, ?string $guestIp): bool
    {
        // Check if authenticated user owns this image
        if ($userId) {
            return $image->user_id === $userId;
        }

        // Check if guest IP matches (for non-authenticated users)
        if ($guestIp) {
            return $image->guest_ip === $guestIp;
        }

        return false;
    }

    public function toggleLike(Request $request): JsonResponse
    {
        $request->validate([
            'image_id' => 'required|string',
        ]);

        // Parse the image ID (format: "id-index")
        $imageId = explode('-', $request->image_id)[0];

        $image = AiImageProModel::find($imageId);

        if (! $image) {
            return response()->json([
                'success' => false,
                'message' => __('Image not found'),
            ], 404);
        }

        // Only allow likes on published images
        if (! $image->published) {
            return response()->json([
                'success' => false,
                'message' => __('Cannot like unpublished images'),
            ], 403);
        }

        $userId = auth()->check() ? auth()->id() : null;
        $guestIp = $userId ? null : ($request->header('cf-connecting-ip') ?? $request->ip());

        $isLiked = $image->toggleLike($userId, $guestIp);

        return response()->json([
            'success'     => true,
            'liked'       => $isLiked,
            'likes_count' => $image->likes_count,
            'message'     => $isLiked ? __('Image added to your likes') : __('Image removed from your likes'),
        ]);
    }

    /**
     * Publish an image to the community.
     */
    public function togglePublish(Request $request): JsonResponse
    {
        $request->validate([
            'image_id' => 'required|string',
        ]);

        // Parse the image ID (format: "id-index")
        $imageId = explode('-', $request->image_id)[0];

        $image = AiImageProModel::find($imageId);

        if (! $image) {
            return response()->json([
                'success' => false,
                'message' => __('Image not found'),
            ], 404);
        }

        // Check if user owns this image
        if ($image->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => __('You can only publish your own images'),
            ], 403);
        }

        // Check if already published
        if ($image->published) {
            return response()->json([
                'success' => false,
                'message' => __('Image is already published'),
            ], 400);
        }

        if ($image->publish_requested_at) {
            return response()->json([
                'success' => false,
                'message' => __('Publish request is already pending review'),
            ], 400);
        }

        $image->update([
            'publish_requested_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => __('Publish request submitted successfully'),
        ]);
    }

    /**
     * Increment view count for an image.
     */
    public function incrementView(Request $request): JsonResponse
    {
        $request->validate([
            'image_id' => 'required|string',
        ]);

        // Parse the image ID (format: "id-index")
        $imageId = explode('-', $request->image_id)[0];

        $image = AiImageProModel::find($imageId);

        if (! $image) {
            return response()->json([
                'success' => false,
                'message' => __('Image not found'),
            ], 404);
        }

        $image->incrementViews();

        return response()->json([
            'success'     => true,
            'views_count' => $image->views_count,
        ]);
    }

    /**
     * Format image data for API responses and views.
     */
    protected function formatImageData(Model|AiImageProModel|null $image, ?int $userId = null, ?string $guestIp = null): array
    {
        $canPublish = $this->userCanPublishImage($image, $userId, $guestIp);
        $imageDate = $image->completed_at ?? $image->created_at;

        $width = $image->image_width;
        $height = $image->image_height;

        if ($width && $height) {
            $cssAspectRatio = $width . '/' . $height;
        } else {
            $dimensions = $this->parseDimensionsFromAspectRatio($image->params['aspect_ratio'] ?? null);
            $cssAspectRatio = $this->parseCssAspectRatio($image->params['aspect_ratio'] ?? null);
            $width = $dimensions['width'];
            $height = $dimensions['height'];
        }

        return [
            'id'               => $image->id,
            'prompt'           => $image->prompt,
            'model'            => $image->model,
            'style'            => $image->params['style'] ?? null,
            'ratio'            => $image->params['aspect_ratio'] ?? null,
            'aspect_ratio'     => $cssAspectRatio,
            'width'            => $width,
            'height'           => $height,
            'negative_prompt'  => $image->params['negative_prompt'] ?? null,
            'generated_images' => $image->generated_images ?? [],
            'thumbnails'       => array_map(fn (string $img): string => $this->getThumbnailUrl($img, 160, 160, 70), $image->generated_images ?? []),
            'date'             => optional($imageDate)->diffForHumans(),
            'date_iso'         => optional($imageDate)->toIso8601String(),
            'can_publish'      => $canPublish,
            'published'        => $image->published ?? false,
            'tags'             => $image->metadata['tags'] ?? [],
            'likes_count'      => $image->likes_count,
            'views_count'      => $image->views_count,
            'is_liked'         => $userId
                ? $image->isLikedBy($userId)
                : $image->isLikedBy($guestIp),
            'user'            => $image->user_id
                ? [
                    'id'      => $image->user_id,
                    'name'    => optional($image->user)->name,
                    'avatar'  => $this->formatAvatarUrl(optional($image->user)->avatar),
                    'initial' => strtoupper(substr(optional($image->user)->name ?? 'U', 0, 1)),
                ]
                : [
                    'id'      => null,
                    'name'    => 'Anonymous',
                    'initial' => 'A',
                    'avatar'  => null,
                ],
        ];
    }

    protected function formatAvatarUrl(?string $avatar): ?string
    {
        if (! $avatar) {
            return null;
        }

        if (strpos($avatar, 'http') === false) {
            return custom_theme_url('/' . $avatar);
        }

        return $avatar;
    }

    /**
     * Parse aspect ratio string and return width/height dimensions.
     *
     * Supports formats:
     * - Pixel dimensions: "1024x1024", "1792x1024"
     * - Ratios: "1:1", "16:9", "21:9"
     * - Named ratios: "square_hd", "portrait_4_3", "landscape_16_9"
     *
     * @return array{width: int, height: int}
     */
    protected function parseDimensionsFromAspectRatio(?string $aspectRatio): array
    {
        $defaultSize = 1024;

        if (empty($aspectRatio)) {
            return ['width' => $defaultSize, 'height' => $defaultSize];
        }

        // Named aspect ratios mapping
        $namedRatios = [
            'square'         => [1, 1],
            'square_hd'      => [1, 1],
            'portrait_4_3'   => [3, 4],
            'portrait_16_9'  => [9, 16],
            'landscape_4_3'  => [4, 3],
            'landscape_16_9' => [16, 9],
            'auto'           => [1, 1],
            'auto_2K'        => [1, 1],
            'auto_4K'        => [1, 1],
        ];

        // Check if it's a named ratio
        if (isset($namedRatios[$aspectRatio])) {
            [$w, $h] = $namedRatios[$aspectRatio];

            return $this->calculateDimensionsFromRatio($w, $h, $defaultSize);
        }

        // Check for pixel dimensions format (e.g., "1024x1024", "1792x1024")
        if (preg_match('/^(\d+)x(\d+)$/i', $aspectRatio, $matches)) {
            return [
                'width'  => (int) $matches[1],
                'height' => (int) $matches[2],
            ];
        }

        // Check for ratio format (e.g., "1:1", "16:9", "21:9")
        if (preg_match('/^(\d+):(\d+)$/', $aspectRatio, $matches)) {
            $w = (int) $matches[1];
            $h = (int) $matches[2];

            return $this->calculateDimensionsFromRatio($w, $h, $defaultSize);
        }

        // Default fallback
        return ['width' => $defaultSize, 'height' => $defaultSize];
    }

    /**
     * Calculate pixel dimensions from a ratio while maintaining the base size.
     *
     * @return array{width: int, height: int}
     */
    protected function calculateDimensionsFromRatio(int $ratioW, int $ratioH, int $baseSize): array
    {
        if ($ratioW >= $ratioH) {
            // Landscape or square
            $width = $baseSize;
            $height = (int) round($baseSize * $ratioH / $ratioW);
        } else {
            // Portrait
            $height = $baseSize;
            $width = (int) round($baseSize * $ratioW / $ratioH);
        }

        return ['width' => $width, 'height' => $height];
    }

    /**
     * Parse aspect ratio param into a CSS-friendly aspect-ratio string (e.g. "16/9", "1/1").
     */
    protected function parseCssAspectRatio(?string $aspectRatio): string
    {
        $namedRatios = [
            'square'         => '1/1',
            'square_hd'      => '1/1',
            'portrait_4_3'   => '3/4',
            'portrait_16_9'  => '9/16',
            'landscape_4_3'  => '4/3',
            'landscape_16_9' => '16/9',
            'auto'           => '1/1',
            'auto_2K'        => '1/1',
            'auto_4K'        => '1/1',
        ];

        if (empty($aspectRatio)) {
            return '1/1';
        }

        if (isset($namedRatios[$aspectRatio])) {
            return $namedRatios[$aspectRatio];
        }

        if (preg_match('/^(\d+)x(\d+)$/i', $aspectRatio, $matches)) {
            return $matches[1] . '/' . $matches[2];
        }

        if (preg_match('/^(\d+):(\d+)$/', $aspectRatio, $matches)) {
            return $matches[1] . '/' . $matches[2];
        }

        return '1/1';
    }

    /**
     * Get user's images for dashboard with pagination and filtering.
     */
    public function getUserImages(Request $request): JsonResponse
    {
        $request->validate([
            'filter'    => 'nullable|in:creations,bookmarks,videos,images',
            'page'      => 'nullable|integer|min:1',
            'favorites' => 'nullable|array',
        ]);

        $perPage = 20;
        $page = $request->get('page', 1);
        $filter = $request->get('filter', 'creations');
        $favorites = $request->get('favorites', []);
        $userId = auth()->id();

        // Base query based on filter type
        if ($filter === 'bookmarks') {
            // For bookmarks, get images that are either:
            // 1. Created by the user, OR
            // 2. Published by other users
            $query = AiImageProModel::with('user')
                ->where('status', AiImageStatusEnum::COMPLETED->value)
                ->whereNotNull('generated_images')
                ->where(function ($q) use ($userId) {
                    $q->where('user_id', $userId)
                        ->orWhere('published', true);
                });
        } elseif ($filter === 'images') {
            // For images tab, show user's own images (filtered to exclude videos)
            $query = AiImageProModel::with('user')
                ->where('user_id', $userId)
                ->where('status', AiImageStatusEnum::COMPLETED->value)
                ->whereNotNull('generated_images');
        } elseif ($filter === 'videos') {
            // For videos tab, show user's own videos
            $query = AiImageProModel::with('user')
                ->where('user_id', $userId)
                ->where('status', AiImageStatusEnum::COMPLETED->value)
                ->whereNotNull('generated_images');
        } else {
            // For creations and other tabs, only show user's own images/videos
            $query = AiImageProModel::with('user')
                ->where('user_id', $userId)
                ->where('status', AiImageStatusEnum::COMPLETED->value)
                ->whereNotNull('generated_images');
        }

        // Apply specific filters
        switch ($filter) {
            case 'bookmarks':
                // Filter by favorite image IDs
                if (! empty($favorites)) {
                    $baseIds = array_unique(array_map(static function ($id) {
                        $idStr = (string) $id;

                        return explode('-', $idStr)[0];
                    }, $favorites));
                    $baseIds = array_filter(array_map('intval', $baseIds));
                    if (! empty($baseIds)) {
                        $query->whereIn('id', $baseIds);
                    } else {
                        // No valid IDs, return empty result
                        $query->whereRaw('1 = 0');
                    }
                } else {
                    // No favorites, return empty result
                    $query->whereRaw('1 = 0');
                }

                break;

            case 'images':
                // User's images - will filter out videos in the loop below
                break;

            case 'videos':
                // User's videos - will filter to only videos in the loop below
                break;

            case 'creations':
            default:
                // All user's creations - no additional filtering
                break;
        }

        $images = $query->orderBy('completed_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        $formattedImages = [];

        $favoritesSet = array_map(static function ($id) {
            return (string) $id;
        }, $favorites);

        $guestIp = $userId ? null : ($request->header('cf-connecting-ip') ?? $request->ip());

        foreach ($images as $image) {
            if (! empty($image->generated_images)) {
                $baseData = $this->formatImageData($image, $userId, $guestIp);

                // Check if image has variations/edits
                $metadata = $image->metadata ?? [];
                $hasEdits = isset($metadata['edits_count']) && $metadata['edits_count'] > 0;
                $hasVariations = isset($metadata['variations_count']) && $metadata['variations_count'] > 0;

                foreach ($image->generated_images as $index => $generatedImage) {
                    $imageId = $image->id . '-' . $index;

                    // Check if this is a video based on file extension
                    $isVideo = $this->isVideoFile($generatedImage);

                    // For videos filter, only include videos
                    if ($filter === 'videos' && ! $isVideo) {
                        continue;
                    }

                    // For images filter, exclude videos
                    if ($filter === 'images' && $isVideo) {
                        continue;
                    }

                    // For bookmarks filter, only include favorited images
                    if ($filter === 'bookmarks') {
                        $isInFavorites = in_array($imageId, $favoritesSet) ||
                            in_array((string) $image->id, $favoritesSet) ||
                            in_array($image->id, $favoritesSet, true);

                        if (! $isInFavorites) {
                            continue;
                        }
                    }

                    $imageData = array_merge($baseData, [
                        'id'        => $imageId,
                        'url'       => $generatedImage,
                        'thumbnail' => $this->getThumbnailUrl($generatedImage),
                        'isVideo'   => $isVideo,
                    ]);

                    // Add edit/variation badges if applicable
                    if ($hasEdits) {
                        $imageData['edits'] = $metadata['edits_count'];
                    }
                    if ($hasVariations) {
                        $imageData['variations'] = $metadata['variations_count'];
                    }

                    $formattedImages[] = $imageData;
                }
            }
        }

        return response()->json([
            'images'  => $formattedImages,
            'hasMore' => $images->hasMorePages(),
            'page'    => $images->currentPage(),
            'total'   => $images->lastPage(),
        ]);
    }

    /**
     * Check if a file URL is a video based on its extension.
     */
    private function isVideoFile(string $url): bool
    {
        $videoExtensions = ['mp4', 'webm', 'mov', 'avi', 'mkv', 'flv', 'wmv', 'm4v'];
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        return in_array($extension, $videoExtensions, true);
    }

    /**
     * Get thumbnail URL for an image.
     */
    private function getThumbnailUrl(string $imageUrl, int $maxWidth = 800, int $maxHeight = 800, int $quality = 80): string
    {
        if ($this->isVideoFile($imageUrl)) {
            return $imageUrl;
        }

        $path = ltrim(parse_url($imageUrl, PHP_URL_PATH), '/');

        return ThumbImage($path, $maxWidth, $maxHeight, $quality);
    }

    /**
     * Enhance a given prompt using AI.
     */
    public function enhancePrompt(Request $request): JsonResponse
    {
        $request->validate([
            'prompt'    => 'nullable|string|max:1000',
            'tool_type' => 'nullable|string|max:255',
        ]);

        $originalPrompt = $request->input('prompt');
        $toolType = $request->input('tool_type', 'Prompt Enhancer');

        try {
            $enhancedPrompt = AIImageProService::enhancePrompt($originalPrompt, $toolType);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('Failed to enhance prompt: ') . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success'         => true,
            'original_prompt' => $originalPrompt,
            'enhanced_prompt' => $enhancedPrompt,
        ]);
    }

    public function generateToolImage(Request $request): JsonResponse
    {
        $request->validate([
            'tool_id'   => 'required|integer',
            'tool_name' => 'required|string',
        ]);

        // Check daily limit for guests
        if (Helper::appIsDemo() || ! auth()->check()) {
            $limitCheck = $this->checkGuestDailyLimit($request, 1);

            if (! $limitCheck['allowed']) {
                return response()->json([
                    'success' => false,
                    'message' => $limitCheck['message'],
                ], 403);
            }
        }

        $toolId = $request->input('tool_id');
        $toolName = $request->input('tool_name');

        // Load tool configuration
        $toolsConfig = AIImageProService::getToolsConfiguration();
        $tool = collect($toolsConfig)->firstWhere('id', $toolId);

        if (! $tool) {
            return response()->json([
                'success' => false,
                'message' => __('Tool not found'),
            ], 404);
        }

        $request->merge($this->getMissingToolSelectDefaults($request, $tool['data']['inputs'] ?? []));

        // Validate tool inputs dynamically
        $validationRules = $this->buildToolValidationRules($tool['data']['inputs']);
        $validated = $request->validate($validationRules);

        // Process file uploads
        $processedData = $this->processToolInputs($validated, $tool['data']['inputs']);

        // Determine which model/engine to use based on tool type
        $modelConfig = $this->getModelForTool(! empty($processedData));

        $driver = auth()->check() ? Entity::driver(EntityEnum::fromSlug($modelConfig['model']))->inputImageCount(1)->calculateCredit() : null;
        if ($driver && ! $driver->hasCreditBalanceForInput()) {
            return response()->json([
                'success' => false,
                'message' => __('You have no credits left. Please consider upgrading your plan.'),
            ], 403);
        }

        // Determine aspect ratio - use fixed_ratio from tool config if set, otherwise user selection
        $aspectRatio = $tool['data']['fixed_ratio'] ?? $processedData['ratio'] ?? '1:1';

        // Create the generation payload
        $payload = [
            'model'        => $modelConfig['model'],
            'engine'       => $modelConfig['engine'],
            'slug'         => $modelConfig['slug'],
            'tool_name'    => $toolName,
            'tool_id'      => $toolId,
            'prompt'       => $this->buildPromptFromToolInputs($toolName, $processedData),
            'aspect_ratio' => $aspectRatio,
            'image_count'  => 1,
        ];

        // Add tool-specific parameters based on tool type
        if (isset($processedData['product_image']) && isset($processedData['model_image'])) {
            $payload['image_reference'] = [$processedData['model_image'], $processedData['product_image']];
        } elseif (isset($processedData['product_image'])) {
            $payload['image_reference'] = $processedData['product_image'];
        }
        if (isset($processedData['user_photo'])) {
            $payload['image_reference'] = $processedData['user_photo'];
        }
        if (isset($processedData['reference_image'])) {
            $payload['style_reference'] = $processedData['reference_image'];
        }

        try {
            $recordId = AIImageProService::dispatchImageGenerationJob($payload, auth()?->id(), $driver);

            // Update the record with tool metadata
            $record = AiImageProModel::find($recordId);
            if ($record) {
                $record->setToolMetadata($toolId, $toolName, $processedData);
            }

            return response()->json([
                'success'   => true,
                'message'   => __('Image generation request submitted. Processing will start shortly.'),
                'record_id' => $recordId,
            ]);
        } catch (Exception $e) {
            Log::error('Tool-based image generation failed', [
                'error'     => $e->getMessage(),
                'tool_id'   => $toolId,
                'tool_name' => $toolName,
            ]);

            return response()->json([
                'success' => false,
                'message' => __('Failed to submit image generation request'),
            ], 500);
        }
    }

    /**
     * Build validation rules from tool input configuration.
     */
    protected function buildToolValidationRules(array $inputs): array
    {
        $rules = [];

        foreach ($inputs as $input) {
            $key = $input['key'];
            $inputRules = [];

            $inputRules[] = ($input['required'] ?? false) ? 'required' : 'nullable';

            switch ($input['type']) {
                case 'file':
                    $inputRules[] = 'file';
                    if (str_contains($input['accept'] ?? '', 'image')) {
                        $inputRules[] = 'image';
                        $inputRules[] = 'mimes:jpeg,jpg,png,webp';
                        $inputRules[] = 'max:25600'; // 25MB
                    }

                    break;

                case 'textarea':
                case 'text':
                    $inputRules[] = 'string';
                    $inputRules[] = 'max:5000';

                    break;

                case 'select':
                    $values = array_column($input['options'] ?? [], 'value');
                    if ($values) {
                        $inputRules[] = 'in:' . implode(',', $values);
                    }

                    break;
            }

            $rules[$key] = $inputRules;
        }

        return $rules;
    }

    /**
     * Process tool inputs including file uploads.
     */
    protected function processToolInputs(array $validated, array $inputsConfig): array
    {
        $processed = [];

        foreach ($inputsConfig as $input) {
            $key = $input['key'];

            if (! isset($validated[$key])) {
                continue;
            }

            if ($input['type'] === 'file' && $validated[$key] instanceof UploadedFile) {
                $processed[$key] = getSelectedOrUploadedFile($validated[$key]);
            } else {
                $processed[$key] = $validated[$key];
            }
        }

        return $processed;
    }

    /**
     * Resolve missing tool select inputs using configuration defaults.
     */
    protected function getMissingToolSelectDefaults(Request $request, array $inputs): array
    {
        $defaults = [];

        foreach ($inputs as $input) {
            if (($input['type'] ?? null) !== 'select') {
                continue;
            }

            $key = $input['key'] ?? null;
            if (! is_string($key) || $key === '' || $request->filled($key)) {
                continue;
            }

            $defaultValue = $this->getToolSelectDefaultValue($input);
            if ($defaultValue !== null && $defaultValue !== '') {
                $defaults[$key] = $defaultValue;
            }
        }

        return $defaults;
    }

    /**
     * Determine default value for a tool select input.
     */
    protected function getToolSelectDefaultValue(array $input): ?string
    {
        $options = $input['options'] ?? [];
        if (! is_array($options) || $options === []) {
            return null;
        }

        foreach ($options as $option) {
            if (! is_array($option)) {
                continue;
            }

            if (($option['selected'] ?? false) && isset($option['value']) && is_string($option['value'])) {
                return $option['value'];
            }
        }

        $firstOption = $options[0] ?? null;
        if (! is_array($firstOption) || ! isset($firstOption['value']) || ! is_string($firstOption['value'])) {
            return null;
        }

        return $firstOption['value'];
    }

    /**
     * Build a prompt from tool inputs using the tool's template configuration.
     */
    protected function buildPromptFromToolInputs(string $toolName, array $data): string
    {
        // Load tool configuration
        $toolsConfig = AIImageProService::getToolsConfiguration();
        $tool = collect($toolsConfig)->firstWhere('name', $toolName);

        if (! $tool || empty($tool['data']['prompt_template'])) {
            // Fallback if no template is defined
            return $data['scene'] ?? $data['details'] ?? 'High quality professional image';
        }

        $template = $tool['data']['prompt_template'];
        $defaults = $tool['data']['prompt_defaults'] ?? [];

        // Merge defaults with actual data
        $mergedData = array_merge($defaults, $data);

        // Replace placeholders in template
        $prompt = preg_replace_callback('/\{(\w+)\}/', static function ($matches) use ($mergedData) {
            $key = $matches[1];

            return $mergedData[$key] ?? $matches[0]; // Keep placeholder if no value found
        }, $template);

        // Clean up any remaining unreplaced placeholders
        $prompt = preg_replace('/\{[\w]+\}/', '', $prompt);

        // Clean up extra spaces and punctuation
        $prompt = preg_replace('/\s+/', ' ', $prompt);
        $prompt = preg_replace('/\.\s*\./', '.', $prompt);

        return trim($prompt);
    }

    /**
     * Get the appropriate model configuration for a tool.
     */
    protected function getModelForTool(bool $hasImage): array
    {
        $preferredEditModel = (string) setting('ai_image_pro_edit_model', EntityEnum::NANO_BANANA->value);

        $model = match ($preferredEditModel) {
            EntityEnum::GROK_IMAGINE_IMAGE->value, EntityEnum::GROK_IMAGINE_IMAGE_EDIT->value => $hasImage ? EntityEnum::GROK_IMAGINE_IMAGE_EDIT : EntityEnum::GROK_IMAGINE_IMAGE,
            EntityEnum::NANO_BANANA_PRO->value, EntityEnum::NANO_BANANA_PRO_EDIT->value     => $hasImage ? EntityEnum::NANO_BANANA_PRO_EDIT : EntityEnum::NANO_BANANA_PRO,
            EntityEnum::NANO_BANANA_2->value, EntityEnum::NANO_BANANA_2_EDIT->value         => $hasImage ? EntityEnum::NANO_BANANA_2_EDIT : EntityEnum::NANO_BANANA_2,
            default                                                                        => $hasImage ? EntityEnum::NANO_BANANA_EDIT : EntityEnum::NANO_BANANA,
        };

        return [
            'model'  => $model->value,
            'engine' => $model->engine()->slug(),
            'slug'   => $model->slug(),
        ];
    }

    /**
     * Display the media library page.
     * Images are loaded via AJAX using getMediaLibraryImages().
     */
    public function viewMediaLibrary(Request $request): View|RedirectResponse
    {
        if ($request->route()?->getName() !== 'dashboard.user.ai-image-pro.media-library') {
            return redirect()->route('dashboard.user.ai-image-pro.media-library');
        }

        $imageStats = $this->getUserImageStats($request);

        return view('ai-image-pro::media-library', compact('imageStats'));
    }

    /**
     * Get media library images via AJAX with filters, search, sort, and pagination.
     */
    public function getMediaLibraryImages(Request $request): JsonResponse
    {
        $request->validate([
            'filter'    => 'nullable|in:assets,bookmarks',
            'sort'      => 'nullable|in:date,popularity,variations,edits',
            'direction' => 'nullable|in:asc,desc',
            'search'    => 'nullable|string|max:255',
            'page'      => 'nullable|integer|min:1',
            'per_page'  => 'nullable|integer|min:1|max:100',
            'favorites' => 'nullable|array',
        ]);

        $userId = auth()->id();
        $guestIp = $userId ? null : ($request->header('cf-connecting-ip') ?? $request->ip());
        $filter = $request->get('filter', 'assets');
        $sort = $request->get('sort', 'date');
        $direction = $request->get('direction', 'desc');
        $search = $request->get('search');
        $perPage = $request->get('per_page', 24);
        $page = $request->get('page', 1);
        $favorites = $request->get('favorites', []);

        $query = $userId
            ? AiImageProModel::with('user')->where('user_id', $userId)
            : AiImageProModel::with('user')->where('guest_ip', $guestIp);

        $query->where('status', AiImageStatusEnum::COMPLETED->value)
            ->whereNotNull('generated_images');

        // Apply filter
        if ($filter === 'bookmarks' && ! empty($favorites)) {
            $baseIds = array_unique(array_map(static function ($id) {
                return explode('-', (string) $id)[0];
            }, $favorites));
            $baseIds = array_filter(array_map('intval', $baseIds));

            if (! empty($baseIds)) {
                $query->whereIn('id', $baseIds);
            } else {
                return response()->json([
                    'images'  => [],
                    'hasMore' => false,
                    'page'    => 1,
                    'total'   => 0,
                ]);
            }
        }

        // Apply search
        if ($search) {
            $query->where('prompt', 'like', '%' . $search . '%');
        }

        // Apply sort with direction
        switch ($sort) {
            case 'popularity':
                $query->orderBy('views_count', $direction)->orderBy('likes_count', $direction);

                break;
            case 'variations':
                $directionSql = $direction === 'asc' ? 'ASC' : 'DESC';
                $query->orderByRaw("JSON_EXTRACT(metadata, '$.variations_count') {$directionSql} NULLS LAST");

                break;
            case 'edits':
                $directionSql = $direction === 'asc' ? 'ASC' : 'DESC';
                $query->orderByRaw("JSON_EXTRACT(metadata, '$.edits_count') {$directionSql} NULLS LAST");

                break;
            case 'date':
            default:
                $directionSql = $direction === 'asc' ? 'ASC' : 'DESC';
                $query->orderByRaw("COALESCE(completed_at, created_at) {$directionSql}")
                    ->orderBy('id', $direction);

                break;
        }

        $images = $query->paginate($perPage, ['*'], 'page', $page);

        $formattedImages = [];
        $favoritesSet = array_map('strval', $favorites);

        foreach ($images as $image) {
            if (! empty($image->generated_images)) {
                $baseData = $this->formatImageData($image, $userId, $guestIp);
                $metadata = $image->metadata ?? [];

                foreach ($image->generated_images as $index => $generatedImage) {
                    $imageId = $image->id . '-' . $index;

                    // For bookmarks filter, only include favorited images
                    if ($filter === 'bookmarks') {
                        $isInFavorites = in_array($imageId, $favoritesSet, true) ||
                            in_array((string) $image->id, $favoritesSet, true);

                        if (! $isInFavorites) {
                            continue;
                        }
                    }

                    $imageData = array_merge($baseData, [
                        'id'        => $imageId,
                        'url'       => $generatedImage,
                        'thumbnail' => $this->getThumbnailUrl($generatedImage),
                    ]);

                    if (isset($metadata['edits_count']) && $metadata['edits_count'] > 0) {
                        $imageData['edits'] = $metadata['edits_count'];
                    }
                    if (isset($metadata['variations_count']) && $metadata['variations_count'] > 0) {
                        $imageData['variations'] = $metadata['variations_count'];
                    }

                    $formattedImages[] = $imageData;
                }
            }
        }

        return response()->json([
            'images'  => $formattedImages,
            'hasMore' => $images->hasMorePages(),
            'page'    => $images->currentPage(),
            'total'   => $images->lastPage(),
        ]);
    }

    /**
     * Delete media library images (bulk or single).
     */
    public function deleteMediaLibraryImages(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'success' => false,
                'message' => __('This feature is disabled in demo mode.'),
            ], 403);
        }

        $request->validate([
            'image_ids'   => 'required|array|min:1',
            'image_ids.*' => 'required|string',
        ]);

        $userId = auth()->id();
        $guestIp = $userId ? null : ($request->header('cf-connecting-ip') ?? $request->ip());
        $imageIds = $request->input('image_ids');

        // Extract base IDs (remove the -index suffix)
        $baseIds = array_unique(array_map(static function ($id) {
            return (int) explode('-', (string) $id)[0];
        }, $imageIds));

        $query = $userId
            ? AiImageProModel::where('user_id', $userId)
            : AiImageProModel::where('guest_ip', $guestIp);

        $deletedCount = $query->whereIn('id', $baseIds)->delete();

        return response()->json([
            'success' => true,
            'message' => __(':count image(s) deleted successfully', ['count' => $deletedCount]),
            'deleted' => $deletedCount,
        ]);
    }

    /**
     * Check if guest user can generate the requested number of images based on daily limit.
     * Uses cache locking to prevent race conditions.
     */
    protected function checkGuestDailyLimit(Request $request, int $requestedImageCount): array
    {
        $dailyGuestLimit = (int) setting('ai_image_pro:guest_daily_limit', 2);

        // If limit is negative, no restrictions
        if ($dailyGuestLimit < 0) {
            return ['allowed' => true];
        }

        // If limit is zero, generation is not allowed for guests
        if ($dailyGuestLimit === 0) {
            return [
                'allowed' => false,
                'message' => __('Image generation is not allowed for guest users. Please create an account to continue.'),
            ];
        }

        $userIp = $request->header('cf-connecting-ip') ?? $request->ip();
        $lockKey = "ai_image_pro_guest_limit_check:{$userIp}";
        $cacheKey = "ai_image_pro_guest_daily_count:{$userIp}:" . now()->toDateString();

        // Acquire lock with 10 second timeout
        $lock = Cache::lock($lockKey, 10);

        try {
            // Wait up to 10 seconds to acquire the lock
            $lock->block(10);

            // Get or calculate today's generated image count
            $todayGeneratedCount = Cache::remember($cacheKey, 3600, function () use ($userIp) {
                $todayRecords = AiImageProModel::where('guest_ip', $userIp)
                    ->whereDate('created_at', now()->toDateString())
                    ->get(['generated_images', 'params']);

                return $todayRecords->sum(function ($record) {
                    // Count actual generated images (completed)
                    $generatedCount = count($record->generated_images ?? []);

                    // If no generated images yet, count the requested amount
                    if ($generatedCount === 0) {
                        $generatedCount = (int) ($record->params['image_count'] ?? 1);
                    }

                    return $generatedCount;
                });
            });

            // Check if user can generate the requested images
            $totalAfterRequest = $todayGeneratedCount + $requestedImageCount;

            if ($totalAfterRequest > $dailyGuestLimit) {
                $remaining = max(0, $dailyGuestLimit - $todayGeneratedCount);

                return [
                    'allowed' => false,
                    'message' => __('Daily limit exceeded. You have generated :count out of :limit images today. You can generate :remaining more image(s). Please create an account to continue.', [
                        'count'     => $todayGeneratedCount,
                        'limit'     => $dailyGuestLimit,
                        'remaining' => $remaining,
                    ]),
                    'current_count' => $todayGeneratedCount,
                    'limit'         => $dailyGuestLimit,
                    'remaining'     => $remaining,
                ];
            }

            // Update cache with new count (optimistic update)
            Cache::put($cacheKey, $totalAfterRequest, 3600);

            return [
                'allowed'       => true,
                'current_count' => $todayGeneratedCount,
                'limit'         => $dailyGuestLimit,
                'remaining'     => $dailyGuestLimit - $totalAfterRequest,
            ];

        } catch (LockTimeoutException $e) {
            Log::warning('Failed to acquire lock for guest limit check', [
                'ip'    => $userIp,
                'error' => $e->getMessage(),
            ]);

            return [
                'allowed' => false,
                'message' => __('Too many requests. Please try again in a moment.'),
            ];
        } finally {
            // Always release the lock
            optional($lock)->release();
        }
    }

    /**
     * Display the realtime image generator page.
     */
    public function realtimeIndex(Request $request): View|RedirectResponse
    {
        if (auth()->check() && ! $request->routeIs('dashboard.user.ai-image-pro.*')) {
            return redirect()->route('dashboard.user.ai-image-pro.realtime');
        }

        $images = $this->getRealtimeImagesQuery($request)
            ->take(20)
            ->get();

        $userId = auth()->check() ? auth()->id() : null;
        $guestIp = $userId ? null : ($request->header('cf-connecting-ip') ?? $request->ip());

        $formattedImages = $images->map(function ($image) use ($userId, $guestIp) {
            $data = $this->formatImageData($image, $userId, $guestIp);
            $data['image_url'] = $data['generated_images'][0] ?? null;

            return $data;
        })->toArray();

        $isCreativeSuiteInstalled = MarketplaceHelper::isRegistered('creative-suite');
        $isAdvancedImageInstalled = MarketplaceHelper::isRegistered('advanced-image');

        return view('ai-image-pro::realtime', compact(
            'formattedImages',
            'isCreativeSuiteInstalled',
            'isAdvancedImageInstalled',
        ));
    }

    /**
     * Display the image editor page.
     */
    public function editIndex(Request $request): View|RedirectResponse
    {
        if (! auth()->check()) {
            return redirect()->route('dashboard.user.ai-image-pro.edit');
        }

        if ((! $request->routeIs('dashboard.user.ai-image-pro.*')) && auth()->check()) {
            return redirect()->route('dashboard.user.ai-image-pro.edit');
        }

        $activeImageModels = AIChatImageService::getActiveImageModels();
        $chat = null;

        if (auth()->check()) {
            $category = OpenaiGeneratorChatCategory::query()
                ->whereNotIn('slug', ['ai_vision', 'ai_webchat', 'ai_pdf'])
                ->where('role', 'default')
                ->first();

            if ($category) {
                $chat = new UserOpenaiChat;
                $chat->user_id = auth()->id();
                $chat->chat_type = 'chatpro-image';
                $chat->openai_chat_category_id = $category?->id;
                $chat->title = __('Image Editor Session');
                $chat->total_credits = 0;
                $chat->total_words = 0;
                $chat->save();

                $message = new UserOpenaiChatMessage;
                $message->user_openai_chat_id = $chat->id;
                $message->user_id = auth()->id();
                $message->response = 'First Initiation';
                $message->output = __('Image editor session started.');
                $message->hash = Str::random(256);
                $message->credits = 0;
                $message->words = 0;
                $message->save();
            }
        }

        return view('ai-image-pro::edit', compact(
            'activeImageModels',
            'chat',
        ));
    }

    /**
     * Handle realtime image generation via AJAX.
     */
    public function generateRealtimeImage(
        GenerateRealtimeImageRequest $request,
        RealtimeGenerationService $service,
    ): JsonResponse {
        if (Helper::appIsDemo()) {
            return response()->json([
                'success' => false,
                'message' => trans('This feature is disabled in demo mode.'),
            ], 403);
        }

        $validated = $request->validated();

        if (Helper::appIsDemo() || ! auth()->check()) {
            $limitCheck = $this->checkGuestDailyLimit($request, 1);

            if (! $limitCheck['allowed']) {
                return response()->json([
                    'success' => false,
                    'message' => $limitCheck['message'],
                ], 403);
            }
        }

        $driver = auth()->check()
            ? Entity::driver(EntityEnum::BLACK_FOREST_LABS_FLUX_1_SCHNELL)->inputImageCount(1)->calculateCredit()
            : null;

        if ($driver && ! $driver->hasCreditBalanceForInput()) {
            return response()->json([
                'success' => false,
                'message' => __('You have no credits left. Please consider upgrading your plan.'),
            ], 403);
        }

        $record = AiImageProModel::query()->create([
            'user_id'  => auth()->id(),
            'guest_ip' => auth()->check() ? null : ($request->header('cf-connecting-ip') ?? $request->ip()),
            'model'    => EntityEnum::BLACK_FOREST_LABS_FLUX_1_SCHNELL->value,
            'engine'   => EntityEnum::BLACK_FOREST_LABS_FLUX_1_SCHNELL->engine()->slug(),
            'prompt'   => $validated['prompt'],
            'params'   => [
                'style'        => $validated['style'] ?? null,
                'aspect_ratio' => '1024x768',
                'image_count'  => 1,
            ],
            'metadata' => ['is_realtime' => true],
            'status'   => AiImageStatusEnum::PENDING,
        ]);

        try {
            $record = $service->generate($record);
        } catch (Exception $e) {
            $record->markAsFailed($e->getMessage());

            if ($request->user()?->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return response()->json([
                'success' => false,
                'message' => __('Failed to generate image'),
            ], 422);
        }

        if ($record->status !== AiImageStatusEnum::COMPLETED) {
            $errorMessage = data_get($record->metadata, 'error', __('Failed to generate image'));

            if ($request->user()?->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                ], 422);
            }

            return response()->json([
                'success' => false,
                'message' => __('Failed to generate image'),
            ], 422);
        }

        $driver?->decreaseCredit();
        Usage::getSingle()->updateImageCounts(1);

        $userId = auth()->check() ? auth()->id() : null;
        $guestIp = $userId ? null : ($request->header('cf-connecting-ip') ?? $request->ip());
        $imageData = $this->formatImageData($record, $userId, $guestIp);
        $imageData['image_url'] = $imageData['generated_images'][0] ?? null;

        return response()->json([
            'success'        => true,
            'message'        => __('Image generated successfully'),
            'data'           => $imageData,
            'formatted_date' => $record->created_at->diffInMinutes() < 1
                ? __('Just now')
                : $record->created_at->diffForHumans(),
        ]);
    }

    /**
     * Get paginated realtime images for the sidebar via AJAX.
     */
    public function getRealtimeImages(Request $request): JsonResponse
    {
        $perPage = 20;
        $page = $request->get('page', 1);

        $images = $this->getRealtimeImagesQuery($request)
            ->paginate($perPage, ['*'], 'page', $page);

        $userId = auth()->check() ? auth()->id() : null;
        $guestIp = $userId ? null : ($request->header('cf-connecting-ip') ?? $request->ip());

        $formattedImages = $images->map(function ($image) use ($userId, $guestIp) {
            $data = $this->formatImageData($image, $userId, $guestIp);
            $data['image_url'] = $data['generated_images'][0] ?? null;

            return $data;
        })->toArray();

        return response()->json([
            'images'   => $formattedImages,
            'has_more' => $images->hasMorePages(),
            'page'     => $images->currentPage(),
            'total'    => $images->total(),
        ]);
    }

    /**
     * Build the base query for realtime images belonging to the current user/guest.
     *
     * @return Builder<AiImageProModel>
     */
    private function getRealtimeImagesQuery(Request $request): Builder
    {
        if (auth()->check()) {
            $query = AiImageProModel::query()->where('user_id', auth()->id());
        } else {
            $userIp = $request->header('cf-connecting-ip') ?? $request->ip();
            $query = AiImageProModel::query()->where('guest_ip', $userIp);
        }

        return $query
            ->where('status', AiImageStatusEnum::COMPLETED->value)
            ->whereNotNull('generated_images')
            ->whereJsonContains('metadata->is_realtime', true)
            ->orderBy('completed_at', 'desc');
    }
}
