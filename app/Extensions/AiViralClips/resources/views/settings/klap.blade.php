@php use App\Domains\Entity\Enums\EntityEnum; @endphp
@extends('panel.layout.settings', ['layout' => 'wide'])
@section('title', __('Klap Setting'))
@section('titlebar_actions', '')
@section('titlebar_subtitle', __('This API Configuration is used for AI Viral Clip'))

@section('additional_css')
    <link
        href="{{ custom_theme_url('/assets/libs/select2/select2.min.css') }}"
        rel="stylesheet"
    />
@endsection

@section('settings')
    <form
        class="grid grid-cols-1 gap-5"
        id="klap-setting-form"
        method="POST"
        action="{{ route('dashboard.admin.settings.klap.update') }}"
    >
        @csrf

        <!-- API Key Field -->
        <x-forms.input
            id="klap_api_key"
            size="lg"
            type="text"
            name="klap_api_key"
            label="{{ __('Klap API Key') }}"
            placeholder="{{ __('Enter your Klap API key') }}"
            tooltip="{{ __('Please enter your Klap API key') }}"
            value="{{ setting('klap_api_key') }}"
            required
        />

        <!-- Submit Button -->
        <x-button
            class="w-full"
            id="klap_save_button"
            size="lg"
            type="submit"
        >
            {{ __('Save') }}
        </x-button>
    </form>
@endsection
@push('script')
    <script src="{{ custom_theme_url('/assets/js/panel/settings.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/select2/select2.min.js') }}"></script>
@endpush
