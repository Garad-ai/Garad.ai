<?php

namespace App\Extensions\MarketingBot\System\Http\Requests\Training;

use Illuminate\Foundation\Http\FormRequest;

class FileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id'     => 'required',
            'file'   => 'required|file',
        ];
    }
}
