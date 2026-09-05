@php
    $aspect_ratio = $aspect_ratio ?? '1/1';
    $isDynamic = $aspect_ratio === 'dynamic';
@endphp

<div
    class="image-result group relative cursor-pointer overflow-hidden rounded-[3px] bg-foreground/[2%]"
    x-data="{ inView: false, imageLoaded: false, imageKey: null }"
    x-intersect:enter.margin.250px.once="inView = true"
    x-effect="if (imageKey !== image.id) { imageLoaded = false; imageKey = image.id; }"
    @if ($isDynamic) :style="{ aspectRatio: image.aspect_ratio || '1/1' }"
    @else
        style="aspect-ratio: {{ $aspect_ratio }};" @endif
    :data-id-prefix="galleryPrefix || 'preview'"
    :data-id="image.id"
    :data-payload="JSON.stringify(image)"
    @click="setActiveModal(image, galleryPrefix || 'preview')"
>
    {{-- Skeleton loading overlay --}}
    <div
        class="lqd-loading-skeleton lqd-is-loading absolute inset-0 z-1"
        x-show="inView && !imageLoaded"
    >
        <div
            class="absolute size-full"
            data-lqd-skeleton-el
        ></div>
    </div>

    {{-- Video --}}
    <template x-if="image.isVideo">
        <video
            class="h-full w-full object-cover"
            :src="inView ? image.url : ''"
            @if ($isDynamic) :width="image.width"
                :height="image.height" @endif
            muted
            loop
            playsinline
            @mouseenter="$el.play()"
            @mouseleave="$el.pause(); $el.currentTime = 0"
            @loadeddata="imageLoaded = true"
        ></video>
    </template>

    {{-- Image --}}
    <template x-if="!image.isVideo">
        <img
            class="h-full w-full object-cover"
            :src="inView && imageLoaded ? `${(image.thumbnail || image.url).startsWith('upload') ? '/' : ''}${image.thumbnail || image.url}` :
                `data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 ${image.width || 1} ${image.height || 1}'%3E%3C/svg%3E`"
            :alt="image.prompt || image.title"
            @if ($isDynamic) :width="image.width"
                :height="image.height" @endif
            @load="imageLoaded = true"
        >
    </template>

    {{-- Video Badge --}}
    <template x-if="image.isVideo">
        <div class="absolute bottom-2 left-2 flex items-center gap-1 rounded-md bg-black/60 px-2 py-1 text-xs font-medium text-white shadow-sm">
            <x-tabler-player-play-filled class="size-3" />
            {{ __('Video') }}
        </div>
    </template>

    {{-- TODO: make these dynamically generated --}}
    <template x-if="image.edits">
        <span class="absolute start-3 top-3 inline-block rounded-full bg-background px-3.5 py-2 text-xs font-medium text-heading-foreground">
            {{ __(':count edits', ['count' => 3]) }}
        </span>
    </template>

    {{-- TODO: make these dynamically generated --}}
    <template x-if="image.variations">
        <span class="absolute start-3 top-3 inline-block rounded-full bg-background px-3.5 py-2 text-xs font-medium text-heading-foreground">
            {{ __(':count variations', ['count' => 4]) }}
        </span>
    </template>

    {{-- Hover overlay --}}
    <div class="absolute inset-x-0 bottom-0 flex items-center justify-between px-5 py-6">
        <x-progressive-blur
            class="absolute inset-x-0 -top-3 bottom-0 z-0 opacity-0 group-hover:opacity-100 group-has-[.lqd-is-active]:translate-y-0 group-has-[.lqd-is-active]:opacity-100"
        />

        <div
            class="relative z-1 flex min-w-0 flex-1 translate-y-1 items-center gap-2 overflow-hidden opacity-0 transition group-hover:translate-y-0 group-hover:opacity-100 group-has-[.lqd-is-active]:translate-y-0 group-has-[.lqd-is-active]:opacity-100">
            <div class="flex size-5 shrink-0 overflow-hidden rounded-full">
                <template x-if="image.user && image.user.avatar">
                    <img
                        class="h-full w-full object-cover"
                        :src="image.user.avatar"
                        aria-hidden="true"
                        alt="{{ __('User avatar') }}"
                    >
                </template>
                <template x-if="!image.user || !image.user.avatar">
                    <span
                        class="inline-grid size-full place-items-center bg-white text-3xs font-medium text-black"
                        x-text="image.user?.initial || 'U'"
                    ></span>
                </template>
            </div>
            <span
                class="truncate text-3xs font-medium text-white"
                x-text="image.user?.name || 'Anonymous'"
            ></span>
        </div>

        <div
            class="relative z-1 flex flex-shrink-0 translate-y-1 items-center gap-1.5 opacity-0 transition group-hover:translate-y-0 group-hover:opacity-100 group-has-[.lqd-is-active]:translate-y-0 group-has-[.lqd-is-active]:opacity-100">
            <x-button
                class="flex size-7 items-center justify-center p-0 text-white transition-transform hover:scale-110"
                type="button"
                variant="ghost"
                size="none"
                href="#"
                @click.prevent.stop="toggleFavorite(image.id)"
            >
                <x-tabler-bookmark
                    class="size-5"
                    ::fill="isFavorite(image.id) ? 'currentColor' : 'none'"
                />
            </x-button>

            <x-dropdown.dropdown
                anchor="end"
                offsetY="5px"
            >
                <x-slot:trigger
                    class="size-7 p-0 text-white group-[&.lqd-is-active]/dropdown:scale-110"
                    variant="ghost"
                >
                    <x-tabler-dots-circle-horizontal class="size-5" />
                </x-slot:trigger>

                <x-slot:dropdown
                    class="min-w-56 p-1"
                >
                    <x-button
                        class="w-full justify-start !rounded-md text-start text-2xs font-medium hover:transform-none hover:shadow-none"
                        variant="ghost"
                        href="#"
                        @click.prevent="editWithAssistant(image)"
                    >
                        <x-tabler-message-circle class="size-4 shrink-0" />
                        {{ __('Open with Assistant') }}
                    </x-button>
                    <x-button
                        class="w-full justify-start !rounded-md text-start text-2xs font-medium hover:transform-none hover:shadow-none"
                        variant="ghost"
                        href="#"
                        @click.prevent="editWithEditor(image)"
                    >
                        <x-tabler-scissors class="size-4 shrink-0" />
                        {{ __('Open with Editor') }}
                    </x-button>
                    <x-button
                        class="w-full justify-start !rounded-md text-start text-2xs font-medium hover:transform-none hover:shadow-none"
                        variant="ghost"
                        href="#"
                        @click.prevent="openWithCreativeSuite(image)"
                        x-show="isCreativeSuiteInstalled"
                    >
                        <x-tabler-brush class="size-4 shrink-0" />
                        {{ __('Open with Creative Suite') }}
                    </x-button>
                </x-slot:dropdown>
            </x-dropdown.dropdown>
        </div>
    </div>
</div>
