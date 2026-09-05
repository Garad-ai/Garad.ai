<?php

namespace App\Extensions\ElevenLabsVoiceChat\System\Http\Requests\Train;

use App\Extensions\ElevenLabsVoiceChat\System\Enum\TrainTypeEnum;
use App\Extensions\ElevenLabsVoiceChat\System\Models\VoiceChatBot;
use Illuminate\Foundation\Http\FormRequest;

class DataRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id'         => 'required|exists:' . (new VoiceChatBot)->getTable() . ',id',
            'type'       => ['sometimes', 'nullable', 'in:' . implode(',', TrainTypeEnum::toArray())],
        ];
    }
}
