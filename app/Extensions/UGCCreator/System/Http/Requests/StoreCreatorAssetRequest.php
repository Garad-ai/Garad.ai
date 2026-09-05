<?php

declare(strict_types=1);

namespace App\Extensions\UGCCreator\System\Http\Requests;

use App\Extensions\UGCCreator\System\Models\UGCCreatorAsset;
use App\Extensions\UGCCreator\System\Services\UGCCreatorRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCreatorAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'kind'  => ['required', Rule::in(UGCCreatorAsset::KINDS)],
            'title' => 'nullable|string|max:160',
            'image' => 'required|file|image|mimes:jpg,jpeg,png,webp|max:' . UGCCreatorRegistry::getMaxUploadSizeKb(),
        ];
    }
}
