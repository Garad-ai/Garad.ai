@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', __('Marketing Bot Settings'))

@section('titlebar_actions')

@endsection

@section('content')
    <div class="grid grid-cols-1 gap-8 py-10 lg:grid-cols-2">
        <x-card>
            <x-slot name="head">
                {{ __('Marketing Bot Telegram') }}
            </x-slot>
            <form
                action="{{ route('dashboard.user.marketing-bot.settings.telegram') }}"
                method="post"
            >
                @method('post')
                <x-forms.input
                    id="access_token"
                    size="lg"
                    label="{{ __('Access Token') }}"
                    name="access_token"
                    required
                    value="{{ $app_is_demo ? '**********' : $telegram?->access_token }}"
                />
                @if ($app_is_demo)
                    <x-button
                        class="mt-3"
                        type="button"
                        onclick="return toastr.info('This feature is disabled in Demo version.');"
                    >
                        {{ __('Save') }}
                    </x-button>
                @else
                    <x-button
                        class="mt-3"
                        type="submit"
                    >
                        {{ __('Save') }}
                    </x-button>
                @endif
            </form>
        </x-card>
        <x-card>
            <x-slot name="head">
                {{ __('Marketing Bot Whatsapp Settings') }}
            </x-slot>
            <form
                x-data="{ provider: '{{ $whatsapp?->whatsapp_provider ?? 'twilio' }}' }"
                action="{{ route('dashboard.user.marketing-bot.settings.whatsapp') }}"
                method="post"
            >
                <input
                    hidden
                    name="channel"
                    value="whatsapp"
                >
                <input
                    hidden
                    name="user_id"
                    value="{{ \Illuminate\Support\Facades\Auth::id() }}"
                >
                @csrf

                {{-- Provider selector --}}
                <div class="mb-5">
                    <x-forms.input
                        id="whatsapp_provider"
                        type="select"
                        size="lg"
                        name="whatsapp_provider"
                        label="{{ __('WhatsApp Provider') }}"
                        x-model="provider"
                    >
                        @php $enabledProviders = \App\Extensions\MarketingBot\System\Http\Controllers\Admin\MarketingBotAdminSettingController::getEnabledProviders(); @endphp
                        @if(in_array('twilio', $enabledProviders))
                            <option value="twilio">@lang('Twilio')</option>
                        @endif
                        @if(in_array('meta', $enabledProviders))
                            <option value="meta">@lang('Meta (Official API)')</option>
                        @endif
                    </x-forms.input>
                </div>

                {{-- Twilio fields --}}
                <div x-show="provider === 'twilio'" x-cloak>
                    <div class="mb-3">
                        <x-forms.input
                            :label="__('Account SID')"
                            name="whatsapp_sid"
                            size="lg"
                            value="{{ $app_is_demo ? '**********' : $whatsapp?->whatsapp_sid }}"
                        >
                        </x-forms.input>
                    </div>
                    <div class="mb-3">
                        <x-forms.input
                            :label="__('Auth Token')"
                            name="whatsapp_token"
                            size="lg"
                            value="{{ $app_is_demo ? '**********' : $whatsapp?->whatsapp_token }}"
                        >
                        </x-forms.input>
                    </div>
                    <div class="mb-3">
                        <x-forms.input
                            :label="__('Phone Number')"
                            name="whatsapp_phone"
                            size="lg"
                            value="{{ $app_is_demo ? '**********' : $whatsapp?->whatsapp_phone }}"
                        >
                        </x-forms.input>
                    </div>
                    <div class="mb-3">
                        <x-forms.input
                            :label="__('Sandbox Phone Number')"
                            name="whatsapp_sandbox_phone"
                            size="lg"
                            value="{{ $app_is_demo ? '**********' : $whatsapp?->whatsapp_sandbox_phone }}"
                        >
                        </x-forms.input>
                    </div>
                    <div class="mb-3">
                        <x-forms.input
                            id="whatsapp_environment"
                            type="select"
                            size="lg"
                            name="whatsapp_environment"
                            label="{{ __('Environment') }}"
                        >
                            <option
                                {{ $whatsapp?->whatsapp_environment === 'sandbox' ? 'selected' : '' }}
                                value="sandbox"
                            >@lang('SANDBOX')</option>
                            <option
                                {{ $whatsapp?->whatsapp_environment === 'production' ? 'selected' : '' }}
                                value="production"
                            >@lang('PRODUCTION')</option>
                        </x-forms.input>
                    </div>
                </div>

                {{-- Meta (Official API) fields --}}
                <div x-show="provider === 'meta'" x-cloak>
                    <div class="mb-3">
                        <x-forms.input
                            :label="__('Access Token')"
                            name="meta_access_token"
                            size="lg"
                            value="{{ $app_is_demo ? '**********' : $whatsapp?->meta_access_token }}"
                        >
                        </x-forms.input>
                    </div>
                    <div class="mb-3">
                        <x-forms.input
                            :label="__('Phone Number ID')"
                            name="meta_phone_number_id"
                            size="lg"
                            value="{{ $app_is_demo ? '**********' : $whatsapp?->meta_phone_number_id }}"
                        >
                        </x-forms.input>
                    </div>
                    <div class="mb-3">
                        <x-forms.input
                            :label="__('WhatsApp Business Account ID (WABA ID)')"
                            name="meta_waba_id"
                            size="lg"
                            value="{{ $app_is_demo ? '**********' : $whatsapp?->meta_waba_id }}"
                        >
                        </x-forms.input>
                    </div>
                    <div class="mb-3">
                        <x-forms.input
                            :label="__('Webhook Verify Token')"
                            name="meta_verify_token"
                            size="lg"
                            value="{{ $app_is_demo ? '**********' : $whatsapp?->meta_verify_token }}"
                        >
                        </x-forms.input>
                    </div>
                </div>

                @if ($whatsapp)
                    <div class="mb-3">
                        <x-forms.input
                            :label="__('Webhook URL')"
                            name="webhook"
                            size="lg"
                            value="{{ route('api.marketing-bot.whatsapp.webhook', $whatsapp?->id) }}"
                        >
                        </x-forms.input>
                    </div>
                @endif

                @if ($app_is_demo)
                    <x-button
                        type="button"
                        onclick="return toastr.info('This feature is disabled in Demo version.');"
                    >
                        {{ __('Save') }}
                    </x-button>
                @else
                    <x-button
                        class="mt-3"
                        type="submit"
                    >
                        {{ __('Save') }}
                    </x-button>
                @endif
            </form>
        </x-card>
    </div>
@endsection

@push('script')
@endpush
