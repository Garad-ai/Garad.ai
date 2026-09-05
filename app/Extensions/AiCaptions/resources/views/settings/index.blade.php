@extends('panel.layout.settings')
@section('title', __('AI Captions Settings'))
@section('titlebar_actions', '')
@section('titlebar_subtitle', __('Configure the Captions API key used to generate stylized captions for videos.'))

@section('settings')
    <form
        method="post"
        action="{{ route('dashboard.admin.ai-captions.update') }}"
    >
        @csrf

        <x-card
            class="mb-2 max-md:text-center"
            size="lg"
        >
            <div class="form-control mb-5 border-none p-0">
                <label class="form-label" for="ai_captions_api_key">
                    {{ __('Captions API Key') }}
                </label>
                <input
                    class="form-control"
                    id="ai_captions_api_key"
                    type="password"
                    name="ai_captions_api_key"
                    autocomplete="off"
                    value="{{ $app_is_demo ?? false ? '*********************' : (string) setting('ai_captions_api_key', '') }}"
                    placeholder="{{ __('Paste your Captions API key') }}"
                />
                <x-alert class="mt-2" variant="lg">
                    <p>
                        {!! __('Get an API key from the <a class="underline" href="https://captions.ai/help/docs/api/video-captions" target="_blank" rel="noreferrer">Captions API dashboard</a>. The key is stored privately and never exposed to end users.') !!}
                    </p>
                </x-alert>
            </div>

            <div class="form-control mb-5 border-none p-0">
                <label class="form-label" for="ai_captions_default_minutes">
                    {{ __('Default monthly minutes allowance') }}
                </label>
                <input
                    class="form-control"
                    id="ai_captions_default_minutes"
                    type="number"
                    min="0"
                    name="ai_captions_default_minutes"
                    value="{{ (int) setting('ai_captions_default_minutes', config('aicaptions.default_monthly_minutes', 30)) }}"
                />
                <x-alert class="mt-2" variant="lg">
                    <p>
                        {{ __('Used when a plan does not explicitly set an AI Captions minutes limit. Use -1 for unlimited, 0 to disable by default.') }}
                    </p>
                </x-alert>
            </div>
        </x-card>

        <button class="btn btn-primary w-full" type="submit">
            {{ __('Save') }}
        </button>
    </form>
@endsection
