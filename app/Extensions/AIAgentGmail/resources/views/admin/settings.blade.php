@extends('panel.layout.settings', ['layout' => 'wide'])
@section('title', __('Gmail Settings'))
@section('titlebar_actions', '')
@section('titlebar_subtitle', __('Configure Google OAuth credentials for Gmail integration'))

@section('settings')
    <form
        method="post"
        action="{{ route('dashboard.admin.ai-agent.gmail.settings.update') }}"
        id="settings_form"
        enctype="multipart/form-data"
    >
        @csrf
        <h3 class="mb-[25px] text-[20px]">{{ __('Google OAuth Credentials') }}</h3>
        <div class="row">
            <x-card
                class="mb-3 max-md:text-center"
                size="lg"
            >
                <div class="col-md-12">
                    <div class="mb-3">
                        <label class="form-label">{{ __('Client ID') }}</label>
                        <input
                            class="form-control"
                            type="text"
                            name="google_client_id"
                            value="{{ $clientId }}"
                            placeholder="{{ __('Google OAuth Client ID') }}"
                        >
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Client Secret') }}</label>
                        <input
                            class="form-control"
                            type="password"
                            name="google_client_secret"
                            value=""
                            placeholder="{{ __('Leave blank to keep existing secret') }}"
                            autocomplete="new-password"
                        >
                    </div>
                    <div
                        class="mb-3"
                        x-data="{ copied: false, url: '{{ route('dashboard.user.ai-agent.connectors.gmail.callback') }}' }"
                    >
                        <label class="form-label">{{ __('Authorized Redirect URI') }}</label>
                        <p class="mb-1 text-[13px] text-heading-foreground/60">{{ __('Add this URL to your Google Cloud Console OAuth 2.0 authorized redirect URIs.') }}</p>
                        <div class="flex gap-2">
                            <input
                                class="form-control font-mono text-[13px]"
                                type="text"
                                :value="url"
                                readonly
                                @click="$el.select()"
                            >
                            <button
                                class="btn btn-outline-secondary shrink-0"
                                type="button"
                                @click="navigator.clipboard.writeText(url); copied = true; setTimeout(() => copied = false, 2000)"
                            >
                                <span x-show="! copied">{{ __('Copy') }}</span>
                                <span x-show="copied">{{ __('Copied!') }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </x-card>
        </div>
        <button
            class="btn btn-primary w-full"
            type="submit"
        >
            {{ __('Save') }}
        </button>
    </form>
@endsection

@push('script')
    <script src="{{ custom_theme_url('/assets/js/panel/settings.js') }}"></script>
@endpush
