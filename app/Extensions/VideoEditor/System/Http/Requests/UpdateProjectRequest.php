<?php

declare(strict_types=1);

namespace App\Extensions\VideoEditor\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                                      => ['nullable', 'string', 'max:255'],
            'timeline_data'                             => ['nullable', 'array'],
            'timeline_data.tracks'                      => ['required_with:timeline_data', 'array', 'max:50'],
            'timeline_data.tracks.*.id'                 => ['required', 'string', 'max:100'],
            'timeline_data.tracks.*.type'               => ['required', 'string', 'in:video,audio,text,image'],
            'timeline_data.tracks.*.label'              => ['required', 'string', 'max:100'],
            'timeline_data.tracks.*.locked'             => ['boolean'],
            'timeline_data.tracks.*.muted'              => ['boolean'],
            'timeline_data.tracks.*.clips'              => ['array', 'max:200'],
            'timeline_data.tracks.*.clips.*.id'         => ['required', 'string', 'max:100'],
            'timeline_data.tracks.*.clips.*.mediaId'    => ['nullable', 'integer', 'min:1'],
            'timeline_data.tracks.*.clips.*.startTime'  => ['required', 'numeric', 'min:0'],
            'timeline_data.tracks.*.clips.*.endTime'    => ['required', 'numeric', 'min:0'],
            // Sub-row inside a track for stacked/overlapping clips. Without
            // whitelisting it here, $validated strips it and every clip
            // collapses to lane 0 on reload — all stacked on top of each other.
            'timeline_data.tracks.*.clips.*.lane'       => ['nullable', 'integer', 'min:0', 'max:50'],
            'timeline_data.tracks.*.clips.*.trimStart'  => ['nullable', 'numeric', 'min:0'],
            'timeline_data.tracks.*.clips.*.trimEnd'    => ['nullable', 'numeric', 'min:0'],
            'timeline_data.tracks.*.clips.*.x'          => ['nullable', 'numeric'],
            'timeline_data.tracks.*.clips.*.y'          => ['nullable', 'numeric'],
            'timeline_data.tracks.*.clips.*.width'      => ['nullable', 'numeric', 'min:0'],
            'timeline_data.tracks.*.clips.*.height'     => ['nullable', 'numeric', 'min:0'],
            'timeline_data.tracks.*.clips.*.opacity'    => ['nullable', 'numeric', 'min:0', 'max:1'],
            'timeline_data.tracks.*.clips.*.speed'      => ['nullable', 'numeric', 'min:0.1', 'max:10'],
            'timeline_data.tracks.*.clips.*.volume'     => ['nullable', 'numeric', 'min:0', 'max:2'],
            'timeline_data.tracks.*.clips.*.text'       => ['nullable', 'string', 'max:500'],
            'timeline_data.tracks.*.clips.*.color'      => ['nullable', 'string', 'max:30', 'regex:/^[a-zA-Z0-9#(),.% ]+$/'],
            'timeline_data.tracks.*.clips.*.fontSize'   => ['nullable', 'integer', 'min:8', 'max:500'],
            'timeline_data.tracks.*.clips.*.fontFamily' => ['nullable', 'string', 'max:100'],
            'timeline_data.tracks.*.clips.*.fontWeight' => ['nullable', 'string', 'max:10'],
            'timeline_data.version'                     => ['required_with:timeline_data', 'integer'],
            'duration_ms'                               => ['nullable', 'integer', 'min:0'],
            'aspect_ratio'                              => ['nullable', 'string', 'in:16:9,9:16,1:1,4:3'],
            'resolution'                                => ['nullable', 'string', 'in:720p,1080p,4k'],
            'thumbnail'                                 => ['nullable', 'string', 'max:500000'],
        ];
    }
}
