<?php

namespace App\Extensions\AIImagePro\System\Http\Requests;

use App\Extensions\AIImagePro\System\Services\AIImageProService;
use Illuminate\Foundation\Http\FormRequest;

class GenerateAIImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $model = $this->input('model');
        $rules = AIImageProService::getValidationRulesFor($model);

        // Allow optional extras
        $rules['engine'] = ['nullable', 'string'];
        $rules['slug'] = ['nullable', 'string'];
        $rules['style_id'] = ['nullable', 'string'];
        $rules['style_reference'] = ['nullable'];

        return $rules;
    }
}
