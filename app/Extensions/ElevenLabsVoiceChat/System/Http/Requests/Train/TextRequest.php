<?php

namespace App\Extensions\ElevenLabsVoiceChat\System\Http\Requests\Train;

use Illuminate\Foundation\Http\FormRequest;

class TextRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title'   => ['required', 'string'],
            'content' => ['required', 'string'],
        ];
    }
}
