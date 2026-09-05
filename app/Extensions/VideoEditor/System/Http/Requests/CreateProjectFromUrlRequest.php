<?php

declare(strict_types=1);

namespace App\Extensions\VideoEditor\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateProjectFromUrlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'url'         => ['required', 'url', 'max:2048'],
            'title'       => ['nullable', 'string', 'max:200'],
            'duration_ms' => ['nullable', 'integer', 'min:0', 'max:36000000'],
            'width'       => ['nullable', 'integer', 'min:1', 'max:16384'],
            'height'      => ['nullable', 'integer', 'min:1', 'max:16384'],
        ];
    }
}
