<div
    class="pb-20"
    x-show="images.length"
    x-cloak
    x-data="galleryPreview"
>
    {{-- Header --}}
    <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
        <h2>
            <span class="block text-[0.7em] opacity-50">
                {{ __('Hello') . ' ' . auth()?->user()?->name }},
            </span>
            {{ __('Get inspired') }}
        </h2>

        <x-button
            @click.prevent="switchView('community')"
            href="#"
            variant="link"
        >
            {{ __('View All') }}
            <x-tabler-arrow-right class="size-4 transition group-hover:translate-x-1 rtl:rotate-180" />
        </x-button>
    </div>

    {{-- Image Grid --}}
    <x-masonry-grid
        class="-mx-0.5 flex flex-wrap"
        x-ref="masonryGrid"
    >
        <template
            x-for="(image, index) in images"
            {{-- Disabling :key to avoid conflict between Alpine and Isotope --}}
            {{-- :key="image.id" --}}
        >
            <x-masonry-grid-item class="mb-0.5 w-1/2 px-px md:w-1/3">
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

    <div class="mt-20 flex justify-center">
        <x-button
            class="rounded-xl text-sm leading-relaxed outline-2"
            @click.prevent="switchView('community')"
            href="#"
            variant="outline"
            hover-variant="primary"
        >
            {{ __('View More') }}
            <x-tabler-arrow-right class="size-4 transition group-hover:translate-x-1 rtl:rotate-180" />
        </x-button>
    </div>
</div>

@pushOnce('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('galleryPreview', () => ({
                images: [],
                galleryPrefix: 'preview',

                async init() {
                    await this.loadImages();
                },

                async loadImages() {
                    try {
                        const response = await fetch('{{ route('ai-image-pro.community.images') }}?page=1');
                        if (!response.ok) {
                            throw new Error('Failed to fetch images');
                        }
                        const data = await response.json();
                        this.images = data.images || data;
                    } catch (error) {
                        console.error('Failed to load images:', error);
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
