<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Http\Requests;

use App\Extensions\AIAgent\System\Enums\ChannelEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Enum;

class StoreChannelRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'type'        => ['required', new Enum(ChannelEnum::class)],
            'credentials' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['user_id' => Auth::id()]);
    }
}
