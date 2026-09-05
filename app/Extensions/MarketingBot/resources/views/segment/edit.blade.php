@extends('panel.layout.settings')
@section('title', __('Segment Edit'))
@section('titlebar_subtitle', __('Segment edit page for your whatsapp.'))
@section('titlebar_actions')
    <div class="flex gap-4 lg:justify-end">
        <x-button
            variant="ghost-shadow"
            href="{{ route('dashboard.user.marketing-bot.segment.index') }}"
        >
            {{ __('Segments') }}
        </x-button>
    </div>
@endsection

@section('settings')
    <form
        class="card flex flex-col gap-5 p-4"
        method="post"
        action="{{ route('dashboard.user.marketing-bot.segment.update', $item->id) }}"
    >
        @csrf
        @method('put')
        <x-forms.input
            id="name"
            size="lg"
            label="{{ __('Segment') }}"
            name="name"
            required
            value="{{ $item?->name }}"
        />

        <x-forms.input
            id="status"
            name="status"
            type="checkbox"
            switcher
            type="checkbox"
            :checked="$item->status == '1'"
            label="{{ __('Status') }}"
        />

        @if ($app_is_demo)
            <x-button
                type="button"
                onclick="return toastr.info('This feature is disabled in Demo version.');"
            >
                {{ __('Save') }}
            </x-button>
        @else
            <x-button type="submit">
                {{ __('Save') }}
            </x-button>
        @endif
    </form>
@endsection

@push('script')
@endpush
