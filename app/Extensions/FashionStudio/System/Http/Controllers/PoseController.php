<?php

namespace App\Extensions\FashionStudio\System\Http\Controllers;

use App\Domains\Entity\Facades\Entity;
use App\Extensions\FashionStudio\System\Enums\ImageStatusEnum;
use App\Extensions\FashionStudio\System\Http\Controllers\Concerns\DispatchesAssetCreation;
use App\Extensions\FashionStudio\System\Models\Pose;
use App\Extensions\FashionStudio\System\Services\FashionStudioFalAIService;
use App\Http\Controllers\Controller;
use App\Models\Usage;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PoseController extends Controller
{
    use DispatchesAssetCreation;

    public function __construct(
        protected FashionStudioFalAIService $falAIService
    ) {}

    /**
     * Load user's poses
     */
    public function loadPoses(Request $request): JsonResponse
    {
        $userId = Auth::id();

        // Get user's uploaded poses (hide failed generations)
        $poses = Pose::where('user_id', $userId)
            ->where('status', '!=', ImageStatusEnum::failed->value)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'id'         => $item->id,
                    'name'       => $item->pose_name,
                    'image_url'  => $item->image_url,
                    'thumbnail'  => ThumbImage($item->image_url),
                    'category'   => $item->pose_category,
                    'exist_type' => $item->exist_type,
                    'status'     => $item->status ?? 'completed',
                    'created_at' => $item->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'poses'   => $poses,
        ]);
    }

    /**
     * Upload pose to Poses table
     */
    public function uploadPose(Request $request): JsonResponse
    {
        $request->validate([
            'pose_image' => 'required|image|mimes:jpeg,png,jpg|max:25600',
            'pose_name'  => 'nullable|string|max:255',
        ]);

        try {
            $userId = Auth::id();
            $file = $request->file('pose_image');

            // Store the file
            $url = processSecureFileUpload($file, 'media/images/u-' . $userId);
            $pose = Pose::create([
                'user_id'       => $userId,
                'pose_name'     => $request->input('pose_name', pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)),
                'pose_type'     => $file->guessExtension() ?? $file->getClientOriginalExtension(),
                'pose_category' => $request->input('pose_category', 'other'),
                'description'   => '',
                'image_url'     => $url,
                'exist_type'    => 'uploaded',
                'status'        => 'completed',
            ]);

            return response()->json([
                'success' => true,
                'message' => __('Pose uploaded successfully'),
                'pose'    => [
                    'id'         => $pose->id,
                    'name'       => $pose->pose_name,
                    'image_url'  => $pose->image_url,
                    'thumbnail'  => ThumbImage($pose->image_url),
                    'category'   => $pose->pose_category,
                    'exist_type' => $pose->exist_type,
                    'status'     => $pose->status,
                    'created_at' => $pose->created_at,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('Failed to upload pose: ' . $e->getMessage()),
            ], 500);
        }
    }

    /**
     * Create AI-generated pose
     */
    public function createPose(Request $request): JsonResponse
    {
        $request->validate([
            'pose_description' => 'required|string|min:10|max:1000',
        ]);

        $lockKey = 'pose-create-' . Auth::id() . '-' . now()->timestamp;

        try {
            if (! Cache::lock($lockKey, 10)->get()) {
                return response()->json([
                    'message' => __('Another generation in progress. Please try again later.'),
                ], 409);
            }
            $resolvedModel = $this->resolveAssetCreationModel();
            $driver = Entity::driver($resolvedModel)->inputImageCount(1)->calculateCredit();

            try {
                $driver->redirectIfNoCreditBalance();
            } catch (Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => __('You do not have enough credits to create a pose.'),
                ], 402);
            }

            $userId = Auth::id();
            $description = $request->input('pose_description');

            // Enhanced prompt for pose generation
            $prompt = "Professional model pose photography: {$description}. Full body pose, clean background, professional studio lighting, high quality, 4K resolution.";

            // Create pose entry with pending status (status will be updated by dispatcher)
            $pose = Pose::create([
                'user_id'       => $userId,
                'pose_name'     => __('AI Generated Pose'),
                'pose_type'     => 'png',
                'pose_category' => 'other',
                'description'   => $description,
                'image_url'     => '/themes/default/assets/img/loading.svg',
                'exist_type'    => 'created',
                'status'        => ImageStatusEnum::pending->value,
            ]);

            $this->dispatchAssetGeneration($pose, $prompt, 'pose', $resolvedModel);

            // Track usage
            Usage::getSingle()->updateImageCounts($driver->calculate());
            $driver->decreaseCredit();

            Cache::lock($lockKey)->release();

            return response()->json([
                'success' => true,
                'message' => __('Pose generation started'),
                'pose'    => [
                    'id'         => $pose->id,
                    'name'       => $pose->pose_name,
                    'image_url'  => '/themes/default/assets/img/loading.svg',
                    'thumbnail'  => '/themes/default/assets/img/loading.svg',
                    'category'   => $pose->pose_category,
                    'exist_type' => $pose->exist_type,
                    'status'     => $pose->status,
                    'created_at' => $pose->created_at,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Pose creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('Failed to create pose: ' . $e->getMessage()),
            ], 500);
        } finally {
            Cache::lock($lockKey)->forceRelease();
        }
    }

    /**
     * Check pose generation status from database (job updates the record)
     */
    public function checkStatus(string $id): JsonResponse
    {
        $pose = Pose::where('user_id', Auth::id())->findOrFail($id);

        $response = [
            'success' => true,
            'status'  => strtolower($pose->status),
        ];

        if ($pose->status === ImageStatusEnum::completed->value) {
            $response['pose'] = [
                'id'         => $pose->id,
                'name'       => $pose->pose_name,
                'image_url'  => $pose->image_url,
                'thumbnail'  => ThumbImage($pose->image_url),
                'category'   => $pose->pose_category,
                'exist_type' => $pose->exist_type,
                'status'     => $pose->status,
                'created_at' => $pose->created_at,
            ];
        } elseif ($pose->status === ImageStatusEnum::failed->value) {
            $response['success'] = false;
            $response['message'] = __('Pose generation failed. Please try again.');
        }

        return response()->json($response);
    }

    /**
     * Delete pose from Poses table
     */
    public function deletePose(Request $request, $id): JsonResponse
    {
        try {
            $userId = Auth::id();

            $pose = Pose::where('id', $id)
                ->where('user_id', $userId)
                ->first();

            if (! $pose) {
                return response()->json([
                    'success' => false,
                    'message' => __('Pose not found or unauthorized'),
                ], 404);
            }

            // Delete the pose
            $pose->delete();

            return response()->json([
                'success' => true,
                'message' => __('Pose deleted successfully'),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('Failed to delete pose: ' . $e->getMessage()),
            ], 500);
        }
    }
}
