<?php

namespace App\Extensions\FashionStudio\System\Http\Controllers;

use App\Domains\Entity\Facades\Entity;
use App\Extensions\FashionStudio\System\Enums\ImageStatusEnum;
use App\Extensions\FashionStudio\System\Http\Controllers\Concerns\DispatchesAssetCreation;
use App\Extensions\FashionStudio\System\Models\Wardrobe;
use App\Extensions\FashionStudio\System\Services\FashionStudioFalAIService;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\Usage;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class WardrobeController extends Controller
{
    use DispatchesAssetCreation;

    public function __construct(
        protected FashionStudioFalAIService $falAIService
    ) {}

    public function index(): View
    {
        return view('fashion-studio::wardrobe');
    }

    /**
     * Load user's wardrobe products
     */
    public function loadWardrobe(Request $request): JsonResponse
    {
        $userId = Auth::id();

        // Get user's uploaded products (hide failed generations)
        $products = Wardrobe::where('user_id', $userId)
            ->where('status', '!=', ImageStatusEnum::failed->value)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'id'         => $item->id,
                    'name'       => $item->product_name,
                    'image_url'  => $item->image_url,
                    'thumbnail'  => ThumbImage($item->image_url),
                    'category'   => $item->product_category,
                    'exist_type' => $item->exist_type,
                    'status'     => $item->status ?? 'completed',
                    'created_at' => $item->created_at,
                ];
            });

        return response()->json([
            'success'  => true,
            'products' => $products,
        ]);
    }

    /**
     * Upload product to wardrobe
     */
    public function uploadProduct(Request $request): JsonResponse
    {
        $request->validate([
            'product_image' => 'required|image|mimes:jpeg,png,jpg|max:25600',
            'product_name'  => 'nullable|string|max:255',
        ]);

        try {
            $userId = Auth::id();
            $file = $request->file('product_image');

            // Store the file
            $url = processSecureFileUpload($file, 'media/images/u-' . $userId);
            $product = Wardrobe::create([
                'user_id'          => $userId,
                'product_name'     => $request->input('product_name', pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)),
                'product_type'     => $file->guessExtension() ?? $file->getClientOriginalExtension(),
                'product_category' => $request->input('product_category', 'other'),
                'description'      => '',
                'image_url'        => $url,
                'exist_type'       => 'uploaded',
                'status'           => 'completed',
            ]);

            return response()->json([
                'success' => true,
                'message' => __('Product uploaded successfully'),
                'product' => [
                    'id'         => $product->id,
                    'name'       => $product->product_name,
                    'image_url'  => $product->image_url,
                    'thumbnail'  => ThumbImage($product->image_url),
                    'category'   => $product->product_category,
                    'exist_type' => $product->exist_type,
                    'status'     => $product->status,
                    'created_at' => $product->created_at,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('Failed to upload product: ' . $e->getMessage()),
            ], 500);
        }
    }

    /**
     * Create AI-generated product
     */
    public function createProduct(Request $request): JsonResponse
    {
        $demoLimitResponse = Helper::checkFashionStudioDemoLimit('wardrobe_product', 3);

        if ($demoLimitResponse !== null) {
            return $demoLimitResponse;
        }

        $request->validate([
            'product_description' => 'required|string|min:10|max:1000',
        ]);

        $lockKey = 'product-create-' . Auth::id() . '-' . now()->timestamp;

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
                    'message' => __('You do not have enough credits to create a product.'),
                ], 402);
            }

            $userId = Auth::id();
            $description = $request->input('product_description');

            // Enhanced prompt for product generation
            $prompt = "Professional product photography of: {$description}. High quality, clean white background, well-lit, commercial photography style, detailed, 4K resolution.";

            // Create wardrobe entry with pending status (status will be updated by dispatcher)
            $product = Wardrobe::create([
                'user_id'          => $userId,
                'product_name'     => __('AI Generated Product'),
                'product_type'     => 'png',
                'product_category' => 'other',
                'description'      => $description,
                'image_url'        => '/themes/default/assets/img/loading.svg',
                'exist_type'       => 'created',
                'status'           => ImageStatusEnum::pending->value,
            ]);

            $this->dispatchAssetGeneration($product, $prompt, 'wardrobe', $resolvedModel);

            // Track usage
            Usage::getSingle()->updateImageCounts($driver->calculate());
            $driver->decreaseCredit();

            Cache::lock($lockKey)->release();

            return response()->json([
                'success' => true,
                'message' => __('Product generation started'),
                'product' => [
                    'id'         => $product->id,
                    'name'       => $product->product_name,
                    'image_url'  => '/themes/default/assets/img/loading.svg',
                    'thumbnail'  => '/themes/default/assets/img/loading.svg',
                    'category'   => $product->product_category,
                    'exist_type' => $product->exist_type,
                    'status'     => $product->status,
                    'created_at' => $product->created_at,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Product creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('Failed to create product: ' . $e->getMessage()),
            ], 500);
        } finally {
            Cache::lock($lockKey)->forceRelease();
        }
    }

    /**
     * Check product generation status from database (job updates the record)
     */
    public function checkStatus(string $id): JsonResponse
    {
        $product = Wardrobe::where('user_id', Auth::id())->findOrFail($id);

        $response = [
            'success' => true,
            'status'  => strtolower($product->status),
        ];

        if ($product->status === ImageStatusEnum::completed->value) {
            $response['product'] = [
                'id'         => $product->id,
                'name'       => $product->product_name,
                'image_url'  => $product->image_url,
                'thumbnail'  => ThumbImage($product->image_url),
                'category'   => $product->product_category,
                'exist_type' => $product->exist_type,
                'status'     => $product->status,
                'created_at' => $product->created_at,
            ];
        } elseif ($product->status === ImageStatusEnum::failed->value) {
            $response['success'] = false;
            $response['message'] = __('Product generation failed. Please try again.');
        }

        return response()->json($response);
    }

    /**
     * Delete product from wardrobe
     */
    public function deleteProduct(Request $request, $id): JsonResponse
    {
        try {
            $userId = Auth::id();

            $product = Wardrobe::where('id', $id)
                ->where('user_id', $userId)
                ->first();

            if (! $product) {
                return response()->json([
                    'success' => false,
                    'message' => __('Product not found or unauthorized'),
                ], 404);
            }

            // Delete the product
            $product->delete();

            return response()->json([
                'success' => true,
                'message' => __('Product deleted successfully'),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('Failed to delete product: ' . $e->getMessage()),
            ], 500);
        }
    }
}
