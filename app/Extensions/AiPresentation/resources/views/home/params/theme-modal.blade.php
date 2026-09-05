<div x-data="{
    selectedTheme: null,
    selectedThemeId: null,
    showModal: false,
    themeSearch: '',
    selectedFilter: 'all',
    themes: [],
    async init() {
        try {
            const response = await fetch('/vendor/ai-presentation/themes.json');
            const data = await response.json();
            this.themes = data;
            // Set default theme if none selected
            if (!this.selectedTheme && this.themes.length > 0) {
                this.selectedTheme = this.themes[0].themeName;
                this.selectedThemeId = this.themes[0].themeId;
            }
        } catch (error) {
            console.error('Failed to load themes:', error);
        }
    },
    get filteredThemes() {
        let filtered = this.themes;

        // Filter by category
        if (this.selectedFilter !== 'all') {
            filtered = filtered.filter(theme => {
                if (this.selectedFilter === 'dark') return theme.themeType === 'dark';
                if (this.selectedFilter === 'light') return theme.themeType === 'light';
                return true;
            });
        }

        // Filter by search
        if (this.themeSearch) {
            filtered = filtered.filter(theme =>
                theme.themeName.toLowerCase().includes(this.themeSearch.toLowerCase())
            );
        }

        return filtered;
    },
    selectTheme(theme) {
        this.selectedTheme = theme.themeName;
        this.selectedThemeId = theme.themeId;
        this.showModal = false;
    }
}">
    <x-modal
        class:modal-content="min-w-[min(100%,1170px)]"
        class="static"
        title="{{ __('Presentation Themes') }}"
        disable-focus
    >
        <x-slot:trigger
            class="outline-foreground/[7%]"
            variant="outline"
        >
            <x-tabler-palette class="size-4" />
            <span x-text="selectedTheme || '{{ $default_theme }}'">
                {{ $default_theme }}
            </span>
        </x-slot:trigger>
        <x-slot:modal>
            <div class="p-6 pt-0">
                <!-- Search Input with Shuffle Button -->
                <div class="mb-6 flex gap-3">
                    <div class="relative flex-1">
                        <x-tabler-search class="absolute start-4 top-1/2 size-5 -translate-y-1/2 text-foreground/40" />
                        <x-forms.input
                            class="w-full rounded-xl bg-transparent bg-none py-3.5 pe-4 ps-12 text-base"
                            size="xl"
                            type="text"
                            placeholder="{{ __('Search for a theme') }}"
                            x-model="themeSearch"
                        />
                    </div>
                    <x-button
                        class="aspect-square shrink-0 rounded-xl px-4 py-3.5"
                        variant="outline"
                        type="button"
                        @click="themes = themes.sort(() => Math.random() - 0.5)"
                        title="{{ __('Shuffle themes') }}"
                    >
                        <svg
                            class="size-5 text-foreground/70"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="M16 3h5v5M4 20L21 3M21 16v5h-5M15 15l6 6M4 4l5 5" />
                        </svg>
                    </x-button>
                </div>

                <!-- Filter Tabs -->
                <div class="mb-6 flex flex-wrap gap-2">
                    <x-button
                        class="rounded-full px-5 py-2.5 text-sm font-medium transition-all [&.active]:bg-primary [&.active]:text-primary-foreground [&.active]:outline-primary"
                        type="button"
                        variant="outline"
                        ::class="{ 'active': selectedFilter === 'all' }"
                        @click="selectedFilter = 'all'"
                    >
                        {{ __('All') }}
                    </x-button>
                    <x-button
                        class="rounded-full px-5 py-2.5 text-sm font-medium transition-all [&.active]:bg-primary [&.active]:text-primary-foreground [&.active]:outline-primary"
                        type="button"
                        variant="outline"
                        ::class="{ 'active': selectedFilter === 'dark' }"
                        @click="selectedFilter = 'dark'"
                    >
                        {{ __('Dark') }}
                    </x-button>
                    <x-button
                        class="rounded-full px-5 py-2.5 text-sm font-medium transition-all [&.active]:bg-primary [&.active]:text-primary-foreground [&.active]:outline-primary"
                        type="button"
                        variant="outline"
                        ::class="{ 'active': selectedFilter === 'light' }"
                        @click="selectedFilter = 'light'"
                    >
                        {{ __('Light') }}
                    </x-button>
                </div>

                <!-- Themes Grid -->
                <div class="grid max-h-[500px] grid-cols-2 gap-4 overflow-y-auto pr-2 sm:grid-cols-3 lg:grid-cols-4">
                    <template
                        x-for="theme in filteredThemes"
                        :key="theme.themeId"
                    >
                        <div
                            class="group cursor-pointer"
                            @click="selectTheme(theme); modalOpen = false;"
                        >
                            <div
                                class="overflow-hidden rounded-xl border-2 transition-all hover:border-primary [&.active]:border-primary [&.active]:bg-primary/5 [&.active]:shadow-lg"
                                :class="{ 'active': selectedThemeId === theme.themeId }"
                            >
                                <!-- Theme Preview Image -->
                                <div
                                    class="relative aspect-video bg-foreground/5 bg-cover bg-center"
                                    :style="theme.bgImage != null && { backgroundImage: `url(${theme.bgImage})` }"
                                >
                                    <div class="p-4">
                                        <div
                                            class="w-full p-4"
                                            :style="theme.cardStyles"
                                        >
                                            <img
                                                class="h-auto w-32"
                                                :src="theme.previewImage"
                                                :alt="theme.themeName"
                                                loading="lazy"
                                            />
                                        </div>
                                    </div>
                                    <!-- Selected Checkmark Overlay -->
                                    <div
                                        class="absolute end-3 top-3 inline-grid size-9 place-items-center rounded-full bg-primary text-primary-foreground shadow-lg shadow-black/5"
                                        x-show="selectedThemeId === theme.themeId"
                                        x-transition
                                    >
                                        <x-tabler-check
                                            class="size-5"
                                            stroke-width="2.5"
                                        />
                                    </div>
                                </div>
                                <!-- Theme Name -->
                                <div class="p-4 text-center">
                                    <p
                                        class="m-0 truncate text-xs font-medium transition-colors"
                                        :class="selectedThemeId === theme.themeId ? 'text-primary' : 'text-foreground'"
                                        x-text="theme.themeName"
                                    ></p>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- No Results Message -->
                <div
                    class="py-12 text-center"
                    x-show="filteredThemes.length === 0 && themes.length > 0"
                >
                    <p class="text-lg text-foreground/60">{{ __('No themes found matching your search') }}</p>
                </div>

                <!-- Loading State -->
                <div
                    class="py-12 text-center"
                    x-show="themes.length === 0"
                >
                    <div class="inline-block h-8 w-8 animate-spin rounded-full border-b-2 border-primary"></div>
                    <p class="mt-4 text-foreground/60">{{ __('Loading themes...') }}</p>
                </div>
            </div>
        </x-slot:modal>
    </x-modal>
    <!-- Hidden inputs to store selected theme -->
    <input
        type="hidden"
        name="theme"
        x-model="selectedTheme"
    >
    <input
        type="hidden"
        name="theme_id"
        x-model="selectedThemeId"
    >
</div>
