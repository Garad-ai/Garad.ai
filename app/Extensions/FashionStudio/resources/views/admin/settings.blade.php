@extends('panel.layout.settings', ['layout' => 'wide'])
@section('title', __('Fashion Studio Settings'))
@section('titlebar_actions', '')
@section('titlebar_subtitle', __('Configure Fashion Studio image and video generation settings'))

@section('settings')
    <form
        method="post"
        action="{{ route('dashboard.admin.fashion-studio.settings.update') }}"
        id="settings_form"
        enctype="multipart/form-data"
        x-data='{
            selectedImageModel: @json($currentImageModel),
            openAIModels: @json($openAIModelValues),
            get isOpenAI() { return this.openAIModels.includes(this.selectedImageModel); }
        }'
    >
        @csrf
        <h3 class="mb-[25px] text-[20px]">{{ __('Image Generation Settings') }}</h3>
        <div class="row">
            <x-card
                class="mb-3 max-md:text-center"
                size="lg"
            >
                <div class="col-md-12">
                    <div class="mb-3">
                        <label class="form-label">{{ __('Default Image Model') }}</label>
                        <select
                            class="form-select"
                            id="fashion-studio-image-default-model"
                            name="fashion-studio-image-default-model"
                            x-model="selectedImageModel"
                        >
                            @foreach ($imageModels as $value => $label)
                                <option
                                    value="{{ $value }}"
                                    @selected($currentImageModel === $value && ($imageModelEnabled[$value] ?? true))
                                    @disabled(! ($imageModelEnabled[$value] ?? true))
                                >{{ $label }}{{ ($imageModelEnabled[$value] ?? true) ? '' : ' — ' . __('disabled in API integration settings') }}</option>
                            @endforeach
                        </select>
                        <x-alert class="mt-2">
                            <p>
                                {{ __('Select the default AI model used for image generation and editing in Fashion Studio. Models from FAL (nano-banana family) and OpenAI (gpt-image family) are supported.') }}
                            </p>
                            <p>
                                {{ __('Edit variants are auto-selected when reference images are attached.') }}
                            </p>
                        </x-alert>
                    </div>
                </div>
            </x-card>
        </div>
        <div x-show="isOpenAI" x-cloak>
        <h3 class="mb-[25px] text-[20px]">{{ __('OpenAI Image Settings') }}</h3>
        <div class="row">
            <x-card
                class="mb-3 max-md:text-center"
                size="lg"
            >
                <div class="col-md-12">
                    <div class="mb-3">
                        <label class="form-label">{{ __('Quality') }}</label>
                        <select
                            class="form-select"
                            id="fashion-studio-openai-quality"
                            name="fashion-studio-openai-quality"
                        >
                            @foreach ($qualityOptions as $value => $label)
                                <option
                                    value="{{ $value }}"
                                    @selected($currentQuality === $value)
                                >{{ __($label) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Size') }}</label>
                        <select
                            class="form-select"
                            id="fashion-studio-openai-size"
                            name="fashion-studio-openai-size"
                        >
                            @foreach ($sizeOptions as $value => $label)
                                <option
                                    value="{{ $value }}"
                                    @selected($currentSize === $value)
                                >{{ __($label) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-alert class="mt-2">
                        <p>
                            {{ __('Lower quality and smaller size reduce per-image cost. \'Low\' quality at 1024x1024 is the cheapest.') }}
                        </p>
                        <p>
                            {{ __('Larger sizes (2048+, 3840) only apply to GPT Image 2.') }}
                        </p>
                    </x-alert>
                </div>
            </x-card>
        </div>
        </div>
        <h3 class="mb-[25px] text-[20px]">{{ __('Video Generation Settings') }}</h3>
        <div class="row">
            <x-card
                class="mb-3 max-md:text-center"
                size="lg"
            >
                <div class="col-md-12">
                    <div class="mb-3">
                        <label class="form-label">{{ __('Default Video Model') }}</label>
                        <select
                            class="form-select"
                            id="fashion-studio-video-default-model"
                            name="fashion-studio-video-default-model"
                        >
                            @foreach ($videoModels as $value => $label)
                                <option
                                    value="{{ $value }}"
                                    @selected($currentVideoModel === $value)
                                >{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-alert class="mt-2">
                            <p>
                                {{ __('Select the default AI model used for image-to-video generation in Fashion Studio.') }}
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
@endpush
