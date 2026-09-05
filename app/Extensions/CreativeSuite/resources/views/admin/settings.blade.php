@extends('panel.layout.settings')
@section('title', __('Creative Suite Settings'))
@section('titlebar_actions', '')
@section('titlebar_subtitle', __('Configure Creative Suite and any installed sub-extensions in one place.'))

@php
    use App\Helpers\Classes\MarketplaceHelper;

    // When the annotations extension is installed it owns the unified
    // settings form (a single form + a single save button covers both
    // Creative Suite and Annotations fields). When it isn't installed the
// standalone Creative Suite form below is the only thing on the page.
$csaInstalled = MarketplaceHelper::isRegistered('creative-suite-annotations');
@endphp

@section('settings')
    @if ($csaInstalled)
        @include('creative-suite-annotations::admin.settings-section', [
            'parentModels' => true,
        ])
    @else
        <form
            action="{{ route('dashboard.admin.creative-suite.settings.update') }}"
            method="POST"
        >
            @csrf
            <h3 class="mb-[25px] text-[20px]">{{ __('Creative Suite') }}</h3>

            @include('creative-suite::setting.particles.ai-tools-settings')

            <x-button
                class="w-full"
                size="lg"
                type="submit"
            >
                {{ __('Save') }}
            </x-button>
        </form>
    @endif
@endsection
