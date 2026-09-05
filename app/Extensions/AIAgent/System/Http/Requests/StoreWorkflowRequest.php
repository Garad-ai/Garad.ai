<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Http\Requests;

use App\Extensions\AIAgent\System\Enums\TriggerTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Enum;

class StoreWorkflowRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'                => ['required', 'string', 'max:255'],
            'description'         => ['nullable', 'string'],
            'role'                => ['nullable', 'string', 'max:255'],
            'trigger_type'        => ['required', new Enum(TriggerTypeEnum::class)],
            'trigger_config'      => ['nullable', 'array'],
            'actions'             => ['nullable', 'array'],
            'actions.*.type'      => ['required_with:actions', 'string'],
            'actions.*.config'    => ['nullable', 'array'],
            'actions.*.config.*'  => ['nullable'],
            'steps'               => ['nullable', 'array'],
            'steps.*.id'          => ['nullable', 'string'],
            'steps.*.type'        => ['required_with:steps', 'string'],
            'steps.*.app'         => ['nullable', 'string'],
            'steps.*.action'      => ['nullable', 'string'],
            'steps.*.config'      => ['nullable', 'array'],
            'steps.*.config.*'    => ['nullable'],
            'steps.*.order'       => ['nullable', 'integer'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['user_id' => Auth::id()]);
    }
}
