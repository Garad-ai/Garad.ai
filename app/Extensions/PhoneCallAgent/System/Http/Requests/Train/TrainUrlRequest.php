<?php

namespace App\Extensions\PhoneCallAgent\System\Http\Requests\Train;

use App\Extensions\PhoneCallAgent\System\Models\ExtPhoneCallAgent;
use Illuminate\Foundation\Http\FormRequest;

class TrainUrlRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id'     => 'required|exists:' . (new ExtPhoneCallAgent)->getTable() . ',id',
            'url'    => ['required', 'url'],
            'single' => ['required', 'in:1,0'],
        ];
    }
}
