@extends('panel.layout.settings', ['disable_tblr' => true])
@section('title', $title)
@section('titlebar_subtitle', __('Contact edit page for your whatsapp.'))
@section('titlebar_actions')
    <div class="flex gap-4 lg:justify-end">
        <x-button
            variant="ghost-shadow"
            href="{{ route('dashboard.user.marketing-bot.contact-list.index') }}"
        >
            {{ __('Contacts') }}
        </x-button>
    </div>
@endsection

@section('settings')
    <form
        class="card flex flex-col gap-5 p-4"
        method="post"
        action="{{ $action }}"
    >
        @csrf
        @method($method)
        <x-forms.input
            id="name"
            size="lg"
            label="{{ __('First Name') }}"
            name="name"
            required
            value="{{ old('name', $item?->name) }}"
        />
        <x-forms.input
            id="last_name"
            size="lg"
            label="{{ __('Last Name') }}"
            name="last_name"
            value="{{ old('last_name', $item?->last_name) }}"
        />
        <x-forms.input
            id="phone"
            size="lg"
            label="{{ __('Phone Number (including Country Code)') }}"
            name="phone"
            required
            value="{{ old('phone', $item?->phone) }}"
        />

        <x-forms.input
            class:label="text-heading-foreground"
            type="select"
            size="lg"
            name="country_code"
            label="{{ __('Country code') }}"
        >
            @foreach ($countries as $country)
                <option
                    {{ old('country_code', $item->country_code) == data_get($country, 'phonecode') ? 'selected' : '' }}
                    value="{{ data_get($country, 'phonecode') }}"
                >
                    {{ data_get($country, 'name') }}
                </option>
            @endforeach
        </x-forms.input>

        <x-forms.input
            class:label="text-heading-foreground"
            type="select"
            multiple
            size="lg"
            name="contacts[]"
            label="{{ __('Select Contact List') }}"
        >
            @foreach ($contacts as $contact)
                <option
                    {{ in_array($contact->id, old('contacts', $selectedContacts)) ? 'selected' : '' }}
                    value="{{ $contact->id }}"
                >
                    {{ data_get($contact, 'name') }}
                </option>
            @endforeach
        </x-forms.input>

        <x-forms.input
            class:label="text-heading-foreground"
            type="select"
            multiple
            size="lg"
            name="segments[]"
            label="{{ __('Select Segments') }}"
        >
            @foreach ($segments as $segment)
                <option
                    {{ in_array($segment->id, old('segments', $selectedSegments), true) ? 'selected' : '' }}
                    value="{{ $segment->id }}"
                >
                    {{ data_get($segment, 'name') }}
                </option>
            @endforeach
        </x-forms.input>

        @if ($app_is_demo)
            <x-button
                type="button"
                onclick="return toastr.info('This feature is disabled in Demo version.');"
            >
                {{ $item?->id ? trans('Edit Contact') : __('Add Contact') }}
            </x-button>
        @else
            <x-button type="submit">
                {{ $item?->id ? trans('Edit Contact') : __('Add Contact') }}
            </x-button>
        @endif
    </form>
@endsection

@push('script')
@endpush
