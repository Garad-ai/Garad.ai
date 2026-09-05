<div x-data="aiImageProGenerationsDropdown">
    <x-dropdown.dropdown class:dropdown-dropdown="w-[min(100%,300px)]">
        <x-slot:trigger
            class="inline-flex items-center gap-1.5 px-0"
            variant="none"
        >
            <span class="relative inline-grid size-[38px] grid-cols-1 place-items-center rounded-button border border-foreground/10 text-base font-medium text-heading-foreground">
                <svg
                    style="stroke-dasharray: 130; stroke-dashoffset: 100;"
                    width="43"
                    height="43"
                    viewBox="0 0 43 43"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                    @class([
                        'col-start-1 col-end-1 row-start-1 row-end-1 h-full w-full animate-spin',
                        'hidden' => $imageStats['in_progress_count'] === 0,
                    ])
                    :class="{ 'hidden': inProgressCount === 0 }"
                >
                    <circle
                        cx="21.5"
                        cy="21.5"
                        r="20.5"
                        stroke="currentColor"
                        stroke-width="2"
                    />
                </svg>
                <span
                    x-text="inProgressCount >= 100 ? '99+' : inProgressCount"
                    @class([
                        'col-start-1 col-end-1 row-start-1 row-end-1',
                        'hidden' => $imageStats['in_progress_count'] === 0,
                    ])
                    :class="{ 'hidden': inProgressCount === 0 }"
                >
                    {{ $imageStats['in_progress_count'] >= 100 ? '99+' : $imageStats['in_progress_count'] }}
                </span>
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="32"
                    height="32"
                    fill="currentColor"
                    viewBox="0 0 256 256"
                    @class([
                        'col-start-1 col-end-1 row-start-1 row-end-1 size-[19px]',
                        'hidden' => $imageStats['in_progress_count'] > 0,
                    ])
                    :class="{ 'hidden': inProgressCount > 0 }"
                >
                    <path
                        d="M208,88H48a16,16,0,0,0-16,16v96a16,16,0,0,0,16,16H208a16,16,0,0,0,16-16V104A16,16,0,0,0,208,88Zm0,112H48V104H208v96ZM48,64a8,8,0,0,1,8-8H200a8,8,0,0,1,0,16H56A8,8,0,0,1,48,64ZM64,32a8,8,0,0,1,8-8H184a8,8,0,0,1,0,16H72A8,8,0,0,1,64,32Z"
                    ></path>
                </svg>
            </span>
            <span
                @class([
                    'hidden' => $imageStats['in_progress_count'] === 0,
                ])
                :class="{ 'hidden': inProgressCount === 0 }"
            >
                {{ __('In progress.') }}
            </span>
        </x-slot:trigger>

        <x-slot:dropdown
            class="w-full max-h-[calc(100vh-var(--header-height,0px)-var(--bottom-menu-height,0px)-var(--body-padding,0px)-1rem)] overflow-y-auto"
        >
            <!-- In Progress Section -->
            <template x-if="inProgressCount > 0">
                <div>
                    <p class="m-0 block border-b px-4 py-3 text-4xs font-medium uppercase tracking-widest text-foreground/50">
                        {{ __('In progress') }}
                    </p>
                    <template
                        x-for="image in inProgressImages"
                        :key="image.id"
                    >
                        <div class="flex items-center gap-3 border-b px-6 py-3.5 last:border-b-0">
                            <div class="lqd-loading-skeleton lqd-is-loading relative size-6 shrink-0 overflow-hidden rounded">
                                <div
                                    class="absolute size-full"
                                    data-lqd-skeleton-el
                                ></div>
                            </div>
                            <p
                                class="m-0 w-full truncate text-2xs font-medium"
                                :title="image.prompt"
                                x-text="image.prompt"
                            ></p>
                        </div>
                    </template>
                </div>
            </template>

            <!-- Generated Images Section -->
            <template x-if="completedCount > 0 || completedImages.length > 0">
                <div :class="{ 'border-t': inProgressCount > 0 }">
                    <p class="m-0 block border-b px-4 py-3 text-4xs font-medium uppercase tracking-widest text-foreground/50">
                        {{ __('Generated Images') }}
                    </p>

                    <div
                        class="w-full"
                        x-ref="completedImagesContainer"
                        @scroll="handleScroll"
                    >
                        <template x-for="item in flattenedCompletedImages" :key="item.uniqueKey">
                            <div
                                class="image-result flex w-full cursor-pointer items-center gap-3 border-b px-6 py-3.5 transition last:border-b-0 hover:bg-foreground/5"
                                style="order: var(--item-order);"
                                :style="'order: ' + item.sortOrder"
                                data-id-prefix="topnav"
                                :data-id="`topnav-${item.imageId}-${item.imageIndex}`"
                                :data-payload="JSON.stringify({ ...item.image, url: item.url, output: item.url, title: item.image.prompt, input: item.image.prompt, prompt: item.image.prompt })"
                                @click="openImageModal(item.image, item.url, item.imageIndex); toggle('collapse')"
                            >
                                <img
                                    class="size-6 shrink-0 rounded object-cover"
                                    src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0iI2UwZTBlMCI+PHJlY3Qgd2lkdGg9IjI0IiBoZWlnaHQ9IjI0IiByeD0iNCIvPjwvc3ZnPg=="
                                    :data-src="`${(item.thumbnail || item.url).startsWith('upload') ? '/' : ''}${item.thumbnail || item.url}`"
                                    :alt="item.image.prompt"
									x-intersect:enter.once="$el.src = $el.getAttribute('data-src')"
                                >
                                <p
                                    class="m-0 truncate text-2xs font-medium"
                                    :title="item.image.prompt"
                                    x-text="item.image.prompt"
                                ></p>
                            </div>
                        </template>

                        <!-- Loading indicator -->
                        <div
                            x-show="loadingMore"
                            class="flex items-center justify-center gap-2 px-6 py-3.5 text-foreground/50"
                        >
                            <svg
                                class="size-4 animate-spin"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <circle
                                    class="opacity-25"
                                    cx="12"
                                    cy="12"
                                    r="10"
                                    stroke="currentColor"
                                    stroke-width="4"
                                ></circle>
                                <path
                                    class="opacity-75"
                                    fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                ></path>
                            </svg>
                            <span class="text-2xs">{{ __('Loading...') }}</span>
                        </div>

                        <!-- Load more trigger (invisible sentinel element for infinite scroll) -->
                        <div
                            x-show="hasMoreImages && !loadingMore"
                            x-intersect:enter="loadMoreImages"
                            class="h-1"
                        ></div>
                    </div>
                </div>
            </template>

            <template x-if="completedCount === 0 && inProgressCount === 0">
                <x-empty-state
                    class="p-5"
                    icon="tabler-photo-off"
                    title="{{ __('No images yet') }}"
                    description="{{ __('Your generated images will appear here') }}"
                />
            </template>
        </x-slot:dropdown>
    </x-dropdown.dropdown>
</div>

@pushOnce('script')
    <script>
        (() => {
            document.addEventListener('alpine:init', () => {
                Alpine.data('aiImageProGenerationsDropdown', () => ({
                    inProgressCount: {{ $imageStats['in_progress_count'] ?? 0 }},
                    completedCount: {{ $imageStats['completed_count'] ?? 0 }},
                    inProgressImages: @json($imageStats['in_progress_images'] ?? []),
                    completedImages: @json($imageStats['completed_images'] ?? []),
                    pollingInterval: null,
                    imagesLoaded: false,
                    currentPage: 1,
                    hasMoreImages: {{ ($imageStats['completed_count'] ?? 0) > 20 ? 'true' : 'false' }},
                    loadingMore: false,

                    get flattenedCompletedImages() {
                        const items = [];
                        let sortOrder = 0;
                        for (const image of this.completedImages) {
                            if (image.generated_images && image.generated_images.length > 0) {
                                for (let index = 0; index < image.generated_images.length; index++) {
                                    items.push({
                                        uniqueKey: `${image.id}-${index}`,
                                        imageId: image.id,
                                        imageIndex: index,
                                        url: image.generated_images[index],
                                        thumbnail: image.thumbnails?.[index] || image.generated_images[index],
                                        image: image,
                                        sortOrder: sortOrder++
                                    });
                                }
                            }
                        }
                        return items;
                    },

                    init() {
                        this.startPolling();

                        // Listen for new image generation requests
                        window.addEventListener('ai-image-generation-started', (event) => {
                            this.onGenerationStarted(event.detail);
                        });
                    },

                    onGenerationStarted(detail) {
                        // Immediately increment in-progress count for instant feedback
                        this.inProgressCount++;

                        // Fetch fresh stats after a short delay
                        setTimeout(() => {
                            this.fetchImageStats();
                        }, 1000);

                        // Ensure polling is running
                        this.startPolling();
                    },

                    startPolling() {
                        // Clear any existing interval first
                        if (this.pollingInterval) {
                            clearInterval(this.pollingInterval);
                        }

                        if (this.inProgressCount > 0) {
                            this.pollingInterval = setInterval(() => {
                                this.fetchImageStats();
                            }, 5000);
                        }
                    },

                    stopPolling() {
                        if (this.pollingInterval) {
                            clearInterval(this.pollingInterval);
                            this.pollingInterval = null;
                        }
                    },

                    async fetchImageStats() {
                        try {
                            const response = await fetch('{{ route('ai-image-pro.stats') }}');
                            const data = await response.json();

                            // Check if any images completed (transitioned from in-progress to completed)
                            const hadInProgress = this.inProgressCount > 0;
                            const previousInProgressCount = this.inProgressCount;

                            this.inProgressCount = data.in_progress_count;
                            this.inProgressImages = data.in_progress_images;

                            // When images complete, reset pagination and reload completed images
                            if (hadInProgress && this.inProgressCount < previousInProgressCount) {
                                this.currentPage = 1;
                                this.completedImages = data.completed_images;
                                this.completedCount = data.completed_count;
                                this.hasMoreImages = data.completed_count > 20;
                                this.refreshDashboard();
                            }

                            if (this.inProgressCount === 0) {
                                this.stopPolling();
                            }
                        } catch (error) {
                            console.error('Error fetching image stats:', error);
                        }
                    },

                    async loadMoreImages() {
                        if (this.loadingMore || !this.hasMoreImages) {
                            return;
                        }

                        this.loadingMore = true;

                        try {
                            const nextPage = this.currentPage + 1;
                            const response = await fetch(`{{ route('ai-image-pro.completed-images') }}?page=${nextPage}`);
                            const data = await response.json();

                            if (data.images && data.images.length > 0) {
                                this.completedImages = [...this.completedImages, ...data.images];
                                this.currentPage = data.page;
                                this.hasMoreImages = data.has_more;
                            } else {
                                this.hasMoreImages = false;
                            }
                        } catch (error) {
                            console.error('Error loading more images:', error);
                        } finally {
                            this.loadingMore = false;
                        }
                    },

                    handleScroll(event) {
                        const container = event.target;
                        const scrollTop = container.scrollTop;
                        const scrollHeight = container.scrollHeight;
                        const clientHeight = container.clientHeight;

                        // Load more when user is near the bottom (within 100px)
                        if (scrollHeight - scrollTop - clientHeight < 100) {
                            this.loadMoreImages();
                        }
                    },

                    refreshDashboard() {
                        window.dispatchEvent(new CustomEvent('ai-images-completed', {
                            detail: {
                                completedImages: this.completedImages
                            }
                        }));
                    },

                    // Open modal using parent component
                    openImageModal(image, generatedImageUrl, index) {
                        // Create a complete image data object
                        const uniqueId = `topnav-${image.id}-${index}`;
                        const imageData = {
                            id: image.id,
                            url: generatedImageUrl,
                            output: generatedImageUrl,
                            title: image.prompt,
                            input: image.prompt,
                            prompt: image.prompt,
                            date: image.date || 'Today',
                            model: image.model || null,
                            style: image.style || null,
                            ratio: image.ratio || null,
                            credits: image.credits || null,
                            can_publish: image.can_publish || false,
                            published: image.published || false,
                            tags: image.tags || [],
                            user: image.user || null
                        };

                        // Get parent component and call setActiveModal with the unique ID
                        const parentEl = document.querySelector('.lqd-adv-img-editor');
                        if (parentEl && parentEl._x_dataStack && parentEl._x_dataStack[0]) {
                            const parentData = parentEl._x_dataStack[0];
                            if (parentData.setActiveModal) {
                                // Use the parent modal API so image loading state is initialized correctly.
                                parentData.setActiveModal(imageData, uniqueId);
                            }
                        }
                    },
                }))
            })
        })();
    </script>
@endPushOnce
