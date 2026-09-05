@push('before-head-close')
    <style>
        .chat-content .lqd-entity-highlight,
        .lqd-entity-highlight {
            color: hsl(var(--primary));
            cursor: pointer;
            text-decoration: dashed underline hsl(var(--primary) / 0.4);
            text-underline-offset: 3px;
            transition: all 0.3s;
        }

        .lqd-entity-highlight:hover,
        .lqd-entity-highlight:focus-visible {
            background: hsl(var(--primary) / 0.06);
        }

        .lqd-entity-highlight:focus-visible {
            outline: 2px solid hsl(var(--primary) / 0.3);
            outline-offset: 2px;
        }

        @media (min-width: 992px) {
            :root {
                --entity-panel-width: min(440px, 100%);
            }

            .lqd-entity-drawer-open .conversation-area-wrap {
                width: calc(100% - var(--entity-panel-width));
                padding-inline: 1.25rem;
            }

            .lqd-entity-drawer-open .chats-container {
                width: min(100%, var(--chats-container-width));
            }
        }

        @media (max-width: 991px) {
            :root {
                --entity-panel-width: 100%;
            }
        }
    </style>
@endpush

<template x-teleport="body">
    {{-- Entity Detail Drawer --}}
    <div
        class="pointer-events-none"
        class="lqd-entity-drawer"
        id="lqd-entity-drawer"
        x-data="lqdEntityDrawer"
        x-cloak
        @keydown.escape.window="if (isOpen) close()"
        :class="{ 'pointer-events-auto': isOpen }"
    >
        {{-- Panel --}}
        <div
            class="fixed bottom-0 end-0 top-[--header-h] z-50 flex w-[var(--entity-panel-width)] flex-col border-t bg-background transition-all max-md:top-auto max-md:max-h-[80vh] max-md:translate-y-full max-md:pb-[--bottom-menu-height] md:translate-x-full md:rounded-ss-[10px] md:border-s rtl:md:-translate-x-full max-md:[&.is-open]:translate-y-0 md:[&.is-open]:translate-x-0 rtl:md:[&.is-open]:translate-x-0"
            x-ref="panel"
            :class="{
                'is-open': isOpen
            }"
        >
            {{-- Close button --}}
            <button
                class="absolute end-7 top-7 z-10 inline-grid size-8 place-items-center rounded-full text-foreground backdrop-blur transition-colors hover:bg-foreground/[8%]"
                @click="close()"
                aria-label="{{ __('Close') }}"
            >
                <x-tabler-x class="size-4" />
            </button>

            {{-- Body --}}
            <div class="flex-1 overflow-y-auto px-7 pb-24 pt-5">
                {{-- Loading state --}}
                <template x-if="isLoading">
                    <div>
                        <h2
                            class="mb-1 text-xl font-semibold leading-tight text-heading-foreground"
                            x-text="currentEntity?.text"
                        ></h2>
                        <div class="mt-4 flex flex-col gap-2.5">
                            <div class="lqd-shimmer-effect relative h-3.5 w-[40%] overflow-hidden rounded bg-foreground/5"></div>
                            <div class="lqd-shimmer-effect relative h-3.5 w-full overflow-hidden rounded bg-foreground/5"></div>
                            <div class="lqd-shimmer-effect relative h-3.5 w-[90%] overflow-hidden rounded bg-foreground/5"></div>
                            <div class="lqd-shimmer-effect relative h-3.5 w-[70%] overflow-hidden rounded bg-foreground/5"></div>
                            <div class="relative h-6 w-0 overflow-hidden rounded bg-foreground/5"></div>
                            <div class="lqd-shimmer-effect relative h-3.5 w-[50%] overflow-hidden rounded bg-foreground/5"></div>
                            <div class="lqd-shimmer-effect relative h-3.5 w-[80%] overflow-hidden rounded bg-foreground/5"></div>
                            <div class="lqd-shimmer-effect relative h-3.5 w-[65%] overflow-hidden rounded bg-foreground/5"></div>
                            <div class="lqd-shimmer-effect relative h-3.5 w-[45%] overflow-hidden rounded bg-foreground/5"></div>
                            <div class="lqd-shimmer-effect relative h-3.5 w-[75%] overflow-hidden rounded bg-foreground/5"></div>
                        </div>
                    </div>
                </template>

                {{-- Error state --}}
                <template x-if="hasError && !isLoading">
                    <div>
                        <h2
                            class="mb-1 text-xl font-semibold leading-tight text-heading-foreground"
                            x-text="currentEntity?.text"
                        ></h2>
                        <p class="text-[0.9375rem] leading-relaxed text-foreground/50">
                            {{ __('Could not load details for this entity. Please try again.') }}
                        </p>
                    </div>
                </template>

                {{-- Content state --}}
                <template x-if="details && !isLoading && !hasError">
                    <div>
                        <h2
                            class="mb-1 text-xl font-semibold leading-tight text-heading-foreground"
                            x-text="details.title"
                        ></h2>

                        <p
                            class="mb-4 text-sm text-foreground/60"
                            x-show="details.subtitle"
                            x-text="details.subtitle"
                        ></p>

                        {{-- Image shimmer (while fetching URL from API) --}}
                        <template x-if="imageLoading && !imageUrl">
                            <div class="lqd-loading-skeleton lqd-is-loading relative mb-4 h-80 w-full overflow-hidden rounded-lg">
                                <div
                                    class="size-full"
                                    data-lqd-skeleton-el
                                ></div>
                            </div>
                        </template>

                        {{-- Image (URL known, may still be loading in browser) --}}
                        <template x-if="imageUrl">
                            <div
                                class="lqd-loading-skeleton relative mb-4 h-80 w-full overflow-hidden rounded-lg"
                                :class="{ 'lqd-is-loading': imageLoading }"
                            >
                                <div
                                    class="size-full"
                                    data-lqd-skeleton-el
                                >
                                    <img
                                        class="size-full cursor-zoom-in object-cover transition-opacity duration-200 hover:opacity-90"
                                        :src="imageUrl"
                                        :alt="details.title || currentEntity?.text"
                                        @click="openLightbox()"
                                        x-on:load="imageLoading = false"
                                        x-on:error="imageUrl = null; imageLoading = false"
                                    />
                                </div>
                            </div>
                        </template>

                        <p
                            class="mb-6 text-sm leading-relaxed text-foreground"
                            x-text="details.description"
                        ></p>

                        {{-- Key facts --}}
                        <template x-if="details.key_facts?.length">
                            <div class="mb-6">
                                <h3 class="mb-3 text-base font-semibold text-heading-foreground">
                                    {{ __('Key facts') }}
                                </h3>
                                <ul class="list-disc ps-5">
                                    <template
                                        x-for="fact in details.key_facts"
                                        :key="fact"
                                    >
                                        <li
                                            class="mb-1 text-sm leading-relaxed text-foreground"
                                            x-text="fact"
                                        ></li>
                                    </template>
                                </ul>
                            </div>
                        </template>

                        {{-- Additional sections --}}
                        <template
                            x-for="section in (details.sections || [])"
                            :key="section.title"
                        >
                            <div class="mb-6">
                                <h3
                                    class="mb-3 text-base font-semibold text-heading-foreground"
                                    x-text="section.title"
                                ></h3>
                                <p
                                    class="text-[0.9375rem] leading-[1.7] text-foreground"
                                    x-text="section.content"
                                ></p>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            {{-- Footer --}}
            <div class="absolute inset-x-0 z-1 flex flex-col items-center gap-3 px-6 max-md:bottom-[calc(var(--bottom-menu-height,0px)+2rem)] md:bottom-8">
                <button
                    class="z-1 inline-flex h-14 items-center rounded-full bg-surface-background px-[26px] py-4 text-sm font-normal text-foreground shadow-[0_22px_55px_hsl(0_0%_0%_/15%)] transition hover:bg-primary hover:text-primary-foreground"
                    type="button"
                    @click.prevent="onFollowupClick"
                    aria-label="{{ __('Ask a follow-up') }}"
                >
                    {{ __('Ask a follow-up') }}
                </button>
            </div>
        </div>
    </div>
</template>

@push('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('lqdEntityDrawer', () => ({
                isOpen: false,
                isLoading: false,
                hasError: false,
                currentEntity: null,
                currentMessageId: null,
                details: null,
                imageUrl: null,
                imageSource: '',
                imageDomain: '',
                imageLink: '',
                imageLoading: false,

                _fetchController: null,
                _prefetchCache: new Map(),
                _touchState: {
                    startY: 0,
                    currentY: 0,
                    isDragging: false
                },

                init() {
                    document.addEventListener('entity-drawer:open', (e) => this.open(e.detail));
                    document.addEventListener('entity-drawer:close', () => this.close());
                    document.addEventListener('entity-drawer:prefetch', (e) => this.prefetch(e.detail));

                    this.$watch('isOpen', (val) => {
                        document.body.classList.toggle('overflow-hidden', val);
                    });
                },

                open({
                    entity,
                    messageId
                }) {
                    // Abort any in-flight request
                    if (this._fetchController) {
                        this._fetchController.abort();
                    }
                    this._fetchController = new AbortController();

                    // Reset state for new entity
                    this.currentEntity = entity;
                    this.currentMessageId = messageId;
                    this.details = null;
                    this.imageUrl = null;
                    this.imageSource = '';
                    this.imageDomain = '';
                    this.imageLink = '';
                    this.imageLoading = false;
                    this.hasError = false;
                    this.isLoading = true;
                    this.isOpen = true;

                    const cacheKey = entity.text + '|' + (messageId || '');

                    document.body.classList.toggle('lqd-entity-drawer-open', this.isOpen);

                    // Use prefetched data if available, otherwise fetch fresh
                    const cached = this._prefetchCache.get(cacheKey);
                    const detailsPromise = cached ?
                        cached.promise :
                        this.fetchDetails(entity, messageId, this._fetchController.signal);

                    detailsPromise
                        .then(details => {
                            if (!details) throw new Error('No details');
                            this.details = details;
                            this.isLoading = false;

                            // Handle image (imageLoading stays true until browser <img> fires load/error)
                            if (details.image_url) {
                                this.imageLoading = true;
                                this.imageUrl = details.image_url;
                                this.imageSource = details.image_source || '';
                                this.imageDomain = details.image_domain || '';
                                this.imageLink = details.image_link || '';
                            } else if (details.image_search_query) {
                                this.fetchImage(details.image_search_query, messageId, entity.text);
                            }
                        })
                        .catch(err => {
                            if (err.name !== 'AbortError') {
                                this.isLoading = false;
                                this.hasError = true;
                            }
                        });
                },

                close() {
                    this.isOpen = false;

                    if (this._fetchController) {
                        this._fetchController.abort();
                        this._fetchController = null;
                    }

                    document.body.classList.toggle('lqd-entity-drawer-open', this.isOpen);
                },

                prefetch({
                    entity,
                    messageId
                }) {
                    const cacheKey = entity.text + '|' + (messageId || '');
                    if (this._prefetchCache.has(cacheKey)) return;

                    const controller = new AbortController();
                    const promise = this.fetchDetails(entity, messageId, controller.signal)
                        .catch(() => null);

                    this._prefetchCache.set(cacheKey, {
                        promise,
                        controller
                    });
                },

                async fetchDetails(entity, messageId, signal) {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

                    const response = await fetch('/dashboard/user/ai-chat-pro-entity-highlight/entity-detail', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            entity_text: entity.text,
                            entity_type: entity.type,
                            message_id: messageId,
                            context: entity.context_snippet || '',
                        }),
                        signal,
                    });

                    if (!response.ok) throw new Error('Failed to fetch entity details');
                    return response.json();
                },

                async fetchImage(query, messageId, entityText) {
                    this.imageLoading = true;

                    try {
                        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                        const response = await fetch('/dashboard/user/ai-chat-pro-entity-highlight/entity-image', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({
                                query,
                                message_id: messageId,
                                entity_text: entityText
                            }),
                        });

                        const data = await response.json();

                        if (data.image_url) {
                            // imageLoading stays true until the browser <img> fires load/error
                            this.imageUrl = data.image_url;
                            this.imageSource = data.image_source || '';
                            this.imageDomain = data.image_domain || '';
                            this.imageLink = data.image_link || '';
                        } else {
                            this.imageLoading = false;
                        }
                    } catch {
                        this.imageLoading = false;
                    }
                },

                openLightbox() {
                    if (this.imageUrl && smartImageLightbox?.open) {
                        smartImageLightbox.open(
                            [{
                                url: this.imageUrl,
                                title: this.details?.title || this.currentEntity?.text || '',
                                source: this.imageSource || '',
                                domain: this.imageDomain || '',
                                link: this.imageLink || '',
                            }],
                            0
                        );
                    }
                },

                onFollowupClick() {
                    this.addToolSelection('entity-follow-up-' + this.currentEntity.text);

                    document.querySelector('#prompt')?.focus();
                },
            }));
        });
    </script>
@endpush
