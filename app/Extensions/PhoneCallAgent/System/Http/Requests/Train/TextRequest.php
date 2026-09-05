<?php

namespace App\Extensions\PhoneCallAgent\System\Http\Requests\Train;

use App\Extensions\PhoneCallAgent\System\Models\ExtPhoneCallAgent;
use Illuminate\Foundation\Http\FormRequest;

class TextRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id'      => 'required|exists:' . (new ExtPhoneCallAgent)->getTable() . ',id',
            'title'   => ['required', 'string'],
            'content' => ['required', 'string'],
        ];
    }
}
