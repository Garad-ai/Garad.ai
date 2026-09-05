<?php

declare(strict_types=1);

namespace App\Extensions\VideoEditor\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExportProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', 'exists:video_editor_projects,id'],
        ];
    }
}
