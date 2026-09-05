@extends('panel.layout.settings', ['layout' => 'wide', 'disable_tblr' => true])
@section('title', __($title))
@section('titlebar_actions', '')

@section('additional_css')
@endsection

@section('settings')
    <form
        class="flex flex-wrap justify-between gap-y-5"
        id="form-submit"
        action="{{ $action }}"
        enctype="multipart/form-data"
        method="post"
    >
        @csrf
        @method($method)
        <div class="w-full space-y-2">
            <x-forms.input
                class:container="w-full"
                id="name"
                type="text"
                name="name"
                value="{{ $item?->name }}"
                label="{{ __('Name') }}"
                tooltip="{{ __('Email Subject') }}"
            />
            @error('name')
                <p class="text-red-500">
                    {{ $message }}
                </p>
            @enderror
        </div>

        <x-button
            class="w-full"
            size="lg"
            type="submit"
        >
            {{ __('Save') }}
        </x-button>
    </form>
@endsection

@push('script')
@endpush
