{{-- Connectors Manager Modal --}}
@php
    $availableConnectors = [];

    if (class_exists(\App\Extensions\AIAgentGmail\System\AIAgentGmailServiceProvider::class)) {
        $availableConnectors[] = [
            'type' => 'gmail',
            'label' => 'Gmail',
            'description' => __('Send emails, manage labels, create drafts, and more.'),
            'icon' => 'tabler-brand-gmail',
            'category' => 'mail',
            'route' => route('dashboard.user.ai-agent.connectors.gmail.redirect'),
        ];
    }

    if (class_exists(\App\Extensions\AIAgentOutlook\System\AIAgentOutlookServiceProvider::class)) {
        $availableConnectors[] = [
            'type' => 'outlook',
            'label' => 'Outlook',
            'description' => __('Send emails, manage calendar events, contacts, and more.'),
            'icon' => 'tabler-brand-office',
            'category' => 'mail',
            'route' => route('dashboard.user.ai-agent.connectors.outlook.redirect'),
        ];
    }
@endphp

@if (count($availableConnectors) > 0)
    <x-modal
        class:modal-content="flex flex-col p-5 md:px-8 md:pb-8 md:pt-5 !max-h-none max-h-[calc(100vh-4rem)] !min-w-0 w-[min(940px,100%)]"
        class:modal-head="p-0 shrink-0"
        class:modal-title="text-[12px] font-semibold"
        class:modal-body="p-0 flex flex-col grow"
        class:modal-container="flex flex-col grow w-full"
        class:close-btn="size-7"
        class:close-btn-icon="size-4"
        id="ai-agent-connectors-modal"
    >
        <x-slot:trigger
            custom
            variant="outline"
        >
            @unless($hideConnectorTrigger ?? false)
            <label class="lqd-input-label relative mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                {{ __('Connectors') }}
                <x-info-tooltip text="{{ __('Gmail, Outlook, and other service integrations.') }}" />
            </label>
            <x-button
                class="w-full justify-start border-input-border text-start [--button-rounded:var(--input-rounded)] sm:text-xs"
                ::class="{ '!py-1.5': connectors.length && connectors.some(c => c.picture) }"
                size="lg"
                type="button"
                variant="outline"
                @click.prevent="connectorsModal.tab = 'available'; toggleConnectorsModal()"
            >
                <x-tabler-plug class="size-4 text-foreground" />
                <span>
                    {{ __('Manage Connectors') }}
                </span>
                <template x-if="connectors.length > 0">
                    <span class="peer/pics ms-auto inline-flex items-center gap-2 text-2xs font-medium">
                        <span class="inline-flex items-center">
                            <template
                                x-for="(connector, index) in connectors"
                                :key="connector.id"
                            >
                                <template x-if="index < 4">
                                    <span
                                        class="-ms-1 size-8 rounded-full border-2 bg-center bg-no-repeat last:ms-0"
                                        :style="{
                                            'background-image': 'url(' + (connector.picture ? connector.picture :
                                                `{{ custom_theme_url('/vendor/ai-agent/images/platforms/') }}${connector.type}.png`) + ')',
                                            'background-size': connector
                                                .picture ? 'cover' : '24px'
                                        }"
                                    ></span>
                                </template>
                            </template>
                        </span>
                        <span
                            x-show="connectors.length > 5"
                            x-text="'{{ __('and more') }}'"
                        ></span>
                    </span>
                </template>

                <x-tabler-chevron-right class="ms-auto size-4 peer-has-[span]/pics:ms-0" />
            </x-button>
            @endunless
        </x-slot:trigger>

        <x-slot:head-content>
            <div class="flex gap-5">
                <button
                    class="active py-2 text-[12px] font-semibold text-heading-foreground opacity-50 transition [&.active]:opacity-100"
                    @click.prevent="connectorsModal.tab = 'available'"
                    :class="{ 'active': connectorsModal.tab === 'available' }"
                >
                    {{ __('Add Connector') }}
                </button>

                <button
                    class="py-2 text-[12px] font-semibold text-heading-foreground opacity-50 transition [&.active]:opacity-100"
                    @click.prevent="connectorsModal.tab = 'list'"
                    :class="{ 'active': connectorsModal.tab === 'list' }"
                >
                    {{ __('All Connectors') }}
                </button>
            </div>
        </x-slot:head-content>

        <x-slot:modal>
            <div
                class="flex flex-wrap items-start gap-4 py-5"
                x-show="connectorsModal.tab === 'available'"
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
                        x-model="connectorsModal.search"
                        placeholder="{{ __('Search connectors...') }}"
                    />
                    <x-tabler-search class="absolute end-5 top-1/2 z-1 size-4 -translate-y-1/2" />
                </form>

                {{-- Left sidebar --}}
                <div class="sticky top-12 z-3 w-full shrink-0 lg:top-16 lg:w-40">
                    <div
                        class="relative flex flex-col gap-4 [interpolate-size:allow-keywords]"
                        @click.outside="connectorsModal.mobile.categoryDropdownOpen = false"
                    >
                        <x-button
                            class="justify-between bg-background/90 py-2.5 text-2xs backdrop-blur-sm [--button-rounded:theme(spacing.2)] lg:hidden"
                            variant="outline"
                            @click.prevent="connectorsModal.mobile.categoryDropdownOpen = !connectorsModal.mobile.categoryDropdownOpen"
                        >
                            <span
                                class="capitalize"
                                x-text="connectorsModal.category.replace('_', ' ')"
                            >
                                {{ __('All') }}
                            </span>
                            <x-tabler-chevron-down
                                class="size-4 transition"
                                ::class="{ 'rotate-180': connectorsModal.mobile.categoryDropdownOpen }"
                            />
                        </x-button>
                        <div
                            class="max-lg:absolute max-lg:inset-x-0 max-lg:top-full max-lg:z-3 max-lg:h-0 max-lg:overflow-hidden max-lg:rounded-lg max-lg:bg-dropdown-background max-lg:shadow-xl max-lg:shadow-black/5 max-lg:transition-all max-lg:[&.open]:h-auto"
                            :class="{ 'open': connectorsModal.mobile.categoryDropdownOpen }"
                        >
                            <div class="flex flex-col gap-5 max-lg:max-h-[65vh] max-lg:overflow-y-auto max-lg:border max-lg:p-4">
                                <button
                                    class="text-start text-xs font-medium text-foreground/65 transition hover:text-foreground [&.active]:text-foreground [&.active]:underline [&.active]:underline-offset-4"
                                    type="button"
                                    :class="{ 'active': connectorsModal.category === 'all' }"
                                    @click.prevent="connectorsModal.category = 'all'"
                                >
                                    {{ __('All') }}
                                </button>
                                @foreach ([['mail', __('Mail')]] as [$t, $Label])
                                    <button
                                        class="text-start text-xs font-medium text-foreground/65 transition hover:text-foreground [&.active]:text-foreground [&.active]:underline [&.active]:underline-offset-4"
                                        type="button"
                                        :class="{ 'active': connectorsModal.category === '{{ $t }}' }"
                                        @click.prevent="connectorsModal.category = '{{ $t }}'"
                                    >
                                        {{ $Label }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Right content --}}
                <div
                    class="min-w-0 flex-1 max-lg:w-full"
                    x-init="connectorsModal.availableConnectors = {{ json_encode($availableConnectors) }}"
                >
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <template
                            x-for="connector in filteredAvailableConnectors"
                            :key="connector.type"
                        >
                            <x-card
                                class:body="flex items-center gap-4"
                                class="hover:-translate-y-0.5 hover:shadow-xl hover:shadow-black/5"
                                size="sm"
                            >
                                <img
                                    class="h-auto w-10 shrink-0"
                                    :src="`{{ custom_theme_url('/vendor/ai-agent/images/platforms/') }}${connector.type}.png`"
                                    :alt="connector.label"
                                    aria-hidden="true"
                                >
                                <div class="flex-1">
                                    <p
                                        class="mb-0 text-2xs font-semibold"
                                        x-text="connector.label"
                                    ></p>
                                    <p
                                        class="m-0 text-2xs opacity-70"
                                        x-text="connector.description"
                                    ></p>
                                </div>

                                <a
                                    class="absolute inset-0 z-1"
                                    href="#"
                                    @click.prevent="openConnectorPopup(connector.route + '?popup=1')"
                                ></a>
                            </x-card>
                        </template>
                    </div>

                    <template x-if="filteredAvailableConnectors.length === 0">
                        <x-empty-state
                            class="p-5"
                            icon="tabler-plug-x"
                            title="{{ __('No connectors available') }}"
                            description="{{ __('No connectors match your search or selected category.') }}"
                        />
                    </template>
                </div>
            </div>

            <div x-show="connectorsModal.tab === 'list'">
                {{-- Empty state --}}
                <template x-if="connectors.length === 0">
                    <div class="p-5">
                        <x-empty-state
                            icon="tabler-plug-x"
                            title="{{ __('No connectors yet') }}"
                            description="{{ __('Connect a service account to use it in your workflows.') }}"
                        />
                        <x-button
                            variant="outline"
                            type="button"
                            @click.prevent="connectorsModal.tab = 'available'"
                        >
                            {{ __('Add a connector') }}
                        </x-button>
                    </div>
                </template>

                {{-- No matches for current search/category --}}
                <template x-if="connectors.length > 0 && filteredUserConnectors.length === 0">
                    <x-empty-state
                        class="p-5"
                        icon="tabler-search-off"
                        title="{{ __('No connectors match your search') }}"
                        description="{{ __('Try a different keyword or category.') }}"
                    />
                </template>

                {{-- Connector list --}}
                <template x-if="filteredUserConnectors.length > 0">
                    <div class="grid grid-cols-1 gap-2 py-5 sm:grid-cols-2">
                        <template
                            x-for="conn in filteredUserConnectors"
                            :key="conn.id"
                        >
                            <x-card
                                class:body="flex items-center gap-4"
                                class="hover:-translate-y-0.5 hover:shadow-xl hover:shadow-black/5"
                                size="sm"
                            >
                                <div class="relative shrink-0">
                                    <template x-if="conn.picture">
                                        <img
                                            class="size-8 rounded-full object-cover"
                                            :src="conn.picture"
                                            alt=""
                                        >
                                    </template>
                                    <template x-if="!conn.picture">
                                        <div class="flex size-8 items-center justify-center rounded-full bg-primary/10 text-primary">
                                            <x-tabler-plug class="size-4" />
                                        </div>
                                    </template>
                                    {{-- Status dot --}}
                                    <span
                                        class="absolute -end-0.5 -top-0.5 size-2.5 rounded-full border-2 border-background"
                                        :class="conn.is_active ? 'bg-green-500' : 'bg-foreground/20'"
                                    ></span>
                                </div>
                                <div class="flex-1">
                                    <p
                                        class="mb-0 truncate text-2xs font-semibold"
                                        x-text="conn.name || conn.email"
                                        :title="conn.name || conn.email"
                                    ></p>
                                    <p
                                        class="m-0 truncate text-2xs opacity-70"
                                        x-text="conn.email"
                                        :title="conn.email"
                                    ></p>
                                </div>

                                {{-- Type badge + actions --}}
                                <div class="flex shrink-0 items-center gap-1">
                                    <img
                                        class="me-1 size-4 shrink-0 object-contain object-center"
                                        :src="`{{ custom_theme_url('/vendor/ai-agent/images/platforms/') }}${conn.type}.png`"
                                        aria-hidden="true"
                                        :alt="conn.type"
                                    />

                                    {{-- Reconnect --}}
                                    <template x-if="conn.reconnect_url">
                                        <button
                                            class="grid size-7 place-items-center rounded-lg transition-colors hover:bg-foreground/5"
                                            :class="conn.is_active ? 'text-green-500' : 'text-red-500'"
                                            type="button"
                                            @click="openConnectorPopup(conn.reconnect_url + '?popup=1')"
                                            title="{{ __('Reconnect') }}"
                                        >
                                            <x-tabler-reload class="size-3.5" />
                                        </button>
                                    </template>

                                    {{-- Disconnect --}}
                                    <button
                                        class="grid size-7 place-items-center rounded-lg text-foreground/30 transition-colors hover:bg-red-50 hover:text-red-500"
                                        type="button"
                                        @click="disconnectConnector(conn.id, conn.delete_url)"
                                        title="{{ __('Disconnect') }}"
                                    >
                                        <x-tabler-trash class="size-3.5" />
                                    </button>
                                </div>
                            </x-card>
                        </template>
                    </div>
                </template>
            </div>
        </x-slot:modal>
    </x-modal>
@endif
