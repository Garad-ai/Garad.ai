<?php

namespace App\Extensions\MarketingBot\System\Http\Requests\Training;

use App\Extensions\MarketingBot\System\Enums\EmbeddingTypeEnum;
use Illuminate\Foundation\Http\FormRequest;

class DataRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id'         => 'required',
            'type'       => ['sometimes', 'nullable', 'in:' . implode(',', EmbeddingTypeEnum::toArray())],
        ];
    }
}
