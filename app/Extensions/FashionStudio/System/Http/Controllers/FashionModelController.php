<?php

namespace App\Extensions\FashionStudio\System\Http\Controllers;

use App\Domains\Entity\Facades\Entity;
use App\Extensions\FashionStudio\System\Enums\ImageStatusEnum;
use App\Extensions\FashionStudio\System\Http\Controllers\Concerns\DispatchesAssetCreation;
use App\Extensions\FashionStudio\System\Models\FashionModel;
use App\Extensions\FashionStudio\System\Services\FashionStudioFalAIService;
use App\Http\Controllers\Controller;
use App\Models\Usage;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FashionModelController extends Controller
{
    use DispatchesAssetCreation;

    public function __construct(
        protected FashionStudioFalAIService $falAIService
    ) {}

    /**
     * Load user's models
     */
    public function loadModels(Request $request): JsonResponse
    {
        $userId = Auth::id();

        // Get user's uploaded models (hide failed generations)
        $fashionModels = FashionModel::where('user_id', $userId)
            ->where('status', '!=', ImageStatusEnum::failed->value)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'id'             => $item->id,
                    'model_name'     => $item->model_name,
                    'name'           => $item->model_name,
                    'gender'         => $item->model_gender,
                    'model_gender'   => $item->model_gender,
                    'image_url'      => $item->image_url,
                    'thumbnail'      => ThumbImage($item->image_url),
                    'model_category' => $item->model_category,
                    'category'       => $item->model_category,
                    'exist_type'     => $item->exist_type,
                    'status'         => $item->status ?? 'completed',
                    'created_at'     => $item->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'models'  => $fashionModels,
        ]);
    }

    /**
     * Upload model to Models table
     */
    public function uploadModel(Request $request): JsonResponse
    {
        $request->validate([
            'model_image'    => 'required|image|mimes:jpeg,png,jpg|max:25600',
            'model_name'     => 'required|string|max:255',
            'model_gender'   => 'required|in:Male,Female',
            'model_category' => 'nullable|string|max:255',
        ]);

        try {
            $userId = Auth::id();
            $file = $request->file('model_image');

            // Store the file
            $url = processSecureFileUpload($file, 'media/images/u-' . $userId);
            $fashionModel = FashionModel::create([
                'user_id'        => $userId,
                'model_name'     => $request->input('model_name'),
                'model_gender'   => $request->input('model_gender'),
                'model_type'     => $file->guessExtension() ?? $file->getClientOriginalExtension(),
                'model_category' => $request->input('model_gender'),
                'description'    => '',
                'image_url'      => $url,
                'exist_type'     => 'uploaded',
                'status'         => 'completed',
            ]);

            return response()->json([
                'success' => true,
                'message' => __('Model uploaded successfully'),
                'model'   => [
                    'id'             => $fashionModel->id,
                    'model_name'     => $fashionModel->model_name,
                    'name'           => $fashionModel->model_name,
                    'gender'         => $fashionModel->model_gender,
                    'image_url'      => $fashionModel->image_url,
                    'thumbnail'      => ThumbImage($fashionModel->image_url),
                    'model_category' => $fashionModel->model_category,
                    'category'       => $fashionModel->model_category,
                    'exist_type'     => $fashionModel->exist_type,
                    'status'         => $fashionModel->status,
                    'created_at'     => $fashionModel->created_at,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('Failed to upload model: ' . $e->getMessage()),
            ], 500);
        }
    }

    /**
     * Create AI-generated model
     */
    public function createModel(Request $request): JsonResponse
    {
        $request->validate([
            'model_description' => 'required|string|min:10|max:1000',
        ]);

        $lockKey = 'model-create-' . Auth::id() . '-' . now()->timestamp;

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
                    'message' => __('You do not have enough credits to create a model.'),
                ], 402);
            }

            $userId = Auth::id();
            $description = $request->input('model_description');

            // Enhanced prompt for model generation
            $prompt = "Professional fashion model portrait: model, {$description}. Full body shot, clean background, professional studio lighting, high quality, 4K resolution, fashion photography.";

            // Create model entry with pending status (status will be updated by dispatcher)
            $fashionModel = FashionModel::create([
                'user_id'        => $userId,
                'model_name'     => __('AI Generated Model'),
                'model_gender'   => 'Unspecified',
                'model_type'     => 'png',
                'model_category' => 'Unspecified',
                'description'    => $description,
                'image_url'      => '/themes/default/assets/img/loading.svg',
                'exist_type'     => 'created',
                'status'         => ImageStatusEnum::pending->value,
            ]);

            $this->dispatchAssetGeneration($fashionModel, $prompt, 'fashion_model', $resolvedModel);

            // Track usage
            Usage::getSingle()->updateImageCounts($driver->calculate());
            $driver->decreaseCredit();

            Cache::lock($lockKey)->release();

            return response()->json([
                'success' => true,
                'message' => __('Model generation started'),
                'model'   => [
                    'id'             => $fashionModel->id,
                    'model_name'     => $fashionModel->model_name,
                    'name'           => $fashionModel->model_name,
                    'gender'         => $fashionModel->model_gender,
                    'image_url'      => '/themes/default/assets/img/loading.svg',
                    'thumbnail'      => '/themes/default/assets/img/loading.svg',
                    'model_category' => $fashionModel->model_category,
                    'category'       => $fashionModel->model_category,
                    'exist_type'     => $fashionModel->exist_type,
                    'status'         => $fashionModel->status,
                    'created_at'     => $fashionModel->created_at,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Model creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('Failed to create model: ' . $e->getMessage()),
            ], 500);
        } finally {
            Cache::lock($lockKey)->forceRelease();
        }
    }

    /**
     * Check model generation status from database (job updates the record)
     */
    public function checkStatus(string $id): JsonResponse
    {
        $fashionModel = FashionModel::where('user_id', Auth::id())->findOrFail($id);

        $response = [
            'success' => true,
            'status'  => strtolower($fashionModel->status),
        ];

        if ($fashionModel->status === ImageStatusEnum::completed->value) {
            $response['model'] = [
                'id'             => $fashionModel->id,
                'model_name'     => $fashionModel->model_name,
                'name'           => $fashionModel->model_name,
                'gender'         => $fashionModel->model_gender,
                'image_url'      => $fashionModel->image_url,
                'thumbnail'      => ThumbImage($fashionModel->image_url),
                'model_category' => $fashionModel->model_category,
                'category'       => $fashionModel->model_category,
                'exist_type'     => $fashionModel->exist_type,
                'status'         => $fashionModel->status,
                'created_at'     => $fashionModel->created_at,
            ];
        } elseif ($fashionModel->status === ImageStatusEnum::failed->value) {
            $response['success'] = false;
            $response['message'] = __('Model generation failed. Please try again.');
        }

        return response()->json($response);
    }

    /**
     * Delete model from Models table
     */
    public function deleteModel(Request $request, $id): JsonResponse
    {
        try {
            $userId = Auth::id();

            $fashionModel = FashionModel::where('id', $id)
                ->where('user_id', $userId)
                ->first();

            if (! $fashionModel) {
                return response()->json([
                    'success' => false,
                    'message' => __('Model not found or unauthorized'),
                ], 404);
            }

            // Delete the model
            $fashionModel->delete();

            return response()->json([
                'success' => true,
                'message' => __('Model deleted successfully'),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('Failed to delete model: ' . $e->getMessage()),
            ], 500);
        }
    }
}
