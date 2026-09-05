<?php

declare(strict_types=1);

namespace App\Extensions\MarketingBot\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class CsvImportRequest extends FormRequest
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
            'file'                 => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
            'mapping'              => ['sometimes', 'array'],
            'mapping.name'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'mapping.phone'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'mapping.country_code' => ['sometimes', 'nullable', 'string', 'max:255'],
            'mapping.username'     => ['sometimes', 'nullable', 'string', 'max:255'],
            'segment_id'           => ['sometimes', 'nullable', 'integer', 'exists:ext_segments,id'],
            'contact_id'           => ['sometimes', 'nullable', 'integer', 'exists:ext_contacts,id'],
            'group_id'             => ['sometimes', 'nullable', 'integer', 'exists:ext_telegram_groups,id'],
            'include_phones'       => ['sometimes', 'array'],
            'include_phones.*'     => ['sometimes', 'string', 'max:20'],
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

        // Files from the app's Content Manager are stored without extensions.
        // Accept only when the client-provided name has NO extension and the
        // actual MIME detected by PHP finfo is a plain-text/CSV type.
        if ($extension !== '') {
            return false;
        }

        $mime = strtolower($file->getMimeType() ?? '');

        return in_array($mime, ['text/csv', 'text/plain', 'application/csv'], true);
    }
}
