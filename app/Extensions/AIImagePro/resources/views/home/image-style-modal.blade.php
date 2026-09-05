<div x-data="aiImageProImageStyleModal(input)">
    <x-modal
        class:modal-content="flex flex-col w-[min(calc(100%-40px),1140px)] min-h-[min(90vh,850px)]"
        class="static"
        class:modal-head="hidden"
        class:modal-body="p-5 lg:px-10 grow flex flex-col"
        class:modal-container="max-w-none w-full flex flex-col grow"
        class:modal-backdrop="backdrop-blur bg-black/50"
    >
        <x-slot:trigger
            class="whitespace-nowrap text-center outline-2 outline-foreground/5 hover:bg-primary hover:text-primary-foreground hover:outline-primary"
            variant="outline"
        >
            <span x-text="selectedStyle || '{{ __('Style') }}'">
                {{ __('Style') }}
            </span>
        </x-slot:trigger>

        <x-slot:modal>
            <div @class([
                'sticky top-0 z-10 -mx-5 px-5 lg:-mx-10 lg:px-10',
                'bg-background' => ($theme ?? null) !== 'social-media-agent-dashboard',
                'bg-surface-background' => ($theme ?? null) === 'social-media-agent-dashboard',
            ])>
                <div class="flex gap-5 border-b">
                    <button
                        class="-mb-px border-b border-b-transparent p-0 py-2.5 text-start text-[12px] font-semibold text-heading-foreground opacity-50 [&.active]:border-b-current [&.active]:opacity-100"
                        variant="none"
                        type="button"
                        :class="{ 'active': activeTab === 'pick' }"
                        @click="activeTab = 'pick'"
                    >
                        {{ __('Pick a Style') }}
                    </button>

                    <button
                        class="-mb-px border-b border-b-transparent p-0 py-2.5 text-start text-[12px] font-semibold text-heading-foreground opacity-50 [&.active]:border-b-current [&.active]:opacity-100"
                        variant="none"
                        type="button"
                        :class="{ 'active': activeTab === 'favorites' }"
                        @click="activeTab = 'favorites'"
                    >
                        {{ __('Favorites') }}
                    </button>

                    <button
                        class="ms-auto inline-grid size-10 place-content-center"
                        type="button"
                        @click.prevent="modalOpen = false"
                    >
                        <x-tabler-x class="size-4" />
                    </button>
                </div>
            </div>

            {{-- Styles Grid --}}
            <div
                class="grid grow grid-cols-2 gap-4 py-4 sm:grid-cols-3 md:gap-5 lg:grid-cols-5"
                x-show="!isLoading && itemsToShow.length"
            >
                <template
                    x-for="style in itemsToShow"
                    :key="style.styleId"
                >
                    <div
                        class="group relative cursor-pointer before:pointer-events-none before:absolute before:-inset-3 before:z-0 before:scale-95 before:rounded-[10px] before:bg-foreground/10 before:opacity-0 before:transition [&.selected]:before:scale-100 [&.selected]:before:opacity-100"
                        @click="selectStyle(style); modalOpen = false;"
                        :class="{ 'selected': selectedStyleId === style.styleId }"
                    >
                        {{-- Add Style Reference Card --}}
                        <template x-if="style.isAddCard && input?.props?.refImageAllowed">
                            <div class="group relative z-1 w-full">
                                <div
                                    class="relative mb-2 inline-grid aspect-square w-full place-items-center rounded-lg bg-foreground/5 shadow-md shadow-black/15 transition group-hover:scale-105 group-hover:shadow-xl group-hover:shadow-black/10">
                                    <div class="inline-grid size-[42px] place-items-center rounded-full bg-foreground/80 text-background transition group-hover:scale-110">
                                        <x-tabler-plus class="size-4" />
                                    </div>
                                </div>

                                <p
                                    class="mb-0.5 text-[11px] font-medium opacity-50"
                                    x-text="style.category"
                                ></p>
                                <p
                                    class="m-0 text-[13px] font-medium"
                                    x-text="style.styleName"
                                ></p>
                            </div>
                        </template>

                        {{-- Regular Style Card --}}
                        <div
                            class="group relative z-1 w-full"
                            x-show="!style.isAddCard"
                        >
                            {{-- Style Preview Image --}}
                            <div
                                class="relative mb-2 inline-grid aspect-square w-full place-items-center overflow-hidden rounded-lg bg-foreground/5 shadow-md shadow-black/15 transition group-hover:scale-105 group-hover:shadow-xl group-hover:shadow-black/10">
                                <img
                                    class="size-full scale-105 object-cover transition group-hover:scale-100"
                                    :src="style.previewImage"
                                    :alt="style.styleName"
                                    loading="lazy"
                                />

                                {{-- Favorite Heart Button --}}
                                <button
                                    class="absolute end-3 top-3 z-1 inline-grid size-6 place-items-center rounded-full bg-background opacity-0 transition hover:scale-110 group-hover:opacity-100"
                                    type="button"
                                    @click="toggleFavorite(style.styleId, $event)"
                                    :class="isFavorite(style.styleId) ? 'text-red-500' : 'text-foreground/40'"
                                >
                                    <svg
                                        class="size-[18px] text-heading-foreground"
                                        :fill="isFavorite(style.styleId) ? 'currentColor' : 'none'"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"
                                        />
                                    </svg>
                                </button>
                            </div>

                            {{-- Style Info --}}
                            <p
                                class="mb-0.5 text-[11px] font-medium opacity-50"
                                x-text="style.category || 'Impressions'"
                            ></p>
                            <p
                                class="m-0 text-[13px] font-medium"
                                x-text="style.styleName"
                            ></p>
                        </div>
                    </div>
                </template>
            </div>

            {{-- No Results Message --}}
            <div
                class="flex grow flex-col items-center justify-center py-10 text-center"
                x-show="styleSearch.trim() && !isLoading && !itemsToShow.length"
            >
                <div class="mb-3 inline-grid size-28 place-items-center rounded-full bg-foreground/5">
                    <x-tabler-search-off class="size-10" />
                </div>
                <p class="mb-0 text-base font-medium text-heading-foreground">
                    {{ __('No styles found matching your search') }}
                </p>
                <p class="mb-0 text-sm font-medium text-heading-foreground/50">
                    {{ __('Try adjusting your search term') }}
                </p>
            </div>

            {{-- No Favorites Message --}}
            <div
                class="flex grow flex-col items-center justify-center py-10 text-center"
                x-show="!isLoading && activeTab === 'favorites' && !itemsToShow.length && !styleSearch.trim()"
            >
                <div class="mb-3 inline-grid size-28 place-items-center rounded-full bg-foreground/5">
                    <x-tabler-heart-off class="size-10" />
                </div>
                <p class="mb-1 text-base font-medium text-heading-foreground">
                    {{ __('No favorite styles yet') }}
                </p>
                <p class="mb-0 text-sm font-medium text-heading-foreground/50">
                    {{ __('Click the heart icon on any style to add it to favorites') }}
                </p>
            </div>

            {{-- Loading State --}}
            <div
                class="flex grow flex-col items-center justify-center py-10 text-center"
                x-show="isLoading"
            >
                <p class="mb-0 flex items-center gap-2 text-base font-medium text-heading-foreground">
                    <x-tabler-loader-2 class="size-5 shrink-0 animate-spin"></x-tabler-loader-2>
                    {{ __('Loading styles...') }}
                </p>
            </div>

            <div class="sticky inset-x-0 bottom-7 z-2">
                <div class="relative mx-auto w-full max-w-[760px]">
                    <x-tabler-search class="pointer-events-none absolute start-6 top-1/2 z-1 size-4 -translate-y-1/2 text-heading-foreground" />
                    <input
                        class="h-16 w-full rounded-full bg-background/60 ps-14 shadow-2xl shadow-black/20 backdrop-blur-lg placeholder:text-foreground"
                        type="text"
                        placeholder="{{ __('Search for styles') }}"
                        x-model="styleSearch"
                    />
                </div>
            </div>
        </x-slot:modal>
    </x-modal>

    {{-- Hidden inputs to store selected style --}}
    <input
        type="hidden"
        name="style"
        x-model="selectedStyle"
    >
    <input
        type="hidden"
        name="style_id"
        x-model="selectedStyleId"
    >

    {{-- Hidden file input for style reference --}}
    <input
        class="hidden"
        type="file"
        @if (!auth()->check()) data-exclude-media-manager="true" @endif
        x-ref="styleReferenceInput"
        @change="handleStyleReferenceUpload($event)"
        accept="image/*"
        name="style_reference"
    >
</div>

@push('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('aiImageProImageStyleModal', (input) => ({
                input,
                selectedStyle: null,
                selectedStyleId: null,
                showModal: false,
                styleSearch: '',
                activeTab: 'pick',
                styles: [],
                favorites: [],
                isLoading: true,
                styleReferenceFile: null,
                async init() {
                    try {
                        // Load styles from JSON file
                        const response = await fetch('/vendor/ai-image-pro/styles.json');
                        const data = await response.json();
                        this.styles = data.styles;
                    } catch (error) {
                        console.error('Failed to load styles:', error);
                        // Fallback to empty array or show error message
                        this.styles = [];
                    } finally {
                        this.isLoading = false;
                    }

                    // Load favorites from localStorage if available
                    const savedFavorites = localStorage.getItem('styleFavorites');
                    if (savedFavorites) {
                        this.favorites = JSON.parse(savedFavorites);
                    }
                },
                get itemsToShow() {
                    const refImageAllowed = this.input?.props?.refImageAllowed;
                    return this.activeTab === 'pick' ?
                        this.filteredStyles.filter(s => refImageAllowed || !s.isAddCard) :
                        this.filteredStyles.filter(s => this.isFavorite(s.styleId) && (!s.isAddCard || refImageAllowed))
                },
                get filteredStyles() {
                    let filtered = this.styles;
                    // Filter by search
                    if (this.styleSearch) {
                        filtered = filtered.filter(style =>
                            style.styleName.toLowerCase().includes(this.styleSearch.toLowerCase()) ||
                            (style.description && style.description.toLowerCase().includes(this.styleSearch.toLowerCase())) ||
                            (style.category && style.category.toLowerCase().includes(this.styleSearch.toLowerCase()))
                        );
                    }
                    return filtered;
                },
                selectStyle(style) {
                    if (style.isAddCard) {
                        if (!this.input?.props?.refImageAllowed) return;
                        this.$refs.styleReferenceInput.click();
                        return;
                    }
                    this.selectedStyle = style.styleName;
                    this.selectedStyleId = style.styleId;
                    this.showModal = false;
                },
                handleStyleReferenceUpload(event) {
                    const file = event.target.files[0];
                    if (file) {
                        this.styleReferenceFile = file;
                        this.selectedStyle = file.name;
                        this.selectedStyleId = 'custom-reference';
                        this.showModal = false;
                    }
                },
                toggleFavorite(styleId, event) {
                    event.stopPropagation();
                    const index = this.favorites.indexOf(styleId);
                    if (index > -1) {
                        this.favorites.splice(index, 1);
                    } else {
                        this.favorites.push(styleId);
                    }
                    // Save to localStorage
                    localStorage.setItem('styleFavorites', JSON.stringify(this.favorites));
                },
                isFavorite(styleId) {
                    return this.favorites.includes(styleId);
                }
            }))
        })
    </script>
@endpush
