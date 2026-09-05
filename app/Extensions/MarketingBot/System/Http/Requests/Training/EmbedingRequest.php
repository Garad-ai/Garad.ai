<?php

namespace App\Extensions\MarketingBot\System\Http\Requests\Training;

use Illuminate\Foundation\Http\FormRequest;

class EmbedingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id'     => 'required',
            'data'   => 'required|array',
            'data.*' => 'required',
        ];
    }
}
