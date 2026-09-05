@extends('panel.layout.settings')
@section('title', __('Live Customizer'))
@section('titlebar_actions', 'Live Customizer')
@section('titlebar_subtitle', '')

@section('additional_css')
@endsection

@section('settings')
    <form
        method="post"
        enctype="multipart/form-data"
        action="{{ route('dashboard.admin.live-customizer.setting') }}"
    >
        @csrf
        <x-card
            class="mb-2 max-md:text-center"
            szie="lg"
        >

            <div
                class="form-control mb-3 border-none p-0 [&_.select2-selection--multiple]:!rounded-[--tblr-border-radius] [&_.select2-selection--multiple]:!border-[--tblr-border-color] [&_.select2-selection--multiple]:!p-[1em_1.23em]">
                <x-forms.input
                    id="show_live_customizer"
                    name="show_live_customizer"
                    type="checkbox"
                    switcher
                    type="checkbox"
                    :checked="setting('show_live_customizer', '1') == '1'"
                    label="{{ __('Live customizer editor') }}"
                >
                    <x-badge
                        class="ms-2 text-2xs"
                        variant="secondary"
                    >
                        @lang('New')
                    </x-badge>
                </x-forms.input>
                <x-alert
                    class="mt-2"
                    variant="lg"
                >
                    <p>
                        {{ __('If you close the editor, the theme customization process will be cancelled.') }}
                    </p>
                </x-alert>
            </div>

        </x-card>
        <x-button
            class="w-full"
            type="submit"
        >
            {{ __('Save') }}
        </x-button>
    </form>
@endsection

@push('script')
@endpush
