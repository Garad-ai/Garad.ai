@php
    $community_enabled = $community_enabled ?? false;
@endphp

<template x-teleport="body">
    <div
        class="lqd-modal-img group/modal invisible fixed start-0 top-0 z-[999] grid h-screen w-screen place-items-center px-5 opacity-0 [&.is-active]:visible [&.is-active]:opacity-100"
        :class="{ 'is-active': modalShow }"
        @keyup.escape.window="modalShow = false"
        @keydown.left.window="if (modalShow) prevImageModal()"
        @keydown.right.window="if (modalShow) nextImageModal()"
    >
        <div
            class="lqd-modal-img-backdrop absolute start-0 top-0 z-0 h-screen w-screen bg-black/50 opacity-0 backdrop-blur transition-opacity group-[&.is-active]/modal:opacity-100"
            @click="modalShow = false"
        ></div>

        <div class="lqd-modal-img-content-wrap relative z-10 w-[min(1230px,calc(100%-4rem))]">
            {{-- Close button --}}
            <a
                class="absolute -end-4 -top-4 z-10 flex size-9 items-center justify-center rounded-full bg-background text-inherit shadow-sm transition-all hover:bg-red-500 hover:text-white lg:-end-12 lg:top-0"
                @click.prevent="modalShow = false"
                href="#"
            >
                <x-tabler-x class="size-4" />
            </a>

            <div
                class="lqd-modal-img-content relative flex h-full max-h-[calc(100vh-50px)] scale-[0.985] flex-wrap justify-between overflow-y-auto overscroll-contain rounded-xl bg-background opacity-0 shadow-2xl transition-all group-[&.is-active]/modal:translate-y-0 group-[&.is-active]/modal:scale-100 group-[&.is-active]/modal:opacity-100 md:h-[min(90vh,850px)] md:flex-nowrap">

                {{-- Image Section --}}
                <div class="lqd-modal-fig relative min-h-px w-full border-b p-6 md:w-1/2 md:border-b-0 md:border-e lg:sticky lg:top-0 lg:h-full lg:w-8/12">
                    <div
                        class="absolute inset-6 grid place-items-center rounded bg-foreground/[0.04]"
                        x-show="modalImageLoading"
                    >
                        <div class="size-10 animate-spin rounded-full border-2 border-foreground/20 border-t-foreground/70"></div>
                    </div>

                    <img
                        class="lqd-modal-img h-auto w-full rounded md:h-full md:max-h-full md:object-contain"
                        :src="modalImageSrc"
                        :alt="activeModal?.title || activeModal?.input"
                        x-show="!modalImageLoading && modalImageSrc"
                    />
                </div>

                {{-- Details Sidebar --}}
                <div class="w-full p-6 md:w-1/2 md:px-10 lg:w-4/12 lg:py-7">
                    {{-- Header with User Info --}}
                    <button
                        @class([
                            'mb-4 flex items-center gap-2',
                            'cursor-pointer' => $community_enabled,
                            'cursor-default' => !$community_enabled,
                        ])
                        @if ($community_enabled) @click.prevent="viewUserGallery(activeModal?.user?.id)" @endif
                        type="button"
                        title="{{ __('View user gallery') }}"
                    >
                        <span class="flex size-5 shrink-0 overflow-hidden rounded-full">
                            <template x-if="activeModal?.user && activeModal.user.avatar">
                                <img
                                    class="size-full object-cover"
                                    :src="activeModal.user.avatar"
                                    alt=""
                                >
                            </template>
                            <template x-if="!activeModal?.user || !activeModal.user.avatar">
                                <span
                                    class="inline-grid size-full place-items-center bg-foreground/10 text-3xs font-semibold text-foreground"
                                    x-text="activeModal?.user?.initial || 'U'"
                                ></span>
                            </template>
                        </span>
                        <span
                            class="m-0 min-w-0 flex-1 truncate text-3xs font-medium text-foreground/70"
                            x-text="activeModal?.user?.name || '{{ __('Anonymous') }}'"
                        ></span>
                    </button>

                    <p
                        class="text-2xs leading-[1.4em] text-foreground/70"
                        x-text="activeModal?.input ?? activeModal?.prompt"
                        x-show="activeModal?.input || activeModal?.prompt"
                    ></p>

                    {{-- Action Buttons Row --}}
                    <div class="-ms-2 flex items-center gap-2">
                        <x-button
                            class="size-8 p-0"
                            @click.prevent.stop="toggleFavorite(activeModal?.id)"
                            variant="ghost"
                            size="none"
                            href="#"
                        >
                            <x-tabler-bookmark
                                class="size-[18px]"
                                ::fill="isFavorite(activeModal?.id) ? 'currentColor' : 'none'"
                            />
                        </x-button>

                        @if ($community_enabled)
                            <x-button
                                class="size-8 p-0"
                                @click.prevent.stop="replicateImage(activeModal)"
                                variant="ghost"
                                size="none"
                                href="#"
                                title="{{ __('Replicate') }}"
                            >
                                <x-tabler-refresh class="size-[18px]" />
                            </x-button>
                        @endif

                        <x-button
                            class="size-8 p-0"
                            @click.prevent.stop="toggleLike(activeModal?.id)"
                            variant="ghost"
                            size="none"
                            href="#"
                        >
                            <x-tabler-thumb-up
                                class="size-[18px]"
                                ::fill="isLiked(activeModal?.id) ? 'currentColor' : 'none'"
                            />
                        </x-button>

                        <x-dropdown.dropdown
                            anchor="end"
                            offsetY="5px"
                        >
                            <x-slot:trigger
                                class="size-8 p-0"
                                variant="ghost"
                            >
                                <x-tabler-dots class="size-[18px]" />
                            </x-slot:trigger>

                            <x-slot:dropdown
                                class="min-w-56 p-1"
                            >
                                <x-button
                                    class="w-full justify-start !rounded-md text-start text-2xs font-medium"
                                    variant="ghost"
                                    href="#"
                                    @click.prevent="editWithAssistant(activeModal)"
                                >
                                    <x-tabler-sparkles class="size-4 shrink-0" />
                                    {{ __('Edit with Assistant') }}
                                </x-button>
                                <x-button
                                    class="w-full justify-start !rounded-md text-start text-2xs font-medium"
                                    variant="ghost"
                                    href="#"
                                    @click.prevent="editWithEditor(activeModal)"
                                >
                                    <x-tabler-photo-edit class="size-4 shrink-0" />
                                    {{ __('Edit with Editor') }}
                                </x-button>
                                <x-button
                                    class="w-full justify-start !rounded-md text-start text-2xs font-medium"
                                    variant="ghost"
                                    href="#"
                                    @click.prevent="openWithCreativeSuite(activeModal)"
                                    x-show="isCreativeSuiteInstalled"
                                >
                                    <x-tabler-palette class="size-4 shrink-0" />
                                    {{ __('Open with Creative Suite') }}
                                </x-button>
                            </x-slot:dropdown>
                        </x-dropdown.dropdown>
                    </div>

                    {{-- Main Action Buttons --}}
                    <div
                        class="grid grid-cols-2 gap-1.5 pt-4"
                        :class="{ 'md:grid-cols-3': activeModal?.can_publish, 'md:grid-cols-2': !activeModal?.can_publish }"
                    >
                        <x-button
                            class="w-full bg-foreground/5 text-xs font-medium leading-relaxed text-foreground"
                            hover-variant="primary"
                            size="lg"
                            href="#"
                            @click.prevent="downloadImage(activeModal)"
                        >
                            {{ __('Download') }}
                        </x-button>
                        <x-button
                            class="w-full bg-foreground/5 text-xs font-medium leading-relaxed text-foreground"
                            hover-variant="primary"
                            @click.prevent="publishImage(activeModal?.id)"
                            size="lg"
                            href="#"
                            x-show="activeModal?.can_publish"
                        >
                            {{ __('Publish') }}
                        </x-button>
                        <x-button
                            class="w-full bg-foreground/5 text-xs font-medium leading-relaxed text-foreground"
                            hover-variant="primary"
                            @click.prevent="shareImage(activeModal?.id, activeModal)"
                            size="lg"
                            href="#"
                        >
                            {{ __('Share') }}
                        </x-button>
                    </div>

                    <x-button
                        class="mt-4 w-full text-xs font-medium leading-relaxed"
                        href="#"
                        size="lg"
                        @click.prevent="editImage(activeModal)"
                    >
                        {{ __('Edit Image') }}
                    </x-button>

                    {{-- Metadata --}}
                    <div class="mt-10 space-y-3.5">
                        <div class="flex w-full items-center justify-between gap-1 py-1.5 text-2xs font-medium">
                            <p class="mb-0">
                                @lang('Date')
                            </p>
                            <p
                                class="mb-0 opacity-50"
                                x-text="activeModal?.date || activeModal?.format_date || activeModal?.formatted_date || '{{ __('None') }}'"
                            ></p>
                        </div>

                        <div
                            class="flex w-full items-center justify-between gap-1 py-1.5 text-2xs font-medium"
                            x-show="activeModal?.ratio || activeModal?.size"
                        >
                            <p class="mb-0">
                                @lang('Ratio')
                            </p>
                            <p
                                class="mb-0 opacity-50"
                                x-text="activeModal?.ratio || activeModal?.size || '{{ __('None') }}'"
                            ></p>
                        </div>

                        <div
                            class="flex w-full items-center justify-between gap-1 py-1.5 text-2xs font-medium"
                            x-show="activeModal?.credits"
                        >
                            <p class="mb-0">
                                @lang('Credit')
                            </p>
                            <p
                                class="mb-0 opacity-50"
                                x-text="activeModal?.credits || '{{ __('None') }}'"
                            ></p>
                        </div>

                        <div
                            class="flex w-full items-center justify-between gap-1 py-1.5 text-2xs font-medium"
                            x-show="activeModal?.model || activeModal?.response"
                        >
                            <p class="mb-0">
                                @lang('AI Model')
                            </p>
                            <p
                                class="mb-0 opacity-50"
                                x-text="activeModal?.model || activeModal?.response || '{{ __('None') }}'"
                            ></p>
                        </div>

                        <div
                            class="flex w-full items-center justify-between gap-1 py-1.5 text-2xs font-medium"
                            x-show="activeModal?.style || activeModal?.image_style"
                        >
                            <p class="mb-0">
                                @lang('Art Style')
                            </p>
                            <p
                                class="mb-0 opacity-50"
                                x-text="activeModal?.style || activeModal?.image_style || '{{ __('None') }}'"
                            ></p>
                        </div>
                    </div>

                    {{-- Tags --}}
                    <div
                        class="border-t border-border/50 pt-4"
                        x-show="activeModal?.tags && activeModal.tags.length > 0"
                    >
                        <h4 class="mb-3 text-sm font-medium text-foreground/70">{{ __('Tags') }}</h4>
                        <div class="flex flex-wrap gap-2">
                            <template
                                x-for="tag in activeModal?.tags || []"
                                :key="tag"
                            >
                                <span
                                    class="rounded-lg bg-foreground/5 px-3 py-1.5 text-xs font-medium text-foreground/70"
                                    x-text="tag"
                                ></span>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Prev/Next Navigation Buttons --}}
            <a
                class="absolute -start-2 top-[20vh] z-10 inline-flex size-9 items-center justify-center rounded-full bg-background text-inherit shadow-md transition-all hover:scale-110 hover:bg-primary hover:text-primary-foreground md:top-1/2 md:-translate-y-1/2 lg:-start-4"
                href="#"
                @click.prevent="prevImageModal()"
            >
                <x-tabler-chevron-left class="size-5" />
            </a>
            <a
                class="absolute -end-2 top-[20vh] z-10 inline-flex size-9 items-center justify-center rounded-full bg-background text-inherit shadow-md transition-all hover:scale-110 hover:bg-primary hover:text-primary-foreground md:top-1/2 md:-translate-y-1/2 lg:-end-4"
                href="#"
                @click.prevent="nextImageModal()"
            >
                <x-tabler-chevron-right class="size-5" />
            </a>
        </div>
    </div>
</template>
