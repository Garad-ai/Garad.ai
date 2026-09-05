<div
    class="lqd-ext-phone-history invisible fixed end-0 start-0 top-0 z-40 flex h-screen bg-background opacity-0 transition-all max-sm:block lg:start-[--navbar-width] lg:group-[&.focus-mode]/body:start-0 [&.lqd-open]:visible [&.lqd-open]:opacity-100"
    x-data="phoneCallAgentHistory"
    :class="{ 'lqd-open': open }"
    @keydown.window.escape="setOpen(false)"
>
    {{-- Sidebar --}}
    <div
        class="lqd-ext-phone-history-sidebar group/history-sidebar relative flex shrink-0 flex-col bg-foreground/[3%] lg:w-[clamp(250px,27%,400px)]"
        :class="{ 'mobile-dropdown-open': mobileDropdownOpen }"
    >
        @include('phone-call-agent::home.call-history.call-history-filter')

        {{-- Mobile dropdown toggle --}}
        <x-button
            class="w-full rounded-none border-y py-4 sm:hidden"
            variant="link"
            @click="mobileDropdownOpen = !mobileDropdownOpen"
        >
            @lang('Calls List')
            <x-tabler-chevron-down class="ms-auto size-4" />
        </x-button>

        {{-- Calls list --}}
        <div class="grow overflow-y-auto max-sm:absolute max-sm:inset-x-0 max-sm:top-full max-sm:z-2 max-sm:hidden max-sm:h-[60vh] max-sm:overflow-y-auto max-sm:bg-background max-sm:shadow-xl max-sm:group-[&.mobile-dropdown-open]/history-sidebar:block">
            @include('phone-call-agent::home.call-history.calls-list')

            <div
                class="lqd-ext-phone-history-load-wrap mt-1 grid place-items-center py-3 font-medium text-heading-foreground"
                x-ref="loadMoreWrap"
            >
                <x-button
                    class="lqd-ext-phone-history-load-more col-start-1 col-end-1 row-start-1 row-end-1 w-full"
                    variant="link"
                    href="{{ route('dashboard.phone-call-agent.calls', ['page' => 1]) }}"
                    x-ref="loadMore"
                    x-show="!allLoaded && !fetching"
                >
                    {{ __('Load More...') }}
                </x-button>
                <span
                    class="col-start-1 col-end-1 row-start-1 row-end-1 inline-flex gap-2"
                    x-show="!allLoaded && fetching"
                >
                    {{ __('Loading') }}
                    <x-tabler-refresh class="size-4 animate-spin" />
                </span>
                <span
                    class="col-start-1 col-end-1 row-start-1 row-end-1 inline-flex gap-2"
                    x-ref="allLoaded"
                    x-show="allLoaded"
                >
                    {{ __('All Items Loaded') }}
                    <x-tabler-check class="size-4" />
                </span>
            </div>
        </div>
    </div>

    {{-- Content area --}}
    <div
        class="lqd-ext-phone-history-content-wrap flex h-full grow flex-col overflow-y-auto"
        x-ref="historyContentWrap"
    >
        {{-- Active call header --}}
        <div class="lqd-ext-phone-history-head sticky top-0 z-1 h-14 w-full shrink-0 border-b bg-background px-5 lg:h-[--header-height] lg:border-b-0 lg:bg-transparent">
            <x-progressive-blur
                class="-bottom-12 hidden h-auto lg:block"
                dir="reverse"
            />

            <div
                class="relative z-1 flex h-full items-center justify-between gap-4 transition"
                :class="{ 'opacity-0': transcriptSearchVisible }"
            >


                {{-- Desktop buttons --}}
                <div class="ms-auto hidden grow items-center justify-end gap-2 lg:flex">
                    <x-button
                        class="size-7 shrink-0 bg-foreground/5"
                        size="none"
                        variant="none"
                        title="{{ __('Search In Transcript') }}"
                        @click.prevent="transcriptSearchVisible = !transcriptSearchVisible"
                    >
                        <x-tabler-search class="size-4" />
                    </x-button>

                    <x-button
                        class="size-7 shrink-0 bg-foreground/5"
                        size="none"
                        variant="none"
                        title="{{ __('Pin The Call') }}"
                        @click.prevent="pinCall(activeCall)"
                    >
                        <x-tabler-pin
                            class="size-4"
                            x-show="!callsList.find(c => c.id == activeCall)?.pinned"
                        />
                        <x-tabler-pinned
                            class="size-4 fill-current"
                            x-cloak
                            x-show="callsList.find(c => c.id == activeCall)?.pinned"
                        />
                    </x-button>

                    <x-dropdown.dropdown
                        anchor="end"
                        triggerType="click"
                        offsetY="10px"
                    >
                        <x-slot:trigger
                            class="size-7 shrink-0 bg-foreground/5 p-0"
                            variant="none"
                            size="none"
                            title="{{ __('More Options') }}"
                        >
                            <x-tabler-dots-vertical class="size-4" />
                        </x-slot:trigger>

                        <x-slot:dropdown class="min-w-56 !rounded-2xl px-4 pb-1 pt-4 text-xs font-medium">
                            <p class="mb-0 border-b pb-3 text-3xs/none font-semibold uppercase tracking-wider text-foreground/60">
                                {{ __('Actions') }}
                            </p>
                            <ul>
                                <li class="border-b last:border-b-0">
                                    <x-button
                                        class="w-full justify-start py-3 text-start"
                                        href="#"
                                        variant="link"
                                        @click.prevent="deleteCall(activeCall)"
                                    >
                                        {{ __('Delete') }}
                                    </x-button>
                                </li>
                                <li class="border-b last:border-b-0">
                                    <button
                                        class="group m-0 flex w-full items-center justify-between gap-2 py-3 text-start text-xs font-medium"
                                        type="button"
                                        @click.prevent="showExportOptions = !showExportOptions"
                                        :class="{ 'active': showExportOptions }"
                                    >
                                        {{ __('Export Call') }}
                                        <x-tabler-chevron-down class="size-4 transition group-[&.active]:rotate-180" />
                                    </button>
                                    <div
                                        x-cloak
                                        x-show="showExportOptions"
                                        x-collapse
                                    >
                                        @foreach ([['format' => 'csv', 'label' => __('Download as CSV'), 'icon' => 'tabler-file-type-csv'], ['format' => 'json', 'label' => __('Download as JSON'), 'icon' => 'tabler-json'], ['format' => 'pdf', 'label' => __('Download as PDF'), 'icon' => 'tabler-file-type-pdf']] as $exportFormat)
                                            <a
                                                class="flex items-center gap-2 py-2 text-xs font-medium hover:underline"
                                                href="#"
                                                @click.prevent="exportActiveCall('{{ $exportFormat['format'] }}')"
                                            >
                                                <x-dynamic-component
                                                    class="size-4"
                                                    :component="$exportFormat['icon']"
                                                />
                                                {{ $exportFormat['label'] }}
                                            </a>
                                        @endforeach
                                    </div>
                                </li>
                            </ul>
                        </x-slot:dropdown>
                    </x-dropdown.dropdown>
                </div>

                {{-- Mobile buttons --}}
                <div class="ms-auto flex items-center gap-0.5 lg:hidden">
                    <x-button
                        class="size-8 [&.active]:bg-primary [&.active]:text-primary-foreground"
                        variant="none"
                        size="none"
                        ::class="{ 'active': mobile.contactInfoVisible }"
                        @click.prevent="mobile.filtersVisible = false; mobile.contactInfoVisible = !mobile.contactInfoVisible"
                        aria-label="{{ __('Details') }}"
                    >
                        <x-tabler-info-circle class="size-5" />
                    </x-button>

                    <x-button
                        class="size-8 [&.active]:bg-primary [&.active]:text-primary-foreground"
                        variant="none"
                        size="none"
                        ::class="{ 'active': mobile.filtersVisible }"
                        @click.prevent="mobile.contactInfoVisible = false; mobile.filtersVisible = !mobile.filtersVisible; mobileDropdownOpen = mobile.filtersVisible"
                        aria-label="{{ __('Filters') }}"
                    >
                        <x-tabler-dots
                            class="col-start-1 col-end-1 row-start-1 row-end-1 size-5"
                            x-show="!mobile.filtersVisible"
                        />
                        <x-tabler-x
                            class="col-start-1 col-end-1 row-start-1 row-end-1 size-5"
                            x-show="mobile.filtersVisible"
                            x-cloak
                        />
                    </x-button>
                </div>
            </div>

            {{-- Transcript search overlay --}}
            <form
                class="absolute start-0 top-0 z-2 h-full w-full"
                action="#"
                @submit.prevent
                x-cloak
                x-show="transcriptSearchVisible"
                @keyup.escape.window="transcriptSearchVisible = false; transcriptSearch = ''"
                x-trap="transcriptSearchVisible"
                x-transition
            >
                <input
                    class="lqd-input h-full w-full rounded-none border-none bg-transparent pe-6 ps-10 font-medium text-input-foreground outline-none focus:ring-0 sm:ps-12 lg:pe-12"
                    type="search"
                    placeholder="{{ __('Search in transcript...') }}"
                    x-model="transcriptSearch"
                    x-ref="transcriptSearchInput"
                />
                <x-tabler-search class="pointer-events-none absolute start-4 top-1/2 size-[18px] -translate-y-1/2 sm:start-5" />

                <x-button
                    class="absolute end-4 top-1/2 size-6 -translate-y-1/2 bg-foreground/15 text-foreground hover:-translate-y-1/2 hover:scale-105"
                    hover-variant="danger"
                    @click.prevent="transcriptSearchVisible = false; transcriptSearch = ''"
                    size="none"
                >
                    <x-tabler-x class="size-4" />
                </x-button>
            </form>
        </div>

        {{-- Transcript messages --}}
        <div
            class="lqd-ext-phone-history-messages relative mt-auto flex flex-col gap-2 py-10"
            x-ref="historyMessages"
        >
            <div class="space-y-2 px-4 max-lg:pb-[calc(var(--bottom-menu-height)+2rem)] xl:px-10">
                @include('phone-call-agent::home.call-history.call-transcripts')
            </div>
        </div>
    </div>

    {{-- Details panel --}}
    <div
        class="lqd-ext-phone-contact-info flex flex-col border-s transition-all max-lg:fixed max-lg:bottom-0 max-lg:top-[calc(var(--header-height)+3.5rem)] max-lg:z-10 max-lg:h-0 max-lg:max-h-[calc(100%-var(--header-height)-3.5rem)] max-lg:w-full max-lg:overflow-hidden max-lg:bg-background/90 max-lg:backdrop-blur-lg lg:w-[clamp(250px,27%,400px)] max-lg:[&.active]:h-full"
        :class="{ 'active': mobile.contactInfoVisible }"
    >
        @include('phone-call-agent::home.call-history.call-details-head')

        <div class="grid grow grid-cols-1 place-items-start overflow-y-auto">
            @include('phone-call-agent::home.call-history.call-details')
        </div>
    </div>
</div>

@push('css')
    <link
        rel="stylesheet"
        href="{{ custom_theme_url('/assets/libs/datepicker/air-datepicker.css') }}"
    />
    <link rel="stylesheet" href="{{ custom_theme_url('assets/libs/jscolorpicker/dist/colorpicker.css') }}">
@endpush

@push('script')
    <script src="{{ custom_theme_url('/assets/libs/datepicker/air-datepicker.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/datepicker/locale/en.js') }}"></script>
    <script src="{{ custom_theme_url('assets/libs/jscolorpicker/dist/colorpicker.iife.min.js') }}"></script>

    <script>
        const callTagRoutes = {
            index: '{{ route("dashboard.phone-call-agent.call-tags.index") }}',
            store: '{{ route("dashboard.phone-call-agent.call-tags.store") }}',
            sync: '{{ route("dashboard.phone-call-agent.call-tags.sync") }}',
        };

        const callExportRoute = '{{ route("dashboard.phone-call-agent.calls.export") }}';
        const callDestroyRoute = '{{ route("dashboard.phone-call-agent.calls.destroy", ":id") }}';
        const callPinRoute = '{{ route("dashboard.phone-call-agent.calls.pin") }}';

        (() => {
            document.addEventListener('alpine:init', () => {
                Alpine.data('phoneCallAgentHistory', () => ({
                    open: false,
                    callsList: [],
                    activeCall: null,
                    fetching: true,
                    currentPage: 1,
                    allLoaded: false,
                    loadMoreIO: null,
                    mobileDropdownOpen: false,
                    searchFormVisible: false,
                    transcriptSearchVisible: false,
                    transcriptSearch: '',
                    showExportOptions: false,
                    datepicker: null,
                    filters: {
                        sort: 'newest',
                        status: 'all',
                        agent: 'all',
                        provider: 'all',
                        search: '',
                        dateRange: { start: null, end: null },
                    },
                    contactInfo: { activeTab: 'details' },
                    mobile: { filtersVisible: false, contactInfoVisible: false },
                    callTagModal: {
                        show: false,
                        loading: false,
                        saving: false,
                        creating: false,
                        items: [],
                        selected: [],
                        form: { tag: '', tag_color: '#6366f1' },
                    },

                    init() {
                        Alpine.store('phoneCallAgentHistory', this);
                        this.initDateRangePicker();
                        if (@json($autoOpen ?? null) === 'history') {
                            this.setOpen(true);
                        }
                    },

                    setupLoadMoreIO() {
                        this.loadMoreIO = new IntersectionObserver(async ([entry]) => {
                            if (entry.isIntersecting && !this.fetching && !this.allLoaded) {
                                this.currentPage += 1;
                                this.$refs.loadMore.href = this.$refs.loadMore.href.replace(/page=\d+/, `page=${this.currentPage}`);
                                await this.fetchCalls();
                            }
                        });

                        this.loadMoreIO.observe(this.$refs.loadMoreWrap);
                    },

                    async setOpen(open) {
                        if (this.open === open) { return; }

                        const topNoticeBar = document.querySelector('.top-notice-bar');
                        const navbar = document.querySelector('.lqd-navbar');
                        const pageContentWrap = document.querySelector('.lqd-page-content-wrap');
                        const navbarExpander = document.querySelector('.lqd-navbar-expander');

                        this.open = open;

                        document.documentElement.style.overflow = this.open ? 'hidden' : '';

                        if (navbar) { navbar.style.position = this.open ? 'fixed' : ''; }
                        if (pageContentWrap && navbar?.offsetWidth > 0) {
                            pageContentWrap.style.paddingInlineStart = this.open ? 'var(--navbar-width)' : '';
                        }
                        if (topNoticeBar) { topNoticeBar.style.visibility = this.open ? 'hidden' : ''; }
                        if (navbarExpander) {
                            navbarExpander.style.visibility = this.open ? 'hidden' : '';
                            navbarExpander.style.opacity = this.open ? 0 : 1;
                        }

                        if (this.open && !this.callsList.length) {
                            await this.fetchCalls();
                            this.setupLoadMoreIO();
                        }
                    },

                    async fetchCalls() {
                        if (this.allLoaded) { return void (this.fetching = false); }

                        this.fetching = true;

                        let url = this.$refs.loadMore.href;
                        url += `&sort=${this.filters.sort}&status=${this.filters.status}&agent=${this.filters.agent}&provider=${this.filters.provider}`;

                        if (this.filters.search) {
                            url += `&search=${encodeURIComponent(this.filters.search)}`;
                        }

                        if (this.filters.dateRange.start && this.filters.dateRange.end) {
                            const fmt = (d) => {
                                if (d instanceof Date) {
                                    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
                                }

                                return d;
                            };
                            url += `&start_date=${fmt(this.filters.dateRange.start)}&end_date=${fmt(this.filters.dateRange.end)}`;
                        }

                        const res = await fetch(url, {
                            method: 'GET',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        });
                        const data = await res.json();
                        const { data: calls } = data;

                        if (!res.ok || !calls) {
                            if (data.message) { toastr.error(data.message); }
                            this.fetching = false;
                            return;
                        }

                        this.callsList.push(...calls);

                        if (!this.activeCall && calls.length) {
                            this.activeCall = calls[0].id;
                        }

                        if (this.currentPage >= (data.meta?.last_page ?? 1)) {
                            this.allLoaded = true;
                        }

                        this.fetching = false;
                        this.scrollMessagesToBottom();
                    },

                    async resetAndFetch() {
                        this.callsList = [];
                        this.activeCall = null;
                        this.currentPage = 1;
                        this.allLoaded = false;
                        this.$refs.loadMore.href = this.$refs.loadMore.href.replace(/page=\d+/, 'page=1');
                        await this.fetchCalls();
                    },

                    async filterSort(sort) {
                        if (this.filters.sort === sort) { return; }
                        this.filters.sort = sort;
                        await this.resetAndFetch();
                    },

                    async filterAgent(agent) {
                        if (this.filters.agent === agent) { return; }
                        this.filters.agent = agent;
                        await this.resetAndFetch();
                    },

                    async pinCall(callId) {
                        if (!callId) { return; }

                        const res = await fetch(callPinRoute, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                            },
                            body: JSON.stringify({ call_id: callId }),
                        });
                        const data = await res.json();

                        if (!res.ok) {
                            toastr.error(data.message ?? '{{ __('Failed to pin call.') }}');
                            return;
                        }

                        const idx = this.callsList.findIndex(c => c.id == callId);
                        if (idx !== -1) { this.callsList[idx].pinned = data.pinned; }

                        this.callsList.sort((a, b) => {
                            if (b.pinned !== a.pinned) { return (b.pinned ?? 0) - (a.pinned ?? 0); }
                            const dateA = new Date(a.started_at ?? a.created_at);
                            const dateB = new Date(b.started_at ?? b.created_at);
                            return this.filters.sort === 'oldest' ? dateA - dateB : dateB - dateA;
                        });

                        toastr.success(data.message);
                    },

                    async deleteCall(callId) {
                        if (!callId || !confirm('{{ __('Delete this call? This cannot be undone.') }}')) { return; }

                        const url = callDestroyRoute.replace(':id', callId);
                        const res = await fetch(url, {
                            method: 'DELETE',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                            },
                        });
                        const data = await res.json();

                        if (!res.ok) {
                            toastr.error(data.message ?? '{{ __('Failed to delete call.') }}');
                            return;
                        }

                        this.callsList = this.callsList.filter(c => c.id !== callId);
                        this.activeCall = this.callsList[0]?.id ?? null;
                        toastr.success(data.message ?? '{{ __('Call deleted.') }}');
                    },

                    exportActiveCall(format) {
                        if (!this.activeCall) { return; }
                        const params = new URLSearchParams({ format, call_id: this.activeCall });
                        window.location.href = `${callExportRoute}?${params.toString()}`;
                    },

                    exportCallHistory(format) {
                        const params = new URLSearchParams({ format });

                        if (this.filters.sort) { params.set('sort', this.filters.sort); }
                        if (this.filters.status && this.filters.status !== 'all') { params.set('status', this.filters.status); }
                        if (this.filters.agent && this.filters.agent !== 'all') { params.set('agent', this.filters.agent); }
                        if (this.filters.provider && this.filters.provider !== 'all') { params.set('provider', this.filters.provider); }
                        if (this.filters.search) { params.set('search', this.filters.search); }

                        if (this.filters.dateRange.start && this.filters.dateRange.end) {
                            const fmt = (d) => {
                                if (d instanceof Date) {
                                    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
                                }

                                return d;
                            };
                            params.set('start_date', fmt(this.filters.dateRange.start));
                            params.set('end_date', fmt(this.filters.dateRange.end));
                        }

                        window.location.href = `${callExportRoute}?${params.toString()}`;
                    },

                    async handleSearch() {
                        await this.resetAndFetch();
                    },

                    async applyDateRange() {
                        if (!this.filters.dateRange.start || !this.filters.dateRange.end) {
                            toastr.warning('{{ __('Please select a date range') }}');
                            return;
                        }
                        await this.resetAndFetch();
                        toastr.success('{{ __('Date filter applied') }}');
                    },

                    async clearDateRange() {
                        this.filters.dateRange.start = null;
                        this.filters.dateRange.end = null;
                        if (this.datepicker) { this.datepicker.clear(); }
                        await this.resetAndFetch();
                        toastr.success('{{ __('Date filter cleared') }}');
                    },

                    initDateRangePicker() {
                        this.$nextTick(() => {
                            if (!document.getElementById('callHistoryDatepicker')) { return; }

                            this.datepicker = new AirDatepicker('#callHistoryDatepicker', {
                                locale: defaultLocale,
                                inline: true,
                                range: true,
                                multipleDatesSeparator: ' - ',
                                dateFormat: 'yyyy-MM-dd',
                                onSelect: ({ date }) => {
                                    if (Array.isArray(date) && date.length === 2) {
                                        this.filters.dateRange.start = date[0];
                                        this.filters.dateRange.end = date[1];
                                    }
                                },
                            });
                        });
                    },

                    async setActiveCall(callId) {
                        if (!callId || this.activeCall === callId) { return; }
                        this.activeCall = callId;
                        this.mobileDropdownOpen = false;
                        this.callTagModal.items = [];
                        this.callTagModal.show = false;
                        this.syncCallTagSelection();
                        this.scrollMessagesToBottom();
                    },

                    scrollMessagesToBottom() {
                        this.$nextTick(() => {
                            this.$refs.historyContentWrap?.scrollTo({
                                top: this.$refs.historyContentWrap.scrollHeight,
                                behavior: 'smooth',
                            });
                        });
                    },

                    formatDuration(seconds) {
                        const m = Math.floor(seconds / 60).toString().padStart(2, '0');
                        const s = (seconds % 60).toString().padStart(2, '0');
                        return `${m}:${s}`;
                    },

                    getDiffHumanTime(time) {
                        const diff = Math.floor((new Date() - new Date(time)) / 1000);

                        return diff < 60 ? '{{ __('Just now') }}' :
                            diff < 3600 ? (Math.floor(diff / 60) === 1 ?
                                '1 {{ __('minute ago') }}' : Math.floor(diff / 60) + ' {{ __('minutes ago') }}') :
                            diff < 86400 ? (Math.floor(diff / 3600) === 1 ?
                                '1 {{ __('hour ago') }}' : Math.floor(diff / 3600) + ' {{ __('hours ago') }}') :
                            Math.floor(diff / 86400) === 1 ? '1 {{ __('day ago') }}' : Math.floor(diff / 86400) + ' {{ __('days ago') }}';
                    },

                    getShortDiffHumanTime(diff) {
                        return diff < 60 ? '{{ __('Just now') }}' :
                            diff < 3600 ? Math.floor(diff / 60) + '{{ __('m') }}' :
                            diff < 86400 ? Math.floor(diff / 3600) + '{{ __('h') }}' :
                            Math.floor(diff / 86400) + '{{ __('d') }}';
                    },

                    getStatusClass(status) {
                        return {
                            'completed': 'bg-green-500/15 text-green-600',
                            'answered':  'bg-blue-500/15 text-blue-600',
                            'ringing':   'bg-yellow-500/15 text-yellow-600',
                            'missed':    'bg-orange-500/15 text-orange-600',
                            'failed':    'bg-red-500/15 text-red-600',
                        }[status] || 'bg-heading-foreground/10 text-heading-foreground';
                    },

                    getCallerColor(str) {
                        if (!str) { return 'hsl(250, 55%, 52%)'; }

                        let hash = 0;
                        for (let i = 0; i < str.length; i++) {
                            hash = str.charCodeAt(i) + ((hash << 5) - hash);
                        }

                        return `hsl(${Math.abs(hash) % 360}, 55%, 48%)`;
                    },

                    getCallerInitial(callerNumber) {
                        if (!callerNumber) { return '?'; }
                        return callerNumber.replace(/\D/g, '').charAt(0) || callerNumber.charAt(0).toUpperCase();
                    },

                    syncCallTagSelection() {
                        const call = this.callsList.find(c => c.id == this.activeCall);
                        if (!call || !Array.isArray(call.tags)) {
                            this.callTagModal.selected = [];
                            return;
                        }
                        this.callTagModal.selected = call.tags.map(t => t.id);
                    },

                    async toggleCallTagModal() {
                        this.callTagModal.show = !this.callTagModal.show;
                        if (this.callTagModal.show && !this.callTagModal.items.length) {
                            this.$nextTick(() => this.loadCallTags());
                        }
                    },

                    async loadCallTags() {
                        if (!this.activeCall) { return; }
                        this.callTagModal.loading = true;
                        try {
                            const res = await fetch(`${callTagRoutes.index}?call_id=${this.activeCall}`, {
                                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                            });
                            const data = await res.json();
                            if (!res.ok) { if (data.message) toastr.error(data.message); return; }
                            this.callTagModal.items = data.tags ?? [];
                            this.callTagModal.selected = data.assigned ?? [];
                        } finally {
                            this.callTagModal.loading = false;
                        }
                    },

                    async toggleCallTagSelection(tagId) {
                        if (!this.activeCall || this.callTagModal.saving) { return; }
                        const wasSelected = this.callTagModal.selected.includes(tagId);
                        const previous = [...this.callTagModal.selected];
                        if (wasSelected) {
                            this.callTagModal.selected = this.callTagModal.selected.filter(id => id !== tagId);
                        } else {
                            this.callTagModal.selected = [...this.callTagModal.selected, tagId];
                        }
                        const success = await this.syncCallTags();
                        if (!success) { this.callTagModal.selected = previous; }
                    },

                    async syncCallTags() {
                        if (!this.activeCall) { return false; }
                        this.callTagModal.saving = true;
                        try {
                            const res = await fetch(callTagRoutes.sync, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                                },
                                body: JSON.stringify({ call_id: this.activeCall, tag_ids: this.callTagModal.selected }),
                            });
                            const data = await res.json();
                            if (!res.ok) { if (data.message) toastr.error(data.message); return false; }
                            const idx = this.callsList.findIndex(c => c.id == this.activeCall);
                            if (idx !== -1) { this.callsList[idx].tags = data.data?.call_tags ?? []; }
                            this.syncCallTagSelection();
                            toastr.success(data.message ?? '{{ __('Tags updated.') }}');
                            return true;
                        } catch (e) {
                            toastr.error('{{ __('Unable to update tags.') }}');
                            return false;
                        } finally {
                            this.callTagModal.saving = false;
                        }
                    },

                    async createCallTag() {
                        const tagName = (this.callTagModal.form.tag || '').trim();
                        if (!tagName) { toastr.error('{{ __('Please enter a tag name.') }}'); return; }
                        this.callTagModal.creating = true;
                        try {
                            const res = await fetch(callTagRoutes.store, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                                },
                                body: JSON.stringify({ tag: tagName, tag_color: this.callTagModal.form.tag_color }),
                            });
                            const data = await res.json();
                            if (!res.ok) { if (data.message) toastr.error(data.message); return; }
                            toastr.success(data.message ?? '{{ __('Tag created.') }}');
                            this.callTagModal.form.tag = '';
                            await this.loadCallTags();
                        } finally {
                            this.callTagModal.creating = false;
                        }
                    },
                }));
            });
        })();
    </script>
@endpush
