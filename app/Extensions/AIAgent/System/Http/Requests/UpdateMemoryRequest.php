<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMemoryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'memory' => ['required', 'string', 'max:1000'],
        ];
    }
}
