<?php

namespace App\Extensions\ElevenLabsVoiceChat\System\Http\Requests\Train;

use App\Extensions\ElevenLabsVoiceChat\System\Models\VoiceChatBotTrain;
use Illuminate\Foundation\Http\FormRequest;

class TrainRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'data'   => 'required|array',
            'data.*' => 'required|exists:' . (new VoiceChatBotTrain)->getTable() . ',id',
        ];
    }
}
