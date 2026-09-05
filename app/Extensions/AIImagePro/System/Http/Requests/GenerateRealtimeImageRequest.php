<?php

namespace App\Extensions\AIImagePro\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateRealtimeImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:5000'],
            'style'  => ['nullable', 'string', 'max:255'],
        ];
    }
}
