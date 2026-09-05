<?php

namespace App\Extensions\FashionStudio\System\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VirtualTryOnController extends BaseFashionStudioController
{
    private array $uploadedPaths = [];

    public function index(): View
    {
        return view('fashion-studio::virtual-try-on');
    }

    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'model_image'   => 'required|image|max:5120',
            'clothes_image' => 'required|image|max:5120',
        ]);

        $lockKey = $request->lock_key ?? 'request-' . now()->timestamp . '-' . auth()->id();

        // Upload images
        $modelPath = $this->uploadFile($request->file('model_image'));
        $clothesPath = $this->uploadFile($request->file('clothes_image'));

        // Store for later use in getImageUrls()
        $this->uploadedPaths = [
            'model'   => url($modelPath),
            'clothes' => url($clothesPath),
        ];

        return $this->processGeneration($lockKey, [
            'model_image_path'   => url($modelPath),
            'clothes_image_path' => url($clothesPath),
        ]);
    }

    protected function getGenerationTitle(): string
    {
        return __('Virtual Try-On Generation');
    }

    protected function getSlugSuffix(): string
    {
        return 'tryon';
    }

    protected function getPrompt(): string
    {
        return 'Apply the clothes from the second image onto the person in the first image, ensuring a natural and realistic fit. Maintain the original pose and background of the person while seamlessly integrating the clothing.';
    }

    protected function getImageUrls(): array
    {
        return [
            $this->uploadedPaths['model'],
            $this->uploadedPaths['clothes'],
        ];
    }

    protected function getResponseKey(): string
    {
        return 'tryon';
    }

    protected function getDemoLimitFeature(): string
    {
        return 'virtual_tryon';
    }
}
