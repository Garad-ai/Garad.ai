<?php

namespace App\Extensions\MegaMenu\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class MegaMenuRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'   => 'required|max:181',
            'slug'   => 'sometimes|max:191',
            'status' => 'required|in:0,1',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug'   => Str::slug($this->name),
            'status' => 1,
        ]);
    }
}
