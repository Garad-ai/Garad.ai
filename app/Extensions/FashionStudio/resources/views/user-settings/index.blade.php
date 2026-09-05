@extends('panel.layout.settings', ['layout' => 'wide'])
@section('title', __('Photoshoot Settings'))
@section('titlebar_actions', '')
@section('titlebar_subtitle', __('View and manage photoshoot settings.'))

@section('settings')
    <form
        method="post"
        action="{{ route('dashboard.user.fashion-studio.user_settings.update') }}"
        id="settings_form"
    >
        @csrf
        <h3 class="mb-[25px] text-[20px]">{{ __('Generation Settings') }}</h3>
        <div class="row">
            <x-card
                class="mb-3 max-md:text-center"
                size="lg"
            >
                <div class="col-md-12 space-y-4">
                    <x-forms.input
                        id="num_images"
                        name="num_images"
                        type="select"
                        label="{{ __('Number of Generated Images') }}"
                        tooltip="{{ __('Choose how many images to generate per photoshoot. More images use more credits.') }}"
                    >
                        @for ($i = 1; $i <= $maxImages; $i++)
                            <option
                                value="{{ $i }}"
                                @selected($settings->num_images === $i)
                            >{{ $i }} {{ $i === 1 ? __('image') : __('images') }}</option>
                        @endfor
                    </x-forms.input>

                    <x-forms.input
                        id="resolution"
                        name="resolution"
                        type="select"
                        label="{{ __('Resolution') }}"
                        tooltip="{{ __('Higher resolution produces better quality images but may take longer to generate.') }}"
                    >
                        @foreach ($resolutions as $resolution)
                            <option
                                value="{{ $resolution }}"
                                @selected($settings->resolution === $resolution)
                            >{{ $resolution }}</option>
                        @endforeach
                    </x-forms.input>

                    <x-forms.input
                        id="ratio"
                        name="ratio"
                        type="select"
                        label="{{ __('Ratio') }}"
                        tooltip="{{ __('Select the aspect ratio for your generated images.') }}"
                    >
                        @foreach ($ratios as $ratio)
                            <option
                                value="{{ $ratio }}"
                                @selected($settings->ratio === $ratio)
                            >{{ $ratio }}</option>
                        @endforeach
                    </x-forms.input>
                </div>
            </x-card>
        </div>
        <x-button
            variant="primary"
			class="w-full"
            type="submit"
        >
            {{ __('Save') }}
        </x-button>
    </form>
@endsection