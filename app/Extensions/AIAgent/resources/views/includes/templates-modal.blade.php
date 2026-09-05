<x-modal
    class:modal-content="p-5 md:px-8 md:pb-8 md:pt-5 !max-h-none h-[min(670px,calc(100vh-4rem))] !min-w-0 w-[min(940px,100%)]"
    class:modal-head="px-0"
    class:modal-title="text-[12px] font-semibold"
    class:modal-body="p-0"
    class:close-btn="size-7"
    class:close-btn-icon="size-4"
    id="ai-agent-templates-modal"
    title="{{ __('Add Agent') }}"
>
    <x-slot:modal>
        <div
            class="flex flex-wrap items-start gap-4 pt-5"
            x-data="templateModal"
        >
            <form
                class="sticky top-0 z-5 w-full lg:mb-5"
                @submit.prevent
            >
                <x-progressive-blur
                    class="-bottom-20 -end-5 -start-5 -top-5 md:-end-8 md:-start-8 lg:-bottom-14"
                    dir="reverse"
                />
                <x-forms.input
                    class="relative z-1 h-10 !border-none bg-foreground/5 shadow-[0_-1px_5px_hsl(var(--background))_inset] backdrop-blur-sm"
                    type="text"
                    x-model="search"
                    placeholder="{{ __('Search templates...') }}"
                    @keydown.escape.stop="open = false"
                />
                <x-tabler-search class="absolute end-5 top-1/2 z-1 size-4 -translate-y-1/2" />
            </form>

            {{-- Left sidebar --}}
            <div class="sticky top-12 z-3 w-full shrink-0 lg:top-16 lg:w-40">
                <div
                    class="relative flex flex-col gap-4 [interpolate-size:allow-keywords]"
                    @click.outside="mobile.categoryDropdownOpen = false"
                >
                    <x-button
                        class="justify-between bg-background/90 py-2.5 text-2xs backdrop-blur-sm [--button-rounded:theme(spacing.2)] lg:hidden"
                        variant="outline"
                        @click.prevent="mobile.categoryDropdownOpen = !mobile.categoryDropdownOpen"
                    >
                        <span
                            class="capitalize"
                            x-text="category.replace('_', ' ')"
                        >
                            {{ __('All') }}
                        </span>
                        <x-tabler-chevron-down
                            class="size-4 transition"
                            ::class="{ 'rotate-180': mobile.categoryDropdownOpen }"
                        />
                    </x-button>
                    <div
                        class="max-lg:absolute max-lg:inset-x-0 max-lg:top-full max-lg:z-3 max-lg:h-0 max-lg:overflow-hidden max-lg:rounded-lg max-lg:bg-dropdown-background max-lg:shadow-xl max-lg:shadow-black/5 max-lg:transition-all max-lg:[&.open]:h-auto"
                        :class="{ 'open': mobile.categoryDropdownOpen }"
                    >
                        <div class="flex flex-col gap-5 max-lg:max-h-[65vh] max-lg:overflow-y-auto max-lg:border max-lg:p-4">
                            <button
                                class="text-start text-xs font-medium text-foreground/65 transition hover:text-foreground [&.active]:text-foreground [&.active]:underline [&.active]:underline-offset-4"
                                type="button"
                                :class="{ 'active': category === 'all' }"
                                @click.prevent="category = 'all'"
                            >
                                {{ __('All') }}
                            </button>
                            <button
                                class="text-start text-xs font-medium text-foreground/65 transition hover:text-foreground [&.active]:text-foreground [&.active]:underline [&.active]:underline-offset-4"
                                type="button"
                                :class="{ 'active': category === 'favorite' }"
                                @click.prevent="category = 'favorite'"
                            >
                                {{ __('Favorite') }}
                            </button>
                            @foreach ([['crm', __('CRM')], ['gmail', __('Gmail')], ['outlook', __('Outlook')], ['social_media', __('Social Media')], ['marketing', __('Marketing')], ['chatbot', __('Chatbot')], ['reports', __('Reports')], ['ai', __('AI & Web')]] as [$t, $Label])
                                <button
                                    class="text-start text-xs font-medium text-foreground/65 transition hover:text-foreground [&.active]:text-foreground [&.active]:underline [&.active]:underline-offset-4"
                                    type="button"
                                    :class="{ 'active': category === '{{ $t }}' }"
                                    @click.prevent="category = '{{ $t }}'"
                                >
                                    {{ $Label }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right content --}}
            <div class="min-w-0 flex-1 max-lg:w-full">
                <div class="grid grid-cols-1 gap-2 md:grid-cols-2 lg:grid-cols-3">
                    {{-- Blank workflow --}}
                    <x-card
                        class:body="px-5 pt-4 pb-5"
                        class="relative overflow-hidden transition hover:z-1 hover:-translate-y-0.5 hover:shadow-xl"
                        x-show="category === 'all' && search.trim() === ''"
                    >
                        <div
                            class="absolute inset-x-0 top-0 z-0 h-12 origin-top rounded-t-card bg-gradient-to-t from-[--color] to-[hsl(from_var(--color)_h_s_l/50%)] opacity-0 brightness-75 contrast-125 hue-rotate-60 transition-all duration-200 group-hover/card:opacity-100 group-hover/card:brightness-100 group-hover/card:contrast-100 group-hover/card:hue-rotate-0"
                            :style="{ '--color': '#fff087' }"
                        ></div>

                        <img
                            class="relative z-1 mb-3.5 aspect-square max-h-[50px] w-[50px] object-contain object-center"
                            src="{{ custom_theme_url('/vendor/ai-agent/images/agents/agent-new.png') }}"
                            alt="{{ __('Light Bulb Illustration') }}"
                            aria-hidden="true"
                        >

                        <h5 class="relative z-1 mb-1.5 text-2xs font-semibold">
                            {{ __('Start from Scratch') }}
                        </h5>
                        <p class="relative z-1 mb-0 text-2xs opacity-65">
                            {{ __('Build a custom workflow from an empty canvas.') }}
                        </p>

                        <a
                            class="absolute inset-0 z-2"
                            href="{{ route('dashboard.user.ai-agent.workflows.create') }}"
                            title="{{ __('Start from Scratch') }}"
                        ></a>
                    </x-card>

                    {{-- Template cards --}}
                    <template
                        x-for="tpl in filtered"
                        :key="tpl.key"
                    >
                        <x-card
                            class:body="px-5 pt-4 pb-5"
                            class="relative transition hover:z-1 hover:-translate-y-0.5 hover:shadow-xl"
                            ::class="{ 'is-favorite': isFavorite(tpl.key) }"
                        >
                            <div
                                class="absolute inset-x-0 top-0 z-0 h-12 origin-top rounded-t-card bg-gradient-to-t from-[--color] to-[hsl(from_var(--color)_h_s_l/50%)] opacity-0 brightness-75 contrast-125 hue-rotate-60 transition-all duration-200 group-hover/card:opacity-100 group-hover/card:brightness-100 group-hover/card:contrast-100 group-hover/card:hue-rotate-0"
                                :style="{ '--color': tpl.color }"
                            ></div>

                            <button
                                class="absolute end-3 top-3 z-4 inline-grid size-[25px] place-items-center rounded-full bg-black/50 text-white opacity-0 transition hover:scale-110 active:scale-100 group-hover/card:opacity-100 group-[&.is-favorite]/card:opacity-100"
                                type="button"
                                :aria-pressed="isFavorite(tpl.key)"
                                :aria-label="isFavorite(tpl.key) ? '{{ __('Remove from favorites') }}' : '{{ __('Add to favorites') }}'"
                                @click.prevent.stop="toggleFavorite(tpl.key)"
                            >
                                <x-tabler-heart class="size-4 group-[&.is-favorite]/card:fill-current" />
                            </button>

                            <img
                                class="relative z-1 mb-3.5 aspect-square max-h-[50px] w-[50px] object-contain object-center"
                                :src="tpl.avatar"
                                :alt="tpl.label + ' Thumbnail'"
                                aria-hidden="true"
                            >

                            <h5
                                class="relative z-1 mb-1.5 text-2xs font-semibold"
                                x-text="tpl.label"
                            ></h5>
                            <p
                                class="relative z-1 mb-0 text-2xs opacity-65"
                                x-text="tpl.description"
                            ></p>

                            <a
                                class="absolute inset-0 z-2"
                                :href="`{{ route('dashboard.user.ai-agent.workflows.create') }}?template=${tpl.key}`"
                                :title="tpl.label"
                            ></a>
                        </x-card>
                    </template>

                    {{-- No results --}}
                    <template x-if="filtered.length === 0">
                        <x-empty-state
                            class="col-span-3"
                            icon="tabler-search-off"
                            title="{{ __('No templates found') }}"
                            description="{{ __('Try a different search term or category.') }}"
                        />
                    </template>
                </div>
            </div>
        </div>
    </x-slot:modal>
</x-modal>

@pushOnce('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('templateModal', () => ({
                mobile: {
                    categoryDropdownOpen: false,
                },
                category: 'all',
                search: '',
                templates: @json($templates),
                favorites: Alpine.$persist([]).as('ai-agent-favorite-templates'),

                isFavorite(key) {
                    return this.favorites.includes(key);
                },

                toggleFavorite(key) {
                    if (this.isFavorite(key)) {
                        this.favorites = this.favorites.filter(k => k !== key);
                    } else {
                        this.favorites = [...this.favorites, key];
                    }
                },

                get filtered() {
                    let list = Object.values(this.templates);

                    if (this.category === 'favorite') {
                        list = list.filter(t => this.favorites.includes(t.key));
                    } else if (this.category !== 'all') {
                        list = list.filter(t => Array.isArray(t.tags) && t.tags.includes(this.category));
                    }

                    const q = this.search.trim().toLowerCase();

                    if (!q) {
                        return list;
                    }

                    return list.filter(t =>
                        (t.label || '')
                        .toLowerCase().includes(q) ||
                        (t.description || '').toLowerCase().includes(q)
                    );
                },
            }));
        })
    </script>
@endPushOnce
