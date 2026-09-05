<?php

declare(strict_types=1);

namespace App\Extensions\FashionStudio\System\Http\Controllers;

use App\Domains\Entity\Enums\EntityEnum;
use App\Extensions\FashionStudio\System\Services\FashionStudioImageModelRegistry;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FashionStudioSettingController extends Controller
{
    /**
     * Get available image-to-video models for Fashion Studio
     */
    private function getVideoModels(): array
    {
        return [
            EntityEnum::VEO_3_1_IMAGE_TO_VIDEO->value      => 'Veo 3.1 Image to Video',
            EntityEnum::VEO_3_1_IMAGE_TO_VIDEO_FAST->value => 'Veo 3.1 Image to Video (Fast)',
        ];
    }

    public function index(Request $request): RedirectResponse|View
    {
        $openAIModelValues = array_values(array_keys(array_filter(
            FashionStudioImageModelRegistry::models(),
            static fn ($meta) => ($meta['provider'] ?? null) === FashionStudioImageModelRegistry::PROVIDER_OPENAI,
        )));

        $imageModels = FashionStudioImageModelRegistry::getModelsForAdminSelect();
        $imageModelEnabled = [];
        foreach (array_keys($imageModels) as $value) {
            $imageModelEnabled[$value] = FashionStudioImageModelRegistry::isModelEnabled($value);
        }

        return view('fashion-studio::admin.settings', [
            'videoModels'       => $this->getVideoModels(),
            'currentVideoModel' => setting('fashion-studio-video-default-model', EntityEnum::VEO_3_1_IMAGE_TO_VIDEO->value),
            'imageModels'       => $imageModels,
            'imageModelEnabled' => $imageModelEnabled,
            'currentImageModel' => FashionStudioImageModelRegistry::getDefaultModel()->value,
            'openAIModelValues' => $openAIModelValues,
            'qualityOptions'    => FashionStudioImageModelRegistry::getOpenAIQualityOptions(),
            'currentQuality'    => FashionStudioImageModelRegistry::getOpenAIQuality(),
            'sizeOptions'       => FashionStudioImageModelRegistry::getOpenAISizeOptions(),
            'currentSize'       => FashionStudioImageModelRegistry::getOpenAISize(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        if (Helper::appIsDemo()) {
            return to_route('dashboard.user.index')->with([
                'status'  => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        $imageModelKeys = array_values(array_filter(
            array_keys(FashionStudioImageModelRegistry::getModelsForAdminSelect()),
            static fn (string $value) => FashionStudioImageModelRegistry::isModelEnabled($value),
        ));

        $data = $request->validate([
            'fashion-studio-video-default-model' => 'required|string',
            'fashion-studio-image-default-model' => 'required|string|in:' . implode(',', $imageModelKeys),
            'fashion-studio-openai-quality'      => 'required|string|in:' . implode(',', FashionStudioImageModelRegistry::OPENAI_QUALITY_VALUES),
            'fashion-studio-openai-size'         => 'required|string|in:' . implode(',', FashionStudioImageModelRegistry::OPENAI_SIZE_VALUES),
        ]);

        setting($data)->save();

        return back()->with([
            'type'    => 'success',
            'message' => trans('Settings updated successfully.'),
        ]);
    }
}
