@php
    use Illuminate\Support\Js;

    /** @var array<int, \App\Extensions\AIChatPro\System\Connectors\ConnectorDefinition> $enabledDefinitions */
    $enabledDefinitions = $enabledDefinitions ?? [];
    /** @var array<int, array<string, mixed>> $initialConnectors */
    $initialConnectors = $initialConnectors ?? [];
@endphp

<div x-data="connectorsModal()">
    <template x-teleport="body">
        <div
            class="lqd-modal lqd-modal-connectors"
            :class="{ 'modal-open': open }"
        >
            <div
                class="lqd-modal-modal fixed inset-0 z-[990] flex items-center justify-center overflow-y-auto overscroll-contain px-4"
                x-cloak
                x-show="open"
                @keydown.escape.window="close()"
            >
                {{-- Backdrop --}}
                <div
                    class="lqd-modal-backdrop fixed inset-0 bg-black/10 backdrop-blur-sm"
                    x-show="open"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    @click="close()"
                ></div>

                {{-- Content --}}
                <div
                    class="lqd-modal-content relative z-[100] w-[min(100%,1060px)] max-h-[min(95vh,800px)] overflow-y-auto overscroll-contain rounded-card bg-card-background px-6 py-6 shadow-2xl shadow-black/10 md:px-9 md:py-8"
                    x-show="open"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-[0.98] translate-y-1"
                    x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                    x-transition:leave-end="opacity-0 scale-[0.98] translate-y-1"
                    @click.outside="close()"
                >
                    {{-- Header --}}
                    <div class="flex items-start gap-3 border-b border-card-border pb-5">
                        <div class="grow">
                            <h4 class="m-0 text-xl font-semibold text-heading-foreground">
                                {{ __('Connectors') }}
                            </h4>
                            <p class="m-0 mt-1 text-xs text-foreground/60">
                                {{ __('Personalize your AI by connecting your favorite apps and services.') }}
                            </p>
                        </div>

                        <button
                            class="ms-auto inline-flex size-8 shrink-0 items-center justify-center rounded-lg transition-all hover:bg-foreground/10"
                            @click="close()"
                            type="button"
                        >
                            <x-tabler-x class="size-4" />
                        </button>
                    </div>

                    {{-- Search --}}
                    <div class="relative mt-5">
                        <input
                            class="h-12 w-full rounded-input border-none bg-foreground/[6%] pe-12 ps-4 text-sm font-medium placeholder:text-foreground/60 focus:outline-none focus:ring-0"
                            type="text"
                            placeholder="{{ __('Search connectors') }}"
                            x-model="searchQuery"
                        />
                        <x-tabler-search class="pointer-events-none absolute end-4 top-1/2 size-4 -translate-y-1/2 text-foreground/60" />
                    </div>

                    {{-- Body --}}
                    <div class="mt-4">
                        <div
                            class="py-12 text-center"
                            x-show="visibleCount === 0"
                        >
                            <x-tabler-plug-x class="mx-auto mb-2 size-8 text-foreground/30" />
                            <p class="text-xs text-foreground/50">{{ __('No connectors found.') }}</p>
                        </div>

                        <div class="flex flex-col gap-3" x-show="visibleCount > 0">
                            @foreach ($enabledDefinitions as $key => $definition)
                                @php
                                    $iconName = $definition->icon();
                                    $isBladeIcon = str_contains($iconName, '::') && view()->exists($iconName);
                                @endphp
                                <div
                                    class="flex cursor-pointer items-center gap-4 rounded-card border border-card-border px-4 py-3.5 transition-all hover:shadow-sm hover:shadow-black/5"
                                    @click="openDetails('{{ $key }}')"
                                    x-show="matchesSearch('{{ $key }}')"
                                >
                                    {{-- Icon (server-rendered once) --}}
                                    <figure class="inline-grid size-10 shrink-0 place-items-center overflow-hidden rounded-lg">
                                        <span class="inline-grid size-7 place-items-center [&>svg]:size-full [&>svg]:object-contain">
                                            @if ($isBladeIcon)
                                                @include($iconName)
                                            @else
                                                @svg($iconName, 'size-6 text-heading-foreground')
                                            @endif
                                        </span>
                                    </figure>

                                    {{-- Name + description --}}
                                    <div class="min-w-0 flex-1">
                                        <p class="m-0 text-sm font-semibold text-heading-foreground">
                                            {{ $definition->label() }}
                                        </p>
                                        <p class="m-0 mt-1 line-clamp-2 text-xs text-foreground/60">
                                            {{ $definition->description() }}
                                        </p>

                                        @if (! empty($definition->permissions()))
                                            <ul
                                                class="m-0 mt-2 hidden flex-col gap-1 ps-4 text-2xs text-foreground/70 [&_li]:list-disc"
                                                x-show="state['{{ $key }}'] && state['{{ $key }}'].is_connected"
                                                style="display: none;"
                                            >
                                                @foreach ($definition->permissions() as $permission)
                                                    <li>{{ $permission }}</li>
                                                @endforeach
                                            </ul>
                                        @endif

                                        <p
                                            class="m-0 mt-1 text-2xs font-medium text-red-500"
                                            x-show="state['{{ $key }}'] && state['{{ $key }}'].is_connected && !state['{{ $key }}'].is_active"
                                        >
                                            {{ __('Token expired — reconnect to keep using this connector.') }}
                                        </p>
                                        <p
                                            class="m-0 mt-1 text-2xs font-medium text-amber-600"
                                            x-show="state['{{ $key }}'] && state['{{ $key }}'].is_paused"
                                        >
                                            {{ __('Paused — the AI will not use this connector until you resume it.') }}
                                        </p>
                                    </div>

                                    {{-- Action --}}
                                    <div class="shrink-0">
                                        <button
                                            class="inline-flex items-center whitespace-nowrap rounded-full border border-card-border px-4 py-2 text-2xs font-medium text-heading-foreground transition hover:border-primary hover:text-primary"
                                            type="button"
                                            x-show="!state['{{ $key }}'] || !state['{{ $key }}'].is_connected"
                                            @click.stop="connect('{{ $key }}')"
                                        >
                                            {{ __('Connect') }}
                                        </button>

                                        <div
                                            class="flex flex-wrap items-center justify-end gap-2"
                                            x-show="state['{{ $key }}'] && state['{{ $key }}'].is_connected"
                                        >
                                            <button
                                                class="inline-flex items-center whitespace-nowrap rounded-full border border-card-border px-4 py-2 text-2xs font-medium text-heading-foreground transition hover:border-primary hover:text-primary"
                                                type="button"
                                                x-show="state['{{ $key }}'] && state['{{ $key }}'].is_connected && state['{{ $key }}'].is_active"
                                                @click.stop="manageAccess('{{ $key }}')"
                                            >
                                                {{ __('Manage access') }}
                                            </button>
                                            <button
                                                class="inline-flex items-center whitespace-nowrap rounded-full border border-card-border px-4 py-2 text-2xs font-medium text-heading-foreground transition hover:border-primary hover:text-primary"
                                                type="button"
                                                x-show="state['{{ $key }}'] && state['{{ $key }}'].is_connected"
                                                @click.stop="togglePause('{{ $key }}')"
                                                x-text="state['{{ $key }}'] && state['{{ $key }}'].is_paused ? '{{ __('Resume') }}' : '{{ __('Pause') }}'"
                                            ></button>
                                            <button
                                                class="inline-flex items-center whitespace-nowrap rounded-full border border-card-border px-4 py-2 text-2xs font-medium text-heading-foreground transition hover:border-primary hover:text-primary"
                                                type="button"
                                                x-show="state['{{ $key }}'] && !state['{{ $key }}'].is_active"
                                                @click.stop="connect('{{ $key }}')"
                                            >
                                                {{ __('Reconnect') }}
                                            </button>
                                            <button
                                                class="inline-flex items-center whitespace-nowrap rounded-full border border-red-500/30 bg-red-500/5 px-4 py-2 text-2xs font-medium text-red-500 transition hover:border-red-500 hover:bg-red-500/10"
                                                type="button"
                                                @click.stop="disconnect('{{ $key }}')"
                                            >
                                                {{ __('Disconnect') }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>

    {{-- Details sub-modal --}}
    <template x-teleport="body">
        <div
            class="lqd-modal lqd-modal-connector-details"
            :class="{ 'modal-open': detailsOpen }"
        >
            <div
                class="lqd-modal-modal fixed inset-0 z-[995] flex items-center justify-center overflow-y-auto overscroll-contain px-4"
                x-cloak
                x-show="detailsOpen"
                @keydown.escape.window="detailsOpen && (detailsOpen = false)"
            >
                <div
                    class="lqd-modal-backdrop fixed inset-0 bg-black/15 backdrop-blur-sm"
                    x-show="detailsOpen"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    @click="detailsOpen = false"
                ></div>

                <div
                    class="lqd-modal-content relative z-[100] w-[min(100%,1100px)] max-h-[min(95vh,720px)] overflow-y-auto overscroll-contain rounded-card bg-card-background px-6 py-6 shadow-2xl shadow-black/10 md:px-9 md:py-7"
                    x-show="detailsOpen"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-[0.98] translate-y-1"
                    x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                    x-transition:leave-end="opacity-0 scale-[0.98] translate-y-1"
                    @click.outside="detailsOpen = false"
                >
                    {{-- Header --}}
                    <div class="flex items-start gap-3 border-b border-card-border pb-4">
                        <h4 class="m-0 text-xl font-semibold text-heading-foreground">
                            {{ __('Connector Details') }}
                        </h4>
                        <button
                            class="ms-auto inline-flex size-8 shrink-0 items-center justify-center rounded-lg transition-all hover:bg-foreground/10"
                            @click="detailsOpen = false"
                            type="button"
                        >
                            <x-tabler-x class="size-4" />
                        </button>
                    </div>

                    {{-- One pane per connector, only visible when detailsKey matches --}}
                    @foreach ($enabledDefinitions as $key => $definition)
                        @php
                            $iconName = $definition->icon();
                            $isBladeIcon = str_contains($iconName, '::') && view()->exists($iconName);
                            $details = $definition->details();
                        @endphp
                        <div
                            class="mt-6 grid grid-cols-1 gap-8 md:grid-cols-2"
                            x-show="detailsKey === '{{ $key }}'"
                        >
                            {{-- Left: connector card --}}
                            <div class="rounded-card border border-card-border px-6 py-8 text-center">
                                <figure class="mx-auto mb-4 inline-grid size-14 place-items-center [&>svg]:size-full [&>svg]:object-contain">
                                    <span class="inline-block size-14">
                                        @if ($isBladeIcon)
                                            @include($iconName)
                                        @else
                                            @svg($iconName, 'size-full')
                                        @endif
                                    </span>
                                </figure>

                                <h5 class="m-0 mb-3 text-2xl font-semibold text-heading-foreground">
                                    {{ $definition->label() }}
                                </h5>

                                <p class="m-0 mb-6 text-sm text-foreground/60">
                                    {{ $definition->description() }}
                                </p>

                                <button
                                    class="inline-flex items-center rounded-full bg-primary/10 px-5 py-2 text-xs font-medium text-primary transition hover:bg-primary/20"
                                    type="button"
                                    x-show="!state['{{ $key }}'] || !state['{{ $key }}'].is_connected"
                                    @click="connect('{{ $key }}'); detailsOpen = false"
                                >
                                    {{ __('Connect') }}
                                </button>
                                <button
                                    class="inline-flex items-center rounded-full bg-red-500/10 px-5 py-2 text-xs font-medium text-red-500 transition hover:bg-red-500/20"
                                    type="button"
                                    x-show="state['{{ $key }}'] && state['{{ $key }}'].is_connected"
                                    @click="disconnect('{{ $key }}'); detailsOpen = false"
                                >
                                    {{ __('Disconnect') }}
                                </button>
                            </div>

                            {{-- Right: meta + connection info --}}
                            <div class="self-start">
                                {{-- Connection block (only when connected) --}}
                                <div
                                    class="mb-6 flex items-center gap-3 rounded-card border border-card-border px-4 py-3"
                                    x-show="state['{{ $key }}'] && state['{{ $key }}'].is_connected"
                                >
                                    <img
                                        class="size-10 shrink-0 rounded-full object-cover"
                                        :src="state['{{ $key }}'] && state['{{ $key }}'].picture ? state['{{ $key }}'].picture : ''"
                                        :alt="state['{{ $key }}'] && state['{{ $key }}'].connected_name ? state['{{ $key }}'].connected_name : '{{ $definition->label() }}'"
                                        x-show="state['{{ $key }}'] && state['{{ $key }}'].picture"
                                    />
                                    <div
                                        class="grid size-10 shrink-0 place-items-center rounded-full bg-foreground/[8%] text-foreground/60"
                                        x-show="!state['{{ $key }}'] || !state['{{ $key }}'].picture"
                                    >
                                        <x-tabler-user class="size-5" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p
                                            class="m-0 truncate text-sm font-semibold text-heading-foreground"
                                            x-text="state['{{ $key }}'] && state['{{ $key }}'].connected_name ? state['{{ $key }}'].connected_name : ''"
                                            x-show="state['{{ $key }}'] && state['{{ $key }}'].connected_name"
                                        ></p>
                                        <p
                                            class="m-0 truncate text-xs text-foreground/60"
                                            x-text="state['{{ $key }}'] && state['{{ $key }}'].connected_email ? state['{{ $key }}'].connected_email : ''"
                                        ></p>
                                        <p
                                            class="m-0 mt-0.5 text-2xs text-foreground/50"
                                            x-show="state['{{ $key }}'] && state['{{ $key }}'].connected_at"
                                        >
                                            {{ __('Connected') }}
                                            <span x-text="state['{{ $key }}'] && state['{{ $key }}'].connected_at ? state['{{ $key }}'].connected_at : ''"></span>
                                        </p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-x-6 gap-y-6">
                                    <div>
                                        <p class="m-0 text-sm font-semibold text-heading-foreground">
                                            {{ __('Connector Type') }}
                                        </p>
                                        <p class="m-0 mt-1 text-sm text-foreground/60">
                                            {{ $details['type'] ?? '' }}
                                        </p>
                                    </div>

                                    <div>
                                        <p class="m-0 text-sm font-semibold text-heading-foreground">
                                            {{ __('Author') }}
                                        </p>
                                        <p class="m-0 mt-1 text-sm text-foreground/60">
                                            {{ $details['author'] ?? '' }}
                                        </p>
                                    </div>

                                    <div class="col-span-2">
                                        <p class="m-0 text-sm font-semibold text-heading-foreground">
                                            {{ __('UUID') }}
                                        </p>
                                        <p
                                            class="m-0 mt-1 break-all font-mono text-sm text-foreground/60"
                                            x-text="(state['{{ $key }}'] && state['{{ $key }}'].connection_uuid) ? state['{{ $key }}'].connection_uuid : '{{ $details['uuid'] ?? '' }}'"
                                        ></p>
                                    </div>

                                    <div>
                                        <p class="m-0 text-sm font-semibold text-heading-foreground">
                                            {{ __('More Info') }}
                                        </p>
                                        <a
                                            class="m-0 mt-1 inline-block text-sm text-foreground/60 underline underline-offset-2 hover:text-primary"
                                            href="{{ $details['website'] ?? '#' }}"
                                            target="_blank"
                                            rel="noopener"
                                        >{{ __('Website') }}</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </template>
</div>

@pushOnce('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('connectorsModal', () => ({
                open: false,
                searchQuery: '',
                detailsOpen: false,
                detailsKey: null,
                state: {},
                meta: {},
                isDemo: {{ $app_is_demo ? 'true' : 'false' }},
                isGuest: {{ auth()->check() ? 'false' : 'true' }},
                listUrl: '{{ route('dashboard.user.ai-chat-pro.connectors.index') }}',

                init() {
                    const initial = {!! Js::from($initialConnectors) !!};
                    this.hydrate(initial);

                    window.addEventListener('open-connectors-modal', () => {
                        if (this.isGuest) {
                            toastr.info('{{ __('Please login to use connectors.') }}');
                            return;
                        }
                        this.open = true;
                        // refresh in background so list opens instantly
                        this.refresh();
                    });

                    const seenNonces = new Set();
                    const handleConnectorEvent = (data) => {
                        if (!data || (data.type !== 'ai_chat_pro_connector_connected' && data.type !== 'ai_chat_pro_connector_access_updated')) return;
                        if (data.nonce) {
                            if (seenNonces.has(data.nonce)) return;
                            seenNonces.add(data.nonce);
                            // bound memory; we never need to remember more than a few
                            if (seenNonces.size > 32) seenNonces.delete(seenNonces.values().next().value);
                        }

                        if (data.type === 'ai_chat_pro_connector_connected') {
                            if (data.success) {
                                toastr.success(data.message || '{{ __('Connected successfully.') }}');
                            } else if (data.message) {
                                toastr.error(data.message);
                            }
                        }
                        this.refresh();
                    };

                    window.addEventListener('message', (event) => {
                        if (event.origin !== window.location.origin) return;
                        handleConnectorEvent(event.data);
                    });

                    // BroadcastChannel survives Cross-Origin-Opener-Policy isolation
                    // (Microsoft / Google login pages sever window.opener), so it is
                    // the reliable signal that the OAuth popup finished.
                    try {
                        if (typeof BroadcastChannel === 'function') {
                            const ch = new BroadcastChannel('ai_chat_pro_connectors');
                            ch.addEventListener('message', (event) => handleConnectorEvent(event.data));
                        }
                    } catch (e) {}
                },

                hydrate(list) {
                    const state = {};
                    const meta = {};
                    (list || []).forEach((c) => {
                        meta[c.key] = {
                            label: c.label,
                            description: c.description,
                            redirect_url: c.redirect_url,
                            pre_consent_url: c.pre_consent_url,
                        };
                        state[c.key] = {
                            is_connected: !!c.is_connected,
                            is_active: !!c.is_active,
                            is_paused: !!c.is_paused,
                            connector_id: c.connector_id,
                            connection_uuid: c.connection_uuid,
                            connected_name: c.connected_name,
                            connected_email: c.connected_email,
                            picture: c.picture,
                            connected_at: c.connected_at,
                            disconnect_url: c.disconnect_url,
                            access_url: c.access_url,
                            pause_url: c.pause_url,
                        };
                    });
                    this.state = state;
                    this.meta = meta;
                },

                matchesSearch(key) {
                    const q = this.searchQuery.trim().toLowerCase();
                    if (!q) return true;
                    const m = this.meta[key];
                    if (!m) return true;
                    return (m.label || '').toLowerCase().includes(q)
                        || (m.description || '').toLowerCase().includes(q);
                },

                get visibleCount() {
                    return Object.keys(this.meta).filter((k) => this.matchesSearch(k)).length;
                },

                close() {
                    this.open = false;
                },

                openDetails(key) {
                    this.detailsKey = key;
                    this.detailsOpen = true;
                },

                async refresh() {
                    try {
                        const res = await fetch(this.listUrl, {
                            headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        });
                        const data = await res.json();
                        this.hydrate(data.connectors || []);
                    } catch (err) {
                        // silent — list is already populated from server render
                    }
                },

                openPopup(url, name) {
                    const w = 600, h = 760;
                    const left = (window.outerWidth - w) / 2 + (window.screenX || 0);
                    const top = (window.outerHeight - h) / 2 + (window.screenY || 0);
                    const popup = window.open(url, name, `width=${w},height=${h},left=${left},top=${top}`);
                    // Final safety net: if both BroadcastChannel and postMessage are
                    // dropped (rare), refresh once the popup actually closes.
                    if (popup) {
                        const poll = setInterval(() => {
                            let closed = true;
                            try { closed = popup.closed; } catch (e) { closed = true; }
                            if (closed) {
                                clearInterval(poll);
                                this.refresh();
                            }
                        }, 600);
                    }
                    return popup;
                },

                connect(key) {
                    if (this.isDemo) {
                        toastr.error('{{ __('This feature is disabled in demo mode.') }}');
                        return;
                    }
                    const m = this.meta[key];
                    const baseUrl = (m && (m.pre_consent_url || m.redirect_url)) || null;
                    if (!baseUrl) return;
                    const url = baseUrl + (baseUrl.includes('?') ? '&' : '?') + 'popup=1';
                    this.openPopup(url, 'ai_chat_pro_connector');
                },

                manageAccess(key) {
                    const s = this.state[key];
                    if (!s || !s.access_url) return;
                    const url = s.access_url + (s.access_url.includes('?') ? '&' : '?') + 'popup=1';
                    this.openPopup(url, 'ai_chat_pro_connector_access');
                },

                async togglePause(key) {
                    if (this.isDemo) {
                        toastr.error('{{ __('This feature is disabled in demo mode.') }}');
                        return;
                    }
                    const s = this.state[key];
                    if (!s || !s.pause_url) return;
                    try {
                        const res = await fetch(s.pause_url, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            },
                        });
                        const data = await res.json();
                        if (res.ok) {
                            toastr.success(data.message || '{{ __('Updated.') }}');
                            this.refresh();
                        } else {
                            toastr.error(data.message || '{{ __('Failed to update.') }}');
                        }
                    } catch (err) {
                        toastr.error('{{ __('Failed to update.') }}');
                    }
                },

                async disconnect(key) {
                    if (this.isDemo) {
                        toastr.error('{{ __('This feature is disabled in demo mode.') }}');
                        return;
                    }
                    const s = this.state[key];
                    if (!s || !s.disconnect_url) return;
                    if (!confirm('{{ __('Disconnect this account?') }}')) return;
                    try {
                        const res = await fetch(s.disconnect_url, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });
                        const data = await res.json();
                        if (res.ok) {
                            toastr.success(data.message || '{{ __('Disconnected.') }}');
                            this.refresh();
                        } else {
                            toastr.error(data.message || '{{ __('Failed to disconnect.') }}');
                        }
                    } catch (err) {
                        toastr.error('{{ __('Failed to disconnect.') }}');
                    }
                },
            }));
        });
    </script>
@endPushOnce
