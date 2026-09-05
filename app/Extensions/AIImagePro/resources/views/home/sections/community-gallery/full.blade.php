@php
    if (auth()->check()) {
        $user_avatar = auth()->user()->avatar;
        if (!auth()->user()->github_token && !auth()->user()->google_token && !auth()->user()->facebook_token) {
            $user_avatar = '/' . $user_avatar;
        }
    } else {
        $user_avatar = '/assets/img/auth/default-avatar.png';
    }
@endphp

<div
    class="fixed inset-0 z-50 overflow-y-auto overscroll-contain bg-background"
    x-show="currentView === 'community'"
    x-cloak
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 transform scale-95"
    x-transition:enter-end="opacity-100 transform scale-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100 transform scale-100"
    x-transition:leave-end="opacity-0 transform scale-95"
    x-data="communityGalleryFull"
>
    <div class="min-h-screen bg-gradient-to-b from-background to-foreground/5 py-8 md:py-12 lg:ps-[--navbar-width]">
        <div class="container">
            {{-- Header --}}
            <div class="mb-12 flex flex-col items-center">
                <div class="grid w-full grid-cols-3 items-center gap-4">
                    <div class="flex gap-1">
                        <x-button
                            @click.prevent="switchView('<')"
                            variant="ghost"
                            size="sm"
                        >
                            <x-tabler-arrow-left class="size-4 rtl:rotate-180" />
                            {{ __('Back') }}
                        </x-button>
                    </div>

                    <div>
                        {{-- User Avatar - conditionally show filtered user or current user --}}
                        <div class="mx-auto mb-2.5 size-24 overflow-hidden rounded-full md:size-[120px]">
                            <template x-if="filterUserData && filterUserData.avatar">
                                <img
                                    class="h-full w-full object-cover"
                                    :src="filterUserData.avatar"
                                    aria-hidden="true"
                                    alt=""
                                >
                            </template>
                            <template x-if="filterUserData && !filterUserData.avatar">
                                <div class="flex h-full w-full items-center justify-center bg-foreground/10">
                                    <span
                                        class="text-4xl font-bold text-foreground"
                                        x-text="filterUserData.initial"
                                    ></span>
                                </div>
                            </template>
                            <template x-if="!filterUserData">
                                <img
                                    class="h-full w-full object-cover"
                                    src="{{ custom_theme_url($user_avatar) }}"
                                    aria-hidden="true"
                                    alt=""
                                >
                            </template>
                        </div>

                        {{-- Title - conditionally show filtered user's name or "Community Gallery" --}}
                        <h2 class="text-center text-xl font-medium md:text-[23px]">
                            <template x-if="filterUserData">
                                <span x-text="`${filterUserData.name}'s {{ __('Archive') }}`"></span>
                            </template>
                            <template x-if="!filterUserData">
                                <span>{{ __('Community Gallery') }}</span>
                            </template>
                        </h2>
                    </div>

                    <div class="flex justify-end gap-2">
                        {{-- Clear Filter Button - only show when filtered --}}
                        <x-button
                            @click.prevent="clearUserFilter()"
                            variant="ghost"
                            size="sm"
                            x-show="filterUserId"
                        >
                            <x-tabler-x class="size-4" />
                            {{ __('Clear filter') }}
                        </x-button>
                    </div>
                </div>
            </div>

            {{-- Loading Indicator --}}
            <div
                class="flex items-center justify-center py-20"
                x-show="loading && images.length === 0"
            >
                <div class="h-12 w-12 animate-spin rounded-full border-b-2 border-primary"></div>
            </div>

            @include('ai-image-pro::includes.image-grid')

            {{-- Loading More --}}
            <p
                class="flex items-center justify-center gap-2 px-4 py-12 text-center text-xs font-medium"
                x-show="loading && images.length > 0"
            >
                <x-tabler-loader-2 class="size-5 animate-spin" />
                {{ __('Loading more images...') }}
            </p>

            {{-- All Loaded Message --}}
            <p
                class="flex items-center justify-center gap-2 px-4 py-12 text-center text-xs font-medium"
                x-show="!hasMore && images.length > 0 && !loading"
            >
                {{ __('All images loaded') }}
                <x-tabler-check class="size-5" />
            </p>

            {{-- Empty State --}}
            <x-empty-state
                icon="tabler-photo-off"
                :title="__('No Images Yet')"
                :description="__('The community gallery is empty. Be the first to share your creations!')"
                show="!loading && images.length === 0"
            />
        </div>
    </div>
</div>

@pushOnce('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('communityGalleryFull', () => ({
                images: [],
                page: 1,
                loading: false,
                hasMore: true,
                filterUserId: null,
                filterUserData: null,
                galleryPrefix: 'community',

                async init() {
                    // Listen for filter event FIRST - this sets filterUserId before images load
                    window.addEventListener('filter-user-gallery', (e) => {
                        this.filterByUser(e.detail.userId, e.detail.userData);
                    });

                    this.$watch('currentView', (newView) => {
                        // Only auto-load if switching to community AND no filter is pending
                        // If filterUserId is set, filterByUser already handles loading
                        if (newView === 'community' && this.images.length === 0 && !this.filterUserId) {
                            this.loadImages();
                        }
                    });

                    this.$nextTick(() => {
                        if (this.getParentView() === 'community' && this.images.length === 0 && !this.filterUserId) {
                            this.loadImages().then(() => {
                                this.checkUrlParams();
                            });
                        }
                    });
                },

                checkUrlParams() {
                    const urlParams = new URLSearchParams(window.location.search);
                    const imageId = urlParams.get('image');
                    const userId = urlParams.get('user');

                    if (userId) {
                        this.filterByUser(userId);
                    } else if (imageId) {
                        const image = this.images.find(img => img.id == imageId);
                        if (image) {
                            this.$nextTick(() => {
                                this.openImageModal(image, 'community');
                            });
                        }
                    }
                },

                async filterByUser(userId, userData = null) {
                    this.filterUserId = userId;
                    this.filterUserData = userData;
                    this.images = [];
                    this.page = 1;
                    this.hasMore = true;
                    await this.loadImages();

                    const url = new URL(window.location);
                    url.searchParams.set('user', userId);
                    window.history.pushState({}, '', url);
                },

                clearUserFilter() {
                    this.filterUserId = null;
                    this.filterUserData = null;
                    this.images = [];
                    this.page = 1;
                    this.hasMore = true;
                    this.loadImages();

                    const url = new URL(window.location);
                    url.searchParams.delete('user');
                    window.history.pushState({}, '', url);
                },

                getParentView() {
                    const parentComponent = this.$el.closest('.lqd-adv-img-editor');
                    if (parentComponent && parentComponent._x_dataStack) {
                        const parentData = parentComponent._x_dataStack[0];
                        return parentData?.currentView || 'home';
                    }
                    return 'home';
                },

                async loadImages() {
                    if (this.loading || !this.hasMore) return;
                    this.loading = true;

                    try {
                        const url = new URL('{{ route('ai-image-pro.community.images') }}');
                        url.searchParams.append('page', this.page);

                        if (this.filterUserId) {
                            url.searchParams.append('user_id', this.filterUserId);
                        }

                        const response = await fetch(url);

                        if (!response.ok) {
                            throw new Error('Failed to fetch images');
                        }

                        const data = await response.json();
                        if (data.images && data.images.length > 0) {
                            this.images = [...this.images, ...data.images];
                            this.hasMore = data.hasMore !== false;
                            this.page++;
                        } else {
                            this.hasMore = false;
                        }
                    } catch (error) {
                        console.error('Failed to load images:', error);
                        this.hasMore = false;
                    } finally {
                        this.loading = false;
                    }
                },

                loadMore() {
                    if (!this.loading && this.hasMore) {
                        this.loadImages();
                    }
                },

                openImageModal(image, prefix) {
                    const parentComponent = this.$el.closest('.lqd-adv-img-editor');
                    if (parentComponent && parentComponent._x_dataStack) {
                        const parentData = parentComponent._x_dataStack[0];
                        if (parentData.setActiveModal) {
                            parentData.setActiveModal(image, prefix || this.galleryPrefix);
                        }
                    }
                },

                toggleFavorite(imageId) {
                    const parentComponent = this.$el.closest('.lqd-adv-img-editor');
                    if (parentComponent && parentComponent._x_dataStack) {
                        const parentData = parentComponent._x_dataStack[0];
                        if (parentData.toggleFavorite) {
                            parentData.toggleFavorite(imageId);
                        }
                    }
                },

                isFavorite(imageId) {
                    const parentComponent = this.$el.closest('.lqd-adv-img-editor');
                    if (parentComponent && parentComponent._x_dataStack) {
                        const parentData = parentComponent._x_dataStack[0];
                        return parentData.favorites?.includes(imageId) || false;
                    }
                    return false;
                },

                switchView(view) {
                    const parentComponent = this.$el.closest('.lqd-adv-img-editor');
                    if (parentComponent && parentComponent._x_dataStack) {
                        const parentData = parentComponent._x_dataStack[0];
                        if (parentData.switchView) {
                            parentData.switchView(view);
                        }
                    }
                },

                showNotification(message, type = 'info') {
                    if (window.toastr) {
                        window.toastr[type](message);
                    }
                }
            }));
        });
    </script>
@endPushOnce
