<div class="rounded-card border border-card-border bg-card-background">
    @if (! $usageInfo['unlimited'])
        <div class="border-b border-card-border p-5 pb-4">
            <div class="mb-1.5 flex items-center justify-between text-2xs">
                <span class="text-foreground/60">@lang('Monthly Usage')</span>
                <span class="font-medium">
                    {{ gmdate('H:i:s', $usageInfo['used']) }} / {{ gmdate('H:i:s', $usageInfo['limit']) }}
                </span>
            </div>
            @php
                $usagePercent = $usageInfo['limit'] > 0 ? min(100, round(($usageInfo['used'] / $usageInfo['limit']) * 100)) : 0;
            @endphp
            <div class="h-1.5 w-full overflow-hidden rounded-full bg-foreground/10">
                <div
                    class="h-full rounded-full transition-all {{ $usagePercent >= 90 ? 'bg-red-500' : 'bg-primary' }}"
                    style="width: {{ $usagePercent }}%"
                ></div>
            </div>
        </div>
    @endif

    @php
        $sourceTabs = $provider === 'heygen'
            ? ['youtube', 'url']
            : ['youtube', 'tiktok', 'upload'];
        $sourceTabsJson = json_encode($sourceTabs);
    @endphp

    <form
        class="flex flex-col gap-4 p-5"
        method="POST"
        enctype="multipart/form-data"
        action="{{ LaravelLocalization::localizeUrl(route('dashboard.user.video-dubbing.store')) }}"
        x-data="{
            sourceType: '{{ $sourceTabs[0] }}',
            loading: false,
            fileName: '',
            dragOver: false,
            languagesOpen: false,
            languageSearch: '',
            targetLanguages: [],
            languageList: {{ Js::from(collect($languages)->map(fn ($name, $code) => ['code' => $code, 'name' => __($name)])->values()) }},
            advancedValues: {
                mode: 'fast',
                translate_audio_only: false,
                source_language: 'auto',
                highest_resolution: false,
                drop_background_audio: false,
                use_profanity_filter: false,
                title: '',
            },

            get languageLabel() {
                if (this.targetLanguages.length === 0) {
                    return '{{ __('Select language...') }}';
                }
                const names = this.targetLanguages
                    .map(code => (this.languageList.find(l => l.code === code) || {}).name)
                    .filter(Boolean);
                if (names.length <= 3) {
                    return names.join(', ');
                }
                const remaining = names.length - 2;
                const tmpl = '{{ __('& :count more') }}';
                return names.slice(0, 2).join(', ') + ' ' + tmpl.replace(':count', remaining);
            },

            get filteredLanguages() {
                const q = this.languageSearch.trim().toLowerCase();
                if (!q) return this.languageList;
                return this.languageList.filter(l =>
                    l.name.toLowerCase().includes(q) || l.code.toLowerCase().includes(q)
                );
            },

            toggleLanguage(code) {
                const idx = this.targetLanguages.indexOf(code);
                if (idx === -1) {
                    this.targetLanguages.push(code);
                } else {
                    this.targetLanguages.splice(idx, 1);
                }
            },

            setSourceType(type) {
                this.sourceType = type;
                this.fileName = '';
            },

            handleFileSelect(event) {
                const file = event.target.files[0];
                if (file) {
                    this.fileName = file.name;
                }
            },

            handleDrop(event) {
                this.dragOver = false;
                const file = event.dataTransfer.files[0];
                if (file) {
                    this.$refs.videoFile.files = event.dataTransfer.files;
                    this.fileName = file.name;
                }
            },

            tabLabel(tab) {
                const labels = { youtube: 'YouTube', tiktok: 'TikTok', upload: '{{ __('Upload') }}', url: 'URL' };
                return labels[tab] || tab;
            },

            async submitForm(event) {
                if (this.loading) return;
                this.loading = true;

                try {
                    const formData = new FormData(event.target);
                    const response = await fetch(event.target.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: formData,
                    });

                    let data = {};
                    try { data = await response.json(); } catch (e) {}

                    if (response.ok && data.success) {
                        if (typeof toastr !== 'undefined') {
                            toastr.success(data.message);
                        }

                        const wrap = document.getElementById('lqd-dubbing-videos-wrap');
                        if (wrap && data.videosHtml) {
                            wrap.outerHTML = data.videosHtml;
                        }

                        event.target.reset();
                        this.targetLanguages = [];
                        this.fileName = '';

                        if (typeof checkDubbingStatus === 'function') {
                            checkDubbingStatus();
                        }
                    } else {
                        const msg = (data && data.message) || '{{ __('An error occurred. Please try again.') }}';
                        if (typeof toastr !== 'undefined') {
                            toastr.error(msg);
                        } else {
                            alert(msg);
                        }
                    }
                } catch (e) {
                    const msg = '{{ __('An error occurred. Please try again.') }}';
                    if (typeof toastr !== 'undefined') {
                        toastr.error(msg);
                    } else {
                        alert(msg);
                    }
                } finally {
                    this.loading = false;
                }
            },
        }"
        @submit.prevent="submitForm($event)"
    >
        @csrf
        <input type="hidden" name="source_type" :value="sourceType">

        {{-- Source Type Tabs --}}
        <div>
            <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                {{ __('Source') }}
            </label>
            <div class="lqd-tabs-nav flex w-full gap-1 rounded-full bg-foreground/[7%] p-1 text-xs font-medium">
                <template x-for="tab in {{ $sourceTabsJson }}" :key="tab">
                    <button
                        type="button"
                        class="lqd-tabs-nav-trigger min-w-0 flex-1 basis-0 whitespace-nowrap rounded-full px-2 py-2 text-center text-xs font-medium leading-tight transition-all hover:bg-background/90 [&.active]:bg-background [&.active]:shadow-[0_2px_13px_hsl(0_0%_0%/10%)]"
                        :class="{ 'active': sourceType === tab }"
                        @click="setSourceType(tab)"
                        x-text="tabLabel(tab)"
                    ></button>
                </template>
            </div>
        </div>

        {{-- Upload Video (ElevenLabs only, shown for upload tab) --}}
        @if ($provider === 'elevenlabs')
            <div x-show="sourceType === 'upload'" x-cloak>
                <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                    {{ __('Upload Video') }}
                </label>
                <label
                    class="lqd-filepicker-label flex min-h-34 w-full cursor-pointer flex-col items-center justify-center rounded-card border-2 border-dashed border-foreground/10 bg-background text-center transition-colors hover:bg-background/80"
                    :class="{ '!border-primary !bg-primary/5': dragOver }"
                    @dragover.prevent="dragOver = true"
                    @dragleave.prevent="dragOver = false"
                    @drop.prevent="handleDrop($event)"
                >
                    <div class="flex flex-col items-center justify-center py-6">
                        <template x-if="!fileName">
                            <div class="flex flex-col items-center gap-2">
                                <x-tabler-cloud-upload class="mb-2 size-11" stroke-width="1.5" />
                                <p class="mb-1 text-sm font-semibold">
                                    {{ __('Drop your file here or browse.') }}
                                </p>
                                <p class="file-name mb-0 text-2xs">
                                    {{ __('(MP4, MOV, AVI, WebM — Max 500MB)') }}
                                </p>
                            </div>
                        </template>
                        <template x-if="fileName">
                            <div class="flex items-center gap-2 text-center">
                                <x-tabler-file-type-doc class="size-5 text-primary" />
                                <span class="text-sm font-medium" x-text="fileName"></span>
                            </div>
                        </template>
                    </div>
                    <input
                        type="file"
                        name="video_file"
                        x-ref="videoFile"
                        class="hidden"
                        accept="video/mp4,video/quicktime,video/x-msvideo,video/webm"
                        @change="handleFileSelect($event)"
                    >
                </label>
            </div>
        @endif

        {{-- Video URL (shown for URL-based tabs) --}}
        <div x-show="sourceType !== 'upload'" x-cloak>
            <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                {{ __('Video URL') }}
            </label>
            <x-forms.input
                name="video_url"
                type="url"
                size="lg"
                ::placeholder="sourceType === 'youtube' ? 'youtube.com/watch?v=...' : (sourceType === 'tiktok' ? 'tiktok.com/@user/video/...' : 'https://example.com/video.mp4')"
                label=""
            />
        </div>

        {{-- Target Languages (multi-select) --}}
        <div>
            <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                {{ __('Target Language') }}
                <span
                    class="ms-auto rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-semibold text-primary"
                    x-show="targetLanguages.length > 0"
                    x-text="targetLanguages.length"
                    x-cloak
                ></span>
            </label>

            <div class="relative" @click.outside="languagesOpen = false">
                <button
                    type="button"
                    class="flex h-11 w-full items-center justify-between gap-2 rounded-input border border-input-border bg-input-background px-4 text-start text-sm transition-colors hover:border-foreground/30"
                    @click="languagesOpen = !languagesOpen"
                >
                    <span
                        class="truncate"
                        :class="targetLanguages.length === 0 ? 'text-foreground/50' : ''"
                        x-text="languageLabel"
                    ></span>
                    <x-tabler-chevron-down class="size-4 shrink-0 opacity-60 transition-transform" ::class="languagesOpen ? 'rotate-180' : ''" />
                </button>

                <div
                    class="absolute inset-x-0 top-full z-30 mt-1 overflow-hidden rounded-dropdown border border-card-border bg-card-background shadow-lg"
                    x-show="languagesOpen"
                    x-cloak
                    x-transition.opacity
                >
                    <div class="border-b border-card-border p-2">
                        <input
                            type="text"
                            class="w-full rounded-md border border-input-border bg-input-background px-3 py-1.5 text-xs focus:border-primary focus:outline-none"
                            placeholder="{{ __('Search languages...') }}"
                            x-model="languageSearch"
                        >
                    </div>
                    <div class="max-h-56 overflow-y-auto p-1">
                        <template x-for="lang in filteredLanguages" :key="lang.code">
                            <button
                                type="button"
                                class="flex w-full items-center gap-2 rounded-md px-3 py-1.5 text-start text-xs transition-colors hover:bg-foreground/5"
                                :class="targetLanguages.includes(lang.code) ? 'bg-primary/10 text-primary' : ''"
                                @click="toggleLanguage(lang.code)"
                            >
                                <span
                                    class="flex size-4 shrink-0 items-center justify-center rounded border"
                                    :class="targetLanguages.includes(lang.code) ? 'border-primary bg-primary text-background' : 'border-foreground/30'"
                                >
                                    <template x-if="targetLanguages.includes(lang.code)">
                                        <x-tabler-check class="size-3" />
                                    </template>
                                </span>
                                <span x-text="lang.name"></span>
                            </button>
                        </template>
                        <template x-if="filteredLanguages.length === 0">
                            <p class="px-3 py-4 text-center text-2xs text-foreground/50">{{ __('No languages match your search.') }}</p>
                        </template>
                    </div>
                    <div class="flex items-center justify-between border-t border-card-border bg-background/50 px-3 py-2 text-2xs">
                        <button
                            type="button"
                            class="font-medium text-foreground/70 hover:text-foreground"
                            @click="targetLanguages = []"
                            x-show="targetLanguages.length > 0"
                        >
                            {{ __('Clear') }}
                        </button>
                        <button
                            type="button"
                            class="ms-auto font-medium text-primary"
                            @click="languagesOpen = false"
                        >
                            {{ __('Done') }}
                        </button>
                    </div>
                </div>
            </div>

            <template x-for="code in targetLanguages" :key="'tl-' + code">
                <input type="hidden" name="target_languages[]" :value="code">
            </template>
        </div>

        {{-- Number of Speakers --}}
        <div>
            <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                {{ __('Number of Speakers') }}
            </label>
            <x-forms.input
                name="speaker_num"
                type="select"
                size="lg"
                label=""
            >
                <option value="0">@lang('Auto Detect')</option>
                @for ($i = 1; $i <= 10; $i++)
                    <option value="{{ $i }}">{{ $i }}</option>
                @endfor
            </x-forms.input>
        </div>

        {{-- Advanced Options (Pill Style) --}}
        <div>
            <div class="mb-3 flex items-center gap-1.5">
                <span class="text-2xs font-medium leading-none text-label">{{ __('Advanced Options') }}</span>
            </div>

            <div class="grid grid-cols-3 gap-2">
                @if ($provider === 'heygen')
                    {{-- Mode Pill --}}
                    <div class="group/pill relative" x-data="{ pillOpen: false }" @click.outside="pillOpen = false">
                        <span
                            class="pointer-events-none absolute inset-x-0 bottom-full z-50 mb-1.5 flex justify-center opacity-0 transition-opacity group-hover/pill:opacity-100"
                            x-show="!pillOpen"
                        >
                            <span class="whitespace-nowrap rounded-lg bg-foreground px-2.5 py-1 text-2xs text-background shadow-lg">{{ __('Mode') }}</span>
                        </span>
                        <x-button
                            class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-foreground/10 px-3 py-2 text-xs font-medium transition-colors hover:bg-foreground/5"
                            variant="ghost"
                            hover-variant="none"
                            size="none"
                            type="button"
                            @click="pillOpen = !pillOpen"
                        >
                            <x-tabler-bolt class="size-4 shrink-0 opacity-60" />
                            <span class="truncate" x-text="advancedValues.mode === 'fast' ? '{{ __('Fast') }}' : '{{ __('Quality') }}'"></span>
                        </x-button>
                        <div
                            class="absolute start-0 top-full z-50 mt-1 min-w-40 rounded-dropdown border border-card-border bg-card-background p-2 shadow-lg"
                            x-show="pillOpen"
                            x-cloak
                            x-transition
                        >
                            <div class="flex flex-col gap-0.5">
                                <x-button
                                    class="rounded-md px-3 py-1.5 text-start text-xs transition-colors hover:bg-foreground/5 [&.active]:bg-primary/10 [&.active]:text-primary"
                                    ::class="{ 'active': advancedValues.mode === 'fast' }"
                                    variant="ghost"
                                    size="none"
                                    type="button"
                                    @click="advancedValues.mode = 'fast'; pillOpen = false"
                                >
                                    {{ __('Fast') }}
                                </x-button>
                                <x-button
                                    class="rounded-md px-3 py-1.5 text-start text-xs transition-colors hover:bg-foreground/5 [&.active]:bg-primary/10 [&.active]:text-primary"
                                    ::class="{ 'active': advancedValues.mode === 'quality' }"
                                    variant="ghost"
                                    size="none"
                                    type="button"
                                    @click="advancedValues.mode = 'quality'; pillOpen = false"
                                >
                                    {{ __('Quality') }}
                                </x-button>
                            </div>
                        </div>
                        <input type="hidden" name="mode" :value="advancedValues.mode">
                    </div>

                    {{-- Translate Audio Only Pill --}}
                    <div class="group/pill relative" x-data="{ pillOpen: false }" @click.outside="pillOpen = false">
                        <span
                            class="pointer-events-none absolute inset-x-0 bottom-full z-50 mb-1.5 flex justify-center opacity-0 transition-opacity group-hover/pill:opacity-100"
                            x-show="!pillOpen"
                        >
                            <span class="whitespace-nowrap rounded-lg bg-foreground px-2.5 py-1 text-2xs text-background shadow-lg">{{ __('Audio Only') }}</span>
                        </span>
                        <x-button
                            class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-foreground/10 px-3 py-2 text-xs font-medium transition-colors hover:bg-foreground/5"
                            variant="ghost"
                            hover-variant="none"
                            size="none"
                            type="button"
                            @click="pillOpen = !pillOpen"
                        >
                            <x-tabler-volume class="size-4 shrink-0 opacity-60" />
                            <span class="truncate" x-text="advancedValues.translate_audio_only ? '{{ __('On') }}' : '{{ __('Off') }}'"></span>
                        </x-button>
                        <div
                            class="absolute start-0 top-full z-50 mt-1 min-w-40 rounded-dropdown border border-card-border bg-card-background p-2 shadow-lg"
                            x-show="pillOpen"
                            x-cloak
                            x-transition
                        >
                            <div class="flex flex-col gap-0.5">
                                <x-button
                                    class="rounded-md px-3 py-1.5 text-start text-xs transition-colors hover:bg-foreground/5 [&.active]:bg-primary/10 [&.active]:text-primary"
                                    ::class="{ 'active': advancedValues.translate_audio_only }"
                                    variant="ghost"
                                    size="none"
                                    type="button"
                                    @click="advancedValues.translate_audio_only = true; pillOpen = false"
                                >
                                    {{ __('On') }}
                                </x-button>
                                <x-button
                                    class="rounded-md px-3 py-1.5 text-start text-xs transition-colors hover:bg-foreground/5 [&.active]:bg-primary/10 [&.active]:text-primary"
                                    ::class="{ 'active': !advancedValues.translate_audio_only }"
                                    variant="ghost"
                                    size="none"
                                    type="button"
                                    @click="advancedValues.translate_audio_only = false; pillOpen = false"
                                >
                                    {{ __('Off') }}
                                </x-button>
                            </div>
                        </div>
                        <input type="hidden" name="translate_audio_only" :value="advancedValues.translate_audio_only ? '1' : '0'">
                    </div>
                @endif

                @if ($provider === 'elevenlabs')
                    {{-- Source Language Pill --}}
                    <div class="group/pill relative" x-data="{ pillOpen: false }" @click.outside="pillOpen = false">
                        <span
                            class="pointer-events-none absolute inset-x-0 bottom-full z-50 mb-1.5 flex justify-center opacity-0 transition-opacity group-hover/pill:opacity-100"
                            x-show="!pillOpen"
                        >
                            <span class="whitespace-nowrap rounded-lg bg-foreground px-2.5 py-1 text-2xs text-background shadow-lg">{{ __('Source Language') }}</span>
                        </span>
                        <x-button
                            class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-foreground/10 px-3 py-2 text-xs font-medium transition-colors hover:bg-foreground/5"
                            variant="ghost"
                            hover-variant="none"
                            size="none"
                            type="button"
                            @click="pillOpen = !pillOpen"
                        >
                            <x-tabler-language class="size-4 shrink-0 opacity-60" />
                            <span class="truncate" x-text="advancedValues.source_language === 'auto' ? '{{ __('Auto') }}' : advancedValues.source_language"></span>
                        </x-button>
                        <div
                            class="absolute start-0 top-full z-50 mt-1 max-h-48 min-w-40 overflow-y-auto rounded-dropdown border border-card-border bg-card-background p-2 shadow-lg"
                            x-show="pillOpen"
                            x-cloak
                            x-transition
                        >
                            <div class="flex flex-col gap-0.5">
                                <x-button
                                    class="rounded-md px-3 py-1.5 text-start text-xs transition-colors hover:bg-foreground/5 [&.active]:bg-primary/10 [&.active]:text-primary"
                                    ::class="{ 'active': advancedValues.source_language === 'auto' }"
                                    variant="ghost"
                                    size="none"
                                    type="button"
                                    @click="advancedValues.source_language = 'auto'; pillOpen = false"
                                >
                                    {{ __('Auto Detect') }}
                                </x-button>
                                @foreach ($languages as $code => $name)
                                    <x-button
                                        class="rounded-md px-3 py-1.5 text-start text-xs transition-colors hover:bg-foreground/5 [&.active]:bg-primary/10 [&.active]:text-primary"
                                        ::class="{ 'active': advancedValues.source_language === '{{ $code }}' }"
                                        variant="ghost"
                                        size="none"
                                        type="button"
                                        @click="advancedValues.source_language = '{{ $code }}'; pillOpen = false"
                                    >
                                        {{ __($name) }}
                                    </x-button>
                                @endforeach
                            </div>
                        </div>
                        <input type="hidden" name="source_language" :value="advancedValues.source_language">
                    </div>

                    {{-- Highest Resolution Pill --}}
                    <div class="group/pill relative" x-data="{ pillOpen: false }" @click.outside="pillOpen = false">
                        <span
                            class="pointer-events-none absolute inset-x-0 bottom-full z-50 mb-1.5 flex justify-center opacity-0 transition-opacity group-hover/pill:opacity-100"
                            x-show="!pillOpen"
                        >
                            <span class="whitespace-nowrap rounded-lg bg-foreground px-2.5 py-1 text-2xs text-background shadow-lg">{{ __('Resolution') }}</span>
                        </span>
                        <x-button
                            class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-foreground/10 px-3 py-2 text-xs font-medium transition-colors hover:bg-foreground/5"
                            variant="ghost"
                            hover-variant="none"
                            size="none"
                            type="button"
                            @click="pillOpen = !pillOpen"
                        >
                            <x-tabler-device-desktop class="size-4 shrink-0 opacity-60" />
                            <span class="truncate" x-text="advancedValues.highest_resolution ? '{{ __('On') }}' : '{{ __('Off') }}'"></span>
                        </x-button>
                        <div
                            class="absolute start-0 top-full z-50 mt-1 min-w-40 rounded-dropdown border border-card-border bg-card-background p-2 shadow-lg"
                            x-show="pillOpen"
                            x-cloak
                            x-transition
                        >
                            <div class="flex flex-col gap-0.5">
                                <x-button
                                    class="rounded-md px-3 py-1.5 text-start text-xs transition-colors hover:bg-foreground/5 [&.active]:bg-primary/10 [&.active]:text-primary"
                                    ::class="{ 'active': advancedValues.highest_resolution }"
                                    variant="ghost"
                                    size="none"
                                    type="button"
                                    @click="advancedValues.highest_resolution = true; pillOpen = false"
                                >
                                    {{ __('On') }}
                                </x-button>
                                <x-button
                                    class="rounded-md px-3 py-1.5 text-start text-xs transition-colors hover:bg-foreground/5 [&.active]:bg-primary/10 [&.active]:text-primary"
                                    ::class="{ 'active': !advancedValues.highest_resolution }"
                                    variant="ghost"
                                    size="none"
                                    type="button"
                                    @click="advancedValues.highest_resolution = false; pillOpen = false"
                                >
                                    {{ __('Off') }}
                                </x-button>
                            </div>
                        </div>
                        <input type="hidden" name="highest_resolution" :value="advancedValues.highest_resolution ? '1' : '0'">
                    </div>

                    {{-- Drop Background Audio Pill --}}
                    <div class="group/pill relative" x-data="{ pillOpen: false }" @click.outside="pillOpen = false">
                        <span
                            class="pointer-events-none absolute inset-x-0 bottom-full z-50 mb-1.5 flex justify-center opacity-0 transition-opacity group-hover/pill:opacity-100"
                            x-show="!pillOpen"
                        >
                            <span class="whitespace-nowrap rounded-lg bg-foreground px-2.5 py-1 text-2xs text-background shadow-lg">{{ __('Background Audio') }}</span>
                        </span>
                        <x-button
                            class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-foreground/10 px-3 py-2 text-xs font-medium transition-colors hover:bg-foreground/5"
                            variant="ghost"
                            hover-variant="none"
                            size="none"
                            type="button"
                            @click="pillOpen = !pillOpen"
                        >
                            <x-tabler-volume-off class="size-4 shrink-0 opacity-60" />
                            <span class="truncate" x-text="advancedValues.drop_background_audio ? '{{ __('Drop') }}' : '{{ __('Keep') }}'"></span>
                        </x-button>
                        <div
                            class="absolute end-0 top-full z-50 mt-1 min-w-40 rounded-dropdown border border-card-border bg-card-background p-2 shadow-lg"
                            x-show="pillOpen"
                            x-cloak
                            x-transition
                        >
                            <div class="flex flex-col gap-0.5">
                                <x-button
                                    class="rounded-md px-3 py-1.5 text-start text-xs transition-colors hover:bg-foreground/5 [&.active]:bg-primary/10 [&.active]:text-primary"
                                    ::class="{ 'active': advancedValues.drop_background_audio }"
                                    variant="ghost"
                                    size="none"
                                    type="button"
                                    @click="advancedValues.drop_background_audio = true; pillOpen = false"
                                >
                                    {{ __('Drop') }}
                                </x-button>
                                <x-button
                                    class="rounded-md px-3 py-1.5 text-start text-xs transition-colors hover:bg-foreground/5 [&.active]:bg-primary/10 [&.active]:text-primary"
                                    ::class="{ 'active': !advancedValues.drop_background_audio }"
                                    variant="ghost"
                                    size="none"
                                    type="button"
                                    @click="advancedValues.drop_background_audio = false; pillOpen = false"
                                >
                                    {{ __('Keep') }}
                                </x-button>
                            </div>
                        </div>
                        <input type="hidden" name="drop_background_audio" :value="advancedValues.drop_background_audio ? '1' : '0'">
                    </div>

                    {{-- Profanity Filter Pill --}}
                    <div class="group/pill relative" x-data="{ pillOpen: false }" @click.outside="pillOpen = false">
                        <span
                            class="pointer-events-none absolute inset-x-0 bottom-full z-50 mb-1.5 flex justify-center opacity-0 transition-opacity group-hover/pill:opacity-100"
                            x-show="!pillOpen"
                        >
                            <span class="whitespace-nowrap rounded-lg bg-foreground px-2.5 py-1 text-2xs text-background shadow-lg">{{ __('Profanity Filter') }}</span>
                        </span>
                        <x-button
                            class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-foreground/10 px-3 py-2 text-xs font-medium transition-colors hover:bg-foreground/5"
                            variant="ghost"
                            hover-variant="none"
                            size="none"
                            type="button"
                            @click="pillOpen = !pillOpen"
                        >
                            <x-tabler-ban class="size-4 shrink-0 opacity-60" />
                            <span class="truncate" x-text="advancedValues.use_profanity_filter ? '{{ __('On') }}' : '{{ __('Off') }}'"></span>
                        </x-button>
                        <div
                            class="absolute start-0 top-full z-50 mt-1 min-w-40 rounded-dropdown border border-card-border bg-card-background p-2 shadow-lg"
                            x-show="pillOpen"
                            x-cloak
                            x-transition
                        >
                            <div class="flex flex-col gap-0.5">
                                <x-button
                                    class="rounded-md px-3 py-1.5 text-start text-xs transition-colors hover:bg-foreground/5 [&.active]:bg-primary/10 [&.active]:text-primary"
                                    ::class="{ 'active': advancedValues.use_profanity_filter }"
                                    variant="ghost"
                                    size="none"
                                    type="button"
                                    @click="advancedValues.use_profanity_filter = true; pillOpen = false"
                                >
                                    {{ __('On') }}
                                </x-button>
                                <x-button
                                    class="rounded-md px-3 py-1.5 text-start text-xs transition-colors hover:bg-foreground/5 [&.active]:bg-primary/10 [&.active]:text-primary"
                                    ::class="{ 'active': !advancedValues.use_profanity_filter }"
                                    variant="ghost"
                                    size="none"
                                    type="button"
                                    @click="advancedValues.use_profanity_filter = false; pillOpen = false"
                                >
                                    {{ __('Off') }}
                                </x-button>
                            </div>
                        </div>
                        <input type="hidden" name="use_profanity_filter" :value="advancedValues.use_profanity_filter ? '1' : '0'">
                    </div>
                @endif

                {{-- Title Pill (both providers) --}}
                <div class="group/pill relative" x-data="{ pillOpen: false }" @click.outside="pillOpen = false">
                    <span
                        class="pointer-events-none absolute inset-x-0 bottom-full z-50 mb-1.5 flex justify-center opacity-0 transition-opacity group-hover/pill:opacity-100"
                        x-show="!pillOpen"
                    >
                        <span class="whitespace-nowrap rounded-lg bg-foreground px-2.5 py-1 text-2xs text-background shadow-lg">{{ __('Title') }}</span>
                    </span>
                    <x-button
                        class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-foreground/10 px-3 py-2 text-xs font-medium transition-colors hover:bg-foreground/5"
                        variant="ghost"
                        hover-variant="none"
                        size="none"
                        type="button"
                        @click="pillOpen = !pillOpen"
                    >
                        <x-tabler-align-left class="size-4 shrink-0 opacity-60" />
                        <span class="truncate" x-text="advancedValues.title ? advancedValues.title.substring(0, 15) + (advancedValues.title.length > 15 ? '...' : '') : '{{ __('Title') }}'"></span>
                    </x-button>
                    <div
                        class="absolute end-0 top-full z-50 mt-1 min-w-56 rounded-dropdown border border-card-border bg-card-background p-2 shadow-lg"
                        x-show="pillOpen"
                        x-cloak
                        x-transition
                    >
                        <div class="flex flex-col gap-1 p-1">
                            <label class="text-2xs font-medium text-foreground/60">{{ __('Title') }}</label>
                            <x-forms.input
                                name="title"
                                type="text"
                                size="sm"
                                :placeholder="__('Optional title')"
                                x-model="advancedValues.title"
                                label=""
                                class="!text-xs !px-3 !py-1.5"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div x-init="
            $watch('targetLanguages', langs => {
                $dispatch('generator-changed', { generator: 'video-dubbing', quantity: Math.max(1, langs.length) * 60, _force: Date.now() });
            });
            $nextTick(() => $dispatch('generator-changed', { generator: 'video-dubbing', quantity: Math.max(1, targetLanguages.length) * 60 }));
        ">
        </div>

        {{-- Submit Button --}}
        @if (\App\Helpers\Classes\Helper::appIsDemo())
            <x-button
                class="w-full"
                size="lg"
                type="button"
                onclick="toastr.info('{{ __('This feature is disabled in demo mode.') }}')"
            >
                {{ __('Generate') }}
                <x-tabler-arrow-right class="size-5" />
            </x-button>
        @else
            <x-button
                class="w-full"
                size="lg"
                type="submit"
                ::disabled="loading || targetLanguages.length === 0"
            >
                <template x-if="!loading">
                    <span class="flex items-center gap-2">
                        {{ __('Generate') }}
                        <x-tabler-arrow-right class="size-5" />
                    </span>
                </template>
                <template x-if="loading">
                    <span class="flex items-center gap-2">
                        <x-tabler-loader-2 class="size-4 animate-spin" />
                        {{ __('Processing...') }}
                    </span>
                </template>
            </x-button>
        @endif

        <div
            x-init="
                $watch('targetLanguages', langs => {
                    $dispatch('generator-changed', { generator: 'video-dubbing', quantity: Math.max(1, langs.length) * 60, _force: Date.now() });
                });
                $nextTick(() => $dispatch('generator-changed', { generator: 'video-dubbing', quantity: Math.max(1, targetLanguages.length) * 60 }));
            "
        >
            <x-cost-preview class="w-full justify-end" />
        </div>
    </form>
</div>
