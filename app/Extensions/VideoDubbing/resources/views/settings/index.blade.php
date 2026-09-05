@extends('panel.layout.settings')
@section('title', __('Video Dubbing Settings'))
@section('titlebar_actions', '')
@section('titlebar_subtitle', __('Configure the provider and settings for video dubbing.'))

@section('settings')
    <form
        method="post"
        action=""
    >
        @csrf
        <x-card
            class="mb-2 max-md:text-center"
            size="lg"
        >
            {{-- Default Provider --}}
            <div class="form-control mb-5 border-none p-0">
                <label class="form-label">
                    {{ __('Default Provider') }}
                </label>
                <select
                    class="form-select"
                    name="video_dubbing_default_provider"
                >
                    <option
                        value="elevenlabs"
                        @selected(setting('video_dubbing_default_provider', 'elevenlabs') === 'elevenlabs')
                    >
                        ElevenLabs
                    </option>
                    <option
                        value="heygen"
                        @selected(setting('video_dubbing_default_provider', 'elevenlabs') === 'heygen')
                    >
                        HeyGen
                    </option>
                </select>
                <x-alert class="mt-2" variant="lg">
                    <p>
                        {{ __('The selected provider determines which API is used and which options are shown to users on the dubbing page.') }}
                    </p>
                </x-alert>
            </div>

            <hr class="my-5">

            {{-- Provider Info --}}
            <div class="space-y-4">
                <h4 class="form-label mb-0">{{ __('Provider Details') }}</h4>

                <x-alert variant="lg">
                    <p class="mb-2">
                        <strong>ElevenLabs</strong>
                    </p>
                    <ul class="list-disc ps-5 text-xs leading-relaxed">
                        <li>{{ __('API key is configured in') }} <a href="{{ route('dashboard.admin.settings.tts') }}" class="underline">{{ __('TTS Settings') }}</a></li>
                        <li>{{ __('29 languages supported') }}</li>
                        <li>{{ __('Supports file upload & URL import (YouTube, TikTok, Twitter, Vimeo)') }}</li>
                        <li>{{ __('Options: highest resolution, drop background audio, profanity filter, voice cloning toggle') }}</li>
                        <li>{{ __('Max file size: 1GB / 2.5 hours') }}</li>
                    </ul>
                </x-alert>

                <x-alert variant="lg">
                    <p class="mb-2">
                        <strong>HeyGen</strong>
                    </p>
                    <ul class="list-disc ps-5 text-xs leading-relaxed">
                        <li>{{ __('API key is configured in') }} <a href="{{ route('dashboard.admin.settings.heygen') }}" class="underline">{{ __('HeyGen Settings') }}</a></li>
                        <li>{{ __('175+ languages with regional variants') }}</li>
                        <li>{{ __('Supports URL import (YouTube, direct links, Google Drive)') }}</li>
                        <li>{{ __('Options: fast/quality mode, audio-only translation, lip-sync') }}</li>
                        <li>{{ __('3 API credits per minute of translated video') }}</li>
                    </ul>
                </x-alert>
            </div>
        </x-card>

        <button class="btn btn-primary w-full">
            {{ __('Save') }}
        </button>
    </form>
@endsection
