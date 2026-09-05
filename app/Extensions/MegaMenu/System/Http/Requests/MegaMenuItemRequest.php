<?php

namespace App\Extensions\MegaMenu\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MegaMenuItemRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'mega_menu_id' => ['required', 'integer', 'exists:ext_mega_menus,id'],
            'label'        => ['required', 'string', 'max:255'],
            'link'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'type'         => ['required', 'string', 'max:255'],
            'description'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'space'		      => ['sometimes', 'nullable', 'numeric'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'mega_menu_id' => $this->route('mega_menu')->id,
        ]);
    }
}
