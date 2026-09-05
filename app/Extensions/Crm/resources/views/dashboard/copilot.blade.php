<div
    class="mb-16"
    x-data="crmCopilot"
>
    <div class="container max-w-[740px]">
        <div class="mb-8 flex flex-wrap gap-x-4">
            <div class="grow">
                <a
                    class="group mb-4 inline-flex items-center gap-2 rounded-full border py-2 pe-5 ps-2.5 text-sm text-orange-800 transition hover:bg-black hover:text-white dark:text-orange-200 dark:hover:bg-white dark:hover:text-black"
                    href="{{ route('dashboard.user.crm.tasks.index') }}"
                >
                    <span
                        class="relative inline-grid size-[38px] place-items-center rounded-full border transition-border group-hover:border-white/35 dark:group-hover:border-black/10"
                    >
                        <svg
                            class="overdue-svg absolute start-0 top-0 size-full -rotate-90 transition-all"
                            x-data="{
                                pendingCount: {{ (int) $pendingTasks }},
                                overdueCount: {{ (int) $overdueTasks }},
                                init() {
                                    this.calculateDashoffset();
                                    this.animateDashoffset();
                            
                                    this.$watch('overdueCount', () => {
                                        this.calculateDashoffset();
                                        this.animateDashoffset();
                                    })
                                },
                                calculateDashoffset() {
                                    const pendingCount = Math.max(this.pendingCount || 0, 0);
                                    const overdueCount = Math.max(this.overdueCount || 0, 0);
                                    const backlogCount = Math.max(pendingCount, 1);
                            
                                    this.progressRatio = Math.min(overdueCount / backlogCount, 1);
                                    this.strokeDashoffset = 135 * (1 - this.progressRatio);
                                },
                                animateDashoffset() {
                                    this.$el.animate([{ strokeDashoffset: this.strokeDashoffset }], { duration: 550, easing: 'ease', fill: 'both' })
                                }
                            }"
                            style="stroke-dasharray: 135; stroke-dashoffset: 0;"
                            width="43"
                            height="43"
                            viewBox="0 0 43 43"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                            stroke="currentColor"
                            stroke-width="1.5"
                        >
                            <circle
                                cx="21.5"
                                cy="21.5"
                                r="21"
                            />
                        </svg>
                        <span x-text="overdueTasks">{{ $overdueTasks }}</span>
                    </span>
                    {{ __('Overdue tasks waiting') }}
                    <x-tabler-arrow-right class="size-4.5" />
                </a>
                <h2 class="text-[30px] font-medium max-md:mb-5">
                    <span class="inline-block text-[0.7em]">
                        <span class="opacity-35">
                            {{ __('Hey', ['username' => $userName]) }},
                        </span>
                        👋
                    </span>
                    <span class="block">
                        {{ __('How can I help you?') }}
                    </span>
                </h2>
            </div>

            <div
                class="flex w-[230px] justify-center self-end dark:brightness-[0.95] dark:contrast-[0.95] dark:saturate-[0.95] max-md:order-first max-md:mb-7 max-md:[mask-image:linear-gradient(to_bottom,black_70%,transparent_100%)] max-sm:w-[60vw]"
                aria-hidden="true"
            >
                <img
                    class="h-auto w-full"
                    src="{{ custom_theme_url('/vendor/crm/images/img-1.png') }}"
                    alt="{{ __('CRM Assistant Image') }}"
                    aria-hidden="true"
                    width="452"
                    height="365"
                >
            </div>

            <div class="w-full overflow-hidden rounded-[22px] border border-transparent shadow-2xl shadow-black/[8%] dark:border-foreground/5">
                <x-forms.input
                    class="w-full resize-none rounded-none border-none px-4 py-4 placeholder:text-foreground/75 focus-visible:outline-none focus-visible:ring-0 sm:text-sm md:px-7"
                    rows="3"
                    type="textarea"
                    placeholder="{{ __('What would you like me to handle? I can manage your tasks, reports, contacts and more!') }}"
                    x-model="copilotInput"
                    @keydown.enter.prevent="submitCopilot()"
                    x-init="$nextTick(() => $el.focus())"
                ></x-forms.input>

                <div class="flex justify-end px-7 pb-4">
                    <x-button
                        class="grid size-11 place-items-center rounded-full bg-heading-foreground text-background hover:scale-105 hover:shadow-xl hover:shadow-black/5"
                        hover-variant="primary"
                        size="none"
                        type="button"
                        @click="submitCopilot()"
                        ::disabled="!copilotInput.trim()"
                        disabled
                    >
                        <svg
                            width="15"
                            height="12"
                            viewBox="0 0 15 12"
                            fill="currentColor"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <path d="M0 12V7.5L6 6L0 4.5V0L14.25 6L0 12Z" />
                        </svg>
                    </x-button>
                </div>
            </div>
        </div>

        <div class="mb-8 flex items-center gap-8 text-center text-[12px] font-medium text-foreground/85">
            <span class="inline-flex h-px grow bg-border"></span>
            <span class="[&_u]:underline-offset-2">
                {!! __('or start with <u>Quick Shortcuts</u>') !!}
            </span>
            <span class="inline-flex h-px grow bg-border"></span>
        </div>

        <div class="rounded-[10px] border pt-5">
            <div class="flex items-center justify-between px-5 text-[12px] font-medium text-foreground/85">
                <span>
                    {{ __('Add New') }}
                </span>
                <a
                    class="underline underline-offset-2"
                    href="#"
                    @click.prevent="Alpine.$data(document.querySelector('#crm-tools-modal')).modalOpen = true"
                >
                    {{ __('View All') }}
                </a>
            </div>

            <div class="no-scrollbar flex snap-x snap-mandatory scroll-px-10 gap-4 overflow-x-auto px-5 pb-5 pt-2.5">
                <a
                    class="flex w-9/12 shrink-0 grow-0 basis-auto snap-end flex-col gap-14 rounded-lg bg-gradient-to-b from-[hsl(from_var(--color)_h_s_calc(l+8))] to-[--color] p-4 text-base font-medium text-black transition-all [--color:#E3D4E5] [corner-shape:squircle] hover:scale-105 hover:saturate-200 supports-[corner-shape:squircle]:rounded-5xl md:w-1/2 lg:w-[calc(33.33%-0.5rem)]"
                    href="{{ route('dashboard.user.crm.contacts.index') }}?action=create"
                >
                    <x-tabler-user-circle
                        class="size-[30px] opacity-50"
                        stroke-width="1.25"
                    />
                    {{ __('Contact') }}
                </a>
                <a
                    class="flex w-9/12 shrink-0 grow-0 basis-auto snap-end flex-col gap-14 rounded-lg bg-gradient-to-b from-[hsl(from_var(--color)_h_s_calc(l+8))] to-[--color] p-4 text-base font-medium text-black transition-all [--color:#CECFF2] [corner-shape:squircle] hover:scale-105 hover:saturate-200 supports-[corner-shape:squircle]:rounded-5xl md:w-1/2 lg:w-[calc(33.33%-0.5rem)]"
                    href="{{ route('dashboard.user.crm.tasks.index') }}?action=create"
                >
                    <x-tabler-list-details
                        class="size-[30px] opacity-50"
                        stroke-width="1.25"
                    />
                    {{ __('Task') }}
                </a>
                <a
                    class="flex w-9/12 shrink-0 grow-0 basis-auto snap-end flex-col gap-14 rounded-lg bg-gradient-to-b from-[hsl(from_var(--color)_h_s_calc(l+8))] to-[--color] p-4 text-base font-medium text-black transition-all [--color:#E5E4D4] [corner-shape:squircle] hover:scale-105 hover:saturate-200 supports-[corner-shape:squircle]:rounded-5xl md:w-1/2 lg:w-[calc(33.33%-0.5rem)]"
                    href="{{ route('dashboard.user.crm.deals.create') }}"
                >
                    <x-tabler-clipboard-data
                        class="size-[30px] opacity-50"
                        stroke-width="1.25"
                    />
                    {{ __('Deal') }}
                </a>
                <a
                    class="flex w-9/12 shrink-0 grow-0 basis-auto snap-end flex-col gap-14 rounded-lg bg-gradient-to-b from-[hsl(from_var(--color)_h_s_calc(l+8))] to-[--color] p-4 text-base font-medium text-black transition-all [--color:#D4E5DB] [corner-shape:squircle] hover:scale-105 hover:saturate-200 supports-[corner-shape:squircle]:rounded-5xl md:w-1/2 lg:w-[calc(33.33%-0.5rem)]"
                    href="{{ route('dashboard.user.crm.reports.index') }}"
                >
                    <x-tabler-chart-infographic
                        class="size-[30px] opacity-50"
                        stroke-width="1.25"
                    />
                    {{ __('Reports') }}
                </a>
                <a
                    class="[--color:hsl(from_hsl(var(--gradient-from) supports-[corner-shape:squircle]:rounded-5xl)_h_calc(s-25)_l)] flex w-9/12 shrink-0 grow-0 basis-auto snap-end flex-col gap-14 rounded-5xl bg-gradient-to-b from-[hsl(from_var(--color)_h_s_calc(l+8))] to-[--color] p-4 text-base font-medium text-black transition-all [corner-shape:squircle] hover:scale-105 hover:saturate-200 md:w-1/2 lg:w-[calc(33.33%-0.5rem)]"
                    href="#"
                    @click.prevent="Alpine.$data(document.querySelector('#crm-tools-modal')).modalOpen = true"
                >
                    <x-tabler-plus
                        class="size-6 opacity-60"
                        stroke-width="1.25"
                    />
                    {{ __('More') }}
                </a>
            </div>
        </div>
    </div>

    @include('crm::dashboard.tools-modal')
</div>

@push('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('crmCopilot', () => ({
                copilotInput: '',
                overdueTasks: {{ (int) $overdueTasks }},

                submitCopilot() {
                    if (!this.copilotInput.trim()) return;
                    window.location.href = '{{ route('dashboard.user.crm.ai.index') }}?copilot_init=' + encodeURIComponent(this.copilotInput.trim());
                }
            }));
        });
    </script>
@endpush
