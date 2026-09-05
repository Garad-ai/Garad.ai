<?php

namespace App\Extensions\MarketingBot\System\Http\Requests\Training;

use Illuminate\Foundation\Http\FormRequest;

class QaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id'         => 'required',
            'question'   => ['required', 'string'],
            'answer'     => ['required', 'string'],
        ];
    }
}
