@php use App\Domains\Entity\Enums\EntityEnum; @endphp
@extends('panel.layout.settings', ['layout' => 'wide'])
@section('title', __('GammaAI Settings'))
@section('titlebar_actions', '')
@section('titlebar_subtitle', __('This API key is used for AI Presentation Maker.')))

@section('additional_css')
    <link
        href="{{ custom_theme_url('/assets/libs/select2/select2.min.css') }}"
        rel="stylesheet"
    />
@endsection

@section('settings')
    <form
        method="post"
        action="{{ route('dashboard.admin.settings.gamma-ai') }}"
        id="settings_form"
        enctype="multipart/form-data"
    >
        @csrf
        <div class="row">
            <x-card
                class="mb-3 max-md:text-center"
                szie="lg"
            >

                <div class="col-md-12">
                    <div
                        class="form-control mb-3 border-none p-0 [&_.select2-selection--multiple]:!rounded-[--tblr-border-radius] [&_.select2-selection--multiple]:!border-[--tblr-border-color] [&_.select2-selection--multiple]:!p-[1em_1.23em]">
                        <label class="form-label">{{ __('Gamma API key') }}</label>

						<x-alert class="my-2">
							<x-button
								variant="link"
								href="https://gamma.app/"
								target="_blank"
							>
								{{ __('Get an API key') }}
							</x-button>
						</x-alert>

                        <select
                            class="form-control select2"
                            id="gamma_api_secret"
                            name="gamma_api_secret"
                            multiple
                        >
                            @if($app_is_demo)
                                <option selected value="*********************">*********************</option>
                            @else
                                @foreach (explode(',', setting('gamma_api_secret')) as $secret)
                                    <option
                                        value="{{ $secret }}"
                                        selected
                                    >{{ $secret }}</option>
                                @endforeach
                            @endif
                        </select>
                        <x-alert class="mt-2">
                            <p>
                                {{ __('You can enter as much API key as you want. Click "Enter" after each API key.') }}
                            </p>
                        </x-alert>
                        <x-alert class="mt-2">
                            <p>
                                {{ __('Please ensure that your Gamma API key is fully functional and billing defined on your Gamma account.') }}
                            </p>
                        </x-alert>
                    </div>
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
