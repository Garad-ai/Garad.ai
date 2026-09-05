<?php

namespace App\Extensions\MarketingBot\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'    => 'required|string|max:255',
            'status'  => 'required',
            'user_id' => 'required|integer',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'user_id' => auth()->id(),
            'status'  => $this->request->has('status'),
        ]);
    }
}
