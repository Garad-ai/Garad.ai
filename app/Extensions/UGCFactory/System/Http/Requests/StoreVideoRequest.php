<?php

declare(strict_types=1);

namespace App\Extensions\UGCFactory\System\Http\Requests;

use App\Extensions\UGCFactory\System\Models\UGCFactoryVideo;
use App\Extensions\UGCFactory\System\Services\UGCFactoryRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $audioSources = [
            UGCFactoryVideo::SOURCE_TTS,
            UGCFactoryVideo::SOURCE_UPLOAD,
            UGCFactoryVideo::SOURCE_RECORD,
        ];

        return [
            'title'           => 'required|string|max:200',
            'actor_id'        => 'required|integer|min:0',
            'actor_type'      => 'required|in:preset,upload,generated',
            'preset_key'      => 'nullable|string|max:120|required_if:actor_type,preset',
            'audio_source'    => ['required', Rule::in($audioSources)],
            'script'          => 'required_if:audio_source,tts|nullable|string|max:6000',
            'voice_provider'  => 'required_if:audio_source,tts|nullable|in:openai,elevenlabs',
            'voice_id'        => 'required_if:audio_source,tts|nullable|string|max:120',
            'voice_language'  => 'nullable|string|max:32',
            // fal.ai VEED Fabric accepts mp3/wav/m4a/aac/ogg. Recorded clips
            // are always transcoded to mp3 client-side; manual uploads may be
            // any of the supported formats. Validate by detected MIME (not
            // extension) so libmagic's inconsistent mp4-container detection
            // doesn't reject valid audio files.
            'audio'           => 'required_unless:audio_source,tts|nullable|file|mimetypes:audio/mpeg,audio/mp3,audio/wav,audio/wave,audio/x-wav,audio/mp4,audio/x-m4a,audio/aac,audio/x-aac,audio/aacp,audio/ogg,application/ogg,video/mp4,application/mp4|max:' . (UGCFactoryRegistry::getMaxUploadSizeKb() * 4),
        ];
    }
}
