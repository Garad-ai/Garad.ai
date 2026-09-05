<?php

declare(strict_types=1);

namespace App\Extensions\AiCaptions\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAiCaptionsSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && method_exists($user, 'isAdmin') && $user->isAdmin();
    }

    public function rules(): array
    {
        return [
            'ai_captions_api_key'         => ['nullable', 'string', 'max:255'],
            'ai_captions_default_minutes' => ['required', 'integer', 'min:0', 'max:100000'],
        ];
    }
}
