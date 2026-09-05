@extends('panel.layout.settings')
@section('title', __('AI Chat Pro Smart Image Settings'))
@section('titlebar_actions', '')

@section('settings')
    <form
        method="post"
        action="{{ route('dashboard.admin.ai-chat-pro-smart-image.settings.update') }}"
    >
        @csrf
        @method('PUT')

        <x-form-step
            step="1"
            label="{{ __('Smart Image Display Settings') }}"
        >
        </x-form-step>

        <x-card
            class="mb-2 max-md:text-center"
            szie="lg"
        >
            <x-form.group class="flex flex-col gap-4">
                <x-form.checkbox
                    class="border-input rounded-input border !px-2.5 !py-3"
                    name="ai_chat_pro_smart_image_enabled"
                    label="{{ __('Enable Smart Image Display') }}"
                    checked="{{ (bool) setting('ai_chat_pro_smart_image_enabled', '1') }}"
                    tooltip="{{ __('When enabled, AI Chat Pro will automatically search and display relevant images for visual queries.') }}"
                />

                <x-forms.input
                    class="form-control"
                    label="{{ __('Max Images Per Query') }}"
                    type="number"
                    name="ai_chat_pro_smart_image_max_images"
                    min="1"
                    max="10"
                    tooltip="{{ __('Maximum number of images to fetch per search query.') }}"
                    value="{{ setting('ai_chat_pro_smart_image_max_images', '5') }}"
                />

                <x-alert class="my-2">
                    <p>
                        @lang('Smart Image Display uses the configured realtime search provider (Serper or Perplexity) for image search. Ensure your API key is configured in the realtime search settings.')
                    </p>
                </x-alert>
            </x-form.group>
        </x-card>

        <button class="btn btn-primary mt-5 w-full">
            {{ __('Save') }}
        </button>
    </form>
@endsection
