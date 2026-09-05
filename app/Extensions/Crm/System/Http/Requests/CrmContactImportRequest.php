<?php

declare(strict_types=1);

namespace App\Extensions\Crm\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class CrmContactImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file'               => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
            'mapping'            => ['sometimes', 'array'],
            'mapping.first_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'mapping.last_name'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'mapping.email'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'mapping.phone'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'mapping.job_title'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'mapping.company'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'mapping.status'     => ['sometimes', 'nullable', 'string', 'max:255'],
            'default_status'     => ['sometimes', 'nullable', 'in:active,inactive'],
            'include_indices'    => ['sometimes', 'array'],
            'include_indices.*'  => ['sometimes', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Please select a CSV file to import.',
            'file.file'     => 'The upload must be a file.',
            'file.mimes'    => 'Only CSV files are allowed.',
            'file.max'      => 'The CSV file must not exceed 2MB.',
        ];
    }

    /**
     * Validates the uploaded file is a CSV by checking extension or detected MIME type.
     * Accepts files without extension (e.g. uploaded via the app's Content Manager)
     * by falling back to PHP finfo MIME detection.
     */
    public function isCsvFile(): bool
    {
        /** @var UploadedFile|null $file */
        $file = $this->file('file');

        if ($file === null) {
            return false;
        }

        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'csv') {
            return true;
        }

        if ($extension !== '') {
            return false;
        }

        $mime = strtolower($file->getMimeType() ?? '');

        return in_array($mime, ['text/csv', 'text/plain', 'application/csv'], true);
    }
}
