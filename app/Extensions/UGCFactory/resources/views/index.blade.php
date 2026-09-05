@php
    $maxUploadMb = \App\Extensions\UGCFactory\System\Services\UGCFactoryRegistry::getMaxUploadSizeMb();
    $userActors = $userActors ?? [];
    $isDemo = $isDemo ?? false;
    $voiceProvider = $voiceProvider ?? 'openai';
    $voiceLanguages = $voiceLanguages ?? [];
    $voiceOptions = $voiceOptions ?? [];
    $presets = $presets ?? [];

    // Registry returns { key, label, default_label, image }; the markup below
    // and the JS payload both consume { preset_key, label, preview_image }.
    // Adapt once here so the rest of the view stays unchanged.
    $avatars = array_map(
        static fn(array $p) => [
            'preset_key' => $p['key'],
            'label' => $p['label'],
            'preview_image' => $p['image'],
        ],
        $presets,
    );

    $ugcFactoryInitial = [
        'isDemo' => (bool) $isDemo,
        'csrf' => csrf_token(),
        'voiceProvider' => $voiceProvider,
        'voiceLanguages' => $voiceLanguages,
        'voiceOptions' => $voiceOptions,
        'presets' => array_map(
            fn($a) => [
                'preset_key' => $a['preset_key'],
                'label' => $a['label'],
                'preview_image' => $a['preview_image'],
            ],
            $avatars,
        ),
        'userActors' => $userActors,
        'endpoints' => [
            'actors' => route('dashboard.user.ugc-factory.actors.index'),
            'actorsUpload' => route('dashboard.user.ugc-factory.actors.upload'),
            'actorsGenerate' => route('dashboard.user.ugc-factory.actors.generate'),
            'actorsStatus' => route('dashboard.user.ugc-factory.actors.status', ['actor' => '__ID__']),
            'actorsDestroy' => route('dashboard.user.ugc-factory.actors.destroy', ['actor' => '__ID__']),
            'videosStore' => route('dashboard.user.ugc-factory.videos.store'),
            'ugcStudio' => route('dashboard.user.ugc-studio.index'),
            'aiScript' => url('/dashboard/user/openai/update-writing'),
        ],
        'i18n' => [
            'scriptSeedRequired' => __('Type a short seed first so the AI can refine it.'),
            'scriptGenerationFailed' => __('Could not generate the script. Please try again.'),
        ],
    ];
@endphp

@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', __('UGC Factory'))
@section('titlebar_pretitle', '')
@section('titlebar_subtitle', __('Create realistic UGC content for social media and marketing.'))

@section('titlebar_actions')
    <x-button
        variant="ghost-shadow"
        href="#"
    >
        {{ __('View All Videos') }}
    </x-button>

    <x-button href="#">
        <x-tabler-plus class="size-4" />
        {{ __('New UGC Content') }}
    </x-button>
@endsection

@push('before-head-close')
    <style>
        @media (min-width: 992px) {
            .lqd-page-content-wrap {
                overflow: visible;
            }
        }
    </style>
@endpush

@section('content')
    <div class="py-10">
        <div
            class="lqd-ugc-factory flex flex-wrap items-start gap-10 lg:flex-nowrap"
            x-data="ugcFactory({{ json_encode($ugcFactoryInitial) }})"
            x-init="$nextTick(() => $dispatch('generator-changed', { generator: 'veed/fabric-1.0', quantity: 1 }))"
        >
            <div class="w-full lg:sticky lg:top-1 lg:w-[35%] lg:shrink-0">
                <p class="mb-5 border-b py-2.5 text-[12px] font-semibold text-heading-foreground transition">
                    {{ __('Video Details') }}
                </p>

                <x-card class:body="p-5">
                    <x-tabs.tabs default="text">
                        <x-tabs.nav>
                            <x-tabs.trigger
                                class="basis-1/3"
                                name="text"
                                @click="audioSource = 'tts'"
                            >
                                {{ __('Text') }}
                            </x-tabs.trigger>

                            <x-tabs.trigger
                                class="basis-1/3"
                                name="audio"
                                @click="audioSource = 'upload'"
                            >
                                {{ __('Audio') }}
                            </x-tabs.trigger>

                            <x-tabs.trigger
                                class="basis-1/3"
                                name="record"
                                @click="audioSource = 'record'"
                            >
                                {{ __('Record') }}
                            </x-tabs.trigger>
                        </x-tabs.nav>

                        <x-tabs.content name="text">
                            <form
                                class="flex flex-col gap-5"
                                @submit.prevent="submitVideo($event)"
                            >
                                <x-forms.input
                                    class:label-extra="order-3 ms-auto -my-1.5"
                                    type="textarea"
                                    rows="5"
                                    name="ugc_factory_voiceover_text_script"
                                    label="{{ __('Script') }}"
                                    placeholder="{{ __('What do you want your character to say?') }}"
                                    tooltip="{{ __('The text that you want your character to say') }}"
                                    x-model="script"
                                >
                                    <x-slot:label-extra>
                                        <x-button
                                            class="inline-grid size-7 place-items-center text-2xs"
                                            type="button"
                                            size="none"
                                            variant="ghost"
                                            hover-variant="primary"
                                            ::disabled="scriptGeneration"
                                            @click.prevent="generateAIScript()"
                                        >
                                            <svg
                                                class="col-start-1 col-end-1 row-start-1 row-end-1 size-4 group-hover:fill-current"
                                                :class="{ hidden: scriptGeneration }"
                                                width="17"
                                                height="17"
                                                viewBox="0 0 17 17"
                                                fill="url(#paint0_linear_8906_3722)"
                                                xmlns="http://www.w3.org/2000/svg"
                                            >
                                                <path
                                                    fill-rule="evenodd"
                                                    clip-rule="evenodd"
                                                    d="M16.5085 6.34955L15.113 6.63248C14.4408 6.7689 13.8236 7.10033 13.3386 7.58536C12.8536 8.0704 12.5221 8.68757 12.3857 9.35981L12.1028 10.7552C12.0748 10.8948 11.9994 11.0203 11.8893 11.1105C11.7792 11.2007 11.6412 11.25 11.4989 11.25C11.3566 11.25 11.2187 11.2007 11.1086 11.1105C10.9985 11.0203 10.923 10.8948 10.895 10.7552L10.6121 9.35981C10.4758 8.68751 10.1444 8.07027 9.65938 7.58522C9.17432 7.10016 8.55709 6.76878 7.8848 6.63248L6.48937 6.34955C6.35011 6.32107 6.22495 6.24537 6.13507 6.13525C6.04519 6.02513 5.99609 5.88733 5.99609 5.74519C5.99609 5.60304 6.04519 5.46526 6.13507 5.35514C6.22495 5.24502 6.35011 5.16932 6.48937 5.14084L7.8848 4.8579C8.55709 4.7216 9.17432 4.39022 9.65938 3.90516C10.1444 3.42011 10.4758 2.80288 10.6121 2.13058L10.895 0.73517C10.923 0.595627 10.9985 0.470081 11.1086 0.379882C11.2187 0.289682 11.3566 0.240395 11.4989 0.240395C11.6412 0.240395 11.7792 0.289682 11.8893 0.379882C11.9994 0.470081 12.0748 0.595627 12.1028 0.73517L12.3857 2.13058C12.5221 2.80283 12.8536 3.41999 13.3386 3.90503C13.8236 4.39007 14.4408 4.72148 15.113 4.8579L16.5085 5.14084C16.6477 5.16932 16.7729 5.24502 16.8627 5.35514C16.9526 5.46526 17.0017 5.60304 17.0017 5.74519C17.0017 5.88733 16.9526 6.02513 16.8627 6.13525C16.7729 6.24537 16.6477 6.32107 16.5085 6.34955ZM6.30231 13.4219L5.92312 13.4989C5.45558 13.5937 5.02634 13.8242 4.689 14.1616C4.35167 14.4989 4.12118 14.9281 4.02633 15.3957L3.94934 15.7749C3.92805 15.881 3.87064 15.9766 3.78687 16.0452C3.70309 16.1139 3.59813 16.1514 3.48982 16.1514C3.38151 16.1514 3.27654 16.1139 3.19277 16.0452C3.10899 15.9766 3.05157 15.881 3.03029 15.7749L2.9533 15.3957C2.85844 14.9281 2.62796 14.4989 2.29062 14.1616C1.95328 13.8242 1.52404 13.5937 1.0565 13.4989L0.677333 13.4219C0.571137 13.4006 0.475582 13.3432 0.406935 13.2594C0.338287 13.1756 0.300781 13.0707 0.300781 12.9624C0.300781 12.854 0.338287 12.7491 0.406935 12.6653C0.475582 12.5815 0.571137 12.5241 0.677333 12.5028L1.0565 12.4258C1.52404 12.331 1.95328 12.1005 2.29062 11.7632C2.62796 11.4258 2.85844 10.9966 2.9533 10.5291L3.03029 10.1499C3.05157 10.0437 3.10899 9.94813 3.19277 9.87948C3.27654 9.81083 3.38151 9.77334 3.48982 9.77334C3.59813 9.77334 3.70309 9.81083 3.78687 9.87948C3.87064 9.94813 3.92805 10.0437 3.94934 10.1499L4.02633 10.5291C4.12118 10.9966 4.35167 11.4258 4.689 11.7632C5.02634 12.1005 5.45558 12.331 5.92312 12.4258L6.30231 12.5028C6.4085 12.5241 6.50404 12.5815 6.57269 12.6653C6.64134 12.7491 6.67884 12.854 6.67884 12.9624C6.67884 13.0707 6.64134 13.1756 6.57269 13.2594C6.50404 13.3432 6.4085 13.4006 6.30231 13.4219Z"
                                                />
                                                <defs>
                                                    <linearGradient
                                                        id="paint0_linear_8906_3722"
                                                        x1="17.0017"
                                                        y1="8.19589"
                                                        x2="0.137511"
                                                        y2="6.25241"
                                                        gradientUnits="userSpaceOnUse"
                                                    >
                                                        <stop stop-color="#8D65E9" />
                                                        <stop
                                                            offset="0.483"
                                                            stop-color="#5391E4"
                                                        />
                                                        <stop
                                                            offset="1"
                                                            stop-color="#6BCD94"
                                                        />
                                                    </linearGradient>
                                                </defs>
                                            </svg>
                                            <x-tabler-refresh
                                                class="col-start-1 col-end-1 row-start-1 row-end-1 hidden size-4 animate-spin"
                                                x-show="scriptGeneration"
                                                ::class="{ hidden: !scriptGeneration }"
                                            />
                                        </x-button>
                                    </x-slot:label-extra>
                                </x-forms.input>

                                @if (!empty($voiceLanguages))
                                    <x-forms.selectbox
                                        name="ugc_factory_voice_language"
                                        label="{{ __('Language') }}"
                                        placeholder="{{ __('Choose a language') }}"
                                        :value="$voiceLanguages[0]['value'] ?? null"
                                        :options="$voiceLanguages"
                                    />
                                @endif

                                <x-forms.selectbox
                                    name="ugc_factory_voice"
                                    label="{{ __('Voice') }}"
                                    placeholder="{{ __('Choose a voice') }}"
                                    :value="$voiceOptions[0]['value'] ?? null"
                                >
                                    @foreach ($voiceOptions as $voice)
                                        <x-forms.selectbox-option
                                            value="{{ $voice['value'] }}"
                                            label="{{ $voice['label'] }}"
                                        >
                                            @if (!empty($voice['preview_url']))
                                                <span
                                                    class="inline-grid size-6 shrink-0 place-items-center rounded-lg border bg-background transition hover:bg-primary hover:text-primary-foreground"
                                                    title="{{ __('Preview') }}"
                                                    @click.prevent.stop="previewVoice('{{ $voice['value'] }}')"
                                                >
                                                    <x-tabler-player-play
                                                        class="size-3.5 fill-current"
                                                        x-show="previewingVoice !== '{{ $voice['value'] }}'"
                                                    />
                                                    <x-tabler-volume
                                                        class="size-3.5"
                                                        x-cloak
                                                        x-show="previewingVoice === '{{ $voice['value'] }}'"
                                                    />
                                                </span>
                                            @endif
                                            <span class="grow truncate">{{ $voice['label'] }}</span>
                                        </x-forms.selectbox-option>
                                    @endforeach
                                </x-forms.selectbox>

                                <p
                                    class="m-0 text-3xs text-red-500"
                                    x-show="errorMessage"
                                    x-text="errorMessage"
                                    x-cloak
                                ></p>

                                <x-button
                                    class="w-full py-4"
                                    type="submit"
                                    size="lg"
                                    ::disabled="submitting || isDemo"
                                >
                                    <span x-show="!submitting">{{ __('Generate') }}</span>
                                    <span
                                        x-show="submitting"
                                        x-cloak
                                    >{{ __('Submitting…') }}</span>
                                </x-button>

                                <x-cost-preview class="w-full justify-end" />
                            </form>
                        </x-tabs.content>

                        <x-tabs.content name="audio">
                            <form
                                class="flex flex-col gap-5"
                                @submit.prevent="submitVideo($event)"
                            >
                                <x-forms.droparea
                                    name="ugc_factory_audio_file"
                                    accept="audio/*"
                                    :max-size-mb="$maxUploadMb ?? null"
                                    @change="captureAudioFile($event)"
                                />

                                <p
                                    class="m-0 text-3xs text-red-500"
                                    x-show="errorMessage"
                                    x-text="errorMessage"
                                    x-cloak
                                ></p>

                                <x-button
                                    class="w-full py-4"
                                    type="submit"
                                    size="lg"
                                    ::disabled="submitting || isDemo"
                                >
                                    <span x-show="!submitting">{{ __('Generate') }}</span>
                                    <span
                                        x-show="submitting"
                                        x-cloak
                                    >{{ __('Submitting…') }}</span>
                                </x-button>

                                <x-cost-preview class="w-full justify-end" />
                            </form>
                        </x-tabs.content>

                        <x-tabs.content name="record">
                            <form
                                class="flex flex-col gap-5"
                                @submit.prevent="submitVideo($event)"
                            >
                                <x-forms.recorder
                                    name="ugc_factory_audio_file"
                                    :max-size-mb="$maxUploadMb"
                                    @change="captureAudioFile($event)"
                                />

                                <p
                                    class="m-0 text-3xs text-red-500"
                                    x-show="errorMessage"
                                    x-text="errorMessage"
                                    x-cloak
                                ></p>

                                <x-button
                                    class="w-full py-4"
                                    type="submit"
                                    size="lg"
                                    ::disabled="submitting || isDemo"
                                >
                                    <span x-show="!submitting">{{ __('Generate') }}</span>
                                    <span
                                        x-show="submitting"
                                        x-cloak
                                    >{{ __('Submitting…') }}</span>
                                </x-button>

                                <x-cost-preview class="w-full justify-end" />
                            </form>
                        </x-tabs.content>
                    </x-tabs.tabs>
                </x-card>
            </div>

            <div class="w-full grow">
                <div class="mb-5 flex items-center gap-5.5 overflow-x-auto border-b">
                    <button
                        class="whitespace-nowrap py-2.5 text-[12px] font-semibold text-heading-foreground transition"
                        type="button"
                    >
                        {{ __('Pick an Actor') }}
                    </button>
                    <button
                        class="whitespace-nowrap py-2.5 text-[12px] font-semibold text-heading-foreground opacity-50 transition hover:opacity-85"
                        type="button"
                        @click.prevent="toggleCreateModal(true, 'upload')"
                    >
                        {{ __('Upload Your Actor') }}
                    </button>
                    <button
                        class="whitespace-nowrap py-2.5 text-[12px] font-semibold text-heading-foreground opacity-50 transition hover:opacity-85"
                        type="button"
                        @click.prevent="toggleCreateModal(true, 'create')"
                    >
                        {{ __('Create New Actor') }}
                    </button>
                </div>
                <div class="grid grid-cols-2 gap-1.5 md:grid-cols-3">
                    {{-- User-uploaded / generated actors (SSR rows — Alpine removes them once it boots and re-renders via x-for) --}}
                    @foreach ($userActors as $userActor)
                        <div
                            class="group/ugc-item relative aspect-[1/1.78] scale-100 overflow-hidden rounded-2xl border-[5px] border-transparent transition hover:z-1 hover:scale-[1.03] hover:shadow-xl hover:shadow-black/10 active:scale-100"
                            x-data
                            x-init="$el.remove()"
                        >
                            <img
                                @class([
                                    'size-full scale-[1.03] object-cover transition group-hover/ugc-item:scale-100 group-active/ugc-item:scale-100',
                                    'hidden' => ($userActor['status'] ?? null) !== 'ready',
                                ])
                                src="{{ $userActor['image_url'] ?? '' }}"
                                alt="{{ $userActor['name'] }}"
                            >

                            @if (($userActor['status'] ?? null) !== 'ready')
                                <div class="absolute inset-0 flex items-center justify-center bg-foreground/[3%] p-4 text-center">
                                    @if (($userActor['status'] ?? null) !== 'failed')
                                        <x-shimmer-text>
                                            <span class="capitalize">
                                                {{ $userActor['status'] }}…
                                            </span>
                                        </x-shimmer-text>
                                    @else
                                        <span>
                                            {{ __('Failed to Generate') }}
                                        </span>
                                    @endif
                                </div>
                            @endif

                            <div
                                class="absolute inset-x-0 bottom-0 top-1/2 z-1 translate-y-12 overflow-hidden bg-gradient-to-t from-black from-10% to-transparent to-70% opacity-0 transition group-hover/ugc-item:translate-y-0 group-hover/ugc-item:opacity-100">
                            </div>

                            <div class="absolute inset-0 flex flex-col justify-between gap-2 overflow-hidden p-3.5">
                                <div class="flex items-center justify-between gap-1">
                                    <div class="ms-auto"></div>
                                </div>

                                <div class="relative z-2 translate-y-2 px-2 pb-2 text-center opacity-0 transition group-hover/ugc-item:translate-y-0 group-hover/ugc-item:opacity-100">
                                    <p class="truncate text-sm font-medium text-white">
                                        {{ $userActor['name'] }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endforeach

                    {{-- Alpine-managed user actors (replace SSR rows once Alpine boots; new rows from upload/generate also flow through here) --}}
                    <template
                        x-for="actor in userActors"
                        :key="'user-' + actor.id"
                    >
                        <div
                            class="group/ugc-item relative aspect-[1/1.78] scale-100 overflow-hidden rounded-2xl border-[5px] border-transparent transition hover:z-1 hover:scale-[1.03] hover:shadow-xl hover:shadow-black/10 active:scale-100 [&.selected]:border-primary"
                            :class="{ 'selected': isActorSelected(actor.type, actor.id, null) }"
                        >
                            <img
                                class="size-full scale-[1.03] object-cover transition group-hover/ugc-item:scale-100 group-active/ugc-item:scale-100"
                                :src="actor.image_url"
                                :alt="actor.name"
                                x-cloak
                                x-show="actor.status === 'ready'"
                            >

                            <div
                                class="absolute inset-0 flex items-center justify-center bg-foreground/[3%] p-4 text-center"
                                x-show="actor.status !== 'ready'"
                                x-cloak
                            >
                                <div x-show="actor.status !== 'failed'">
                                    <x-shimmer-text>
                                        <span
                                            class="capitalize"
                                            x-text="`${actor.status}...`"
                                        ></span>
                                    </x-shimmer-text>
                                </div>
                                <span
                                    x-show="actor.status === 'failed'"
                                    x-text="'{{ __('Failed to Generate') }}'"
                                ></span>
                            </div>
                            <div
                                class="absolute inset-x-0 bottom-0 top-1/2 z-1 translate-y-12 overflow-hidden bg-gradient-to-t from-black from-10% to-transparent to-70% opacity-0 transition group-hover/ugc-item:translate-y-0 group-hover/ugc-item:opacity-100">
                            </div>

                            <div class="absolute inset-0 flex flex-col justify-between gap-2 overflow-hidden p-3.5">
                                <div class="flex items-center justify-between gap-1">
                                    <span
                                        class="inline-grid size-9 place-items-center rounded-full bg-secondary text-secondary-foreground shadow-lg shadow-black/5"
                                        x-cloak
                                        x-show="isActorSelected(actor.type, actor.id, null)"
                                    >
                                        <x-tabler-check class="size-5" />
                                    </span>

                                    <x-dropdown.dropdown
                                        class="relative z-3 ms-auto"
                                        class:dropdown-dropdown="p-2"
                                        :teleport="false"
                                        offsetY="20px"
                                        anchor="end"
                                    >
                                        <x-slot:trigger>
                                            <x-button
                                                class="inline-grid size-9 place-items-center rounded-full bg-background text-foreground opacity-0 shadow-lg shadow-black/5 transition group-hover/ugc-item:opacity-100"
                                                size="none"
                                                variant="ghost"
                                                hover-variant="primary"
                                            >
                                                <x-tabler-dots-vertical class="size-5" />
                                            </x-button>
                                        </x-slot:trigger>
                                        <x-slot:dropdown>
                                            <x-button
                                                class="w-full justify-start !rounded-md text-start hover:transform-none"
                                                variant="none"
                                                hover-variant="danger"
                                                @click.prevent="deleteActor(actor)"
                                            >
                                                <x-tabler-trash class="size-4 text-red-500 group-hover:text-current" />
                                                {{ __('Delete') }}
                                            </x-button>
                                        </x-slot:dropdown>
                                    </x-dropdown.dropdown>
                                </div>

                                <div class="relative z-2 translate-y-2 px-2 pb-2 text-center opacity-0 transition group-hover/ugc-item:translate-y-0 group-hover/ugc-item:opacity-100">
                                    <p
                                        class="truncate text-sm font-medium text-white"
                                        x-text="actor.name"
                                    ></p>
                                </div>
                            </div>

                            <a
                                class="absolute inset-0 z-2"
                                href="#"
                                @click.prevent="selectUserActor(actor)"
                            ></a>
                        </div>
                    </template>

                    {{-- Preset actors --}}
                    @foreach ($avatars as $avatar)
                        <div
                            class="group/ugc-item relative aspect-[1/1.78] scale-100 overflow-hidden rounded-2xl border-[5px] border-transparent transition hover:z-1 hover:scale-[1.03] hover:shadow-xl hover:shadow-black/10 active:scale-100 [&.selected]:border-primary"
                            :class="{ 'selected': isActorSelected('preset', 0, '{{ $avatar['preset_key'] }}') }"
                        >
                            <img
                                class="size-full scale-[1.03] object-cover transition group-hover/ugc-item:scale-100 group-active/ugc-item:scale-100"
                                src="{{ $avatar['preview_image'] }}"
                                alt="{{ $avatar['label'] }}"
                            >

                            <div
                                class="absolute inset-x-0 bottom-0 top-1/2 z-1 translate-y-12 overflow-hidden bg-gradient-to-t from-black from-10% to-transparent to-70% opacity-0 transition group-hover/ugc-item:translate-y-0 group-hover/ugc-item:opacity-100">
                            </div>

                            <div class="absolute inset-0 flex flex-col justify-between gap-2 overflow-hidden p-3.5">
                                <div class="flex items-center justify-between gap-1">
                                    <span
                                        class="inline-grid size-9 place-items-center rounded-full bg-secondary text-secondary-foreground shadow-lg shadow-black/5"
                                        x-cloak
                                        x-show="isActorSelected('preset', 0, '{{ $avatar['preset_key'] }}')"
                                    >
                                        <x-tabler-check class="size-5" />
                                    </span>
                                </div>

                                <div class="relative z-2 translate-y-2 px-2 pb-2 text-center opacity-0 transition group-hover/ugc-item:translate-y-0 group-hover/ugc-item:opacity-100">
                                    <p class="truncate text-sm font-medium text-white">
                                        {{ $avatar['label'] }}
                                    </p>
                                </div>
                            </div>

                            <a
                                class="absolute inset-0 z-2"
                                href="#"
                                @click.prevent="selectPresetActor({{ json_encode([
                                    'preset_key' => $avatar['preset_key'],
                                    'label' => $avatar['label'],
                                    'preview_image' => $avatar['preview_image'],
                                ]) }})"
                            ></a>
                        </div>
                    @endforeach
                </div>
            </div>

            @include('ugc-factory::includes.create-modal')
        </div>
    </div>
@endsection

@pushOnce('script-before')
    {{-- lamejs is loaded globally (window.lamejs.Mp3Encoder) and used by the
         recorder to transcode browser-native audio (webm/opus, mp4/aac) to mp3
         before upload — fal.ai's VEED Fabric only accepts mp3/wav/m4a/aac/ogg. --}}
    <script src="{{ custom_theme_url('/assets/libs/lamejs/lame.min.js') }}"></script>
    @vite('app/Extensions/UGCFactory/resources/js/ugcFactory.js')
@endPushOnce
