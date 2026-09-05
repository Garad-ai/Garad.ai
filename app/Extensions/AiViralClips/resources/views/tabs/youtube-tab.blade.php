<div
    class="col-start-1 col-end-1 row-start-1 row-end-1"
    x-show="activeTab == '{{ __('Youtube URL') }}'"
    x-transition.opacity.150ms
>
    <div class="flex flex-col gap-9">
        {{-- Youtube Link --}}
        <div>
            <x-forms.input
                class:label="text-heading-foreground text-xs"
                label="{{ __('Youtube Video Link') }}"
                placeholder="{{ __('Youtube Video Link') }}"
                name="source_video_url"
                tooltip="Youtube Video Link"
                size="lg"
                x-model="formData.source_video_url"
            ></x-forms.input>
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
