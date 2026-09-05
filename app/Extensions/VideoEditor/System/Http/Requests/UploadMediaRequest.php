<?php

declare(strict_types=1);

namespace App\Extensions\VideoEditor\System\Http\Requests;

use App\Extensions\VideoEditor\System\Models\VideoEditorProject;
use Illuminate\Foundation\Http\FormRequest;

class UploadMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // If project_id is provided, verify the user owns it
        if ($this->filled('project_id')) {
            $project = VideoEditorProject::find($this->input('project_id'));

            return $project && $project->user_id === $this->user()->id;
        }

        return true;
    }

    public function rules(): array
    {
        $maxSize = config('video-editor.max_upload_size_mb', 500) * 1024;

        $allowedMimes = array_merge(
            config('video-editor.allowed_video_types', ['mp4', 'webm', 'mov', 'avi']),
            config('video-editor.allowed_audio_types', ['mp3', 'wav', 'ogg', 'aac']),
            config('video-editor.allowed_image_types', ['jpg', 'jpeg', 'png', 'gif', 'webp']),
        );

        $allowedMimeTypes = [
            // Video
            'video/mp4', 'video/webm', 'video/quicktime', 'video/x-msvideo', 'video/avi',
            // Audio
            'audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/x-wav', 'audio/ogg', 'audio/aac', 'audio/mp4',
            // Image
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        ];

        return [
            'file'       => [
                'required',
                'file',
                'max:' . $maxSize,
                'mimes:' . implode(',', $allowedMimes),
                'mimetypes:' . implode(',', $allowedMimeTypes),
            ],
            'project_id' => ['nullable', 'integer', 'exists:video_editor_projects,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.mimes'     => __('Unsupported file type. Allowed: mp4, webm, mov, avi, mp3, wav, ogg, aac, jpg, png, gif, webp.'),
            'file.mimetypes' => __('The file content does not match an allowed media type.'),
            'file.max'       => __('File is too large. Maximum size: :max KB.'),
        ];
    }
}
