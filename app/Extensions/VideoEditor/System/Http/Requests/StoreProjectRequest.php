<?php

declare(strict_types=1);

namespace App\Extensions\VideoEditor\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'max:255'],
            'aspect_ratio' => ['nullable', 'string', 'in:16:9,9:16,1:1,4:3'],
            'resolution'   => ['nullable', 'string', 'in:720p,1080p,4k'],
            'fps'          => ['nullable', 'integer', 'in:24,30,60'],
        ];
    }
}
