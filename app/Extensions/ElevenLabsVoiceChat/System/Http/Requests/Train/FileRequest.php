<?php

namespace App\Extensions\ElevenLabsVoiceChat\System\Http\Requests\Train;

use Illuminate\Foundation\Http\FormRequest;

class FileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'file'   => 'required|file',
        ];
    }
}
