<?php

namespace App\Extensions\MarketingBot\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactListRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'user_id'      => 'required|integer',
            'name'         => 'required|string|max:255',
            'last_name'    => 'nullable|string|max:255',
            'phone'        => 'required|string|max:255',
            'status'       => 'required',
            'country_code' => 'required|string|max:4',
            'contacts'     => 'sometimes|array',
            'segments'     => 'sometimes|array',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'user_id'  => auth()->id(),
            'status'   => $this->request->has('status'),
            'contacts' => is_array(request('contacts')) ? request('contacts') : [],
            'segments' => is_array(request('segments')) ? request('segments') : [],
        ]);
    }
}
