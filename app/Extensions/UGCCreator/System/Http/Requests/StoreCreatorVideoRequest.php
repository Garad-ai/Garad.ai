<?php

declare(strict_types=1);

namespace App\Extensions\UGCCreator\System\Http\Requests;

use App\Extensions\UGCCreator\System\Services\UGCCreatorRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCreatorVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $enabledModels = array_keys(UGCCreatorRegistry::getEnabledModels());
        $enabledPresetKeys = array_column(UGCCreatorRegistry::getEnabledCameraPresets(), 'key');
        $maxKb = UGCCreatorRegistry::getMaxUploadSizeKb();

        return [
            'title'           => 'nullable|string|max:200',
            'video_details'   => 'required|string|max:6000',
            'camera_preset'   => ['nullable', 'string', Rule::in(array_merge(['auto'], $enabledPresetKeys))],
            'model'           => ['nullable', 'string', Rule::in($enabledModels)],
            'audio_enabled'   => 'sometimes|boolean',
            'product_files'   => 'sometimes|array|max:' . UGCCreatorRegistry::getMaxReferenceImages(),
            'product_files.*' => 'file|image|mimes:jpg,jpeg,png,webp|max:' . $maxKb,
            'character_file'  => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:' . $maxKb],
        ];
    }
}
