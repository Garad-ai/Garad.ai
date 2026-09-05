@extends('panel.layout.settings')
@section('title', __('Marketing Bot Settings'))
@section('titlebar_actions', '')

@section('settings')
    <form
        method="POST"
        action="{{ route('dashboard.admin.marketing-bot.settings.update') }}"
    >
        @csrf
        @method('PUT')

        <x-card class="mb-4">
            <x-slot name="head">
                {{ __('WhatsApp Providers') }}
            </x-slot>

            <x-forms.input
                id="whatsapp_providers"
                type="select"
                multiple
                size="lg"
                name="whatsapp_providers[]"
                label="{{ __('Available WhatsApp Providers') }}"
                :tooltip="trans('Select which WhatsApp providers users can choose from. Default is Twilio only.')"
            >
                <option
                    value="twilio"
                    {{ in_array('twilio', $enabledProviders) ? 'selected' : '' }}
                >
                    @lang('Twilio')
                </option>
                <option
                    value="meta"
                    {{ in_array('meta', $enabledProviders) ? 'selected' : '' }}
                >
                    @lang('Meta (Official API)')
                </option>
            </x-forms.input>

            <x-button
                class="mt-4"
                type="submit"
                variant="primary"
            >
                {{ __('Save') }}
            </x-button>
        </x-card>
    </form>
@endsection
