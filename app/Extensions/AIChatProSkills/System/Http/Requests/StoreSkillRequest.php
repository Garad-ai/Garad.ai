<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProSkills\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        // If file is present, validate the file upload
        if ($this->hasFile('file')) {
            return [
                'file' => ['required', 'file', 'max:5120', function ($attribute, $value, $fail) {
                    $origExt = strtolower($value->getClientOriginalExtension());
                    $mime = $value->getMimeType();

                    $allowedExts = ['zip', 'md', 'txt', 'markdown', 'skill'];
                    $allowedMimes = ['application/zip', 'application/octet-stream', 'text/plain', 'text/markdown', 'text/x-markdown'];

                    if (! in_array($origExt, $allowedExts) && ! in_array($mime, $allowedMimes)) {
                        $fail(__('Only .zip, .skill, and .md files are accepted.'));
                    }
                }],
            ];
        }

        // Otherwise, validate manual creation fields
        return [
            'name'         => ['required', 'string', 'max:255'],
            'description'  => ['required', 'string', 'max:2000'],
            'instructions' => ['required', 'string', 'max:50000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.max' => __('The skill file must not be larger than 5MB.'),
        ];
    }
}
