<?php

namespace App\Extensions\PhoneCallAgent\System\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PhoneCallAgentPhoneNumberAssignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'agent_id'        => ['required', 'string', 'exists:ext_phone_call_agents,uuid'],
            'phone_number_id' => ['required', 'string', 'max:255'],
            'phone_number'    => ['required', 'string', 'max:255'],
        ];
    }
}
