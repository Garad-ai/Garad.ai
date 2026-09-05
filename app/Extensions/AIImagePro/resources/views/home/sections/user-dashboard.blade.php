<div
    class="container"
    x-data="aiImageProImagesManager"
>
    {{-- Tabs Navigation --}}
    <div
        class="mb-9 flex flex-wrap items-center justify-between gap-x-2 gap-y-5"
        x-ref="tabsNav"
    >
        <div class="flex grow items-center overflow-x-auto whitespace-nowrap py-1">
            <button
                class="selected flex items-center gap-2 rounded-full px-6 py-[18px] text-sm font-medium text-heading-foreground transition [&.selected]:bg-heading-foreground/5"
                @click.prevent="if ( activeTab !== 'creations' ) { switchingTab = true; await loadImages('creations'); }"
                :class="{ 'selected': activeTab === 'creations' }"
            >
                {{ __('My creations') }}
                <x-tabler-loader-2
                    class="size-4 shrink-0 animate-spin"
                    x-show="switchingTab && loading && activeTab === 'creations'"
                    x-cloak
                />
            </button>

            <button
                class="flex items-center gap-2 rounded-full px-6 py-[18px] text-sm font-medium text-heading-foreground transition [&.selected]:bg-heading-foreground/5"
                @click.prevent="if ( activeTab !== 'inspired' ) { switchingTab = true; await loadImages('inspired'); }"
                :class="{ 'selected': activeTab === 'inspired' }"
            >
                {{ __('Get Inspired') }}
                <x-tabler-loader-2
                    class="size-4 shrink-0 animate-spin"
                    x-show="switchingTab && loading && activeTab === 'inspired'"
                    x-cloak
                />
            </button>

            <button
                class="flex items-center gap-2 rounded-full px-6 py-[18px] text-sm font-medium text-heading-foreground transition [&.selected]:bg-heading-foreground/5"
                @click.prevent="if ( activeTab !== 'bookmarks' ) { switchingTab = true; await loadImages('bookmarks'); }"
                :class="{ 'selected': activeTab === 'bookmarks' }"
            >
                {{ __('Bookmarks') }}
                <x-tabler-loader-2
                    class="size-4 shrink-0 animate-spin"
                    x-show="switchingTab && loading && activeTab === 'bookmarks'"
                    x-cloak
                />
            </button>

            {{-- Videos and Images tab hidden for now
			<button
				class="flex items-center gap-2 rounded-full px-6 py-[18px] text-sm font-medium text-heading-foreground transition [&.selected]:bg-heading-foreground/5"
				@click.prevent="if ( activeTab !== 'videos' ) { switchingTab = true; await loadImages('videos'); }"
				:class="{ 'selected': activeTab === 'videos' }"
			>
				{{ __('Videos') }}
				 <x-tabler-loader-2 class="size-4 animate-spin shrink-0" x-show="switchingTab && loading && activeTab === 'videos'" x-cloak />
			</button>


            <button
                class="flex items-center gap-2 rounded-full px-6 py-[18px] text-sm font-medium text-heading-foreground transition [&.selected]:bg-heading-foreground/5"
                @click.prevent="if ( activeTab !== 'images' ) { switchingTab = true; await loadImages('images'); }"
                :class="{ 'selected': activeTab === 'images' }"
            >
                {{ __('Images') }}
                <x-tabler-loader-2
                    class="size-4 shrink-0 animate-spin"
                    x-show="switchingTab && loading && activeTab === 'images'"
                    x-cloak
                />
            </button>
			--}}
        </div>

        {{-- Grid Size Control --}}
        <div class="flex items-center gap-3">
            <span class="whitespace-nowrap text-sm font-medium text-heading-foreground sm:hidden">
                {{ __('Grid size') }}
            </span>
            <input
                class="h-0.5 w-36 cursor-pointer appearance-none rounded-full bg-foreground/10 focus:outline-primary [&::-moz-range-thumb]:size-2.5 [&::-moz-range-thumb]:appearance-none [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border-none [&::-moz-range-thumb]:bg-primary active:[&::-moz-range-thumb]:scale-110 [&::-webkit-slider-thumb]:size-2.5 [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:border-none [&::-webkit-slider-thumb]:bg-primary active:[&::-webkit-slider-thumb]:scale-110"
                type="range"
                x-model.number="gridSize"
                @input="saveGridSize()"
                min="2"
                max="6"
                step="1"
            />
        </div>
    </div>

    <div
        class="-mx-0.5 flex flex-wrap items-start"
        x-show="loading && !images.length"
    >
        @for ($i = 0; $i < 10; $i++)
            <div
                class="masonry-grid-item mb-0.5 w-1/3 px-px"
                :style="{ width: `${100 / gridSize}%` }"
            >
                <div class="lqd-loading-skeleton lqd-is-loading relative aspect-square w-full overflow-hidden rounded">
                    <div
                        class="absolute size-full"
                        data-lqd-skeleton-el
                    ></div>
                </div>
            </div>
        @endfor
    </div>

    {{-- Image Grid --}}
    <x-masonry-grid
        class="-mx-0.5 flex flex-wrap items-start"
        x-ref="masonryGrid"
        {{-- x-show="loading || images.length" --}}
    >
        <template
            x-for="(image, index) in images"
            {{-- Disabling :key to avoid conflict between Alpine and Isotope --}}
            {{-- :key="image.id" --}}
        >
            <x-masonry-grid-item
                class="mb-0.5 w-1/3 px-px"
                ::style="{ width: `${100 / gridSize}%` }"
            >
                @include('ai-image-pro::includes.image-grid-item', ['aspect_ratio' => 'dynamic'])

                <template x-if="index === images.length - 1">
                    <div x-init="$nextTick(() => {
                        if ($refs.masonryGrid.classList.contains('masonry-grid-initialized')) {
                            $dispatch('masonry:layout');
                        } else {
                            $dispatch('masonry:init');
                        }
                        $el.remove();
                    });"></div>
                </template>
            </x-masonry-grid-item>
        </template>
    </x-masonry-grid>

    {{-- Loading More Indicator --}}
    <p
        class="m-0 flex items-center justify-center gap-2 py-10 text-center text-sm font-medium text-heading-foreground"
        x-show="hasMore"
        x-intersect:enter.margin.500px="hasMore && !loading ? loadMoreImages() : null"
    >
        <x-tabler-loader-2 class="size-4 shrink-0 animate-spin" />
        {{ __('Loading images...') }}
    </p>

    {{-- All Loaded Message --}}
    <p
        class="m-0 flex items-center justify-center gap-2 py-10 text-center text-sm font-medium text-heading-foreground"
        x-show="!hasMore && images.length && !loading"
        x-cloak
    >
        <x-tabler-check class="size-4" />
        {{ __('All images loaded') }}
    </p>

    {{-- Empty State --}}
    <div
        class="py-16 text-center"
        x-show="!loading && !images.length"
        x-cloak
    >
        <div class="mx-auto mb-3 inline-grid size-28 place-items-center rounded-full bg-foreground/5">
            <x-tabler-photo-off class="size-10" />
        </div>

        <p class="mb-1 text-lg font-medium">
            <span
                x-text="activeTab === 'creations' ? '{{ __('No creations yet') }}' :
                      activeTab === 'inspired' ? '{{ __('No community images yet') }}' :
                      activeTab === 'bookmarks' ? '{{ __('No bookmarks yet') }}' :
                      activeTab === 'videos' ? '{{ __('No videos yet') }}' :
                      activeTab === 'images' ? '{{ __('No images yet') }}' :
                      '{{ __('No content yet') }}'"
            >
            </span>
        </p>
        <p class="m-0 text-xs font-medium opacity-50">
            {{ __('Start creating amazing AI-generated content') }}
        </p>
    </div>
</div>

@push('script')
    <script>
        document.addEventListener('alpine:init', function() {
            Alpine.data('aiImageProImagesManager', () => ({
                galleryPrefix: 'dashboard',
                activeTab: 'creations',
                gridSize: 3,
                selectedImages: [],
                images: [],
                hasMore: true,
                currentPage: 1,
                loading: false,
                switchingTab: false,

                init() {
                    // Load saved grid size from storage
                    const savedSize = this.getStoredGridSize();
                    if (savedSize) {
                        this.gridSize = savedSize;
                    }

                    const initialTab = this.getInitialTabFromSlug();
                    this.activeTab = initialTab;
                    const shouldScrollToTabs = this.hasSlugInUrl();

                    this.loadImages(initialTab);

                    if (shouldScrollToTabs) {
                        this.$nextTick(() => this.scrollToTabs());
                    }

                    window.addEventListener('ai-images-completed', () => {
                        if (this.activeTab === 'creations') {
                            this.loadImages('creations');
                        }
                    });
                },

                getInitialTabFromSlug() {
                    try {
                        const slug = new URLSearchParams(window.location.search).get('slug');
                        const tab = (slug || '').toLowerCase();
                        return ['bookmarks', 'inspired'].includes(tab) ? tab : 'creations';
                    } catch (error) {
                        return 'creations';
                    }
                },

                hasSlugInUrl() {
                    try {
                        return !!new URLSearchParams(window.location.search).get('slug');
                    } catch (error) {
                        return false;
                    }
                },

                scrollToTabs() {
                    const tabsElement = this.$refs.tabsNav;
                    if (!tabsElement) return;

                    const formEl = document.querySelector('#submitForm');
                    const formHeight = formEl?.getBoundingClientRect().height ?? 0;
                    const top = tabsElement.getBoundingClientRect().top + window.scrollY - 50 - formHeight;

                    window.scrollTo({
                        top: Math.max(top, 0),
                        behavior: 'smooth'
                    });
                },

                getStoredGridSize() {
                    try {
                        return parseInt(localStorage.getItem('dashboardGridSize')) || 3;
                    } catch (error) {
                        return 3;
                    }
                },

                saveGridSize() {
                    try {
                        localStorage.setItem('dashboardGridSize', this.gridSize.toString());
                    } catch (error) {
                        console.error('Failed to save grid size:', error);
                    }

                    this.$nextTick(() => {
                        this.$refs.masonryGrid?.dispatchEvent(new CustomEvent('masonry:layout'));
                    });
                },

                toggleSelect(imageId) {
                    const index = this.selectedImages.indexOf(imageId);
                    if (index > -1) {
                        this.selectedImages.splice(index, 1);
                    } else {
                        this.selectedImages.push(imageId);
                    }
                },

                isSelected(imageId) {
                    return this.selectedImages.includes(imageId);
                },

                getFavorites() {
                    try {
                        const saved = localStorage.getItem('galleryFavorites');
                        return saved ? JSON.parse(saved) : [];
                    } catch (error) {
                        console.error('Failed to load favorites:', error);
                        return [];
                    }
                },

                buildFetchConfig(tab, page) {
                    if (tab === 'inspired') {
                        return {
                            url: `{{ route('ai-image-pro.community.images') }}?page=${page}`,
                            options: {
                                method: 'GET',
                                headers: {
                                    'Content-Type': 'application/json'
                                }
                            }
                        };
                    }

                    const url = new URL('{{ route('dashboard.user.ai-image-pro.images') }}');
                    url.searchParams.append('filter', tab);
                    url.searchParams.append('page', page);

                    if (tab === 'bookmarks') {
                        return {
                            url: url,
                            options: {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    filter: tab,
                                    page: page,
                                    favorites: this.getFavorites()
                                })
                            }
                        };
                    }

                    return {
                        url: url,
                        options: {
                            method: 'GET',
                            headers: {
                                'Content-Type': 'application/json'
                            }
                        }
                    };
                },

                async loadImages(tab) {
                    this.loading = true;
                    this.currentPage = 1;
                    this.activeTab = tab;

                    try {
                        const {
                            url,
                            options
                        } = this.buildFetchConfig(tab, 1);
                        const response = await fetch(url, options);

                        if (!response.ok) {
                            throw new Error('Failed to fetch images');
                        }

                        const data = await response.json();
                        this.images = data.images || [];
                        this.hasMore = data.hasMore || false;
                        this.currentPage = data.page || 1;
                    } catch (error) {
                        console.error('Failed to load images:', error);
                    } finally {
                        this.loading = false;
                        this.switchingTab = false;

                        this.$nextTick(() => {
                            this.$refs.masonryGrid?.dispatchEvent(new CustomEvent('masonry:layout'));
                        });
                    }
                },

                openImageModal(image) {
                    this.setActiveModal(image, 'dashboard');
                },

                async loadMoreImages() {
                    if (this.loading || !this.hasMore) return;

                    this.loading = true;
                    this.currentPage++;

                    try {
                        const {
                            url,
                            options
                        } = this.buildFetchConfig(this.activeTab, this.currentPage);
                        const response = await fetch(url, options);

                        if (!response.ok) {
                            throw new Error('Failed to fetch images');
                        }

                        const data = await response.json();
                        const newImages = data.images || [];

                        if (newImages.length > 0) {
                            this.images = [...this.images, ...newImages];
                            this.hasMore = data.hasMore || false;
                        } else {
                            this.hasMore = false;
                        }
                    } catch (error) {
                        console.error('Failed to load more images:', error);
                    } finally {
                        this.loading = false;
                    }
                }
            }));
        });
    </script>
@endpush
