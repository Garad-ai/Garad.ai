{{-- Tool Category Modal --}}
<x-modal
    class:modal-content="p-5 md:px-8 md:pb-8 md:pt-5 !max-h-none h-[min(670px,calc(100vh-4rem))] !min-w-0 w-[min(940px,100%)]"
    class:modal-head="px-0"
    class:modal-title="text-[12px] font-semibold"
    class:modal-body="p-0"
    class:close-btn="size-7"
    class:close-btn-icon="size-4"
    id="ai-agent-steps-modal"
    title="{{ __('Add Step') }}"
>
    <x-slot:modal>
        <div class="flex flex-wrap items-start gap-4 pt-5">
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
                    x-model="addStepModal.search"
                    x-ref="modalSearch"
                    placeholder="{{ __('Search templates...') }}"
                />
                <x-tabler-search class="absolute end-5 top-1/2 z-1 size-4 -translate-y-1/2" />
            </form>

            {{-- Branch hint (sub-step mode only) --}}
            <template x-if="addStepModal.mode === 'substep'">
                <p
                    class="relative z-5 -mt-3 w-full text-3xs text-foreground/50 lg:-mt-4"
                    x-text="'{{ __('Branch: ') }}' + (steps.find(s => s.id === addStepModal.parentStepId)?.config?.paths?.[addStepModal.pathIndex]?.name ?? '')"
                ></p>
            </template>

            {{-- Left sidebar --}}
            <div class="sticky top-12 z-5 w-full shrink-0 lg:top-16 lg:w-40">
                <div
                    class="relative flex flex-col gap-4 [interpolate-size:allow-keywords]"
                    @click.outside="addStepModal.mobile.categoryDropdownOpen = false"
                >
                    <x-button
                        class="justify-between bg-background/90 py-2.5 text-2xs backdrop-blur-sm [--button-rounded:theme(spacing.2)] lg:hidden"
                        variant="outline"
                        @click.prevent="addStepModal.mobile.categoryDropdownOpen = !addStepModal.mobile.categoryDropdownOpen"
                    >
                        <span
                            class="capitalize"
                            x-text="addStepModal.category.replace('_', ' ')"
                        >
                            {{ __('All') }}
                        </span>
                        <x-tabler-chevron-down
                            class="size-4 transition"
                            ::class="{ 'rotate-180': addStepModal.mobile.categoryDropdownOpen }"
                        />
                    </x-button>
                    <div
                        class="max-lg:absolute max-lg:inset-x-0 max-lg:top-full max-lg:z-3 max-lg:h-0 max-lg:overflow-hidden max-lg:rounded-lg max-lg:bg-dropdown-background max-lg:shadow-xl max-lg:shadow-black/5 max-lg:transition-all max-lg:[&.open]:h-auto"
                        :class="{ 'open': addStepModal.mobile.categoryDropdownOpen }"
                    >
                        <div class="flex flex-col gap-5 max-lg:max-h-[65vh] max-lg:overflow-y-auto max-lg:border max-lg:p-4">
                            <button
                                class="text-start text-xs font-medium text-foreground/65 transition hover:text-foreground [&.active]:text-foreground [&.active]:underline [&.active]:underline-offset-4"
                                type="button"
                                :class="{ 'active': addStepModal.category === 'all' }"
                                @click.prevent="addStepModal.category = 'all'"
                            >
                                {{ __('All') }}
                            </button>
                            <button
                                class="text-start text-xs font-medium text-foreground/65 transition hover:text-foreground [&.active]:text-foreground [&.active]:underline [&.active]:underline-offset-4"
                                type="button"
                                :class="{ 'active': addStepModal.category === 'favorite' }"
                                @click.prevent="addStepModal.category = 'favorite'"
                            >
                                {{ __('Favorite') }}
                            </button>
                            @foreach ([['ai', __('AI')], ['agents', __('Agents')], ['channels', __('Channels')], ['flow_controls', __('Flow Controls')], ['utilities', __('Utilities')], ['custom', __('Custom')]] as [$t, $label])
                                <button
                                    class="text-start text-xs font-medium text-foreground/65 transition hover:text-foreground [&.active]:text-foreground [&.active]:underline [&.active]:underline-offset-4"
                                    type="button"
                                    :class="{ 'active': addStepModal.category === '{{ $t }}' }"
                                    @click.prevent="addStepModal.category = '{{ $t }}'"
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
                {{-- Failed actions notice --}}
                <template x-if="!addStepModal.loading && addStepModal.failedActions.length > 0">
                    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-900/50 dark:bg-amber-950/30">
                        <p class="mb-1.5 text-2xs font-semibold text-amber-700 dark:text-amber-400">{{ __('Some actions could not be loaded') }}</p>
                        <ul class="space-y-0.5">
                            <template
                                x-for="failed in addStepModal.failedActions"
                                :key="failed.key"
                            >
                                <li class="flex items-center gap-1.5 text-2xs text-amber-600 dark:text-amber-500">
                                    <x-tabler-alert-triangle class="size-3 shrink-0" />
                                    <span x-text="failed.label + ' — {{ __('extension dependency missing') }}'"></span>
                                </li>
                            </template>
                        </ul>
                    </div>
                </template>

                {{-- Loading --}}
                <template x-if="addStepModal.loading">
                    <div class="flex items-center justify-center py-16">
                        <x-tabler-loader-2 class="size-6 animate-spin" />
                    </div>
                </template>

                {{-- No results --}}
                <template x-if="!addStepModal.loading && filteredModalActions.length === 0">
                    <x-empty-state
                        icon="tabler-search-off"
                        title="{{ __('No templates found') }}"
                        description="{{ __('Try a different search term or category.') }}"
                    />
                </template>

                {{-- Action cards --}}
                <div
                    class="grid grid-cols-1 gap-2 md:grid-cols-2"
                    x-show="!addStepModal.loading && filteredModalActions.length > 0"
                >
                    <template
                        x-for="action in filteredModalActions"
                        :key="action.key"
                    >
                        <x-card
                            class="relative transition hover:z-1 hover:-translate-y-0.5 hover:shadow-xl"
                            size="none"
                            ::class="{ 'is-favorite': isStepFavorite(action.key) }"
                        >
                            {{-- Head --}}
                            <div class="flex items-center gap-3 border-b px-4 py-2.5">
                                <div
                                    class="inline-grid size-[30px] place-items-center rounded-full text-black"
                                    :class="{
                                        'bg-[#FFEEAB] text-[#90761F]': action.category === 'ai',
                                        'bg-[#E6E9F1]': !['ai'].includes(action.category),
                                    }"
                                >
                                    {{-- Dynamic icon by key --}}
                                    <template x-if="action.key === 'ai_call'"><x-tabler-brain class="size-4" /></template>
                                    <template x-if="action.key === 'send_message'"><x-tabler-send class="size-4" /></template>
                                    <template x-if="action.key === 'generate_report'"><x-tabler-report-analytics class="size-4" /></template>
                                    <template x-if="action.key === 'path'"><x-tabler-git-fork class="size-4" /></template>
                                    <template x-if="!['ai_call','send_message','generate_report','path'].includes(action.key)">
                                        <x-tabler-bolt class="size-4" />
                                    </template>
                                </div>

                                <p
                                    class="m-0 truncate text-2xs font-medium"
                                    x-text="action.label"
                                ></p>

                                <button
                                    class="relative z-4 ms-auto inline-grid size-[25px] place-items-center rounded-full bg-black/50 text-white opacity-0 transition hover:scale-110 active:scale-100 group-hover/card:opacity-100 group-[&.is-favorite]/card:opacity-100"
                                    type="button"
                                    :aria-pressed="isStepFavorite(action.key)"
                                    :aria-label="isStepFavorite(action.key) ? '{{ __('Remove from favorites') }}' : '{{ __('Add to favorites') }}'"
                                    @click.prevent.stop="toggleStepFavorite(action.key)"
                                >
                                    <x-tabler-heart class="size-4 group-[&.is-favorite]/card:fill-current" />
                                </button>
                            </div>

                            {{-- Text --}}
                            <div class="p-4">
                                <p
                                    class="m-0 text-2xs"
                                    x-text="action.description"
                                ></p>
                            </div>

                            <a
                                class="absolute inset-0 z-3"
                                href="#"
                                @click.prevent="selectActionFromModal(action)"
                            ></a>
                        </x-card>
                    </template>
                </div>
            </div>
        </div>
    </x-slot:modal>
</x-modal>
