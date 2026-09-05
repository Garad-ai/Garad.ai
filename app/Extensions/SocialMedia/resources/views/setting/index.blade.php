@extends('panel.layout.settings')
@section('title', __('AI Social Media Suite Settings'))

@section('settings')
    <div class="space-y-4">
        @foreach ($platforms as $platform)
            <x-card>
                <form
                    class="space-y-3"
                    enctype="multipart/form-data"
                    method="post"
                    action="{{ route('dashboard.admin.social-media.setting.update', $platform->value) }}"
                >
                    <h4 class="mb-4 border-b pb-2.5 text-xl">
                        {{ $platform->label() . ' ' . __('settings') }}
                    </h4>

                    @foreach ($platform->credentials() as $key => $value)
                        <div class="space-y-2">
                            <x-forms.input
                                id="{{ strtoupper($key) }}"
                                label="{{ str($key)->replace('_', ' ')->title() }}"
                                type="text"
                                size="lg"
                                name="{{ strtoupper($key) }}"
                                value="{{ $app_is_demo ? '*********************' : old($key, $value) }}"
                                required
                            />
                            @error($key)
                                <small class="text-red-500">{{ $message }}</small>
                            @enderror
                        </div>
                    @endforeach

                    @if ($platform === \App\Extensions\SocialMedia\System\Enums\PlatformEnum::tiktok)
                        <div class="space-y-2">
                            <x-forms.input
                                id="{{ strtoupper('tiktok_verification_file') }}"
                                label="{{ __('Verification file') }}"
                                type="file"
                                size="lg"
                                name="{{ strtoupper('tiktok_verification_file') }}"
                            />
                            @error('verification_file')
                                <small class="text-red-500">{{ $message }}</small>
                            @enderror
                        </div>
                    @endif

                    @if ($platform->webhookUrl())
                        <x-forms.input
                            class="bg-foreground/5"
                            label="{{ __('Webhook URI') }}"
                            disabled
                            type="text"
                            size="lg"
                            value="{{ $platform->webhookUrl() }}"
                        />
                    @endif

                    <x-forms.input
                        class="bg-foreground/5"
                        label="{{ __('Redirect URI') }}"
                        disabled
                        type="text"
                        size="lg"
                        value="{{ url('/social-media/oauth/callback/' . $platform->value) }}"
                        required
                    />

                    <x-button
                        class="w-full"
                        size="lg"
                        type="submit"
                    >
                        {{ __('Save') }}
                    </x-button>
                </form>
            </x-card>
        @endforeach
    </div>
@endsection
