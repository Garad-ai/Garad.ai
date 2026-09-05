@php use App\Domains\Entity\Enums\EntityEnum; @endphp
@extends('panel.layout.settings', ['layout' => 'wide'])
@section('title', __('AI Image Editor Settings'))
@section('titlebar_actions', '')
@section('titlebar_subtitle', '')

@section('additional_css')
    <link
        href="{{ custom_theme_url('/assets/libs/select2/select2.min.css') }}"
        rel="stylesheet"
    />
@endsection

@section('settings')
    <form
        id="settings_form"
        method="post"
        action="{{ route('dashboard.admin.settings.advanced-image.update') }}"
        enctype="multipart/form-data"
    >
        @csrf
        <h3 class="mb-[25px] text-[20px]">{{ __('FalAI Settings') }}</h3>
        <div class="row">
            <!-- TODO OPENAI API KEY -->
            <x-card class="mb-3 max-md:text-center">
                <div class="col-md-12">
                    @foreach ($tools as $tool)
                        <div class="mb-4">
                            @php
                                $inputName = $tool['action'] . '_model';
                            @endphp
                            <x-forms.input
                                id="{{ $inputName }}"
                                label="{{ __($tool['title']) }}"
                                name="{{ $inputName }}"
                                type="select"
                                size="lg"
                            >
                                @foreach ($tool['models'] as $model)
                                    <option
                                        value="{{ $model }}"
                                        @selected(old($inputName, setting($inputName)) === $model)
                                    >
                                        {{ __($model) }}
                                    </option>
                                @endforeach
                            </x-forms.input>
                        </div>
                    @endforeach
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
    <script src="{{ custom_theme_url('/assets/libs/select2/select2.min.js') }}"></script>
@endpush
