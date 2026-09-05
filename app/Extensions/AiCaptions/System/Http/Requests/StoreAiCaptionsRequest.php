<?php

declare(strict_types=1);

namespace App\Extensions\AiCaptions\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAiCaptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'template_id'       => ['required', 'string', 'max:100'],
            'title'             => ['nullable', 'string', 'max:255'],
            'existing_video_id' => ['nullable', 'integer', 'exists:ai_captions_videos,id'],
            'video_file'        => [
                'nullable',
                'required_without:existing_video_id',
                'file',
                'mimetypes:video/mp4,video/quicktime',
                'max:51200',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'template_id.required'        => __('Please pick a caption template.'),
            'video_file.required_without' => __('Please upload a video or pick a previously uploaded one.'),
            'video_file.mimetypes'        => __('Video must be an MP4 or MOV file.'),
            'video_file.max'              => __('The video file must not exceed 50MB.'),
        ];
    }
}
