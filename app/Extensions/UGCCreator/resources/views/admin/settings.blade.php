@php
    use App\Extensions\UGCCreator\System\Services\UGCCreatorRegistry;
@endphp

@extends('panel.layout.settings', ['disable_tblr' => true, 'layout' => 'wide'])
@section('title', __('UGC Creator Settings'))
@section('titlebar_actions', '')
@section('titlebar_subtitle', __('Configure UGC Creator video generation settings.'))

@section('settings')
    <form
        class="flex flex-col gap-8"
        id="settings_form"
        method="post"
        action="{{ route('dashboard.admin.ugc-creator.settings.update') }}"
        enctype="multipart/form-data"
    >
        @csrf

        <section>
            <h3 class="mb-5">{{ __('Video Model') }}</h3>

            <div class="flex flex-col gap-5">
                <x-forms.input
                    id="{{ UGCCreatorRegistry::SETTING_DEFAULT_MODEL }}"
                    type="select"
                    size="lg"
                    label="{{ __('Default Video Model') }}"
                    tooltip="{{ __('Used by default when a user creates a new UGC Creator video. Must be one of the enabled models below.') }}"
                    name="{{ UGCCreatorRegistry::SETTING_DEFAULT_MODEL }}"
                >
                    @foreach ($videoModels as $key => $meta)
                        <option value="{{ $key }}" @selected($currentDefaultModel === $key)>
                            {{ $meta['label'] }}
                        </option>
                    @endforeach
                </x-forms.input>

                <x-forms.input
                    id="{{ UGCCreatorRegistry::SETTING_QUALITY }}"
                    type="select"
                    size="lg"
                    label="{{ __('Video Quality') }}"
                    tooltip="{{ __('Higher quality consumes more credits and takes longer to generate.') }}"
                    name="{{ UGCCreatorRegistry::SETTING_QUALITY }}"
                >
                    @foreach ($qualities as $q)
                        <option value="{{ $q }}" @selected($currentQuality === $q)>{{ ucfirst($q) }}</option>
                    @endforeach
                </x-forms.input>

                <div>
                    <p class="mb-2 text-[12px] font-semibold text-heading-foreground">
                        {{ __('Enabled Models') }}
                    </p>
                    <p class="mb-3 text-3xs text-label">
                        {{ __('Only enabled models are exposed to users. The default model is automatically re-pointed to an enabled model if you disable it.') }}
                    </p>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        @foreach ($videoModels as $key => $meta)
                            <x-forms.input
                                type="checkbox"
                                switcher
                                size="sm"
                                label="{{ $meta['label'] }}"
                                name="enabled_models[]"
                                :value="$key"
                                :checked="in_array($key, $enabledModelKeys, true)"
                            />
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <hr>

        <section>
            <h3 class="mb-5">{{ __('Limits') }}</h3>

            <div class="flex flex-col gap-5">
                <x-forms.input
                    id="{{ UGCCreatorRegistry::SETTING_MAX_UPLOAD_SIZE_MB }}"
                    type="number"
                    size="lg"
                    label="{{ __('Max Upload Size (MB)') }}"
                    tooltip="{{ __('Maximum size of a product or character reference image a user can upload.') }}"
                    name="{{ UGCCreatorRegistry::SETTING_MAX_UPLOAD_SIZE_MB }}"
                    :value="$currentMaxUploadSizeMb"
                    min="1"
                    max="50"
                />

                <x-forms.input
                    id="{{ UGCCreatorRegistry::SETTING_MAX_REFERENCE_IMAGES }}"
                    type="number"
                    size="lg"
                    label="{{ __('Max Reference Images Per Type') }}"
                    tooltip="{{ __('Maximum number of product or character reference images a user can attach per generation.') }}"
                    name="{{ UGCCreatorRegistry::SETTING_MAX_REFERENCE_IMAGES }}"
                    :value="$currentMaxReferenceImages"
                    min="1"
                    max="10"
                />
            </div>
        </section>

        <hr>

        <section>
            <div class="relative mb-5 flex items-center gap-1">
                <h3 class="relative m-0">{{ __('Camera Style Presets') }}</h3>
                <x-info-tooltip
                    text="{{ __('Camera-style presets silently inject prompt modifiers (camera behaviour, shot composition, framing) into every generation. Disabling a preset hides it from the picker but does not delete it.') }}"
                />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                @foreach ($cameraPresets as $preset)
                    @php
                        $isEnabled = !in_array($preset['key'], $disabledCameraPresetKeys, true);
                        $override = $cameraPresetLabelOverrides[$preset['key']] ?? '';
                    @endphp
                    <div class="flex items-start gap-3 rounded-card border border-card-border bg-card-background p-3">
                        @if (!empty($preset['image']))
                            <img
                                class="size-16 shrink-0 rounded-input object-cover"
                                src="{{ $preset['image'] }}"
                                alt="{{ $preset['default_label'] }}"
                            >
                        @else
                            <span class="grid size-16 shrink-0 place-items-center rounded-input bg-surface text-3xs text-label">
                                {{ $preset['default_label'] }}
                            </span>
                        @endif
                        <div class="flex min-w-0 grow flex-col gap-3">
                            <x-forms.input
                                type="text"
                                size="lg"
                                name="preset_labels[{{ $preset['key'] }}]"
                                :value="$override !== '' ? $override : ''"
                                :placeholder="$preset['default_label']"
                                maxlength="120"
                            />
                            <x-forms.input
                                type="checkbox"
                                switcher
                                label="{{ __('Enabled') }}"
                                name="enabled_presets[]"
                                size="sm"
                                :value="$preset['key']"
                                :checked="$isEnabled"
                            />
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <x-button
            class="w-full"
            type="submit"
            size="lg"
            tag="button"
        >
            {{ __('Save Settings') }}
        </x-button>
    </form>
@endsection
