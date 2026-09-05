<?php

namespace App\Extensions\SocialMedia\System\Http\Requests;

use App\Extensions\SocialMedia\System\Enums\PlatformEnum;
use App\Extensions\SocialMedia\System\Enums\StatusEnum;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class SocialMediaPostStoreRequest extends FormRequest
{
    public function rules(): array
    {
        $platform = $this->get('social_media_platform');
        $postType = $this->get('post_type', 'post');
        $isStory = $postType === 'story';

        $limit = config('social-media.' . $platform . '.requirements.text.limit');

        $isStoryImagePlatform = $isStory && in_array($platform, [
            PlatformEnum::facebook->value,
            PlatformEnum::instagram->value,
        ], true);

        $imageRule = ($platform === PlatformEnum::instagram->value || $isStoryImagePlatform) ? 'required' : 'sometimes';

        $requiresVideo = in_array($platform, [
            PlatformEnum::tiktok->value,
            PlatformEnum::youtube->value,
            PlatformEnum::youtube_shorts->value,
        ], true);

        $videoRule = $requiresVideo ? 'required' : 'sometimes';

        return [
            'user_id'                  => 'required',
            'company_id'               => 'sometimes|nullable|numeric',
            'campaign_id'              => 'sometimes|nullable|numeric',
            'scheduled_at'             => 'sometimes|',
            'is_repeated'              => 'sometimes',
            'repeat_period'            => 'required_if:is_repeated,1|sometimes',
            'repeat_start_date'        => 'required_if:post_now,0|sometimes',
            'repeat_time'              => 'required_if:post_now,0|sometimes',
            'social_media_platform'    => 'required',
            'post_type'                => 'sometimes|in:post,story',
            'link'                     => 'sometimes',
            'is_personalized_content'  => 'sometimes',
            'tone'                     => 'required',
            'content'                  => 'required|min:1|max:' . (is_numeric($limit) ? $limit : 300),
            'image'                    => $imageRule,
            'images'                   => 'sometimes',
            'images.*'                 => 'nullable',
            'video'                    => $videoRule,
            'selectedUserPlatforms'	   => 'array|required',
            'selectedUserPlatforms.*'  => 'exists:ext_social_media_platforms,id',
            'status'                   => 'sometimes',
        ];
    }

    public function messages(): array
    {
        return [
            'content.required'                  => 'Please enter post content',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->request->get('post_now')) {
            $this->merge([
                'post_now'          => true,
                'scheduled_at'      => now()->format('Y-m-d H:i'),
                'repeat_start_date' => now()->format('Y-m-d'),
                'repeat_time'       => now()->format('H:i'),
                'is_repeated'       => false,
            ]);
        } elseif ($this->request->get('post_now') === '0') {
            $this->merge([
                'scheduled_at'      => Carbon::createFromFormat('m/d/Y', $this->request->get('scheduled_at'))->format('Y-m-d') . ' ' . $this->request->get('repeat_time') . ':00',
                'repeat_start_date' => Carbon::createFromFormat('m/d/Y', $this->request->get('repeat_start_date'))->format('Y-m-d'),
                'is_repeated'       => $this->request->get('is_repeated') === 'true' ? '1' : '0',
            ]);
        }

        $isVideoOnlyPlatform = in_array($this->request->get('social_media_platform'), [
            PlatformEnum::tiktok->value,
            PlatformEnum::youtube->value,
            PlatformEnum::youtube_shorts->value,
        ], true);

        $images = $this->request->get('images');

        if (is_string($images)) {
            $images = json_decode($images, true);
        }

        if ($isVideoOnlyPlatform) {
            $this->merge([
                'images' => null,
                'image'  => null,
            ]);
        } elseif (is_array($images) && count($images) > 0) {
            $this->merge([
                'images' => $images,
                'image'  => $images[0],
            ]);
        }

        $this->merge([
            'user_id'                 => Auth::id(),
            'status'                  => StatusEnum::scheduled->value,
            'is_personalized_content' => $this->request->has('is_personalized_content'),
        ]);
    }
}
