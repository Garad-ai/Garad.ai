<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProSkills\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportGithubSkillRequest extends FormRequest
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
        return [
            'url' => ['required', 'url', 'regex:#^https://github\.com/[^/]+/[^/]+#'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'url.required' => __('A GitHub URL is required.'),
            'url.url'      => __('Please provide a valid URL.'),
            'url.regex'    => __('Please provide a valid GitHub repository URL (e.g., https://github.com/owner/repo).'),
        ];
    }
}
