<?php

namespace App\Extensions\FashionStudio\System\Http\Controllers;

use App\Extensions\FashionStudio\System\Enums\ImageStatusEnum;
use App\Extensions\FashionStudio\System\Models\Background;
use App\Extensions\FashionStudio\System\Models\FashionModel;
use App\Extensions\FashionStudio\System\Models\Pose;
use App\Extensions\FashionStudio\System\Models\Wardrobe;
use App\Helpers\Classes\Helper;
use App\Models\UserOpenai;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PhotoShootController extends BaseFashionStudioController
{
    private array $generationData = [];

    public function index(): View
    {
        return view('fashion-studio::photoshoots.index', [
            'numImages' => $this->getNumImages(),
        ]);
    }

    public function myPhotoshoots(): View
    {
        return view('fashion-studio::photoshoots.my');
    }

    public function loadImages(Request $request): JsonResponse
    {
        $perPage = 100;
        $page = $request->get('page', 1);
        $userId = Auth::id();
        $type = $request->get('type', 'all');

        // Build query based on type filter
        $query = UserOpenai::where('user_id', $userId)
            ->where('is_fashion_studio', true)
            ->orderBy('created_at', 'desc');

        if ($type === 'videos') {
            // For videos, show FS-VIDEO responses (both processing and completed)
            $query->where('response', 'FS-VIDEO')
                ->whereIn('status', [ImageStatusEnum::completed->value, ImageStatusEnum::processing->value]);
        } elseif ($type === 'images') {
            // For images only, show completed FS responses (not videos)
            $query->where('response', 'FS')
                ->where('status', ImageStatusEnum::completed->value)
                ->where('output', '!=', null);
        } else {
            // For 'all', show both completed images and videos (including processing videos)
            $query->where(function ($q) {
                $q->where(function ($subQ) {
                    // Completed images
                    $subQ->where('response', 'FS')
                        ->where('status', ImageStatusEnum::completed->value)
                        ->where('output', '!=', null);
                })->orWhere(function ($subQ) {
                    // Videos (completed or processing)
                    $subQ->where('response', 'FS-VIDEO')
                        ->whereIn('status', [ImageStatusEnum::completed->value, ImageStatusEnum::processing->value]);
                });
            });
        }

        $allImages = $query->get();

        $now = now();
        $today = $now->copy()->startOfDay();
        $yesterday = $now->copy()->subDay()->startOfDay();

        $thumbnails = [];

        foreach ($allImages as $image) {
            $isVideo = false;
            $isImage = false;
            $isProcessing = $image->status === ImageStatusEnum::processing->value;

            // Check if it's a video by response type or file extension
            if ($image->response === 'FS-VIDEO') {
                $isVideo = true;
            } elseif (! empty($image->output)) {
                $extension = strtolower(pathinfo($image->output, PATHINFO_EXTENSION));
                $isVideo = in_array($extension, ['mp4', 'mov', 'avi', 'webm', 'mkv']);
                $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
            }

            if (! $isVideo && ! $isImage) {
                $isImage = true;
            }

            if ($type === 'images' && ! $isImage) {
                continue;
            }
            if ($type === 'videos' && ! $isVideo) {
                continue;
            }

            $thumbnailUrl = $isProcessing ? '/themes/default/assets/img/loading.svg' : ThumbImage($image->output);
            $imageWithThumbnail = $image->toArray();
            $imageWithThumbnail['url'] = $isProcessing ? '/themes/default/assets/img/loading.svg' : $image->output;
            $imageWithThumbnail['thumbnail'] = $thumbnailUrl;

            $createdAt = $image->created_at;

            $imageWithThumbnail['is_today'] = $createdAt->greaterThanOrEqualTo($today) && $createdAt->lessThan($now->copy()->addDay()->startOfDay());
            $imageWithThumbnail['is_yesterday'] = $createdAt->greaterThanOrEqualTo($yesterday) && $createdAt->lessThan($today);
            $imageWithThumbnail['is_older'] = $createdAt->lessThan($yesterday);
            $imageWithThumbnail['format_date'] = $image->created_at->diffForHumans();
            $imageWithThumbnail['is_image'] = $isImage;
            $imageWithThumbnail['is_video'] = $isVideo;
            $imageWithThumbnail['is_processing'] = $isProcessing;

            $thumbnails[] = $imageWithThumbnail;
        }

        $total = count($thumbnails);
        $thumbnails = array_slice($thumbnails, ($page - 1) * $perPage, $perPage);

        return response()->json([
            'images'  => $thumbnails,
            'hasMore' => ($page * $perPage) < $total,
            'page'    => (int) $page,
            'total'   => (int) ceil($total / $perPage),
        ]);
    }

    public function cropImage(Request $request): JsonResponse
    {
        $request->validate([
            'image_id'     => 'required|integer',
            'image_data'   => 'required|string',
            'width'        => 'nullable|numeric',
            'height'       => 'nullable|numeric',
            'aspect_ratio' => 'nullable|numeric',
        ]);

        $userId = Auth::id();
        $imageId = $request->input('image_id');

        // Find the original image
        $userOpenai = UserOpenai::where('id', $imageId)
            ->where('user_id', $userId)
            ->where('is_fashion_studio', true)
            ->first();

        if (! $userOpenai) {
            return response()->json(['error' => __('Image not found.')], 404);
        }

        // Decode the base64 image data
        $imageData = $request->input('image_data');

        // Remove the data URL prefix if present
        if (Str::startsWith($imageData, 'data:image')) {
            $imageData = preg_replace('/^data:image\/\w+;base64,/', '', $imageData);
        }

        $decodedImage = base64_decode($imageData);

        if ($decodedImage === false) {
            return response()->json(['error' => __('Invalid image data.')], 400);
        }

        // Generate unique filename
        $extension = 'png';
        $filename = 'cropped_' . Str::random(20) . '_' . time() . '.' . $extension;

        // Determine storage based on settings
        $imageStorage = Helper::settingTwo('ai_image_storage');

        try {
            if ($imageStorage === 'r2') {
                Storage::disk('r2')->put($filename, $decodedImage);
                $newUrl = Storage::disk('r2')->url($filename);
            } elseif ($imageStorage === 's3') {
                Storage::disk('s3')->put($filename, $decodedImage);
                $newUrl = Storage::disk('s3')->url($filename);
            } else {
                // Default to public storage
                Storage::disk('public')->put($filename, $decodedImage);
                $newUrl = '/uploads/' . $filename;
            }

            // Create a new record for the cropped image
            $croppedImage = $userOpenai->replicate();
            $croppedImage->output = $newUrl;
            $croppedImage->created_at = now();
            $croppedImage->updated_at = now();

            // Store the cropped image dimensions and aspect ratio in payload
            $width = $request->input('width');
            $height = $request->input('height');
            $aspectRatio = $request->input('aspect_ratio');

            $existingPayload = $croppedImage->payload;
            if (is_string($existingPayload)) {
                $existingPayload = json_decode($existingPayload, true) ?? [];
            }
            $existingPayload = is_array($existingPayload) ? $existingPayload : [];

            $croppedImage->payload = array_merge($existingPayload, [
                'cropped_width'  => $width,
                'cropped_height' => $height,
                'aspect_ratio'   => $aspectRatio,
            ]);

            $croppedImage->save();

            return response()->json([
                'success'      => true,
                'message'      => __('Image cropped successfully.'),
                'image_id'     => $croppedImage->id,
                'url'          => $newUrl,
                'thumbnail'    => ThumbImage($newUrl),
                'width'        => $width,
                'height'       => $height,
                'aspect_ratio' => $aspectRatio,
            ]);
        } catch (Exception $e) {
            Log::error('Crop image error: ' . $e->getMessage());

            return response()->json(['error' => __('Failed to save cropped image.')], 500);
        }
    }

    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'products'   => 'required|array|min:1|max:3',
            'model'      => 'nullable',
            'pose'       => 'nullable',
            'background' => 'nullable',
        ]);

        $lockKey = $request->lock_key ?? 'photoshoot-' . now()->timestamp . '-' . auth()->id();

        // Get actual image URLs and descriptions
        $productUrls = $this->getProductUrls($request->products);
        $modelUrl = $this->getModelUrl($request->model);
        $modelName = $this->getModelName($request->model);
        $backgroundUrl = $this->getBackgroundUrl($request->background);
        $backgroundName = $this->getBackgroundName($request->background);

        // Get BOTH pose URL and description
        $poseUrl = $this->getPoseUrl($request->pose);
        $poseDescription = $this->getPoseDescription($request->pose);
        $isUserPose = $this->isUserPose($request->pose);

        // Get user settings for resolution and ratio
        $userSettings = $this->getUserSettings();
        $imageSize = $userSettings->getImageSize();

        // Store the generation data for use in other methods
        $this->generationData = [
            'products'          => $request->products,
            'product_urls'      => $productUrls,
            'model'             => $request->model,
            'model_url'         => $modelUrl,
            'model_name'        => $modelName,
            'pose'              => $request->pose,
            'pose_url'          => $poseUrl,
            'pose_description'  => $poseDescription,
            'is_user_pose'      => $isUserPose,
            'background'        => $request->background,
            'background_url'    => $backgroundUrl,
            'background_name'   => $backgroundName,
            'resolution'        => $userSettings->resolution,
            'ratio'             => $userSettings->ratio,
            'image_width'       => $imageSize['width'] ?? null,
            'image_height'      => $imageSize['height'] ?? null,
        ];

        return $this->processGeneration($lockKey, $this->generationData);
    }

    protected function getProductUrls(array $productIds): array
    {
        $urls = [];
        $products = Wardrobe::whereIn('id', $productIds)
            ->where('user_id', Auth::id())
            ->get();

        foreach ($products as $product) {
            if ($product->image_url) {
                $urls[] = url($product->image_url);
            }
        }

        return $urls;
    }

    protected function getModelUrl(?string $modelId): ?string
    {
        if (! $modelId) {
            return $this->getRandomStaticModelUrl();
        }

        if (str_starts_with($modelId, 'user-')) {
            $actualId = str_replace('user-', '', $modelId);
            $model = FashionModel::where('id', $actualId)
                ->where('user_id', Auth::id())
                ->first();

            if ($model && $model->image_url) {
                return url($model->image_url);
            }
        }

        return $this->getStaticModelUrl($modelId);
    }

    protected function getModelName(?string $modelId): string
    {
        if (! $modelId) {
            $randomId = $this->getRandomStaticModelId();

            return $this->getStaticModelName($randomId);
        }

        if (str_starts_with($modelId, 'user-')) {
            $actualId = str_replace('user-', '', $modelId);
            $model = FashionModel::where('id', $actualId)
                ->where('user_id', Auth::id())
                ->first();

            if ($model && $model->model_name) {
                return $model->model_name;
            }
        }

        return $this->getStaticModelName($modelId);
    }

    protected function getStaticModelName(string $modelId): string
    {
        $staticModelNames = [
            '1'  => 'Ethan',
            '2'  => 'Mia',
            '3'  => 'Sophie',
            '4'  => 'Ella',
            '5'  => 'Olivia',
            '6'  => 'Chloe',
            '7'  => 'Emma',
            '8'  => 'Lucas',
            '9'  => 'Liam',
            '10' => 'Noah',
            '11' => 'Oliver',
            '12' => 'Sanchez',
        ];

        return $staticModelNames[$modelId] ?? 'Professional Model';
    }

    protected function getStaticModelUrl(string $modelId): ?string
    {
        $staticModels = [
            '1'  => url('vendor/fashion-studio/images/models/ethan.png'),
            '2'  => url('vendor/fashion-studio/images/models/mia.png'),
            '3'  => url('vendor/fashion-studio/images/models/sophie.png'),
            '4'  => url('vendor/fashion-studio/images/models/ella.png'),
            '5'  => url('vendor/fashion-studio/images/models/olivia.png'),
            '6'  => url('vendor/fashion-studio/images/models/chloe.png'),
            '7'  => url('vendor/fashion-studio/images/models/emma.png'),
            '8'  => url('vendor/fashion-studio/images/models/lucas.png'),
            '9'  => url('vendor/fashion-studio/images/models/liam.png'),
            '10' => url('vendor/fashion-studio/images/models/noah.png'),
            '11' => url('vendor/fashion-studio/images/models/oliver.png'),
            '12' => url('vendor/fashion-studio/images/models/sanchez.png'),
        ];

        return $staticModels[$modelId] ?? null;
    }

    protected function getRandomStaticModelId(): string
    {
        $staticModelIds = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'];

        return $staticModelIds[array_rand($staticModelIds)];
    }

    protected function getRandomStaticModelUrl(): string
    {
        $randomId = $this->getRandomStaticModelId();

        return $this->getStaticModelUrl($randomId);
    }

    /**
     * Check if the pose is a user-uploaded pose
     */
    protected function isUserPose(?string $poseId): bool
    {
        if (! $poseId) {
            return false;
        }

        return str_starts_with($poseId, 'user-');
    }

    /**
     * Get the pose image URL (for user-uploaded poses)
     */
    protected function getPoseUrl(?string $poseId): ?string
    {
        if (! $poseId || ! str_starts_with($poseId, 'user-')) {
            return null; // Static poses don't need URLs, they use descriptions
        }

        $actualId = str_replace('user-', '', $poseId);
        $pose = Pose::where('id', $actualId)
            ->where('user_id', Auth::id())
            ->first();

        if ($pose && $pose->image_url) {
            return url($pose->image_url);
        }

        return null;
    }

    protected function getPoseDescription(?string $poseId): string
    {
        if (! $poseId) {
            return $this->getRandomStaticPoseDescription();
        }

        if (str_starts_with($poseId, 'user-')) {
            $actualId = str_replace('user-', '', $poseId);
            $pose = Pose::where('id', $actualId)
                ->where('user_id', Auth::id())
                ->first();

            if ($pose && $pose->pose_name) {
                return $pose->pose_name;
            }
        }

        return $this->getStaticPoseDescription($poseId);
    }

    protected function getStaticPoseDescription(string $poseId): string
    {
        $staticPoses = [
            '1'  => 'Standing with hand in pockets',
            '2'  => 'Standing with hands in pockets',
            '3'  => 'Standing with hands behind back',
            '4'  => 'Sitting on stool',
            '5'  => 'Leaning against wall',
            '6'  => 'Kneeling pose',
            '7'  => 'Side profile standing',
            '8'  => 'Walking forward',
            '9'  => 'Neutral standing with arms down',
            '10' => 'Spinning or twirl motion',
            '11' => 'Natural relaxed pose',
            '12' => 'Adjusting hair with arms up',
        ];

        return $staticPoses[$poseId] ?? 'Natural professional pose';
    }

    protected function getRandomStaticPoseDescription(): string
    {
        $staticPoseIds = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'];
        $randomId = $staticPoseIds[array_rand($staticPoseIds)];

        return $this->getStaticPoseDescription($randomId);
    }

    protected function getBackgroundUrl(?string $backgroundId): ?string
    {
        if (! $backgroundId) {
            return $this->getRandomStaticBackgroundUrl();
        }

        if (str_starts_with($backgroundId, 'user-')) {
            $actualId = str_replace('user-', '', $backgroundId);
            $background = Background::where('id', $actualId)
                ->where('user_id', Auth::id())
                ->first();

            if ($background && $background->image_url) {
                return url($background->image_url);
            }
        }

        return $this->getStaticBackgroundUrl($backgroundId);
    }

    protected function getBackgroundName(?string $backgroundId): string
    {
        if (! $backgroundId) {
            $randomId = $this->getRandomStaticBackgroundId();

            return $this->getStaticBackgroundName($randomId);
        }

        if (str_starts_with($backgroundId, 'user-')) {
            $actualId = str_replace('user-', '', $backgroundId);
            $background = Background::where('id', $actualId)
                ->where('user_id', Auth::id())
                ->first();

            if ($background && $background->background_name) {
                return $background->background_name;
            }
        }

        return $this->getStaticBackgroundName($backgroundId);
    }

    protected function getStaticBackgroundName(string $backgroundId): string
    {
        $staticBackgroundNames = [
            '1'  => 'Serene',
            '2'  => 'Beach',
            '3'  => 'Ella',
            '4'  => 'Shadow',
            '5'  => 'Dark Noir',
            '6'  => 'NYC',
            '7'  => 'European City',
            '8'  => 'Cozy',
            '9'  => 'Floral',
            '10' => 'Navy',
            '11' => 'Light Brown',
            '12' => 'Pinky',
            '13' => 'Brown',
            '14' => 'Minimal',
            '15' => 'Red Bloom',
        ];

        return $staticBackgroundNames[$backgroundId] ?? 'Professional Background';
    }

    protected function getStaticBackgroundUrl(string $backgroundId): ?string
    {
        $staticBackgrounds = [
            '1'  => url('vendor/fashion-studio/images/bgs/serene.png'),
            '2'  => url('vendor/fashion-studio/images/bgs/beach.png'),
            '3'  => url('vendor/fashion-studio/images/bgs/ella.png'),
            '4'  => url('vendor/fashion-studio/images/bgs/shadow.png'),
            '5'  => url('vendor/fashion-studio/images/bgs/dark-noir.png'),
            '6'  => url('vendor/fashion-studio/images/bgs/nyc.png'),
            '7'  => url('vendor/fashion-studio/images/bgs/european-city.png'),
            '8'  => url('vendor/fashion-studio/images/bgs/cozy.png'),
            '9'  => url('vendor/fashion-studio/images/bgs/floral.png'),
            '10' => url('vendor/fashion-studio/images/bgs/navy.png'),
            '11' => url('vendor/fashion-studio/images/bgs/light-brown.png'),
            '12' => url('vendor/fashion-studio/images/bgs/pinky.png'),
            '13' => url('vendor/fashion-studio/images/bgs/brown.png'),
            '14' => url('vendor/fashion-studio/images/bgs/minimal.png'),
            '15' => url('vendor/fashion-studio/images/bgs/red-bloom.png'),
        ];

        return $staticBackgrounds[$backgroundId] ?? null;
    }

    protected function getRandomStaticBackgroundId(): string
    {
        $staticBackgroundIds = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12', '13', '14', '15'];

        return $staticBackgroundIds[array_rand($staticBackgroundIds)];
    }

    protected function getRandomStaticBackgroundUrl(): string
    {
        $randomId = $this->getRandomStaticBackgroundId();

        return $this->getStaticBackgroundUrl($randomId);
    }

    protected function getGenerationTitle(): string
    {
        return __('Photo Shoot Generation');
    }

    protected function getSlugSuffix(): string
    {
        return 'photo-shoot';
    }

    protected function getPrompt(): string
    {
        $payload = $this->generationData;

        $prompt = 'Generate a photorealistic fashion image. Use only the provided reference images: the first image is the model (preserve this person exactly), then the product/clothing images (the model must wear these exact items), then pose reference if provided, then background. ';

        if (! empty($payload['product_urls'])) {
            $count = count($payload['product_urls']);
            $prompt .= "The model must wear all {$count} provided clothing item" . ($count > 1 ? 's' : '') . ' exactly as shown in the reference images. ';
        }

        if ($payload['is_user_pose']) {
            $prompt .= 'Replicate the exact pose from the pose reference image in every output. ';
        } elseif (! empty($payload['pose_description'])) {
            $prompt .= "Pose: {$payload['pose_description']}. ";
        }

        $prompt .= 'Requirements: ';
        $prompt .= 'Natural skin texture and body proportions. ';
        $prompt .= 'Realistic fabric draping and clothing fit. ';
        $prompt .= 'Seamless integration of model with background. ';
        $prompt .= 'Professional lighting with natural shadows. ';
        $prompt .= 'High-resolution commercial photography quality. ';
        $prompt .= 'No distortions, artificial artifacts, or unnatural elements. ';
        $prompt .= 'Strict consistency with all provided reference images: same model, same pose, same outfit, same background.';

        return $prompt;
    }

    protected function getImageUrls(): array
    {
        $urls = [];

        // 1. Model image (always first)
        if (! empty($this->generationData['model_url'])) {
            $urls[] = $this->generationData['model_url'];
        }

        // 2. Product images
        if (! empty($this->generationData['product_urls'])) {
            $urls = array_merge($urls, $this->generationData['product_urls']);
        }

        // 3. Pose image (ONLY for user-uploaded poses)
        if (! empty($this->generationData['pose_url']) && $this->generationData['is_user_pose']) {
            $urls[] = $this->generationData['pose_url'];
        }

        // 4. Background image (always last)
        if (! empty($this->generationData['background_url'])) {
            $urls[] = $this->generationData['background_url'];
        }

        return $urls;
    }

    protected function getResponseKey(): string
    {
        return 'photoshoot';
    }

    protected function getNumImages(): int
    {
        return $this->getUserSettings()->num_images;
    }

    protected function getDemoLimitFeature(): string
    {
        return 'photoshoot';
    }

    /**
     * Remove an image from the user's photoshoot gallery
     */
    public function removeImage(Request $request): JsonResponse
    {
        $request->validate([
            'image_id' => 'required|integer',
        ]);

        try {
            $record = UserOpenai::where('user_id', Auth::id())
                ->where('is_fashion_studio', true)
                ->findOrFail($request->input('image_id'));

            if ($record->output) {
                $relativePath = Str::after($record->output, '/uploads/');
                Storage::disk('public')->delete($relativePath);
            }

            $record->delete();

            return response()->json([
                'success' => true,
                'message' => __('Image removed successfully'),
            ]);

        } catch (Exception $e) {
            Log::error(__('Failed to remove image'), [
                'id'    => $request->input('image_id'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('Failed to remove image'),
            ], 500);
        }
    }
}
