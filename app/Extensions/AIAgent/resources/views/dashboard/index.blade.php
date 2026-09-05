@extends('panel.layout.app', ['disable_tblr' => true, 'disable_titlebar' => true])
@section('title', __('AI Agent'))
@section('subtitle', __('AI Agent'))
@section('titlebar_subtitle', __('Autonomous workflows, multi-channel messaging, and user memory.'))

@section('content')
    <div class="py-10">

        <div
            class="lqd-ai-agent-dash md:py-5"
            x-data="aiAgentDash"
        >
            {{-- Copilot widget --}}
            <div class="container flex max-w-[740px] flex-col gap-7">
                <section class="flex flex-wrap gap-x-4">
                    <div class="grow">
                        <a
                            class="mb-4 inline-flex items-center gap-2 rounded-full border py-1.5 pe-4 ps-1.5 text-[12px] font-medium text-foreground/80 transition hover:border-foreground/20 hover:bg-foreground/5"
                            href="{{ route('dashboard.user.ai-agent.messages.index') }}"
                        >
                            <span class="inline-grid size-[34px] place-items-center rounded-full bg-foreground/5 text-center">
                                <x-tabler-message-circle class="size-5 fill-current stroke-none text-foreground/70" />
                            </span>
                            <span x-text="newMessagesCount">{{ $unreadCount }}</span>
                            {{ __('Messages') }}
                        </a>

                        <h2 class="text-[30px] font-medium max-md:mb-5">
                            <span class="block opacity-35">
                                {{ __('Hey :username', ['username' => $userName]) }},
                            </span>
                            <span>
                                {{ __('Describe your agent.') }}
                            </span>
                        </h2>
                    </div>

                    <div
                        class="flex w-[230px] justify-center self-end max-md:order-first max-md:mb-7 max-md:[mask-image:linear-gradient(to_bottom,black_70%,transparent_100%)] max-sm:w-[60vw]"
                        aria-hidden="true"
                    >
                        <img
                            class="h-auto w-full"
                            src="{{ custom_theme_url('/vendor/ai-agent/images/img-1.png') }}"
                            alt="{{ __('Agent Image') }}"
                            aria-hidden="true"
                            width="452"
                            height="365"
                        >
                    </div>

                    <div class="w-full overflow-hidden rounded-[22px] border border-transparent shadow-2xl shadow-black/[8%] dark:border-foreground/5">
                        <x-forms.input
                            class="w-full resize-none rounded-none border-none px-7 py-4 focus-visible:outline-none focus-visible:ring-0"
                            rows="4"
                            type="textarea"
                            placeholder="{{ __('What would you like your agent to handle? Share your ideas, and I\'ll create it for you!') }}"
                            x-model="buildAgentInput"
                            @keydown.meta.enter="buildAgentSubmitHandler()"
                            @keydown.ctrl.enter="buildAgentSubmitHandler()"
                            x-init="$nextTick(() => $el.focus())"
                        ></x-forms.input>

                        <div class="flex justify-between px-7 pb-4">
                            <button
                                class="relative z-1 flex items-center"
                                type="button"
                                title="{{ __('Manage Connectors') }}"
                                @click="connectorsModal.tab = 'list'; toggleConnectorsModal(true)"
                            >
                                @foreach (['gmail', 'outlook'] as $platform)
                                    <div @class([
                                        'inline-grid size-9 place-items-center rounded-full border-2 bg-background',
                                        '-ms-3 -z-1' => !$loop->first,
                                    ])>
                                        <img
                                            class="size-5 object-contain"
                                            src="{{ custom_theme_url("/vendor/ai-agent/images/platforms/{$platform}.png") }}"
                                            alt="{{ ucfirst($platform) }} Icon"
                                            width="20"
                                            height="20"
                                        >
                                    </div>
                                @endforeach
                            </button>
                            <x-button
                                class="grid size-11 place-items-center rounded-full bg-heading-foreground text-background hover:scale-105 hover:shadow-xl hover:shadow-black/5"
                                hover-variant="primary"
                                size="none"
                                type="button"
                                @click="buildAgentSubmitHandler()"
                                ::disabled="!buildAgentInput.trim()"
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
                </section>

                <a
                    class="group flex w-full items-center gap-8 py-1"
                    href="{{ route('dashboard.user.ai-agent.workflows.index') }}"
                >
                    <span class="inline-block h-px grow bg-border"></span>
                    <span class="inline-flex items-center text-[12px] font-medium [&_u]:ms-1">
                        {!! __('or start with <u>Visual Builder</u>') !!}
                        <x-tabler-chevron-right class="ms-2 size-4 shrink-0 transition group-hover:translate-x-1 rtl:-scale-x-100" />
                    </span>
                    <span class="inline-block h-px grow bg-border"></span>
                </a>

                <section class="rounded-[10px] border px-5 pt-5">
                    <div class="mb-2.5 flex items-center justify-between gap-2">
                        <p class="m-0 text-[12px] font-medium text-foreground/65">
                            {{ __('Featured Agents') }}
                        </p>

                        <x-button
                            class="text-[12px] font-medium text-foreground/65 underline"
                            href="#"
                            variant="link"
                            @click.prevent="Alpine.$data(document.querySelector('#ai-agent-templates-modal')).modalOpen = true"
                        >
                            {{ __('More agents') }}
                        </x-button>
                    </div>

                    <div class="no-scrollbar -mx-2 flex snap-x snap-mandatory overflow-x-auto overflow-y-hidden overscroll-contain pb-5">
                        @foreach ($templates->filter(fn($template) => $template['featured']) as $template)
                            <div class="group relative w-[90%] shrink-0 grow-0 basis-auto snap-center px-2 md:w-1/2 lg:w-1/3">
                                <div
                                    class="mb-2 inline-grid aspect-[1/0.6] w-full place-items-center rounded-lg bg-gradient-to-t from-[--color] to-[hsl(from_var(--color)_h_s_l/50%)]"
                                    style="--color:{{ $template['color'] }}"
                                >
                                    <img
                                        class="h-auto w-[50px] transition group-hover:scale-110"
                                        src="{{ custom_theme_url($template['avatar']) }}"
                                        alt="{{ $template['label'] }}"
                                    />
                                </div>
                                <p class="m-0 w-full truncate text-2xs font-medium opacity-65 group-hover:underline group-hover:underline-offset-2">
                                    {{ $template['label'] }}
                                </p>

                                <a
                                    class="absolute inset-0 z-1"
                                    href="{{ route('dashboard.user.ai-agent.workflows.create') }}?template={{ $template['key'] }}"
                                    title="{{ $template['label'] }}"
                                ></a>
                            </div>
                        @endforeach

                        <div class="group relative w-[90%] shrink-0 grow-0 snap-center px-2 md:w-1/2 lg:w-1/3">
                            <div
                                class="mb-2 inline-grid aspect-[1/0.6] w-full place-items-center rounded-lg bg-gradient-to-b from-gradient-from to-gradient-to"
                                style="--color: hsl(var(--primary) / 50%)"
                            >
                                <x-tabler-plus class="size-7 text-primary-foreground transition group-hover:scale-110" />
                            </div>
                            <p class="m-0 w-full truncate text-2xs font-medium opacity-65 group-hover:underline group-hover:underline-offset-2">
                                {{ __('More Agents') }}
                            </p>

                            <a
                                class="absolute inset-0 z-1"
                                href="#"
                                @click.prevent="Alpine.$data(document.querySelector('#ai-agent-templates-modal')).modalOpen = true"
                                title="{{ __('More Agents') }}"
                            ></a>
                        </div>
                    </div>
                </section>
            </div>

            {{-- Stats row --}}
            {{-- <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <x-card class="flex flex-col gap-1 p-5">
                    <p class="text-2xs text-foreground/60">@lang('Active Agents')</p>
                    <p class="text-3xl font-bold">{{ $workflowCount }}</p>
                </x-card>
                <x-card class="flex flex-col gap-1 p-5">
                    <p class="text-2xs text-foreground/60">@lang('Connected Channels')</p>
                    <p class="text-3xl font-bold">{{ $channelCount }}</p>
                </x-card>
                <x-card class="flex flex-col gap-1 p-5">
                    <p class="text-2xs text-foreground/60">@lang('Recent Runs')</p>
                    <p class="text-3xl font-bold">{{ $recentRuns->count() }}</p>
                </x-card>
            </div> --}}

            <section class="pt-14">
                <div class="mb-9 flex flex-wrap items-center justify-between gap-4">
                    <h2 class="m-0">
                        {{ __('My Agents') }}
                    </h2>

                    <x-button
                        class="text-2xs text-foreground/65 hover:text-primary"
                        href="{{ route('dashboard.user.ai-agent.workflows.index') }}"
                        variant="link"
                    >
                        {{ __('View All') }}
                        <x-tabler-chevron-right class="size-4 shrink-0 transition group-hover:translate-x-1 rtl:-scale-x-100" />
                    </x-button>
                </div>

                <div class="-mx-2.5 flex">
                    @foreach ($workflows->take(3) as $workflow)
                        <div class="w-full shrink-0 grow-0 basis-auto px-2.5 md:w-1/2 lg:w-1/3">
                            @include('ai-agent::includes.agent-card', ['workflow' => $workflow])
                        </div>
                    @endforeach
                </div>
            </section>

            @include('ai-agent::includes.templates-modal')
            @include('ai-agent::includes.logs-modal')
            @include('ai-agent::includes.connectors-modal', ['hideConnectorTrigger' => true])
        </div>
    </div>
@endsection

@push('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('aiAgentDash', () => ({
                buildAgentInput: '',
                newMessagesCount: {{ (int) $unreadCount }},
                _pollHandle: null,

                connectors: @json($connectors ?? []),

                connectorsModal: {
                    mobile: { categoryDropdownOpen: false },
                    modalEl: null,
                    category: 'all',
                    search: '',
                    tab: 'list',
                    loading: false,
                    availableConnectors: [],
                },

                connectorTypeCategory: {
                    gmail: 'mail',
                    outlook: 'mail',
                },

                get filteredAvailableConnectors() {
                    const cat = this.connectorsModal.category;
                    const q = this.connectorsModal.search.trim().toLowerCase();
                    return (this.connectorsModal.availableConnectors || []).filter(c => {
                        if (cat !== 'all' && cat !== c.category) { return false; }
                        if (!q) { return true; }
                        return (c.label || '').toLowerCase().includes(q) ||
                            (c.description || '').toLowerCase().includes(q) ||
                            (c.type || '').toLowerCase().includes(q);
                    });
                },

                get filteredUserConnectors() {
                    const cat = this.connectorsModal.category;
                    const q = this.connectorsModal.search.trim().toLowerCase();
                    return (this.connectors || []).filter(c => {
                        const connCat = this.connectorTypeCategory[c.type] ?? 'personal';
                        if (cat !== 'all' && cat !== connCat) { return false; }
                        if (!q) { return true; }
                        return (c.name || '').toLowerCase().includes(q) ||
                            (c.email || '').toLowerCase().includes(q) ||
                            (c.type || '').toLowerCase().includes(q);
                    });
                },

                toggleConnectorsModal(state) {
                    if (!this.connectorsModal.modalEl) {
                        this.connectorsModal.modalEl = document.querySelector('#ai-agent-connectors-modal');
                    }
                    Alpine.$data(this.connectorsModal.modalEl).toggleModal(state);
                },

                openConnectorPopup(url) {
                    const w = 600, h = 700;
                    const left = Math.round(screen.width / 2 - w / 2);
                    const top = Math.round(screen.height / 2 - h / 2);
                    window.open(url, 'connectorOAuth', `width=${w},height=${h},left=${left},top=${top},resizable=yes,scrollbars=yes`);
                    const handler = (event) => {
                        if (event.origin !== window.location.origin) { return; }
                        if (!event.data || event.data.type !== 'connector_connected') { return; }
                        window.removeEventListener('message', handler);
                        if (event.data.success && event.data.connector) {
                            const incoming = event.data.connector;
                            const idx = this.connectors.findIndex(c => c.id === incoming.id);
                            if (idx !== -1) { this.connectors[idx] = incoming; } else { this.connectors.push(incoming); }
                            this.connectorsModal.tab = 'list';
                            toastr.success(event.data.message || '{{ __('Connector connected.') }}');
                        } else {
                            toastr.error(event.data.message || '{{ __('Connection failed.') }}');
                        }
                    };
                    window.addEventListener('message', handler);
                },

                async disconnectConnector(id, deleteUrl) {
                    if (!confirm('{{ __('Disconnect this account?') }}')) { return; }
                    try {
                        const res = await fetch(deleteUrl, {
                            method: 'DELETE',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                        });
                        const data = await res.json();
                        if (res.ok) {
                            this.connectors = this.connectors.filter(c => c.id !== id);
                            toastr.success(data.message || '{{ __('Connector disconnected.') }}');
                        } else {
                            toastr.error(data.message || '{{ __('Failed to disconnect.') }}');
                        }
                    } catch {
                        toastr.error('{{ __('Failed to disconnect.') }}');
                    }
                },

                init() {
                    this.startPolling();
                    this._visibilityHandler = () => {
                        document.hidden ? this.stopPolling() : this.startPolling();
                    };
                    document.addEventListener('visibilitychange', this._visibilityHandler);
                },

                destroy() {
                    this.stopPolling();
                    document.removeEventListener('visibilitychange', this._visibilityHandler);
                },

                startPolling() {
                    if (this._pollHandle) return;
                    this.refreshUnreadCount();
                    this._pollHandle = setInterval(() => this.refreshUnreadCount(), 30000);
                },

                stopPolling() {
                    if (this._pollHandle) {
                        clearInterval(this._pollHandle);
                        this._pollHandle = null;
                    }
                },

                async refreshUnreadCount() {
                    try {
                        const res = await fetch('{{ route('dashboard.user.ai-agent.messages.unread-count') }}', {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });
                        const data = await res.json();
                        if (typeof data.count === 'number') {
                            this.newMessagesCount = data.count;
                        }
                    } catch (e) {}
                },

                buildAgentSubmitHandler() {
                    if (!this.buildAgentInput.trim()) return;
                    window.location.href = '{{ route('dashboard.user.ai-agent.workflows.create') }}?copilot_init=' + encodeURIComponent(this.buildAgentInput
                        .trim());
                }
            }));
        });
    </script>
@endpush
