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
            @foreach ([__('URL'), __('Upload')] as $tab)
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
            {{-- Video Link --}}
            <div
                class="flex flex-col gap-9"
                x-show="activeTab == '{{ __('URL') }}'"
                x-transition.opacity.150ms
            >
                {{-- Video type --}}
                <div>
                    <x-forms.input
                        class:label="text-heading-foreground"
                        label="{{ __('Video Type') }}"
                        tooltip="{{ __('Select Video type for your video') }}"
                        name="videoType"
                        size="lg"
                        type="select"
                        x-model="formData.videoType"
                    >
                        @foreach (\App\Packages\Vizard\Enums\VideoType::cases() as $videoType)
                            <option value="{{ $videoType->value }}">
                                {{ $videoType->label() }}
                            </option>
                        @endforeach
                    </x-forms.input>
                </div>

                <x-forms.input
                    class:label="text-heading-foreground text-xs"
                    class:label-txt="flex flex-nowrap gap-2 items-center"
                    label="{{ __('Video Link') }}"
                    placeholder="{{ __('Video Link') }}"
                    name="videoUrl"
                    tooltip="Video Link"
                    size="lg"
                    x-model="formData.videoUrl"
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

            {{-- Lanugage dropdown --}}
            <div>
                <x-forms.input
                    class:label="text-heading-foreground"
                    label="{{ __('Language') }}"
                    tooltip="{{ __('Select language for your video') }}"
                    name="lang"
                    size="lg"
                    type="select"
                    x-model="formData.lang"
                >
                    @foreach (\App\Packages\Vizard\Enums\Language::cases() as $language)
                        <option value="{{ $language->value }}">
                            {{ $language->label() }}
                        </option>
                    @endforeach
                </x-forms.input>
            </div>

            {{-- Ratio dropdown --}}
            <div>
                <x-forms.input
                    class:label="text-heading-foreground"
                    label="{{ __('Ratio Of Clip') }}"
                    tooltip="{{ __('Select Ratio Of Clip') }}"
                    name="ratioOfClip"
                    size="lg"
                    type="select"
                    x-model="formData.ratioOfClip"
                >
                    @foreach (\App\Packages\Vizard\Enums\Ratio::cases() as $ratio)
                        <option value="{{ $ratio->value }}">
                            {{ $ratio->label() }}
                        </option>
                    @endforeach
                </x-forms.input>
            </div>

            {{-- Prefer Length --}}
            <div>
                <x-forms.input
                    class:label="text-heading-foreground"
                    label="{{ __('Prefer Length') }}"
                    tooltip="{{ __('Select Prefer Length Of Clip') }}"
                    name="preferLength"
                    size="lg"
                    type="select"
                    x-model="formData.preferLength"
                >
                    @foreach (\App\Packages\Vizard\Enums\VideoLength::cases() as $length)
                        <option value="{{ $length->value }}">
                            {{ $length->label() }}
                        </option>
                    @endforeach
                </x-forms.input>
            </div>

            {{-- Clip Count --}}
            <div>
                <x-forms.input
                    class:label="text-heading-foreground"
                    label="{{ __('Clip Count') }}"
                    tooltip="{{ __('Max Clip Count') }}"
                    size="lg"
                    type="number"
                    min="1"
                    x-model="formData.maxClipNumber"
                >
                </x-forms.input>
            </div>

            <div class="flex flex-col gap-5">
                {{-- Subtitle --}}
                <div class="flex w-full items-center justify-between py-3">
                    <div class="flex gap-3">
                        <span class="text-xs font-medium text-heading-foreground">@lang('Subtitle')</span>
                        <x-info-tooltip text="{{ __('Subtitle') }}" />
                    </div>
                    <x-forms.input
                        class="checked:bg-primary"
                        type="checkbox"
                        switcher
                        x-model="formData.subtitleSwitch"
                    >
                    </x-forms.input>
                </div>

                {{-- HeadLine --}}
                <div class="flex w-full items-center justify-between py-3">
                    <div class="flex gap-3">
                        <span class="text-xs font-medium text-heading-foreground">@lang('HeadLine')</span>
                        <x-info-tooltip text="{{ __('HeadLine') }}" />
                    </div>
                    <x-forms.input
                        class="checked:bg-primary"
                        type="checkbox"
                        switcher
                        value="1"
                        x-model="formData.headlineSwitch"
                    >
                    </x-forms.input>
                </div>
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
