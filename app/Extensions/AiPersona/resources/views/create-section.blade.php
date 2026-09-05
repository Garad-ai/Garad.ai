<template x-if="showCreateWindow">
    <div class="col-start-1 col-end-1 row-start-1 row-end-1 flex w-full min-w-0 max-w-full flex-wrap gap-10 lg:flex-nowrap">
        <div class="w-full shrink-0 lg:w-[445px]">
            <div class="sticky top-0">
                <p class="mb-5 flex items-center gap-2 border-b py-2.5 text-[12px] font-semibold transition-border">
                    <button
                        class="-ms-2 inline-grid size-6 place-items-center rounded-full transition hover:scale-110 hover:bg-foreground/5 hover:opacity-100"
                        type="button"
                        @click.prevent="toggleCreateWindow(false)"
                    >
                        <x-tabler-chevron-left class="size-4 rtl:rotate-180" />
                        <span class="sr-only">{{ __('Close') }}</span>
                    </button>

                    {{ __('Video Details') }}
                </p>

                <x-card
                    class:body="p-5"
                    class="lg:max-h-[calc(100vh-90px)] lg:overflow-y-auto"
                >
                    <form
                        class="space-y-5 [--label:var(--heading-foreground)]"
                        :action="'{{ route('dashboard.user.ai-persona.store') }}'"
                        method="POST"
                        @submit.prevent="handleCreateFormSubmit($event)"
                    >
                        <div class="flex flex-col gap-5">
                            <ul class="grid grid-cols-2 gap-3 rounded-button bg-foreground/10 p-1">
                                <li class="w-full">
                                    <button
                                        class="w-full rounded-button px-5 py-3 text-2xs font-medium leading-tight transition-all hover:bg-background/80 lg:min-w-40 [&.active]:bg-background [&.active]:shadow-[0_2px_12px_hsl(0_0%_0%/10%)]"
                                        type="button"
                                        @click.prevent="generateForm.voice_type = 'text'"
                                        :class="{ 'active': generateForm.voice_type === 'text' }"
                                    >
                                        {{ __('Use Text Script') }}
                                    </button>
                                </li>
                                <li class="w-full">
                                    <button
                                        class="w-full rounded-button px-5 py-3 text-2xs font-medium leading-tight transition-all hover:bg-background/80 lg:min-w-40 [&.active]:bg-background [&.active]:shadow-[0_2px_12px_hsl(0_0%_0%/10%)]"
                                        type="button"
                                        @click.prevent="generateForm.voice_type = 'audio'"
                                        :class="{ 'active': generateForm.voice_type === 'audio' }"
                                    >
                                        {{ __('Use Audio') }}
                                    </button>
                                </li>
                            </ul>

                            <div
                                class="space-y-5"
                                x-show="generateForm.voice_type === 'text'"
                            >
                                <x-forms.input
                                    id="input_text"
                                    label="{{ __('Script') }}"
                                    placeholder="{{ __('What do you want your character to say?') }}"
                                    type="textarea"
                                    rows="5"
                                    name="input_text"
                                    x-model="generateForm.input_text"
                                >
                                    <x-slot:label-extra>
                                        <x-button
                                            class="inline-grid size-7 place-items-center p-0 text-2xs hover:scale-105"
                                            type="button"
                                            size="none"
                                            variant="ghost-shadow"
                                            title="{{ __('Generate with AI') }}"
                                            @click.prevent="enhanceScript"
                                        >
                                            <span class="inline-grid place-items-center">
                                                <svg
                                                    class="col-start-1 col-end-1 row-start-1 row-end-1 size-4"
                                                    :class="{ hidden: scriptGeneration }"
                                                    width="17"
                                                    height="17"
                                                    viewBox="0 0 17 17"
                                                    fill="none"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                >
                                                    <path
                                                        class="group-hover:fill-current"
                                                        fill-rule="evenodd"
                                                        clip-rule="evenodd"
                                                        d="M16.5085 6.34955L15.113 6.63248C14.4408 6.7689 13.8236 7.10033 13.3386 7.58536C12.8536 8.0704 12.5221 8.68757 12.3857 9.35981L12.1028 10.7552C12.0748 10.8948 11.9994 11.0203 11.8893 11.1105C11.7792 11.2007 11.6412 11.25 11.4989 11.25C11.3566 11.25 11.2187 11.2007 11.1086 11.1105C10.9985 11.0203 10.923 10.8948 10.895 10.7552L10.6121 9.35981C10.4758 8.68751 10.1444 8.07027 9.65938 7.58522C9.17432 7.10016 8.55709 6.76878 7.8848 6.63248L6.48937 6.34955C6.35011 6.32107 6.22495 6.24537 6.13507 6.13525C6.04519 6.02513 5.99609 5.88733 5.99609 5.74519C5.99609 5.60304 6.04519 5.46526 6.13507 5.35514C6.22495 5.24502 6.35011 5.16932 6.48937 5.14084L7.8848 4.8579C8.55709 4.7216 9.17432 4.39022 9.65938 3.90516C10.1444 3.42011 10.4758 2.80288 10.6121 2.13058L10.895 0.73517C10.923 0.595627 10.9985 0.470081 11.1086 0.379882C11.2187 0.289682 11.3566 0.240395 11.4989 0.240395C11.6412 0.240395 11.7792 0.289682 11.8893 0.379882C11.9994 0.470081 12.0748 0.595627 12.1028 0.73517L12.3857 2.13058C12.5221 2.80283 12.8536 3.41999 13.3386 3.90503C13.8236 4.39007 14.4408 4.72148 15.113 4.8579L16.5085 5.14084C16.6477 5.16932 16.7729 5.24502 16.8627 5.35514C16.9526 5.46526 17.0017 5.60304 17.0017 5.74519C17.0017 5.88733 16.9526 6.02513 16.8627 6.13525C16.7729 6.24537 16.6477 6.32107 16.5085 6.34955ZM6.30231 13.4219L5.92312 13.4989C5.45558 13.5937 5.02634 13.8242 4.689 14.1616C4.35167 14.4989 4.12118 14.9281 4.02633 15.3957L3.94934 15.7749C3.92805 15.881 3.87064 15.9766 3.78687 16.0452C3.70309 16.1139 3.59813 16.1514 3.48982 16.1514C3.38151 16.1514 3.27654 16.1139 3.19277 16.0452C3.10899 15.9766 3.05157 15.881 3.03029 15.7749L2.9533 15.3957C2.85844 14.9281 2.62796 14.4989 2.29062 14.1616C1.95328 13.8242 1.52404 13.5937 1.0565 13.4989L0.677333 13.4219C0.571137 13.4006 0.475582 13.3432 0.406935 13.2594C0.338287 13.1756 0.300781 13.0707 0.300781 12.9624C0.300781 12.854 0.338287 12.7491 0.406935 12.6653C0.475582 12.5815 0.571137 12.5241 0.677333 12.5028L1.0565 12.4258C1.52404 12.331 1.95328 12.1005 2.29062 11.7632C2.62796 11.4258 2.85844 10.9966 2.9533 10.5291L3.03029 10.1499C3.05157 10.0437 3.10899 9.94813 3.19277 9.87948C3.27654 9.81083 3.38151 9.77334 3.48982 9.77334C3.59813 9.77334 3.70309 9.81083 3.78687 9.87948C3.87064 9.94813 3.92805 10.0437 3.94934 10.1499L4.02633 10.5291C4.12118 10.9966 4.35167 11.4258 4.689 11.7632C5.02634 12.1005 5.45558 12.331 5.92312 12.4258L6.30231 12.5028C6.4085 12.5241 6.50404 12.5815 6.57269 12.6653C6.64134 12.7491 6.67884 12.854 6.67884 12.9624C6.67884 13.0707 6.64134 13.1756 6.57269 13.2594C6.50404 13.3432 6.4085 13.4006 6.30231 13.4219Z"
                                                        fill="url(#paint0_linear_8906_3722)"
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
                                            </span>
                                            <span class="sr-only">
                                                {{ __('Generate with AI') }}
                                            </span>
                                        </x-button>
                                    </x-slot:label-extra>
                                </x-forms.input>

                                <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                                    <span class="lqd-input-label-txt">
                                        {{ __('Select a Voice') }}
                                    </span>
                                </label>
                                <div class="flex items-end gap-4">
                                    <x-dropdown.dropdown
                                        class="w-full grow"
                                        class:dropdown="w-[min(calc(100vw-30px),400px)] max-h-[min(75vh,500px)] overflow-y-auto bg-dropdown-background/90 px-2 pb-4 pt-0 shadow-[0_10px_15px_-3px_hsl(0_0%_0%/10%)] backdrop-blur-xl"
                                        triggerType="click"
                                        offsetY="0.25rem"
                                    >
                                        <x-slot:trigger
                                            class="h-11 min-h-10 w-full justify-between overflow-hidden !rounded-input text-start capitalize hover:shadow-none"
                                            variant="outline"
                                            type="button"
                                        >
                                            <span
                                                class="w-full grow truncate"
                                                x-text="activeVoiceLabel"
                                            ></span>

                                            <x-tabler-chevron-down class="size-4 shrink-0" />
                                        </x-slot:trigger>

                                        <x-slot:dropdown>
                                            <div class="sticky top-0 z-10 -mx-2 mb-2 mt-1 px-2 pt-2 backdrop-blur-lg">
                                                <x-forms.input
                                                    class="mb-3 shadow-md shadow-black/5"
                                                    type="search"
                                                    placeholder="{{ __('Search voices...') }}"
                                                    x-model="voicesSearch"
                                                />
                                            </div>

                                            <div class="space-y-0.5">
                                                <template
                                                    x-for="voice in visibleVoices"
                                                    :key="voice.voice_id"
                                                >
                                                    <button
                                                        class="group/trigger-btn flex w-full items-center justify-normal gap-2.5 overflow-hidden rounded-lg px-4 py-2.5 text-start text-2xs font-medium capitalize text-heading-foreground transition hover:bg-foreground/5"
                                                        x-data="{ hovering: false }"
                                                        :class="{ 'active': generateForm.voice_id === voice.voice_id }"
                                                        type="button"
                                                        @click.prevent="generateForm.voice_id = voice.voice_id; toggle(false)"
                                                        @mouseenter="hovering = true"
                                                        @mouseleave="hovering = false"
                                                    >
                                                        <span
                                                            class="group inline-grid size-6 shrink-0 place-items-center rounded-lg border bg-background transition hover:bg-primary hover:text-primary-foreground"
                                                            type="button"
                                                            title="{{ __('Preview') }}"
                                                            @click.prevent.stop="playPreview(voice.voice_id)"
                                                        >
                                                            <x-tabler-player-play
                                                                class="size-3.5 fill-current"
                                                                x-show="!audioPreviewPlaying || audioPreviewPlaying !== voice.voice_id"
                                                            />
                                                            <x-tabler-volume
                                                                class="size-3.5"
                                                                x-cloak
                                                                x-show="audioPreviewPlaying === voice.voice_id"
                                                            />
                                                        </span>

                                                        <span
                                                            class="w-full truncate"
                                                            x-text="`${voice.name} - ${voice.language} - ${voice.gender}`"
                                                        ></span>

                                                        <template x-if="generateForm.voice_id === voice.voice_id">
                                                            <x-tabler-check class="ms-auto size-5 shrink-0" />
                                                        </template>
                                                    </button>
                                                </template>

                                                <div
                                                    class="px-4 py-3 text-center text-3xs opacity-60"
                                                    x-show="voicesVisible < filteredVoices.length"
                                                    x-intersect.margin.200px="loadMoreVoices()"
                                                    x-cloak
                                                >
                                                    {{ __('Loading more voices...') }}
                                                </div>

                                                <p
                                                    class="m-0 px-4 py-6 text-center text-2xs opacity-60"
                                                    x-show="filteredVoices.length === 0"
                                                    x-cloak
                                                >
                                                    {{ __('No voices match your search.') }}
                                                </p>
                                            </div>
                                        </x-slot:dropdown>
                                    </x-dropdown.dropdown>
                                    <!-- Hidden input for voice_id -->
                                    <input
                                        type="hidden"
                                        name="voice_id"
                                        x-model="generateForm.voice_id"
                                    >
                                </div>

                                <div x-data="{ showMoreVoiceOptions: false }">
                                    <button
                                        class="m-0 flex w-full items-center gap-4 text-2xs font-medium"
                                        type="button"
                                        @click.prevent="showMoreVoiceOptions = !showMoreVoiceOptions"
                                    >
                                        <span class="inline-flex h-px grow bg-current opacity-5"></span>
                                        <span class="inline-flex items-center gap-2">
                                            {{ __('More Voice Options') }}
                                            <x-tabler-chevron-down
                                                class="size-4 transition"
                                                ::class="{ 'rotate-180': showMoreVoiceOptions }"
                                            />
                                        </span>
                                        <span class="inline-flex h-px grow bg-current opacity-5"></span>
                                    </button>
                                    <div
                                        x-show="showMoreVoiceOptions"
                                        x-cloak
                                        x-collapse
                                    >
                                        <div class="space-y-5 pt-5">
                                            <x-forms.input
                                                id="voice_emotion"
                                                size="lg"
                                                type="select"
                                                label="{{ __('Emotion') }}"
                                                name="voice_emotion"
                                                x-model="generateForm.voice_emotion"
                                            >
                                                <option value="">
                                                    {{ __('Choose Emotion') }}
                                                </option>
                                                <option value="Excited">
                                                    {{ __('Excited') }}
                                                </option>
                                                <option value="Friendly">
                                                    {{ __('Friendly') }}
                                                </option>
                                                <option value="Serious">
                                                    {{ __('Serious') }}
                                                </option>
                                                <option value="Soothing">
                                                    {{ __('Soothing') }}
                                                </option>
                                                <option value="Broadcaster">
                                                    {{ __('Broadcaster') }}
                                                </option>
                                            </x-forms.input>

                                            <div>
                                                <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                                                    <span class="lqd-input-label-txt">
                                                        {{ __('Locale') }}
                                                    </span>
                                                </label>
                                                <x-dropdown.dropdown
                                                    class="w-full grow"
                                                    class:dropdown="w-[min(calc(100vw-30px),400px)] max-h-[min(75vh,500px)] overflow-y-auto bg-dropdown-background/90 px-2 pb-4 pt-0 shadow-[0_10px_15px_-3px_hsl(0_0%_0%/10%)] backdrop-blur-xl"
                                                    triggerType="click"
                                                    offsetY="0.25rem"
                                                >
                                                    <x-slot:trigger
                                                        class="h-11 min-h-10 w-full justify-between overflow-hidden !rounded-input text-start hover:shadow-none"
                                                        variant="outline"
                                                        type="button"
                                                    >
                                                        <span
                                                            class="w-full grow truncate"
                                                            x-text="activeLocaleLabel"
                                                        ></span>

                                                        <x-tabler-chevron-down class="size-4 shrink-0" />
                                                    </x-slot:trigger>

                                                    <x-slot:dropdown>
                                                        <div class="sticky top-0 z-10 -mx-2 mb-2 mt-1 px-2 pt-2 backdrop-blur-lg">
                                                            <x-forms.input
                                                                class="mb-3 shadow-md shadow-black/5"
                                                                type="search"
                                                                placeholder="{{ __('Search locales...') }}"
                                                                x-model="localesSearch"
                                                            />
                                                        </div>

                                                        <div class="space-y-0.5">
                                                            <button
                                                                class="flex w-full items-center justify-normal gap-2.5 overflow-hidden rounded-lg px-4 py-2.5 text-start text-2xs font-medium text-heading-foreground transition hover:bg-foreground/5"
                                                                :class="{ 'active': !generateForm.locale }"
                                                                type="button"
                                                                @click.prevent="generateForm.locale = ''; toggle(false)"
                                                            >
                                                                <span class="w-full truncate">
                                                                    {{ __('Default (voice language)') }}
                                                                </span>
                                                                <template x-if="!generateForm.locale">
                                                                    <x-tabler-check class="ms-auto size-5 shrink-0" />
                                                                </template>
                                                            </button>

                                                            <template
                                                                x-for="locale in filteredLocales"
                                                                :key="locale.locale || locale.language_code"
                                                            >
                                                                <button
                                                                    class="flex w-full items-center justify-normal gap-2.5 overflow-hidden rounded-lg px-4 py-2.5 text-start text-2xs font-medium text-heading-foreground transition hover:bg-foreground/5"
                                                                    :class="{ 'active': generateForm.locale === (locale.locale || locale.language_code) }"
                                                                    type="button"
                                                                    @click.prevent="generateForm.locale = locale.locale || locale.language_code; toggle(false)"
                                                                >
                                                                    <span
                                                                        class="w-full truncate"
                                                                        x-text="locale.label || locale.value || locale.locale"
                                                                    ></span>
                                                                    <span
                                                                        class="shrink-0 text-3xs opacity-50"
                                                                        x-text="locale.locale || locale.language_code"
                                                                    ></span>
                                                                    <template x-if="generateForm.locale === (locale.locale || locale.language_code)">
                                                                        <x-tabler-check class="ms-auto size-5 shrink-0" />
                                                                    </template>
                                                                </button>
                                                            </template>

                                                            <p
                                                                class="m-0 px-4 py-6 text-center text-2xs opacity-60"
                                                                x-show="filteredLocales.length === 0"
                                                                x-cloak
                                                            >
                                                                {{ __('No locales match your search.') }}
                                                            </p>
                                                        </div>
                                                    </x-slot:dropdown>
                                                </x-dropdown.dropdown>
                                                <input
                                                    type="hidden"
                                                    name="locale"
                                                    x-model="generateForm.locale"
                                                >
                                            </div>
                                            <div class="flex w-full items-center gap-4">
                                                <label
                                                    class="text-2xs font-medium text-label"
                                                    for="voice_speed"
                                                >
                                                    {{ __('Speed') }}
                                                </label>
                                                <input
                                                    class="h-1 w-full appearance-none rounded-full bg-foreground/5 [&::-moz-range-thumb]:size-3 [&::-moz-range-thumb]:appearance-none [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border-none [&::-moz-range-thumb]:bg-foreground active:[&::-moz-range-thumb]:scale-110 [&::-webkit-slider-thumb]:size-3 [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:border-none [&::-webkit-slider-thumb]:bg-foreground active:[&::-webkit-slider-thumb]:scale-110"
                                                    id="voice_speed"
                                                    name="voice_speed"
                                                    x-model="generateForm.voice_speed"
                                                    type="range"
                                                    min="0.5"
                                                    max="1.5"
                                                    step="0.1"
                                                />
                                                <input
                                                    class="w-10 border-none bg-transparent text-center text-2xs font-semibold"
                                                    type="number"
                                                    min="0.5"
                                                    max="1.5"
                                                    step="0.1"
                                                    x-model.number="generateForm.voice_speed"
                                                ></input>
                                            </div>
                                            <div class="flex w-full items-center gap-4">
                                                <label
                                                    class="text-2xs font-medium text-label"
                                                    for="voice_pitch"
                                                >
                                                    {{ __('Pitch') }}
                                                </label>
                                                <input
                                                    class="h-1 w-full appearance-none rounded-full bg-foreground/5 [&::-moz-range-thumb]:size-3 [&::-moz-range-thumb]:appearance-none [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border-none [&::-moz-range-thumb]:bg-foreground active:[&::-moz-range-thumb]:scale-110 [&::-webkit-slider-thumb]:size-3 [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:border-none [&::-webkit-slider-thumb]:bg-foreground active:[&::-webkit-slider-thumb]:scale-110"
                                                    id="voice_pitch"
                                                    name="voice_pitch"
                                                    x-model="generateForm.voice_pitch"
                                                    type="range"
                                                    min="-50"
                                                    max="50"
                                                    step="1"
                                                />
                                                <input
                                                    class="w-10 border-none bg-transparent text-center text-2xs font-semibold"
                                                    type="number"
                                                    min="-50"
                                                    max="50"
                                                    step="1"
                                                    x-model.number="generateForm.voice_pitch"
                                                ></input>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="space-y-5"
                                x-show="generateForm.voice_type === 'audio'"
                                x-cloak
                            >
                                <label
                                    class="lqd-filepicker-label block cursor-pointer rounded-input border border-dashed px-4 py-5 text-center transition hover:border-primary/50 hover:bg-primary/[3%]"
                                    for="audio_file"
                                >
                                    <svg
                                        class="mx-auto mb-2.5 opacity-25"
                                        width="24"
                                        height="24"
                                        viewBox="0 0 24 24"
                                        fill="currentColor"
                                        xmlns="http://www.w3.org/2000/svg"
                                    >
                                        <path
                                            d="M20.4677 20.5161C18.1452 22.8387 15.3226 24 12 24C8.67742 24 5.83871 22.8387 3.48387 20.5161C1.16129 18.1613 0 15.3226 0 12C0 8.67742 1.16129 5.85484 3.48387 3.53226C5.83871 1.17742 8.67742 0 12 0C15.3226 0 18.1452 1.17742 20.4677 3.53226C22.8226 5.85484 24 8.67742 24 12C24 15.3226 22.8226 18.1613 20.4677 20.5161ZM18.8226 5.17742C16.9516 3.27419 14.6774 2.32258 12 2.32258C9.32258 2.32258 7.03226 3.27419 5.12903 5.17742C3.25806 7.04839 2.32258 9.32258 2.32258 12C2.32258 14.6774 3.25806 16.9677 5.12903 18.871C7.03226 20.7419 9.32258 21.6774 12 21.6774C14.6774 21.6774 16.9516 20.7419 18.8226 18.871C20.7258 16.9677 21.6774 14.6774 21.6774 12C21.6774 9.32258 20.7258 7.04839 18.8226 5.17742ZM12.9677 18.1935H11.0323C10.6452 18.1935 10.4516 18 10.4516 17.6129V15C10.4516 13.3431 9.10847 12 7.45161 12H7.20968C6.95161 12 6.77419 11.8871 6.67742 11.6613C6.58065 11.4032 6.6129 11.1935 6.77419 11.0323L11.6129 6.19355C11.871 5.93548 12.129 5.93548 12.3871 6.19355L17.2258 11.0323C17.3871 11.1935 17.4194 11.4032 17.3226 11.6613C17.2258 11.8871 17.0484 12 16.7903 12H16.5484C14.8915 12 13.5484 13.3431 13.5484 15V17.6129C13.5484 18 13.3548 18.1935 12.9677 18.1935Z"
                                        />
                                    </svg>
                                    <p
                                        class="mb-2 text-xs font-medium"
                                        x-text="generateForm.audio_file_name.trim() || '{{ __('Upload Audio') }}'"
                                    >
                                        {{ __('Upload Audio') }}
                                    </p>
                                    <p class="file-name mb-0 text-4xs opacity-50">
                                        {{ __('MP3, WAV or WebM. Max 25Mb.') }}
                                    </p>
                                    <input
                                        class="hidden"
                                        id="audio_file"
                                        type="file"
                                        accept="audio/mpeg,audio/mp3,audio/wav,audio/webm"
                                        name="audio_file"
                                        size="lg"
                                        label="{{ __('Upload Voice Audio') }}"
                                        @change="handleAudioFileSelect($event)"
                                    ></input>
                                </label>
                            </div>
                        </div>

                        <x-forms.input
                            id="avatar_style"
                            size="lg"
                            type="select"
                            label="{{ __('Avatar Style') }}"
                            name="avatar_style"
                            required
                            x-model="generateForm.avatar_style"
                        >
                            <option value="normal">
                                {{ __('Normal') }}
                            </option>
                            <option value="circle">
                                {{ __('Circle') }}
                            </option>
                            <option value="closeUp">
                                {{ __('CloseUp') }}
                            </option>
                        </x-forms.input>

                        <x-forms.input
                            id="circle_background_color"
                            size="lg"
                            type="color"
                            label="{{ __('Circle Background Color') }}"
                            name="circle_background_color"
                            x-model="generateForm.circle_background_color"
                            value="#ffffff"
                            x-show="generateForm.avatar_style === 'circle'"
                        />

                        <x-forms.input
                            class:label="flex-row-reverse justify-between"
                            id="matting"
                            size="sm"
                            type="checkbox"
                            name="matting"
                            label="{{ __('Remove Background') }}"
                            switcher
                            x-model="generateForm.matting"
                        />

                        <x-forms.input
                            class:label="flex-row-reverse justify-between"
                            id="caption"
                            size="sm"
                            type="checkbox"
                            name="caption"
                            label="{{ __('Add Captions') }}"
                            switcher
                            x-model="generateForm.caption"
                        />

                        <div>
                            <x-forms.input
                                class:label="flex-row-reverse justify-between"
                                id="use_custom_dimensions"
                                size="sm"
                                type="checkbox"
                                name="use_custom_dimensions"
                                label="{{ __('Use Custom Dimensions') }}"
                                switcher
                                x-model="generateForm.use_custom_dimensions"
                                @change="if (!generateForm.use_custom_dimensions) { generateForm.dimension_width = 1920; generateForm.dimension_height = 1080; }"
                            />

                            <div
                                x-show="generateForm.use_custom_dimensions"
                                x-cloak
                                x-collapse
                            >
                                <div class="grid grid-cols-2 gap-4 pt-4">
                                    <x-forms.input
                                        id="dimension_width"
                                        size="lg"
                                        type="number"
                                        label="{{ __('Width') }}"
                                        name="dimension_width"
                                        min="1"
                                        step="1"
                                        x-model.number="generateForm.dimension_width"
                                    />
                                    <x-forms.input
                                        id="dimension_height"
                                        size="lg"
                                        type="number"
                                        label="{{ __('Height') }}"
                                        name="dimension_height"
                                        min="1"
                                        step="1"
                                        x-model.number="generateForm.dimension_height"
                                    />
                                </div>
                            </div>
                        </div>

                        <input
                            type="hidden"
                            name="avatar_id"
                            x-model="generateForm.avatar_id"
                        >

                        <input
                            type="hidden"
                            name="voice_type"
                            x-model="generateForm.voice_type"
                        >

                        @csrf

                        <div class="pt-4">
                            @if ($app_is_demo)
                                <x-button
                                    class="w-full p-4 text-xs font-semibold"
                                    type="button"
                                    size="lg"
                                    onclick="return toastr.info('This feature is disabled in Demo version.');"
                                >
                                    {{ __('Generate Video') }}
                                </x-button>
                            @else
                                <x-button
                                    class="w-full p-4 text-xs font-semibold"
                                    type="submit"
                                    size="lg"
                                    ::disabled="creating"
                                >
                                    <x-tabler-refresh
                                        class="me-2 size-4 animate-spin"
                                        x-show="creating"
                                        x-cloak
                                    />
                                    <span x-text="creating ? '{{ __('Generating...') }}' : '{{ __('Generate Video') }}'"></span>
                                </x-button>
                            @endif

                            <div x-init="$nextTick(() => $dispatch('generator-changed', { generator: 'heygen', quantity: 1 }))">
                                <x-cost-preview class="w-full justify-end" />
                            </div>
                        </div>
                    </form>
                </x-card>
            </div>
        </div>

        <div class="w-full grow">
            <div class="sticky top-0 w-full">
                <div class="mb-5 flex gap-6 border-b transition-border max-sm:overflow-hidden max-sm:pb-px">
                    <div class="relative max-w-[calc(100%-50px)] grow">
                        <div
                            class="flex grow items-center gap-6 transition max-sm:overflow-x-auto max-sm:overflow-y-hidden max-sm:whitespace-nowrap [&.avatars-search-visible]:pointer-events-none [&.avatars-search-visible]:invisible [&.avatars-search-visible]:opacity-0"
                            :class="{ 'avatars-search-visible': avatarsSearch.visible }"
                        >
                            <button
                                class="-mb-px border-b border-current py-2.5 text-[12px] font-semibold text-heading-foreground transition"
                                type="button"
                                @click.prevent=""
                            >
                                {{ __('Pick an Avatar') }}
                            </button>
                            <button
                                class="-mb-px border-b border-transparent py-2.5 text-[12px] font-semibold text-heading-foreground opacity-50 transition hover:opacity-100"
                                type="button"
                                @click.prevent="uploadModalActiveTab = 'upload'; toggleUploadModal(true)"
                            >
                                {{ __('Upload Your Avatar') }}
                            </button>
                            <button
                                class="-mb-px border-b border-transparent py-2.5 text-[12px] font-semibold text-heading-foreground opacity-50 transition hover:opacity-100"
                                type="button"
                                @click.prevent="uploadModalActiveTab = 'create'; toggleUploadModal(true)"
                            >
                                {{ __('Create Your Avatar') }}
                            </button>
                        </div>

                        <form
                            class="absolute inset-0 m-0"
                            x-cloak
                            x-show="avatarsSearch.visible"
                            x-transition.opacity
                            @submit.prevent="searchAvatars"
                        >
                            <input
                                class="h-full w-full rounded-none border-none bg-transparent placeholder:text-foreground focus-visible:outline-none sm:text-[12px] sm:font-medium"
                                type="search"
                                placeholder="{{ __('Search avatars...') }}"
                                x-model="avatarsSearch.query"
                                x-init="$watch('avatarsSearch.visible', value => { if (value) { $nextTick(() => { $el.focus() }) } })"
                            >
                        </form>
                    </div>

                    <button
                        class="ms-auto inline-grid size-6 shrink-0 place-items-center self-center rounded-full transition hover:scale-110 hover:bg-foreground/5 hover:opacity-100"
                        type="button"
                        @click.prevent="avatarsSearch.visible = !avatarsSearch.visible"
                    >
                        <x-tabler-search
                            class="size-4"
                            x-show="!avatarsSearch.visible"
                        />
                        <x-tabler-x
                            class="size-4"
                            x-cloak
                            x-show="avatarsSearch.visible"
                        />
                        <span class="sr-only">{{ __('Search') }}</span>
                    </button>
                </div>

                <div class="grid grid-cols-2 items-start gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <template
                        x-for="avatar in visibleAvatars"
                        :key="avatar.avatar_id"
                    >
                        <div
                            class="group relative rounded border p-2.5 transition-all hover:-translate-y-1 hover:shadow-lg hover:shadow-black/5 [&.selected]:ring-4 [&.selected]:ring-primary"
                            x-data="{ hovering: false }"
                            @mouseenter="hovering = true"
                            @mouseleave="hovering = false"
                            :class="{ 'selected': generateForm.avatar_id === avatar.avatar_id }"
                            @click="generateForm.avatar_id = generateForm.avatar_id === avatar.avatar_id ? null : avatar.avatar_id"
                        >
                            <div
                                class="invisible absolute end-4 top-4 z-10 inline-grid size-9 scale-90 place-items-center rounded-full bg-secondary text-secondary-foreground opacity-0 shadow-lg shadow-black/5 transition group-[&.selected]:visible group-[&.selected]:scale-100 group-[&.selected]:opacity-100">
                                <x-tabler-check class="size-5" />
                            </div>

                            <div class="relative mb-2 aspect-[1/0.9866] w-full overflow-hidden rounded">
                                <img
                                    class="size-full object-cover"
                                    :src="avatar.preview_image_url"
                                    :alt="avatar.avatar_name"
                                    loading="lazy"
                                >
                                <template x-if="hovering && avatar.preview_video_url">
                                    <div class="absolute inset-0 size-full">
                                        <video
                                            class="size-full object-cover"
                                            :src="avatar.preview_video_url"
                                            autoplay
                                            muted
                                            loop
                                            playsinline
                                        ></video>

                                        <span
                                            class="absolute start-2 top-2 z-2 inline-flex items-center gap-1 rounded-full bg-background/75 px-2 py-1 text-3xs font-medium text-foreground backdrop-blur-lg"
                                        >
                                            <x-tabler-player-play class="size-3 fill-current" />
                                            {{ __('Preview') }}
                                        </span>
                                    </div>
                                </template>
                            </div>
                            <div class="flex items-end justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="m-0 truncate text-3xs font-medium capitalize opacity-60"
                                        x-text="avatar.gender"
                                    ></p>
                                    <p
                                        class="m-0 truncate text-2xs font-medium"
                                        x-text="avatar.avatar_name"
                                    ></p>
                                </div>
                                <template x-if="avatar.is_custom">
                                    <span class="inline-flex shrink-0 items-center rounded-full bg-primary/10 px-2 py-0.5 text-3xs font-semibold text-primary">
                                        {{ __('Custom') }}
                                    </span>
                                </template>
                            </div>
                        </div>
                    </template>

                    <div
                        class="col-span-full py-4 text-center text-3xs opacity-60"
                        x-show="avatarsVisible < filteredAvatars.length"
                        x-intersect.margin.200px="loadMoreAvatars()"
                        x-cloak
                    >
                        {{ __('Loading more avatars...') }}
                    </div>
                </div>

                <div
                    class="py-12 text-center text-xs opacity-60"
                    x-show="filteredAvatars.length === 0"
                    x-cloak
                >
                    {{ __('No avatars match your search.') }}
                </div>
            </div>
        </div>
    </div>
</template>
