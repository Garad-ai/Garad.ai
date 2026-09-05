<x-card
    class="flex flex-col overflow-hidden"
    class:body="flex flex-col grow p-0"
    x-data="crmWhatsNew"
>
    <x-slot:head
        class="flex items-center justify-between px-5 py-4"
    >
        <h4 class="m-0 text-xs font-medium">
            {{ __("What's new") }}
        </h4>

        <x-dropdown.dropdown
            class="text-2xs"
            anchor="end"
            offsetY="15px"
        >
            <x-slot:trigger
                class="text-2xs"
            >
                <span
                    class="capitalize"
                    x-text="currentView.replaceAll('_', ' ')"
                >
                    {{ __('Today') }}
                </span>
                <x-tabler-chevron-down class="size-4" />
            </x-slot:trigger>

            <x-slot:dropdown
                class="p-2"
            >
                <x-button
                    class="lqd-is-active w-full justify-start rounded-md px-3 py-2 text-start text-2xs hover:bg-heading-foreground/5 hover:no-underline [&.lqd-is-active]:text-primary [&.lqd-is-active]:underline"
                    ::class="{ 'lqd-is-active': currentView === 'today' }"
                    variant="none"
                    href="#"
                    @click.prevent="updateView('today')"
                >
                    {{ __('Today') }}
                </x-button>
                <x-button
                    class="w-full justify-start rounded-md px-3 py-2 text-start text-2xs hover:bg-heading-foreground/5 hover:no-underline [&.lqd-is-active]:text-primary [&.lqd-is-active]:underline"
                    ::class="{ 'lqd-is-active': currentView === 'last_7_days' }"
                    variant="none"
                    href="#"
                    @click.prevent="updateView('last_7_days')"
                >
                    {{ __('Last 7 Days') }}
                </x-button>
                <x-button
                    class="w-full justify-start rounded-md px-3 py-2 text-start text-2xs hover:bg-heading-foreground/5 hover:no-underline [&.lqd-is-active]:text-primary [&.lqd-is-active]:underline"
                    ::class="{ 'lqd-is-active': currentView === 'last_30_days' }"
                    variant="none"
                    href="#"
                    @click.prevent="updateView('last_30_days')"
                >
                    {{ __('Last 30 Days') }}
                </x-button>
            </x-slot:dropdown>
        </x-dropdown.dropdown>
    </x-slot:head>

    <div class="grid grid-cols-1 divide-y md:grid-cols-2 md:divide-x md:[&>:nth-child(2)]:!border-t-0 md:[&>:nth-child(even)]:!border-e-0 md:[&>:nth-child(odd)]:!border-s-0">
        <div
            class="relative overflow-hidden p-5 pt-10 before:pointer-events-none before:absolute before:inset-0 before:z-0 before:translate-y-1/2 before:bg-gradient-to-t before:from-[#E3D4E5] before:to-transparent before:to-50% before:opacity-0 before:transition hover:before:translate-y-0 hover:before:opacity-100 md:pt-16">
            <x-number-counter
                class="relative z-1 mb-2.5 self-start text-2xl font-semibold text-heading-foreground"
                :value="$whatsNewStats['today']['contacts']"
                :dynamic-value-listener="'contacts'"
            />
            <p class="relative z-1 m-0 text-xs text-heading-foreground">
                {{ __('Contacts') }}
            </p>
            <a
                class="absolute inset-0 z-2 inline-flex"
                href="{{ route('dashboard.user.crm.contacts.index') }}"
            ></a>
        </div>

        <div
            class="relative overflow-hidden p-5 pt-10 before:pointer-events-none before:absolute before:inset-0 before:z-0 before:translate-y-1/2 before:bg-gradient-to-t before:from-[#E5E4D4] before:to-transparent before:to-50% before:opacity-0 before:transition hover:before:translate-y-0 hover:before:opacity-100 md:pt-16">
            <x-number-counter
                class="relative z-1 mb-2.5 self-start text-2xl font-semibold text-heading-foreground"
                :value="$whatsNewStats['today']['deals']"
                :options="['delay' => 100]"
                :dynamic-value-listener="'deals'"
            />
            <p class="relative z-1 m-0 text-xs text-heading-foreground">
                {{ __('Deals') }}
            </p>
            <a
                class="absolute inset-0 z-2 inline-flex"
                href="{{ route('dashboard.user.crm.deals.index') }}"
            ></a>
        </div>

        <div
            class="relative overflow-hidden p-5 pt-10 before:pointer-events-none before:absolute before:inset-0 before:z-0 before:translate-y-1/2 before:bg-gradient-to-t before:from-[#CECFF2] before:to-transparent before:to-50% before:opacity-0 before:transition hover:before:translate-y-0 hover:before:opacity-100 md:pt-16">
            <x-number-counter
                class="relative z-1 mb-2.5 self-start text-2xl font-semibold text-heading-foreground"
                :value="$whatsNewStats['today']['tasks']"
                :options="['delay' => 200]"
                :dynamic-value-listener="'tasks'"
            />
            <p class="relative z-1 m-0 text-xs text-heading-foreground">
                {{ __('Tasks') }}
            </p>
            <a
                class="absolute inset-0 z-2 inline-flex"
                href="{{ route('dashboard.user.crm.tasks.index') }}"
            ></a>
        </div>

        <div
            class="relative overflow-hidden p-5 pt-10 before:pointer-events-none before:absolute before:inset-0 before:z-0 before:translate-y-1/2 before:bg-gradient-to-t before:from-[#D4E5DB] before:to-transparent before:to-50% before:opacity-0 before:transition hover:before:translate-y-0 hover:before:opacity-100 md:pt-16">
            <x-number-counter
                class="relative z-1 mb-2.5 self-start text-2xl font-semibold text-heading-foreground"
                :value="$whatsNewStats['today']['reports']"
                :options="['delay' => 300]"
                :dynamic-value-listener="'reports'"
            />
            <p class="relative z-1 m-0 text-xs text-heading-foreground">
                {{ __('Reports') }}
            </p>
            <a
                class="absolute inset-0 z-2 inline-flex"
                href="{{ route('dashboard.user.crm.reports.index') }}"
            ></a>
        </div>
    </div>
</x-card>

@push('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('crmWhatsNew', () => ({
                stats: @json($whatsNewStats),
                currentView: 'today',
                init() {
                    this.updateView = this.updateView.bind(this);
                },
                updateView(view) {
                    if (this.currentView === view) return;

                    this.currentView = view;

                    this.$dispatch('dynamic-value-contacts', {
                        value: this.statsContacts,
                        options: {
                            delay: 0
                        }
                    });
                    this.$dispatch('dynamic-value-deals', {
                        value: this.statsDeals,
                        options: {
                            delay: 0
                        }
                    });
                    this.$dispatch('dynamic-value-tasks', {
                        value: this.statsTasks,
                        options: {
                            delay: 0
                        }
                    });
                    this.$dispatch('dynamic-value-reports', {
                        value: this.statsReports,
                        options: {
                            delay: 0
                        }
                    });
                },
                get statsContacts() {
                    return this.stats[this.currentView]['contacts'];
                },
                get statsDeals() {
                    return this.stats[this.currentView]['deals'];
                },
                get statsTasks() {
                    return this.stats[this.currentView]['tasks'];
                },
                get statsReports() {
                    return this.stats[this.currentView]['reports'];
                },
            }))
        })
    </script>
@endpush
