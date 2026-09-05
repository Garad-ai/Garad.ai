@php
    $youtube_icon =
        '<svg class="text-red-600" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M18 3a5 5 0 0 1 5 5v8a5 5 0 0 1 -5 5h-12a5 5 0 0 1 -5 -5v-8a5 5 0 0 1 5 -5zm-9 6v6a1 1 0 0 0 1.514 .857l5 -3a1 1 0 0 0 0 -1.714l-5 -3a1 1 0 0 0 -1.514 .857z" stroke-width="0" fill="currentColor"></path></svg>';
@endphp
<div
    class="col-start-1 col-end-1 row-start-1 row-end-1"
    x-show="currentStep == 2"
    x-data="previewClipsStepData"
    x-init="$nextTick(() => { $watch('aiClipsWindowKey', () => initialize()) })"
>

    <h2 class="mb-3.5">
        @lang('Preview Shorts')
    </h2>
    <p class="mb-3 text-xs/5 opacity-60 lg:max-w-[360px]">
        @lang('You can preview and select short previews in full before choosing to export it.')
    </p>

    <div class="mt-9 grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-2">
        <template x-for="preview in previews">
            <div class="flex flex-col">
                <div class="flex h-full w-full justify-center overflow-hidden rounded-xl">
                    <div
                        class="group relative flex h-auto min-h-80 w-full cursor-pointer justify-center bg-foreground/80"
                        @click="preview.checked=!preview.checked"
                    >
                        <video
                            class="w-full object-cover"
                            x-show="preview.videoDuration"
                            :id="'generated-clips-item-' + preview.id"
                            :src="preview.videoUrl"
                            loop
                            @loadedmetadata="preview.videoDuration = $event.target.duration"
                        ></video>
                        <div
                            class="flex h-full w-full flex-col items-center justify-center gap-2.5"
                            x-show="!preview.videoDuration"
                        >
                            <x-tabler-loader-2
                                class="text-gradient size-6 animate-spin"
                                stroke="url(#icon-gradient)"
                            />
                            <span
                                class="bg-gradient-to-r from-gradient-from via-gradient-via to-gradient-to bg-clip-text text-sm font-semibold text-transparent"
                            >{{ __('Loading') }}</span>
                        </div>
                        <span
                            class="absolute end-1 top-1.5 flex items-center justify-center rounded-full bg-background/40 p-2 shadow-lg"
                            x-show="preview.checked"
                        >
                            <x-tabler-check class="size-4" />
                        </span>
                        <span
                            class="absolute bottom-2 start-2 flex items-center justify-center rounded-lg bg-background/80 p-1 shadow-lg"
                            x-show="preview.videoDuration"
                            x-text="getDurationByString(preview.videoDuration)"
                        >
                        </span>
                        <div
                            class="absolute start-1/2 top-1/2 flex -translate-x-1/2 -translate-y-1/2"
                            x-show="preview.videoDuration"
                        >
                            <span
                                class="hidden cursor-pointer items-center justify-center rounded-full bg-background/40 p-2 shadow-lg group-hover:flex"
                                @click.stop="playVideo(preview)"
                            >
                                <x-tabler-player-play-filled
                                    class="size-4"
                                    x-show="!preview.playing"
                                />
                                <x-tabler-player-pause-filled
                                    class="size-4"
                                    x-show="preview.playing"
                                />
                            </span>
                        </div>
                    </div>
                </div>
                <div class="flex w-full flex-shrink-0 flex-col items-center justify-center gap-2 py-5">
                    <div class="flex max-w-56 flex-col gap-2">
                        <span
                            class="text-center text-sm font-semibold text-heading-foreground"
                            x-text="preview.name"
                        ></span>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <x-button
        class="mt-9 w-full"
        variant="secondary"
        @click.prevent="renderVideos()"
        size="lg"
        type="button"
        ::disabled="submitting"
    >
        @lang('Render Final Videos')
    </x-button>
</div>
