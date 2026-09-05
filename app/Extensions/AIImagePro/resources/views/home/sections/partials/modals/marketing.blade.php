@php
    $templates_list_url = url('/vendor/ai-image-pro/templates/templates.json?v=' . time());
@endphp

<div
    class="flex h-full flex-wrap items-start gap-6"
    x-data="marketingTemplates"
>
    {{-- Sidebar --}}
    <div class="sticky top-0 flex w-full shrink-0 gap-x-4 gap-y-4 overflow-y-auto max-md:overflow-x-auto md:w-52 md:flex-col">
        <template
            x-for="category in categories"
            :key="category"
        >
            <button
                class="group text-start text-base font-medium text-heading-foreground transition-all max-md:whitespace-nowrap"
                @click="selectedCategory = category"
                :class="{ 'active': selectedCategory === category }"
            >
                <span
                    class="group-[&.active]:font-semibold group-[&.active]:underline"
                    x-text="category === 'all' ? '{{ __('All') }}' : category"
                ></span>
                <sup
                    class="ms-1 text-4xs no-underline"
                    x-show="selectedCategory === category"
                    x-text="getCategoryCount(category)"
                ></sup>
            </button>
        </template>
    </div>

    {{-- Templates --}}
    <div class="flex-1">
        {{-- Loading State --}}
        <div
            class="flex items-center justify-center py-20"
            x-show="loadingTemplatesFailed"
            x-cloak
        >
            <div class="text-center">
                <div class="mx-auto mb-3 grid size-28 place-items-center rounded-full bg-foreground/5">
                    <x-tabler-alert-circle class="size-10 text-foreground" />
                </div>
                <p class="mb-1 text-lg font-medium text-heading-foreground">
                    {{ __('Failed to load templates') }}
                </p>
                <p class="mb-3 opacity-70">
                    {{ __('Please try again later') }}
                </p>

                <x-button @click.prevent="retryFetch()">
                    <x-tabler-refresh
                        class="size-4"
                        ::class="{ 'animate-spin': loading }"
                    />
                    {{ __('Retry') }}
                </x-button>
            </div>
        </div>

        {{-- Loading --}}
        <div
            class="flex items-center justify-center py-20"
            x-show="!templatesList.length && !loadingTemplatesFailed"
        >
            <p class="flex items-center gap-2">
                <x-tabler-loader-2 class="size-5 animate-spin" />
                {{ __('Loading Templates') }}
            </p>
        </div>

        {{-- Templates --}}
        <x-masonry-grid
            class="-mx-2 flex flex-wrap"
            x-ref="masonryGrid"
            x-show="templatesList.length"
            x-cloak
        >
            <template
                x-for="(template, index) in filteredTemplates"
                {{-- Disabling :key to avoid conflict between Alpine and Isotope --}}
                {{-- :key="template.id" --}}
            >
                <x-masonry-grid-item
                    class="mb-4 w-1/2 px-2 md:w-1/3"
                    ::data-category="template.category"
                >
                    <a
                        class="group relative flex overflow-hidden rounded-lg shadow-md transition-all hover:scale-[1.02] hover:shadow-xl"
                        href="#"
                        @click.prevent="sessionStorage.setItem('pendingTemplate', JSON.stringify({id: template.id, preview: template.preview, name: template.name}));window.location.href = '/dashboard/user/creative-suite';"
                    >
                        <img
                            class="masonry-image h-auto w-full"
                            alt="{{ __('Template Preview') }}"
                            :src="template.preview"
                            :width="template.preview_dimensions.width"
                            :height="template.preview_dimensions.height"
                            loading="lazy"
                        >

                        {{-- Hover --}}
                        <span class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition-opacity group-hover:opacity-100">
                            <span class="flex size-12 items-center justify-center rounded-full bg-white text-black">
                                <x-tabler-plus class="size-6" />
                            </span>
                        </span>
                    </a>

                    <template x-if="index === filteredTemplates.length - 1">
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

        {{-- Empty --}}
        <div
            class="flex items-center justify-center py-20"
            x-show="templatesList.length && !filteredTemplates.length"
            x-cloak
        >
            <p>
                {{ __('No templates found in this category') }}
            </p>
        </div>
    </div>
</div>

@pushOnce('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('marketingTemplates', () => ({
                templatesList: [],
                loadingTemplatesFailed: false,
                templatesListUrl: '{{ $templates_list_url }}',
                selectedCategory: 'all',
                loading: false,

                init() {
                    this.fetchTemplates();
                },

                fetchTemplates() {
                    this.loading = true;
                    this.loadingTemplatesFailed = false;

                    fetch(this.templatesListUrl)
                        .then(res => res.json())
                        .then(data => this.templatesList = data)
                        .catch(() => this.loadingTemplatesFailed = true)
                        .finally(() => this.loading = false);
                },

                get categories() {
                    if (!this.templatesList.length) return ['all'];
                    const cats = new Set(['all']);
                    this.templatesList.forEach(t => {
                        if (t.category) cats.add(t.category);
                    });
                    return Array.from(cats);
                },

                get filteredTemplates() {
                    if (this.selectedCategory === 'all') {
                        return this.templatesList;
                    }
                    return this.templatesList.filter(t => t.category === this.selectedCategory);
                },

                getCategoryCount(category) {
                    if (category === 'all') return this.templatesList.length;
                    return this.templatesList.filter(t => t.category === category).length;
                },

                retryFetch() {
                    this.loadingTemplatesFailed = false;
                    this.fetchTemplates();
                }
            }));
        });
    </script>
@endPushOnce
