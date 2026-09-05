<?php

namespace App\Extensions\PhoneCallAgent\System\Http\Requests;

use App\Extensions\PhoneCallAgent\System\Enums\BookingProviderEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PhoneCallAgentStoreRequest extends FormRequest
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
            'user_id'               => ['required', 'integer', 'exists:users,id'],
            'uuid'                  => ['required', 'string'],
            'title'                 => ['required', 'string'],
            'welcome_message'       => ['sometimes', 'nullable', 'string'],
            'instructions'          => ['sometimes', 'nullable', 'string'],
            'language'              => ['sometimes', 'nullable', 'string'],
            'provider'              => ['sometimes', 'nullable', 'string', 'in:twilio,elevenlabs'],
            'voice_id'              => ['sometimes', 'nullable', 'string'],
            'ai_model'              => ['sometimes', 'nullable', 'string'],
            'booking_enabled'       => ['sometimes', 'boolean'],
            'booking_provider'      => ['sometimes', 'nullable', Rule::enum(BookingProviderEnum::class)],
            'booking_event_type_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'booking_api_key'       => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'uuid'    => Str::uuid()->toString(),
            'user_id' => Auth::id(),
        ]);
    }
}
