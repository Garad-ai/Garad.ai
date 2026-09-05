<?php

namespace App\Extensions\PhoneCallAgent\System\Http\Requests\Train;

use App\Extensions\PhoneCallAgent\System\Enums\TrainTypeEnum;
use App\Extensions\PhoneCallAgent\System\Models\ExtPhoneCallAgent;
use Illuminate\Foundation\Http\FormRequest;

class DataRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id'   => 'required|exists:' . (new ExtPhoneCallAgent)->getTable() . ',id',
            'type' => ['sometimes', 'nullable', 'in:' . implode(',', TrainTypeEnum::toArray())],
        ];
    }
}
