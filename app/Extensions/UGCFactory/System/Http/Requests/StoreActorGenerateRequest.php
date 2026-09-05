<?php

declare(strict_types=1);

namespace App\Extensions\UGCFactory\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreActorGenerateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name'   => 'required|string|max:120',
            'prompt' => 'required|string|min:8|max:4000',
        ];
    }
}
