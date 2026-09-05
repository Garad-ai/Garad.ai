<?php

namespace App\Extensions\DiscountManager\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class BannerStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::user()?->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title'            => 'required|string',
            'description'      => 'required|string',
            'active'           => 'sometimes',
            'icon'             => 'sometimes|file|nullable',
            'link'             => 'sometimes|nullable',
            'text_color'       => 'required|string',
            'background_color' => 'required|string',
            'enable_countdown' => 'sometimes',
            'end_date'         => 'sometimes|nullable|date|after:now',
        ];
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'active'           => $this->has('active'),
            'enable_countdown' => $this->has('enable_countdown'),
        ]);
    }
}
