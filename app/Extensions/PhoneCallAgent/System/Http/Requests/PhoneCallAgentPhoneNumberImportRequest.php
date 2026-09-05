<?php

namespace App\Extensions\PhoneCallAgent\System\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PhoneCallAgentPhoneNumberImportRequest extends FormRequest
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
            'agent_id'           => ['required', 'string', 'exists:ext_phone_call_agents,uuid'],
            'phone_number'       => ['required', 'string', 'regex:/^\+[1-9]\d{6,14}$/'],
            'label'              => ['sometimes', 'nullable', 'string', 'max:255'],
            'import_type'        => ['required', 'string', 'in:twilio,sip_trunk,exotel'],

            // Twilio source
            'twilio_account_sid' => ['required_if:import_type,twilio', 'nullable', 'string', 'max:255'],
            'twilio_auth_token'  => ['required_if:import_type,twilio', 'nullable', 'string', 'max:255'],

            // SIP trunk source
            'sip_address'        => ['required_if:import_type,sip_trunk', 'nullable', 'string', 'max:255'],
            'sip_transport'      => ['sometimes', 'nullable', 'string', 'in:auto,udp,tcp,tls'],
            'sip_username'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'sip_password'       => ['sometimes', 'nullable', 'string', 'max:255'],

            // Exotel source
            'exotel_account_sid' => ['required_if:import_type,exotel', 'nullable', 'string', 'max:255'],
            'exotel_api_key'     => ['required_if:import_type,exotel', 'nullable', 'string', 'max:255'],
            'exotel_api_token'   => ['required_if:import_type,exotel', 'nullable', 'string', 'max:255'],
            'exotel_subdomain'   => ['required_if:import_type,exotel', 'nullable', 'string', 'in:api.in.exotel.com,api.exotel.com'],
            'exotel_app_id'      => ['required_if:import_type,exotel', 'nullable', 'string', 'max:255'],
            'exotel_applet_url'  => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Default to the Twilio source so the Twilio-provider connect flow needs no extra field.
        if (! $this->filled('import_type')) {
            $this->merge(['import_type' => 'twilio']);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone_number.regex' => __('Enter a valid phone number in E.164 format, e.g. +14155552671.'),
        ];
    }
}
