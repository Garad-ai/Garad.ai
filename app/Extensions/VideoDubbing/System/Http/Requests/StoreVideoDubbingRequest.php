<?php

declare(strict_types=1);

namespace App\Extensions\VideoDubbing\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVideoDubbingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    protected function prepareForValidation(): void
    {
        $languages = $this->input('target_languages');

        if (is_string($languages)) {
            $decoded = json_decode($languages, true);
            $languages = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', $languages)));
        }

        if (! $languages && $this->filled('target_language')) {
            $languages = [$this->input('target_language')];
        }

        $this->merge([
            'target_languages' => is_array($languages) ? array_values(array_unique(array_filter($languages))) : [],
        ]);
    }

    public function rules(): array
    {
        return [
            'source_type'           => 'required|string|in:youtube,tiktok,upload,url',
            'video_url'             => 'nullable|required_unless:source_type,upload|url',
            'video_file'            => 'nullable|required_if:source_type,upload|file|mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/webm|max:512000',
            'target_languages'      => 'required|array|min:1|max:20',
            'target_languages.*'    => 'required|string|distinct',
            'source_language'       => 'nullable|string',
            'speaker_num'           => 'nullable|integer|min:0|max:50',
            'title'                 => 'nullable|string|max:255',
            'mode'                  => 'nullable|string|in:fast,quality',
            'translate_audio_only'  => 'nullable|boolean',
            'highest_resolution'    => 'nullable|boolean',
            'drop_background_audio' => 'nullable|boolean',
            'use_profanity_filter'  => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'video_url.required_unless'  => __('A video URL is required when using YouTube or TikTok source.'),
            'video_file.required_if'     => __('A video file is required when uploading.'),
            'video_file.max'             => __('The video file must not exceed 500MB.'),
            'video_file.mimetypes'       => __('The video file must be an MP4, MOV, AVI, or WebM file.'),
            'target_languages.required'  => __('Please select at least one target language.'),
            'target_languages.min'       => __('Please select at least one target language.'),
            'target_languages.max'       => __('You can select up to :max languages.'),
        ];
    }
}
