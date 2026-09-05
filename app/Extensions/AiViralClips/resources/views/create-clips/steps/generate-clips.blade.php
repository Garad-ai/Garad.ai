@php
    $youtube_icon =
        '<svg class="text-red-600" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M18 3a5 5 0 0 1 5 5v8a5 5 0 0 1 -5 5h-12a5 5 0 0 1 -5 -5v-8a5 5 0 0 1 5 -5zm-9 6v6a1 1 0 0 0 1.514 .857l5 -3a1 1 0 0 0 0 -1.714l-5 -3a1 1 0 0 0 -1.514 .857z" stroke-width="0" fill="currentColor"></path></svg>';
@endphp
<div
    class="col-start-1 col-end-1 row-start-1 row-end-1"
    x-show="currentStep == 1"
    x-data="generateClipsStepData"
    x-init="$nextTick(() => { $watch('aiClipsWindowKey', () => initialize()) })"
>
    <h2 class="mb-3.5">
        @lang('Select or Upload Long Video')
    </h2>
    <p class="mb-3 text-xs/5 opacity-60 lg:max-w-[360px]">
        @lang('Paste a public video link or upload your own file to begin generating clips.')
    </p>

    <div class="lqd-ext-chatbot-training mt-9 flex flex-col justify-center gap-9">
        <ul class="flex w-full flex-wrap justify-between gap-3 rounded-3xl bg-foreground/5 p-1 text-xs font-medium sm:flex-nowrap sm:rounded-full">
            @foreach ([__('Youtube URL'), __('Upload')] as $tab)
                <li class="w-full grow">
                    <button
                        @class([
                            'w-full grow rounded-full px-6 py-2.5 leading-tight transition-all hover:bg-background/80 [&.lqd-is-active]:bg-background [&.lqd-is-active]:shadow-[0_2px_12px_hsl(0_0%_0%/10%)]',
                            'lqd-is-active' => $loop->first,
                        ])
                        @click.prevent="setActiveTab('{{ $tab }}')"
                        :class="{ 'lqd-is-active': activeTab == '{{ $tab }}' }"
                        :disabled="submitting"
                    >
                        {{ $tab }}
                    </button>
                </li>
            @endforeach
        </ul>
        <div class="flex flex-col gap-9">
            {{-- Youtube Link --}}
            <div
                x-show="activeTab == '{{ __('Youtube URL') }}'"
                x-transition.opacity.150ms
            >
                <x-forms.input
                    class:label="text-heading-foreground text-xs"
                    class:label-txt="flex flex-nowrap gap-2 items-center"
                    label="{{ __('Youtube Video Link') }}"
                    :label-icon="$youtube_icon"
                    placeholder="{{ __('Youtube Video Link') }}"
                    name="source_video_url"
                    tooltip="Youtube Video Link"
                    size="lg"
                    x-model="formData.source_video_url"
                ></x-forms.input>
            </div>

            {{-- file upload --}}
            <div
                class="mb-4 flex w-full flex-col gap-5"
                x-show="activeTab == '{{ __('Upload') }}'"
                x-cloak
                x-transition.opacity.150ms
            >
                <label
                    class="group flex min-h-40 w-full cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed border-foreground/10 bg-background text-center text-[12px] transition-colors hover:bg-background/80"
                    for="upload-file"
                >
                    <div class="flex flex-col items-center justify-center py-5">
                        <x-tabler-circle-plus
                            class="mb-3.5 size-11"
                            stroke-width="1"
                            x-show="!submitting"
                        />
                        <x-tabler-refresh
                            class="mb-3.5 size-11 animate-spin"
                            stroke-width="1"
                            x-show="submitting"
                        />

                        <div class="mb-2 text-center text-sm font-medium text-foreground">
                            <p
                                class="mb-0 text-foreground/30"
                                x-show="!fileSelected"
                            >
                                {{ __('Drag and drop video') }}
                            </p>
                            <span x-text="fileSelected ? '{{ __('File is selected!') }}' : '{{ __('click here to browse your files.') }}'"></span>
                        </div>

                        <p
                            class="mb-0 text-4xs font-medium leading-6"
                            x-text="!submitting ? '{{ __('Upload video file') }}' : '{{ __('Submitting...') }}'"
                        >
                        </p>
                    </div>

                    {{-- @todo check accepts are set correctly --}}
                    <input
                        class="hidden"
                        id="upload-file"
                        name="file"
                        type="file"
                        accept="video/*"
                        x-model="formData.file"
                        @change="$event.target?.files[0] ? setFileSelected() : setFileSelected(false)"
                    />
                </label>
            </div>

            {{-- Captions --}}
            <div class="flex w-full items-center justify-between py-3">
                <div class="flex gap-3">
                    <span class="text-xs font-medium text-heading-foreground">@lang('Captions')</span>
                    <x-info-tooltip text="{{ __('Captions') }}" />
                </div>
                <x-forms.input
                    class="checked:bg-primary"
                    type="checkbox"
                    switcher
                    x-model="formData.editing_options.captions"
                >
                </x-forms.input>
            </div>

            {{-- Emojis --}}
            <div class="flex w-full items-center justify-between py-3">
                <div class="flex gap-3">
                    <span class="text-xs font-medium text-heading-foreground">@lang('Emojis')</span>
                    <x-info-tooltip text="{{ __('Emojis') }}" />
                </div>
                <x-forms.input
                    class="checked:bg-primary"
                    type="checkbox"
                    switcher
                    x-model="formData.editing_options.emojis"
                >
                </x-forms.input>
            </div>

            {{-- Intro title --}}
            <div class="flex w-full items-center justify-between py-3">
                <div class="flex gap-3">
                    <span class="text-xs font-medium text-heading-foreground">@lang('Intro title')</span>
                    <x-info-tooltip text="{{ __('Intro title') }}" />
                </div>
                <x-forms.input
                    class="checked:bg-primary"
                    type="checkbox"
                    switcher
                    x-model="formData.editing_options.intro_title"
                >
                </x-forms.input>
            </div>

            {{-- Lanugage dropdown --}}
            <div>
                <x-forms.input
                    class:label="text-heading-foreground"
                    label="{{ __('Language') }}"
                    tooltip="{{ __('Select language for your video') }}"
                    name="language"
                    size="lg"
                    type="select"
                    x-model="formData.language"
                >
                    @foreach (\App\Packages\Klap\Enums\Language::cases() as $language)
                        <option value="{{ $language->value }}">
                            {{ $language->label() }}
                        </option>
                    @endforeach
                </x-forms.input>
            </div>

            {{-- Duration --}}
            <div>
                <x-forms.input
                    class:label="text-heading-foreground"
                    label="{{ __('Duration') }}"
                    tooltip="{{ __('Max duration of clips') }}"
                    size="lg"
                    type="number"
                    max="180"
                    min="1"
                    x-model="formData.max_duration"
                >
                </x-forms.input>
            </div>

            {{-- Clip Count --}}
            <div>
                <x-forms.input
                    class:label="text-heading-foreground"
                    label="{{ __('Clip Count') }}"
                    tooltip="{{ __('Desired Clip Count') }}"
                    size="lg"
                    type="number"
                    min="1"
                    x-model="formData.target_clip_count"
                >
                </x-forms.input>
            </div>
        </div>
    </div>

    <x-button
        class="mt-9 w-full"
        variant="secondary"
        @click.prevent="nextStep()"
        size="lg"
        type="button"
        ::disabled="submitting"
    >
        @lang('Next')
    </x-button>
</div>
