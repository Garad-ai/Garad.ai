@php
    $crmTools = [
        [
            'key' => 'contact',
            'label' => __('Contact'),
            'description' => __('Add a new contact to your CRM.'),
            'icon' => 'tabler-user-circle',
            'color' => '#E3D4E5',
            'href' => route('dashboard.user.crm.contacts.index') . '?action=create',
            'tags' => ['records'],
        ],
        [
            'key' => 'company',
            'label' => __('Company'),
            'description' => __('Create a company profile.'),
            'icon' => 'tabler-building',
            'color' => '#D4DCEF',
            'href' => route('dashboard.user.crm.companies.index') . '?action=create',
            'tags' => ['records'],
        ],
        [
            'key' => 'deal',
            'label' => __('Deal'),
            'description' => __('Track a new sales opportunity.'),
            'icon' => 'tabler-clipboard-data',
            'color' => '#E5E4D4',
            'href' => route('dashboard.user.crm.deals.create'),
            'tags' => ['records'],
        ],
        [
            'key' => 'task',
            'label' => __('Task'),
            'description' => __('Schedule a follow-up or to-do.'),
            'icon' => 'tabler-list-details',
            'color' => '#CECFF2',
            'href' => route('dashboard.user.crm.tasks.index') . '?action=create',
            'tags' => ['productivity'],
        ],
        [
            'key' => 'project',
            'label' => __('Project'),
            'description' => __('Organize work into a project.'),
            'icon' => 'tabler-layout-kanban',
            'color' => '#E7DAD1',
            'href' => route('dashboard.user.crm.projects.index') . '?action=create',
            'tags' => ['productivity'],
        ],
        [
            'key' => 'calendar',
            'label' => __('Calendar'),
            'description' => __('View and manage your schedule.'),
            'icon' => 'tabler-calendar',
            'color' => '#D4E5E1',
            'href' => route('dashboard.user.crm.calendar.index'),
            'tags' => ['productivity'],
        ],
        [
            'key' => 'report',
            'label' => __('Report'),
            'description' => __('Analyze your CRM performance.'),
            'icon' => 'tabler-chart-infographic',
            'color' => '#D4E5DB',
            'href' => route('dashboard.user.crm.reports.index'),
            'tags' => ['insights'],
        ],
        [
            'key' => 'presentation',
            'label' => __('Presentation'),
            'description' => __('Generate an AI-powered presentation.'),
            'icon' => 'tabler-presentation',
            'color' => '#EAD7E3',
            'href' => route('dashboard.user.crm.presentations.index'),
            'tags' => ['insights'],
        ],
        [
            'key' => 'assistant',
            'label' => __('Assistant'),
            'description' => __('Chat with your CRM AI assistant.'),
            'icon' => 'tabler-message-chatbot',
            'color' => '#DCE7D4',
            'href' => route('dashboard.user.crm.ai.index'),
            'tags' => ['ai'],
        ],
    ];

    $crmToolCategories = [['records', __('Records')], ['productivity', __('Productivity')], ['insights', __('Insights')], ['ai', __('AI')]];

    $crmToolCategoryLabels = [
        'all' => __('All'),
        'favorite' => __('Favorite'),
        'records' => __('Records'),
        'productivity' => __('Productivity'),
        'insights' => __('Insights'),
        'ai' => __('AI'),
    ];

    $crmToolsMeta = collect($crmTools)
        ->map(
            fn($tool) => [
                'key' => $tool['key'],
                'label' => $tool['label'],
                'description' => $tool['description'],
                'tags' => $tool['tags'],
            ],
        )
        ->values();
@endphp

<x-modal
    class:modal-content="p-5 md:px-8 md:pb-8 md:pt-5 !max-h-none h-[min(670px,calc(100vh-4rem))] !min-w-0 w-[min(940px,100%)]"
    class:modal-head="px-0"
    class:modal-title="text-[12px] font-semibold"
    class:modal-body="p-0"
    class:close-btn="size-7"
    class:close-btn-icon="size-4"
    id="crm-tools-modal"
    title="{{ __('CRM Tools') }}"
>
    <x-slot:modal>
        <div
            class="flex flex-wrap items-start gap-4 pt-5"
            x-data="crmToolsModal"
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
                    placeholder="{{ __('Search tools...') }}"
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
                            x-text="categoryLabels[category]"
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
                            @foreach ($crmToolCategories as [$t, $label])
                                <button
                                    class="text-start text-xs font-medium text-foreground/65 transition hover:text-foreground [&.active]:text-foreground [&.active]:underline [&.active]:underline-offset-4"
                                    type="button"
                                    :class="{ 'active': category === '{{ $t }}' }"
                                    @click.prevent="category = '{{ $t }}'"
                                >
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right content --}}
            <div class="min-w-0 flex-1 max-lg:w-full">
                <div class="grid grid-cols-1 gap-2 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($crmTools as $tool)
                        <x-card
                            class:body="px-5 pt-4 pb-5"
                            class="relative transition hover:z-1 hover:-translate-y-0.5 hover:shadow-xl"
                            x-show="matches('{{ $tool['key'] }}')"
                            ::class="{ 'is-favorite': isFavorite('{{ $tool['key'] }}') }"
                        >
                            <div
                                class="absolute inset-x-0 top-0 z-0 h-12 origin-top rounded-t-card bg-gradient-to-t from-[--color] to-[hsl(from_var(--color)_h_s_l/50%)] opacity-0 brightness-75 contrast-125 hue-rotate-60 transition-all duration-200 group-hover/card:opacity-100 group-hover/card:brightness-100 group-hover/card:contrast-100 group-hover/card:hue-rotate-0"
                                style="--color: {{ $tool['color'] }}"
                            ></div>

                            <button
                                class="absolute end-3 top-3 z-4 inline-grid size-[25px] place-items-center rounded-full bg-black/50 text-white opacity-0 transition hover:scale-110 active:scale-100 group-hover/card:opacity-100 group-[&.is-favorite]/card:opacity-100"
                                type="button"
                                :aria-pressed="isFavorite('{{ $tool['key'] }}')"
                                :aria-label="isFavorite('{{ $tool['key'] }}') ? '{{ __('Remove from favorites') }}' : '{{ __('Add to favorites') }}'"
                                @click.prevent.stop="toggleFavorite('{{ $tool['key'] }}')"
                            >
                                <x-tabler-heart class="size-4 group-[&.is-favorite]/card:fill-current" />
                            </button>

                            <span
                                class="relative z-1 mb-3.5 inline-grid size-12 place-items-center rounded-xl bg-heading-foreground text-header-background [corner-shape:squircle] supports-[corner-shape:squircle]:rounded-7xl"
                            >
                                <x-dynamic-component
                                    class="size-6"
                                    :component="$tool['icon']"
                                    stroke-width="1.5"
                                    aria-hidden="true"
                                />
                            </span>

                            <h5 class="relative z-1 mb-1.5 text-2xs font-semibold">
                                {{ $tool['label'] }}
                            </h5>
                            <p class="relative z-1 mb-0 text-2xs opacity-65">
                                {{ $tool['description'] }}
                            </p>

                            <a
                                class="absolute inset-0 z-2"
                                href="{{ $tool['href'] }}"
                                title="{{ $tool['label'] }}"
                            ></a>
                        </x-card>
                    @endforeach

                    {{-- No results --}}
                    <template x-if="visibleCount === 0">
                        <x-empty-state
                            class="col-span-3"
                            icon="tabler-search-off"
                            title="{{ __('No tools found') }}"
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
            Alpine.data('crmToolsModal', () => ({
                mobile: {
                    categoryDropdownOpen: false,
                },
                category: 'all',
                search: '',
                tools: @json($crmToolsMeta),
                categoryLabels: @json($crmToolCategoryLabels),
                favorites: Alpine.$persist([]).as('crm-tools-favorites'),

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

                matches(key) {
                    const tool = this.tools.find(t => t.key === key);

                    if (!tool) {
                        return false;
                    }

                    if (this.category === 'favorite') {
                        if (!this.favorites.includes(key)) {
                            return false;
                        }
                    } else if (this.category !== 'all') {
                        if (!Array.isArray(tool.tags) || !tool.tags.includes(this.category)) {
                            return false;
                        }
                    }

                    const q = this.search.trim().toLowerCase();

                    if (!q) {
                        return true;
                    }

                    return (tool.label || '').toLowerCase().includes(q) ||
                        (tool.description || '')
                        .toLowerCase().includes(q);
                },

                get visibleCount() {
                    return this.tools.filter(t => this.matches(t.key)).length;
                },
            }));
        })
    </script>
@endPushOnce
