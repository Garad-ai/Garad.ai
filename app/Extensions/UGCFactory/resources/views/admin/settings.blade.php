@php
    use App\Extensions\UGCFactory\System\Services\UGCFactoryRegistry;
@endphp

@extends('panel.layout.settings', ['disable_tblr' => true, 'layout' => 'wide'])
@section('title', __('UGC Factory Settings'))
@section('titlebar_actions', '')
@section('titlebar_subtitle', __('Configure UGC Factory generation settings.'))

@section('settings')
    <form
        class="flex flex-col gap-8"
        id="settings_form"
        method="post"
        action="{{ route('dashboard.admin.ugc-factory.settings.update') }}"
        enctype="multipart/form-data"
    >
        @csrf

        <section>
            <h3 class="mb-5">
                {{ __('Voiceover & Video Settings') }}
            </h3>

            <div class="flex flex-col gap-5">
                <x-forms.input
                    id="{{ UGCFactoryRegistry::SETTING_RESOLUTION }}"
                    type="select"
                    size="lg"
                    label="{{ __('Default Video Resolution') }}"
                    tooltip="{{ __('Resolution applied to every UGC video. 480p is cheaper, 720p is sharper. Per-second pricing on fal.ai is roughly 2× higher at 720p.') }}"
                    name="{{ UGCFactoryRegistry::SETTING_RESOLUTION }}"
                >
                    @foreach ($resolutions as $resolution)
                        <option
                            value="{{ $resolution }}"
                            @selected($currentResolution === $resolution)
                        >{{ $resolution }}</option>
                    @endforeach
                </x-forms.input>

                <x-forms.input
                    id="{{ UGCFactoryRegistry::SETTING_MAX_UPLOAD_SIZE_MB }}"
                    type="number"
                    size="lg"
                    label="{{ __('Max Upload Size (MB)') }}"
                    tooltip="{{ __('Maximum size of an actor image or audio file a user can upload.') }}"
                    name="{{ UGCFactoryRegistry::SETTING_MAX_UPLOAD_SIZE_MB }}"
                    :value="$currentMaxUploadSizeMb"
                    min="1"
                    max="50"
                />
            </div>
        </section>

        <hr>

        <section>
            <h3 class="mb-5">
                {{ __('AI Actor Generation') }}
            </h3>

            <div class="flex flex-col gap-5">
                <x-forms.input
                    id="{{ UGCFactoryRegistry::SETTING_ACTOR_IMAGE_MODEL }}"
                    type="select"
                    size="lg"
                    label="{{ __('Actor Image Model') }}"
                    tooltip="{{ __('Model used when a user creates a new actor from a text prompt. GPT Image runs synchronously via OpenAI; Nano Banana and Grok Imagine run asynchronously via fal.ai. Make sure the corresponding provider has a working API key.') }}"
                    name="{{ UGCFactoryRegistry::SETTING_ACTOR_IMAGE_MODEL }}"
                >
                    @foreach ($actorImageModels as $value => $meta)
                        <option
                            value="{{ $value }}"
                            @selected($currentActorImageModel === $value)
                        >{{ $meta['label'] }} — {{ ucfirst($meta['provider']) }}</option>
                    @endforeach
                </x-forms.input>

                <x-forms.input
                    id="{{ UGCFactoryRegistry::SETTING_ACTOR_PROMPT_TEMPLATE }}"
                    type="textarea"
                    label="{{ __('Actor System Prompt') }}"
                    tooltip="{{ __('Wraps the user\'s actor description with framing instructions before sending it to the image model. Use the placeholder {prompt} where the user\'s text should be inserted. Leave empty to use the built-in default (shown as placeholder).') }}"
                    name="{{ UGCFactoryRegistry::SETTING_ACTOR_PROMPT_TEMPLATE }}"
                    :value="$currentActorPromptTemplate === $defaultActorPromptTemplate ? '' : $currentActorPromptTemplate"
                    :placeholder="$defaultActorPromptTemplate"
                    rows="10"
                />
            </div>
        </section>

        <hr>

        <section>
            <div class="relative mb-5 flex items-center gap-1">
                <h3 class="relative m-0">
                    {{ __('Premade Actors') }}
                </h3>
                <x-info-tooltip
                    text="{{ __('Premade actors are ready-to-use character presets that users can select when creating a new actor. Disabling a preset will hide it from the picker, but won\'t delete it, so you can re-enable it later if needed.') }}"
                />
            </div>

            <div class="flex flex-col gap-5">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @foreach ($presets as $preset)
                        @php
                            $isEnabled = !in_array($preset['key'], $disabledPresetKeys, true);
                            $override = $presetLabelOverrides[$preset['key']] ?? '';
                        @endphp
                        <div class="flex items-start gap-3 rounded-card border border-card-border bg-card-background p-3">
                            <img
                                class="size-16 shrink-0 rounded-input object-cover"
                                src="{{ $preset['image'] }}"
                                alt="{{ $preset['default_label'] }}"
                            >
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
