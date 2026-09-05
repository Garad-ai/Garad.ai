<?php

namespace App\Extensions\CreativeSuite\System\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreativeSuiteAIRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'selected_tool'   => ['required', 'string', 'in:reimagine,remove_background,edit_with_ai'],
            'ai_model'        => ['nullable', 'string'],
            'uploaded_image'  => ['required', 'file', 'image', 'mimes:jpeg,png,jpg'],
            'description'     => ['nullable', 'string', 'max:2000'],
        ];
    }
}
