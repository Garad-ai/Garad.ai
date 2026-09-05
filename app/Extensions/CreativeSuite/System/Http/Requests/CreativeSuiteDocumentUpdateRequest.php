<?php

namespace App\Extensions\CreativeSuite\System\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreativeSuiteDocumentUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'    => ['required', 'string', 'max:255'],
            'preview' => ['sometimes', 'mimes:jpeg,jpg,png,svg', 'max:100000'],
            'payload' => ['sometimes', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->name ?? 'Untitled Document',
        ]);
    }
}
