@extends('panel.layout.app', [
    'disable_tblr' => true,
    'disable_header' => true,
    'disable_footer' => true,
    'disable_titlebar' => true,
    'disable_navbar' => true,
    'layout_wide' => true,
    'disable_mobile_bottom_menu' => true,
])
@section('title', $workflow ? __('Edit Agent') : __('New Agent'))

@push('css')
    <style>
        .focus-mode .lqd-page-content-container {
            max-width: 100% !important;
        }

        .step-node-active {
            ring-width: 2px;
        }
    </style>
@endpush

@section('content')
    <script>
        window.__workflowData = {
            workflow: @json($workflow),
            channels: @json($channels),
            connectors: @json($connectors ?? []),
            memories: @json($memories),
            avatarPresets: @json($avatarPresets ?? []),
            copilotMessages: @json($copilotMessages ?? []),
            templateData: @json($templateData ?? null),
            templateAvatar: @json($templateAvatar ?? null),
            copilotInit: @json($copilotInit ?? null),
            sendUrl: @json(route('dashboard.user.ai-agent.messages.send')),
            threadBaseUrl: @json(route('dashboard.user.ai-agent.messages.index')),
            conversationsUrl: @json(route('dashboard.user.ai-agent.messages.conversations')),
            channelStatusBaseUrl: @json(route('dashboard.user.ai-agent.channels.index')),
        };
    </script>
    <div
        class="relative flex h-screen flex-col overflow-hidden [--header-h:58px] [--sidebar-w:415px] [interpolate-size:allow-keywords]"
        x-data="workflowBuilder(window.__workflowData.workflow, window.__workflowData.channels, window.__workflowData.memories, window.__workflowData.avatarPresets, window.__workflowData.copilotMessages, window.__workflowData.templateData, window.__workflowData.connectors)"
    >
        {{-- Loading spinner --}}
        <div
            class="fixed inset-0 z-[100] flex flex-col items-center justify-center gap-2 bg-background text-center text-base font-medium transition-all duration-300 [&.fade-out]:pointer-events-none [&.fade-out]:invisible [&.fade-out]:opacity-0"
            x-init="$el.classList.add('fade-out')"
        >
            <x-tabler-loader-2 class="size-8 animate-spin" />
            {{ __('Loading...') }}
        </div>

        {{-- Top Header Bar --}}
        <div class="relative z-50 flex h-[--header-h] justify-between gap-2 border-b bg-background px-3 py-2 max-lg:items-center max-lg:gap-2 lg:gap-4 lg:px-6">
            <div class="flex w-1/3 min-w-0 items-center gap-2 sm:gap-3">
                <x-button
                    class="inline-grid size-[30px] shrink-0 place-items-center"
                    variant="outline"
                    hover-variant="primary"
                    size="none"
                    href="{{ route('dashboard.user.ai-agent.workflows.index') }}"
                >
                    <x-tabler-chevron-left class="size-4 rtl:rotate-180" />
                </x-button>

                <x-header-logo />
            </div>

            <div
                class="lqd-ai-agent-mobile-nav-wrap contents transition-all max-lg:absolute max-lg:inset-x-0 max-lg:top-full max-lg:z-50 max-lg:flex max-lg:h-0 max-lg:max-h-[100vh-var(--header-h)-2rem] max-lg:flex-col max-lg:justify-end max-lg:gap-2 max-lg:overflow-y-auto max-lg:border-b max-lg:bg-background max-lg:px-4 max-lg:shadow-2xl max-lg:shadow-black/5"
                :class="{ 'max-lg:h-auto': mobile.menuOpen, 'max-lg:h-0': !mobile.menuOpen }"
                @click.outside="const target = $event.target; if (!target.closest('.lqd-ai-agent-mobile-nav-trigger') && !target.closest('.lqd-ai-agent-mobile-nav-wrap')) { mobile.menuOpen = false; }"
            >
                <div class="flex items-center gap-1 max-lg:pt-5 lg:w-1/3 lg:justify-center">
                    <x-forms.input
                        class="border-none bg-transparent p-0 text-2xs font-medium !outline-none !ring-0"
                        type="text"
                        x-model="workflowName"
                        placeholder="{{ __('Untitled Agent') }}"
                    />
                    <span
                        class="shrink-0 whitespace-nowrap rounded-full border p-2 text-2xs font-medium leading-none"
                        :class="{
                            'text-green-600': status === 'active',
                            'text-foreground/50': status === 'paused',
                            'text-foreground/40': status === 'archived',
                        }"
                        x-text="status.charAt(0).toUpperCase() + status.slice(1)"
                    ></span>
                </div>

                <div class="max-lg:flex max-lg:flex-col max-lg:gap-2 max-lg:pb-5 lg:flex lg:w-1/3 lg:shrink-0 lg:items-center lg:justify-end lg:gap-2">
                    <div class="order-first contents max-lg:flex max-lg:items-center max-lg:gap-2">
                        <x-button
                            class="max-lg:!transform-none max-lg:justify-start max-lg:px-0 max-lg:text-start max-lg:!shadow-none max-lg:outline-none"
                            class="shrink-0"
                            variant="link"
                            size="none"
                            @click.prevent="undo()"
                            ::disabled="!canUndo"
                            ::class="{ 'opacity-40 pointer-events-none': !canUndo }"
                            title="{{ __('Undo') }}"
                        >
                            <span class="hidden max-lg:inline">
                                {{ __('Undo') }}
                            </span>
                            <span class="inline-grid size-[30px] place-items-center rounded-button border border-button-border">
                                <x-tabler-arrow-back-up class="size-4" />
                            </span>
                        </x-button>

                        <x-button
                            class="max-lg:!transform-none max-lg:justify-start max-lg:px-0 max-lg:text-start max-lg:!shadow-none max-lg:outline-none"
                            class="shrink-0"
                            variant="link"
                            size="none"
                            @click.prevent="redo()"
                            ::disabled="!canRedo"
                            ::class="{ 'opacity-40 pointer-events-none': !canRedo }"
                            title="{{ __('Redo') }}"
                        >
                            <span class="hidden max-lg:inline">
                                {{ __('Redo') }}
                            </span>
                            <span class="inline-grid size-[30px] place-items-center rounded-button border border-button-border">
                                <x-tabler-arrow-forward-up class="size-4" />
                            </span>
                        </x-button>
                    </div>

                    <x-button
                        class="hidden max-lg:flex max-lg:!transform-none max-lg:justify-start max-lg:px-0 max-lg:text-start max-lg:!shadow-none max-lg:outline-none"
                        variant="outline"
                        @click.prevent="copilotOpen = !copilotOpen; mobile.menuOpen = false; closeTestPanel(); closeStepSettings()"
                    >
                        {{ __('Copilot Chat') }}
                    </x-button>

                    @include('ai-agent::includes.workflow-details-modal')

                    <template x-if="workflowId">
                        <x-button
                            class="max-lg:!transform-none max-lg:justify-start max-lg:px-0 max-lg:text-start max-lg:!shadow-none max-lg:outline-none"
                            variant="outline"
                            @click.prevent="testPanelOpen ? closeTestPanel() : openTestPanel(); mobile.menuOpen = false; closeStepSettings(); copilotOpen = false;"
                        >
                            <x-tabler-player-play class="size-4" />
                            {{ __('Test') }}
                        </x-button>
                    </template>

                    <x-button
                        class="max-lg:!transform-none max-lg:justify-start max-lg:px-0 max-lg:text-start max-lg:!shadow-none max-lg:outline-none"
                        variant="outline"
                        @click.prevent="toggleStatus()"
                        ::disabled="!canActivate || saving"
                    >
                        <span x-text="status === 'active' ? '{{ __('Pause') }}' : '{{ __('Activate') }}'">
                            {{ __('Pause') }}
                        </span>
                    </x-button>

                    {{-- Desktop save button --}}
                    <x-button
                        class="max-lg:hidden"
                        variant="outline"
                        @click.prevent="saveWorkflow()"
                        ::disabled="saving"
                    >
                        <span x-show="!saving">
                            {{ __('Save') }}
                        </span>
                        <span
                            x-show="saving"
                            x-cloak
                        >
                            {{ __('Saving') }}
                        </span>
                        <x-tabler-loader-2
                            class="size-4 animate-spin"
                            x-cloak
                            x-show="saving"
                        />
                    </x-button>
                </div>
            </div>

            {{-- Mobile save button --}}
            <x-button
                class="hidden max-lg:ms-auto max-lg:flex"
                variant="outline"
                @click.prevent="saveWorkflow()"
                ::disabled="saving"
            >
                <span x-show="!saving">
                    {{ __('Save') }}
                </span>
                <span
                    x-show="saving"
                    x-cloak
                >
                    {{ __('Saving') }}
                </span>
                <x-tabler-loader-2
                    class="size-4 animate-spin"
                    x-cloak
                    x-show="saving"
                />
            </x-button>

            <x-button
                class="lqd-ai-agent-mobile-nav-trigger hidden max-lg:flex"
                variant="link"
                @click.prevent="mobile.menuOpen = !mobile.menuOpen"
            >
                {{ __('More') }}
                <span class="inline-grid place-items-center">
                    <x-tabler-dots
                        class="size-4"
                        x-show="!mobile.menuOpen"
                    />
                    <x-tabler-x
                        class="size-4"
                        x-cloak
                        x-show="mobile.menuOpen"
                    />
                </span>
            </x-button>
        </div>

        {{-- Center: Canvas --}}
        <div class="flex h-[calc(100%-var(--header-h))] min-w-0 overflow-hidden">
            {{-- Left: Copilot Panel --}}
            @include('ai-agent::workflows.builder.partials.copilot-panel')

            {{-- Canvas --}}
            @include('ai-agent::workflows.builder.partials.workflow-canvas')

            {{-- Right: Step Settings Panel --}}
            @include('ai-agent::workflows.builder.partials.step-settings-panel')

            {{-- Right: Test Panel --}}
            @include('ai-agent::workflows.builder.partials.test-panel')
        </div>

        {{-- Tool Category Modal --}}
        @include('ai-agent::includes.add-step-modal')

        {{-- Connect Channel Modal --}}
        @include('ai-agent::includes.channels-modal')
    </div>
@endsection

@push('style')
    <style>
        .vti-editor-empty::before {
            content: attr(data-placeholder);
            color: hsl(var(--foreground) / 0.3);
            pointer-events: none;
            display: block;
            position: absolute;
            top: 0;
            inset-inline-start: 0;
            padding: 0.5rem 2rem 0.5rem 0.75rem;
            font-size: 0.875rem;
            line-height: 1.5rem;
        }

        .vti-editor-wrap {
            position: relative;
        }
    </style>
@endpush

@push('script')
    <script src="{{ custom_theme_url('/assets/libs/markdownit/markdown-it.min.js') }}"></script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('workflowBuilder', (existingWorkflow, channels, memories, avatarPresets, initialCopilotMessages, templateData, connectors) => ({
                workflowId: existingWorkflow?.id ?? null,
                workflowName: existingWorkflow?.name ?? '',
                workflowDescription: existingWorkflow?.description ?? '',
                workflowRole: existingWorkflow?.role ?? '',
                workflowAvatar: existingWorkflow?.avatar ?? null,
                workflowAvatarUrl: existingWorkflow?.avatar_url ?? (avatarPresets?.[0]?.url ?? null),
                avatarUploading: false,
                status: existingWorkflow?.status ?? 'active',
                saving: false,
                examplePrompt: @json(__("Every morning at 9AM, generate a report of yesterday's activity and send it to my Telegram channel.")),

                channels: channels ?? [],
                connectors: connectors ?? [],
                memories: memories ?? [],
                avatarPresets: avatarPresets ?? [],

                mobile: {
                    menuOpen: false
                },

                // Channel modal state
                channelModal: {
                    tab: 'create',
                    name: '',
                    type: 'telegram',
                    credentials: {},
                    loading: false,
                    errors: {},
                    namePlaceholders: {
                        telegram: '{{ __('e.g. My Telegram Bot') }}',
                        whatsapp: '{{ __('e.g. My WhatsApp Bot') }}',
                        slack: '{{ __('e.g. My Slack App') }}',
                    }
                },

                // Connectors modal state
                connectorsModal: {
                    mobile: {
                        categoryDropdownOpen: false
                    },
                    modalEl: null,
                    category: 'all',
                    search: '',
                    tab: 'list',
                    loading: false,
                    availableConnectors: []
                },

                connectorTypeCategory: {
                    gmail: 'mail',
                    outlook: 'mail'
                },

                // Memory form state
                memoryForm: {
                    open: false,
                    text: '',
                    loading: false
                },

                triggerConfigured: !!existingWorkflow,
                triggerType: existingWorkflow?.trigger_type ?? 'schedule',
                triggerConfig: {
                    cron: existingWorkflow?.trigger_config?.cron ?? '',
                    channel_id: existingWorkflow?.trigger_config?.channel_id ?? null,
                    keyword_filter: existingWorkflow?.trigger_config?.keyword_filter ?? {
                        enabled: false,
                        ruleGroups: [{
                            rules: [{
                                field: '{message_text}',
                                operator: 'contains',
                                value: ''
                            }]
                        }],
                    },
                },

                // Cron builder UI state
                cronPresetKey: null,
                cronCustomOpen: false,
                cronCustomMinute: '0',
                cronCustomHour: '9',
                cronCustomDayOfWeek: '*',
                cronCustomDayOfMonth: '*',

                // New steps array
                steps: [],

                // Step settings panel
                selectedStepId: null,
                settingsPanelOpen: false,
                settingsPanelTab: 'configure',
                availableModelGroups: [],
                knowledgeSources: [],
                knowledgeSourcesLoaded: false,
                knowledgeForm: {
                    open: false,
                    type: 'file',
                    name: '',
                    content: '',
                    file: null,
                    loading: false,
                    dragging: false
                },
                socialMediaAgents: [],
                socialMediaAgentsLoaded: false,
                socialMediaPlatforms: [],
                socialMediaPlatformsLoaded: false,
                crmContacts: [],
                crmDeals: [],
                crmCompanies: [],
                crmStages: [],
                crmRecordsLoading: false,
                crmRecordsLoaded: false,
                gmailMessages: [],
                gmailMessagesLoading: false,
                gmailMessagesLoaded: false,
                gmailThreads: [],
                gmailThreadsLoading: false,
                gmailThreadsLoaded: false,

                activeVariableMenu: null,

                // Add step modal
                addStepModal: {
                    order: 0,
                    search: '',
                    category: 'all',
                    actions: [],
                    failedActions: [],
                    loading: false,
                    mode: 'top-level',
                    parentStepId: null,
                    pathIndex: null,
                    mobile: {
                        categoryDropdownOpen: false
                    }
                },

                favoriteSteps: Alpine.$persist([]).as('ai-agent-favorite-steps'),

                isStepFavorite(key) {
                    return this.favoriteSteps.includes(key);
                },

                toggleStepFavorite(key) {
                    if (this.isStepFavorite(key)) {
                        this.favoriteSteps = this.favoriteSteps.filter(k => k !== key);
                    } else {
                        this.favoriteSteps = [...this.favoriteSteps, key];
                    }
                },

                // Copilot
                copilotOpen: window.innerWidth >= 992,
                copilotMessages: (initialCopilotMessages ?? []).map(m => {
                    if (m.role !== 'assistant' || m.steps) return m;
                    // Convert DB-loaded assistant messages to steps format
                    const steps = [];
                    const thinking = m.metadata?.thinking ?? null;
                    if (thinking && (thinking.duration_ms || thinking.content)) {
                        steps.push({
                            type: 'thinking',
                            status: 'done',
                            content: thinking.content ?? '',
                            durationMs: thinking.duration_ms ?? null,
                            expanded: false
                        });
                    }
                    if (m.content) steps.push({
                        type: 'text',
                        content: m.content
                    });
                    if (m.toolCalls && m.toolCalls.length > 0) {
                        m.toolCalls.forEach(tc => steps.push({
                            type: 'tool_call',
                            name: tc.name,
                            id: tc.id || String(Math.random()),
                            args: tc.args ?? {},
                            status: 'success',
                            expanded: false
                        }));
                    }
                    return {
                        ...m,
                        steps
                    };
                }),
                copilotInput: '',
                copilotLoading: false,
                copilotMutating: false,

                testPanelOpen: false,
                testMessages: [],
                testSending: false,
                testLoading: false,
                testConversationId: null,
                testChannelStatus: null,
                sendUrl: window.__workflowData.sendUrl ?? '',
                threadBaseUrl: window.__workflowData.threadBaseUrl ?? '',
                conversationsUrl: window.__workflowData.conversationsUrl ?? '',
                channelStatusBaseUrl: window.__workflowData.channelStatusBaseUrl ?? '',

                zoom: 100,

                _history: [],
                _historyIdx: -1,
                _historyMax: 50,
                _inUndoRedo: false,
                _panelSnapshot: null,

                get canActivate() {
                    return this.triggerConfigured && this.steps.length > 0;
                },

                get canUndo() {
                    return this._historyIdx > 0;
                },
                get canRedo() {
                    return this._historyIdx < this._history.length - 1;
                },

                get triggerTypeLabel() {
                    const labels = {
                        schedule: '{{ trans('Schedule') }}',
                        channel_message: '{{ trans('Channel Message') }}',
                        webhook: '{{ trans('Webhook') }}',
                    };
                    return labels[this.triggerType] ?? this.triggerType;
                },

                get testChannelType() {
                    if (this.triggerType !== 'channel_message') return null;
                    const ch = this.channels.find(c => c.id == this.triggerConfig.channel_id);
                    return ch?.type ?? 'web';
                },

                get testChannelName() {
                    if (this.triggerType !== 'channel_message') return '';
                    const ch = this.channels.find(c => c.id == this.triggerConfig.channel_id);
                    return ch?.name ?? '';
                },

                get triggerSummary() {
                    if (this.triggerType === 'schedule') {
                        return this.cronHumanPreview || this.triggerConfig.cron || '{{ trans('No schedule set') }}';
                    }
                    if (this.triggerType === 'channel_message') {
                        const ch = this.channels.find(c => c.id == this.triggerConfig.channel_id);
                        return ch ? ch.name : '{{ trans('Any channel') }}';
                    }
                    if (this.triggerType === 'webhook') return '{{ trans('Waiting for webhook') }}';
                    return '';
                },

                get cronPresets() {
                    return [{
                            key: 'weekdays_9am',
                            expr: '0 9 * * 1-5',
                            title: '{{ trans('Weekdays, 9 AM') }}',
                            sub: '{{ trans('Mon–Fri at 9:00 AM UTC') }}'
                        },
                        {
                            key: 'daily_9am',
                            expr: '0 9 * * *',
                            title: '{{ trans('Every Day, 9 AM') }}',
                            sub: '{{ trans('Daily at 9:00 AM UTC') }}'
                        },
                        {
                            key: 'monday_9am',
                            expr: '0 9 * * 1',
                            title: '{{ trans('Mondays, 9 AM') }}',
                            sub: '{{ trans('Every Monday at 9:00 AM UTC') }}'
                        },
                        {
                            key: 'monthly_1st',
                            expr: '0 0 1 * *',
                            title: '{{ trans('1st of Month') }}',
                            sub: '{{ trans('Monthly on the 1st at midnight UTC') }}'
                        },
                        {
                            key: 'hourly',
                            expr: '0 * * * *',
                            title: '{{ trans('Every Hour') }}',
                            sub: '{{ trans('At the top of every hour') }}'
                        },
                        {
                            key: 'every_30min',
                            expr: '*/30 * * * *',
                            title: '{{ trans('Every 30 Min') }}',
                            sub: '{{ trans('Every 30 minutes') }}'
                        },
                    ];
                },

                get cronHumanPreview() {
                    const cron = (this.triggerConfig.cron || '').trim();
                    if (!cron) return '';
                    const preset = this.cronPresets.find(p => p.expr === cron);
                    if (preset) return preset.sub;
                    const parts = cron.split(/\s+/);
                    if (parts.length !== 5) return cron;
                    const [min, hr, dom, mon, dow] = parts;
                    const hourLabels = ['12 AM', '1 AM', '2 AM', '3 AM', '4 AM', '5 AM', '6 AM', '7 AM', '8 AM', '9 AM', '10 AM', '11 AM', '12 PM', '1 PM',
                        '2 PM', '3 PM', '4 PM', '5 PM', '6 PM', '7 PM', '8 PM', '9 PM', '10 PM', '11 PM'
                    ];
                    const dowLabels = {
                        '0': 'Sunday',
                        '1': 'Monday',
                        '2': 'Tuesday',
                        '3': 'Wednesday',
                        '4': 'Thursday',
                        '5': 'Friday',
                        '6': 'Saturday',
                        '0,6': 'weekends',
                        '1-5': 'weekdays'
                    };
                    const isEveryMin = min.startsWith('*/');
                    const isEveryHr = hr === '*' && !isEveryMin;
                    const specificHr = !isNaN(parseInt(hr)) ? hourLabels[parseInt(hr)] : null;
                    const specificMin = !isNaN(parseInt(min)) ? min.padStart(2, '0') : '00';
                    let when = '';
                    if (isEveryMin) {
                        const n = min.slice(2);
                        when = n === '1' ? 'every minute' : `every ${n} minutes`;
                    } else if (isEveryHr) {
                        when = specificMin !== '00' ? `every hour at :${specificMin}` : 'every hour';
                    } else if (specificHr) {
                        when = `at ${specificHr.replace(' ', ':').replace('AM',':00 AM').replace('PM',':00 PM')}`.replace('::', ':').replace(':00:00', ':00');
                        when = `at ${specificHr}${specificMin !== '00' ? ':' + specificMin : ''}`;
                    }
                    let freq = '';
                    if (dom !== '*' && !isNaN(parseInt(dom))) {
                        freq = `on the ${dom}${['st','nd','rd'][parseInt(dom)-1]||'th'} of every month`;
                    } else if (dow !== '*' && dowLabels[dow]) {
                        freq = `every ${dowLabels[dow]}`;
                    } else {
                        freq = 'every day';
                    }
                    return when ? `Runs ${freq} ${when} UTC` : `Runs ${freq} UTC`;
                },

                selectCronPreset(preset) {
                    this.triggerConfig.cron = preset.expr;
                    this.cronPresetKey = preset.key;
                    this.cronCustomOpen = false;
                    this._pushHistory();
                },

                updateCronFromCustom() {
                    this.triggerConfig.cron = `${this.cronCustomMinute} ${this.cronCustomHour} ${this.cronCustomDayOfMonth} * ${this.cronCustomDayOfWeek}`;
                    this.cronPresetKey = null;
                    this._pushHistory();
                },

                openCronCustom() {
                    this.cronCustomOpen = !this.cronCustomOpen;
                    if (this.cronCustomOpen) {
                        this.cronPresetKey = null;
                    }
                },

                _initCronUiState() {
                    const cron = this.triggerConfig.cron;
                    if (!cron) return;
                    const preset = this.cronPresets.find(p => p.expr === cron);
                    if (preset) {
                        this.cronPresetKey = preset.key;
                        return;
                    }
                    const parts = cron.split(/\s+/);
                    if (parts.length === 5) {
                        this.cronCustomMinute = parts[0];
                        this.cronCustomHour = parts[1];
                        this.cronCustomDayOfMonth = parts[2];
                        this.cronCustomDayOfWeek = parts[4];
                        this.cronCustomOpen = true;
                    }
                },

                get selectedStep() {
                    if (!this.selectedStepId || this.selectedStepId === 'trigger') return null;
                    if (this.selectedStepId.startsWith('substep::')) {
                        const parts = this.selectedStepId.split('::');
                        const parentId = parts[1];
                        const pathIdx = parseInt(parts[2], 10);
                        const subId = parts[3];
                        const parent = this.steps.find(s => s.id === parentId);
                        return parent?.config?.paths?.[pathIdx]?.steps?.find(s => s.id === subId) ?? null;
                    }
                    return this.steps.find(s => s.id === this.selectedStepId) ?? null;
                },

                get filteredModalActions() {
                    let list = [];
                    const actions = this.addStepModal.actions;
                    if (this.addStepModal.category === 'all') {
                        Object.values(actions).forEach(cat => {
                            list = list.concat(cat);
                        });
                    } else if (this.addStepModal.category === 'favorite') {
                        Object.values(actions).forEach(cat => {
                            list = list.concat(cat);
                        });
                        list = list.filter(a => this.favoriteSteps.includes(a.key));
                    } else {
                        list = actions[this.addStepModal.category] ?? [];
                    }
                    if (this.addStepModal.mode === 'substep') {
                        list = list.filter(a => a.key !== 'path');
                    }
                    const q = this.addStepModal.search.trim().toLowerCase();
                    if (!q) return list;
                    return list.filter(a =>
                        (a.label || '').toLowerCase().includes(q) ||
                        (a.description || '').toLowerCase().includes(q)
                    );
                },

                get filteredAvailableConnectors() {
                    const cat = this.connectorsModal.category;
                    const q = this.connectorsModal.search.trim().toLowerCase();
                    return (this.connectorsModal.availableConnectors || []).filter(c => {
                        if (cat !== 'all' && cat !== c.category) {
                            return false;
                        }
                        if (!q) {
                            return true;
                        }
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
                        if (cat !== 'all' && cat !== connCat) {
                            return false;
                        }
                        if (!q) {
                            return true;
                        }
                        return (c.name || '').toLowerCase().includes(q) ||
                            (c.email || '').toLowerCase().includes(q) ||
                            (c.type || '').toLowerCase().includes(q);
                    });
                },

                // Every context key a step writes. A structured `ai_call` stores the decoded object
                // under store_output_as *and* one entry per output key, because the engine
                // interpolates /\{(\w+)\}/ and cannot walk nested paths.
                stepOutputVariables(step) {
                    const store = step?.config?.store_output_as ?? (step?.type === 'ai_call' ? 'ai_response' : null);
                    if (!store) return [];

                    const vars = [{ name: store, field: null }];
                    if (step.type === 'ai_call' && step.config?.response_format === 'json') {
                        (step.config?.output_keys ?? []).forEach(key => {
                            const field = String(key ?? '').trim();
                            if (field) vars.push({ name: field, field });
                        });
                    }
                    return vars;
                },

                get availableVariables() {
                    const base = [{
                            name: '{message_text}',
                            short: '{{ trans('Message') }}',
                            description: '{{ trans('Incoming message from trigger channel') }}'
                        },
                        {
                            name: '{sender_id}',
                            short: '{{ trans('Sender') }}',
                            description: '{{ trans('Sender ID from trigger channel') }}'
                        },
                    ];
                    const seen = new Set(base.map(v => v.name));
                    const dynamic = [];
                    const push = (step, stepNum) => {
                        const typeLabel = this.actionTypeLabel(step.type);
                        this.stepOutputVariables(step).forEach(v => {
                            const name = `{${v.name}}`;
                            if (seen.has(name)) return;
                            seen.add(name);
                            const suffix = v.field ? `: ${v.field}` : '';
                            dynamic.push({
                                name,
                                short: `${typeLabel} {{ trans('output') }}${suffix}`,
                                description: `{{ trans('Step') }} ${stepNum} · ${typeLabel} {{ trans('output') }}${suffix}`
                            });
                        });
                    };

                    this.steps.forEach((step, idx) => {
                        const stepNum = idx + 1;
                        push(step, stepNum);
                        if (step.type === 'path' && step.config?.paths) {
                            step.config.paths.forEach(path => {
                                (path.steps ?? []).forEach(ss => push(ss, stepNum));
                            });
                        }
                    });
                    return [...base, ...dynamic];
                },

                prettifyVars(text) {
                    if (!text) return text;
                    const vars = this.availableVariables;
                    return text.replace(/\{[a-z0-9_]+\}/gi, (token) => {
                        const v = vars.find(x => x.name === token);
                        return v ? v.short : token;
                    });
                },

                insertVariable(configKey, variable) {
                    if (!this.selectedStep) {
                        return;
                    }
                    const current = this.selectedStep.config[configKey] ?? '';
                    this.selectedStep.config[configKey] = current + variable;
                    this.activeVariableMenu = null;
                    this._pushHistory();
                    this._panelSnapshot = this._snap();
                },

                addTriggerRule(groupIndex) {
                    this.triggerConfig.keyword_filter.ruleGroups[groupIndex].rules.push({
                        field: '{message_text}',
                        operator: 'contains',
                        value: ''
                    });
                    this._pushHistory();
                },

                removeTriggerRule(groupIndex, ruleIndex) {
                    this.triggerConfig.keyword_filter.ruleGroups[groupIndex].rules.splice(ruleIndex, 1);
                    if (this.triggerConfig.keyword_filter.ruleGroups[groupIndex].rules.length === 0) {
                        this.removeTriggerRuleGroup(groupIndex);
                        return;
                    }
                    this._pushHistory();
                },

                addTriggerRuleGroup() {
                    this.triggerConfig.keyword_filter.ruleGroups.push({
                        rules: [{
                            field: '{message_text}',
                            operator: 'contains',
                            value: ''
                        }]
                    });
                    this._pushHistory();
                },

                removeTriggerRuleGroup(groupIndex) {
                    this.triggerConfig.keyword_filter.ruleGroups.splice(groupIndex, 1);
                    if (this.triggerConfig.keyword_filter.ruleGroups.length === 0) {
                        this.triggerConfig.keyword_filter.ruleGroups = [{
                            rules: [{
                                field: '{message_text}',
                                operator: 'contains',
                                value: ''
                            }]
                        }];
                    }
                    this._pushHistory();
                },

                init() {
                    if (window.innerWidth < 640) {
                        this.zoom = 55;
                        this.copilotOpen = false;
                    }

                    this._initCronUiState();

                    window.addEventListener('keydown', (e) => {
                        const tag = e.target?.tagName;
                        if (tag === 'INPUT' || tag === 'TEXTAREA' || e.target?.isContentEditable) return;
                        if ((e.ctrlKey || e.metaKey) && !e.shiftKey && e.key === 'z') {
                            e.preventDefault();
                            this.undo();
                        } else if ((e.ctrlKey || e.metaKey) && ((!e.shiftKey && e.key === 'y') || (e.shiftKey && e.key === 'Z'))) {
                            e.preventDefault();
                            this.redo();
                        }
                    });

                    this.$watch('triggerConfig.channel_id', () => {
                        if (this.testPanelOpen) this.checkChannelStatus();
                        this._fillSendMessageChannels();
                    });

                    // Apply template when creating a new workflow from a template
                    if (!existingWorkflow && templateData) {
                        this.workflowName = templateData.name ?? '';
                        this.triggerType = templateData.trigger_type ?? 'schedule';
                        this.triggerConfig = Object.assign({}, this.triggerConfig, templateData.trigger_config ?? {});
                        if (templateData.cron) {
                            this.triggerConfig.cron = templateData.cron;
                        }
                        this.triggerConfigured = true;
                        if (window.__workflowData.templateAvatar) {
                            this.workflowAvatarUrl = window.__workflowData.templateAvatar;
                        }
                        if (Array.isArray(templateData.steps)) {
                            this.steps = templateData.steps.map((s, i) => {
                                const config = Object.assign({}, s.config ?? {});
                                if (s.type === 'path' && Array.isArray(config.paths)) {
                                    config.paths = config.paths.map(p => ({
                                        id: crypto.randomUUID(),
                                        name: p.name ?? 'Path',
                                        ruleGroups: p.ruleGroups ?? [],
                                        steps: (p.steps ?? []).map((ss, si) => ({
                                            id: crypto.randomUUID(),
                                            type: ss.type ?? '',
                                            app: this.appFromType(ss.type ?? ''),
                                            action: ss.type ?? '',
                                            config: Object.assign({}, ss.config ?? {}),
                                            order: si,
                                        })),
                                    }));
                                }
                                return this._normalizeStepConfig({
                                    id: crypto.randomUUID(),
                                    type: s.type ?? '',
                                    app: this.appFromType(s.type ?? ''),
                                    action: s.type ?? '',
                                    config,
                                    order: i,
                                });
                            });
                            this._fillSendMessageChannels();
                        }
                    }

                    // Auto-send copilot message when arriving from dashboard widget
                    if (!existingWorkflow && window.__workflowData.copilotInit) {
                        this.copilotInput = window.__workflowData.copilotInit;
                        this.$nextTick(() => this.sendCopilotMessage());
                    }

                    // Load steps from new format or legacy actions
                    if (existingWorkflow?.steps) {
                        this.steps = existingWorkflow.steps.map(s => this._normalizeStepConfig(s));
                    } else if (existingWorkflow?.actions) {
                        const appMap = {
                            ai_call: 'AI',
                            send_message: 'Channels',
                            generate_report: 'Utilities',
                            path: 'Flow Controls',
                        };
                        this.steps = (existingWorkflow.actions || []).map((a, i) => this._normalizeStepConfig({
                            id: crypto.randomUUID(),
                            type: a.type,
                            app: appMap[a.type] ?? 'Action',
                            action: a.type,
                            config: Object.assign({}, a.config ?? {}),
                            order: i,
                        }));
                    }

                    // Listen for AI-generated workflow
                    window.addEventListener('workflow-generated', (e) => {
                        const d = e.detail;
                        this.workflowName = d.name || '';
                        this.triggerType = d.trigger_type || 'schedule';
                        this.triggerConfig = Object.assign({}, this.triggerConfig, d.trigger_config || {});
                        this.triggerConfigured = true;
                        if (Array.isArray(d.steps)) {
                            this.steps = d.steps.map((s, i) => this._normalizeStepConfig({
                                id: s.id || crypto.randomUUID(),
                                type: s.type ?? s.action ?? '',
                                app: s.app ?? this.appFromType(s.type ?? s.action ?? ''),
                                action: s.action ?? s.type ?? '',
                                config: Object.assign({}, s.config ?? {}),
                                order: s.order ?? i,
                            }));
                        }
                        this._initCronUiState();
                        this._history = [];
                        this._historyIdx = -1;
                        this._pushHistory();
                    });

                    this._pushHistory();
                },

                appFromType(type) {
                    const map = {
                        ai_call: 'AI',
                        send_message: 'Channels',
                        generate_report: 'Utilities',
                        path: 'Flow Controls',
                        crm_agent: 'Agents',
                    };
                    return map[type] ?? 'Action';
                },

                iconFromType(type) {
                    const map = {
                        ai_call: 'brain',
                        send_message: 'send',
                        generate_report: 'report-analytics',
                        path: 'git-fork',
                        crm_agent: 'address-book',
                    };
                    return map[type] ?? 'bolt';
                },

                actionTypeLabel(type) {
                    const labels = {
                        ai_call: '{{ trans('Ask Agent') }}',
                        send_message: '{{ trans('Send Message') }}',
                        generate_report: '{{ trans('Generate Report') }}',
                        path: '{{ trans('Path') }}',
                        external_chatbot: '{{ trans('External Chatbot') }}',
                        marketing_bot: '{{ trans('Marketing Bot') }}',
                        social_media_agent: '{{ trans('Social Media Agent') }}',
                        crm_agent: '{{ trans('CRM Agent') }}',
                        gmail: '{{ trans('GMail') }}',
                        outlook: '{{ trans('Outlook') }}',
                    };
                    return labels[type] ?? type;
                },

                stepSummary(step) {
                    if (!step) return '';
                    if (step.type === 'path') {
                        const count = step.config?.paths?.length ?? 0;
                        return count + ' {{ trans('branches') }}';
                    }
                    if (step.type === 'ai_call') return this.prettifyVars(step.config?.user_message || step.config?.system_prompt?.substring(0, 50)) ||
                        '{{ trans('No message set') }}';
                    if (step.type === 'send_message') return this.prettifyVars(step.config?.message) || '{{ trans('No message set') }}';
                    if (step.type === 'generate_report') return step.config?.date_range || 'today';
                    if (step.type === 'external_chatbot') {
                        const eventLabels = {
                            list_chatbots: '{{ trans('List Chatbots') }}',
                            get_chatbot_analytics: '{{ trans('Get Chatbot Analytics') }}',
                            list_chatbot_conversations: '{{ trans('List Conversations') }}',
                            get_chatbot_conversation: '{{ trans('Get Conversation') }}',
                            get_chatbot_conversations: '{{ trans('Get Conversations') }}',
                            close_chatbot_ticket: '{{ trans('Close Ticket') }}',
                        };
                        return eventLabels[step.config?.action_event] || '{{ trans('No event selected') }}';
                    }
                    if (step.type === 'marketing_bot') {
                        const eventLabels = {
                            list_marketing_campaigns: '{{ trans('List Campaigns') }}',
                            get_marketing_stats: '{{ trans('Get Stats') }}',
                            list_marketing_conversations: '{{ trans('List Conversations') }}',
                            create_marketing_campaign: '{{ trans('Create Campaign') }}',
                            schedule_marketing_campaign: '{{ trans('Schedule Campaign') }}',
                        };
                        return eventLabels[step.config?.action_event] || '{{ trans('No event selected') }}';
                    }
                    if (step.type === 'social_media_agent') {
                        const eventLabels = {
                            list_social_media_agents: '{{ trans('List Agents') }}',
                            list_social_posts: '{{ trans('List Posts') }}',
                            create_social_post: '{{ trans('Create Post') }}',
                            get_social_post_analytics: '{{ trans('Get Analytics') }}',
                            approve_social_post: '{{ trans('Approve Post') }}',
                        };
                        return eventLabels[step.config?.action_event] || '{{ trans('No event selected') }}';
                    }
                    if (step.type === 'crm_agent') {
                        const eventLabels = {
                            list_contacts: '{{ trans('List Contacts') }}',
                            create_contact: '{{ trans('Create Contact') }}',
                            list_companies: '{{ trans('List Companies') }}',
                            create_company: '{{ trans('Create Company') }}',
                            list_deals: '{{ trans('List Deals') }}',
                            create_deal: '{{ trans('Create Deal') }}',
                            move_deal_stage: '{{ trans('Move Deal Stage') }}',
                            create_task: '{{ trans('Create Task') }}',
                            add_note: '{{ trans('Add Note') }}',
                            get_crm_analytics: '{{ trans('Get CRM Analytics') }}',
                        };
                        return eventLabels[step.config?.action_event] || '{{ trans('No event selected') }}';
                    }
                    if (step.type === 'gmail') {
                        const eventLabels = {
                            send_email: '{{ trans('Send Email') }}',
                            reply_to_email: '{{ trans('Reply to Email') }}',
                            create_draft: '{{ trans('Create Draft') }}',
                            create_draft_reply: '{{ trans('Create Draft Reply') }}',
                            create_label: '{{ trans('Create Label') }}',
                            add_label_to_email: '{{ trans('Add Label to Email') }}',
                            remove_label_from_email: '{{ trans('Remove Label from Email') }}',
                            remove_label_from_conversation: '{{ trans('Remove Label from Conversation') }}',
                            archive_email: '{{ trans('Archive Email') }}',
                            delete_email: '{{ trans('Delete Email') }}',
                            find_email: '{{ trans('Find Email') }}',
                            get_attachment_by_filename: '{{ trans('Get Attachment by Filename') }}',
                        };
                        const label = eventLabels[step.config?.action_event];
                        if (!label) return '{{ trans('No event selected') }}';
                        if (step.config?.action_event === 'send_email' || step.config?.action_event === 'create_draft') {
                            return label + (step.config?.to ? ' → ' + step.config.to : '');
                        }
                        if (step.config?.action_event === 'find_email' && step.config?.query) {
                            return label + ': ' + step.config.query.substring(0, 30);
                        }
                        return label;
                    }
                    if (step.type === 'outlook') {
                        const eventLabels = {
                            send_email: '{{ trans('Send Email') }}',
                            create_draft_email: '{{ trans('Create Draft Email') }}',
                            send_draft_email: '{{ trans('Send Draft Email') }}',
                            create_draft_reply: '{{ trans('Create Draft Reply') }}',
                            reply_to_email: '{{ trans('Reply to Email') }}',
                            forward_email: '{{ trans('Forward Email') }}',
                            copy_email: '{{ trans('Copy Email') }}',
                            move_email_to_folder: '{{ trans('Move Email to Folder') }}',
                            delete_email: '{{ trans('Delete Email') }}',
                            mark_email_as_read_unread: '{{ trans('Mark Email Read/Unread') }}',
                            flag_unflag_email: '{{ trans('Flag/Unflag Email') }}',
                            set_email_importance: '{{ trans('Set Email Importance') }}',
                            set_categories_on_email: '{{ trans('Set Categories on Email') }}',
                            remove_categories_from_email: '{{ trans('Remove Categories from Email') }}',
                            find_emails: '{{ trans('Find Emails') }}',
                            create_folder: '{{ trans('Create Folder') }}',
                            create_event: '{{ trans('Create Event') }}',
                            update_calendar_event: '{{ trans('Update Calendar Event') }}',
                            add_attendees_to_calendar_event: '{{ trans('Add Attendees to Event') }}',
                            find_calendar_events: '{{ trans('Find Calendar Events') }}',
                            create_contact: '{{ trans('Create Contact') }}',
                            update_contact: '{{ trans('Update Contact') }}',
                            delete_contact: '{{ trans('Delete Contact') }}',
                            find_contacts: '{{ trans('Find Contacts') }}',
                        };
                        const label = eventLabels[step.config?.action_event];
                        if (!label) return '{{ trans('No event selected') }}';
                        if ((step.config?.action_event === 'send_email' || step.config?.action_event === 'create_draft_email') && step.config?.to) {
                            return label + ' → ' + step.config.to;
                        }
                        if (step.config?.action_event === 'find_emails' && step.config?.query) {
                            return label + ': ' + step.config.query.substring(0, 30);
                        }
                        return label;
                    }
                    return step.action || step.type || '';
                },

                // Variables the run context holds by the time `index` executes: the trigger's own
                // variables plus every output stored by an earlier step.
                variablesAvailableBefore(index) {
                    const names = new Set(['message_text', 'sender_id', 'conversation_id', 'channel_id',
                        'fired_at', 'trigger_type', 'cron', 'payload', 'topic'
                    ]);
                    this.steps.slice(0, index).forEach(s => {
                        this.stepOutputVariables(s).forEach(v => names.add(v.name));
                        (s.config?.paths ?? []).forEach(p => (p.steps ?? []).forEach(ss => {
                            this.stepOutputVariables(ss).forEach(v => names.add(v.name));
                        }));
                    });
                    return names;
                },

                // A {var} nothing produces is written to the record verbatim by the engine, so the
                // run fails. Surface it in the builder instead of at run time.
                stepUnknownVariables(step) {
                    const index = this.steps.indexOf(step);
                    if (index < 0 || step.type === 'path') return [];
                    const known = this.variablesAvailableBefore(index);
                    const unknown = new Set();
                    const scan = (value, key) => {
                        if (typeof value === 'string') {
                            if (key === 'system_prompt') return;
                            (value.match(/\{(\w+)\}/g) ?? []).forEach(token => {
                                if (!known.has(token.slice(1, -1))) unknown.add(token);
                            });
                        } else if (value && typeof value === 'object') {
                            Object.entries(value).forEach(([k, v]) => scan(v, k));
                        }
                    };
                    Object.entries(step.config ?? {}).forEach(([k, v]) => scan(v, k));
                    return [...unknown];
                },

                stepHasMissingRequired(step) {
                    const c = step.config ?? {};
                    if (step.type === 'send_message') return !c.channel_id;

                    const actionEventTypes = ['external_chatbot', 'marketing_bot', 'social_media_agent', 'crm_agent', 'gmail', 'outlook'];
                    if (!actionEventTypes.includes(step.type)) return false;
                    if (!c.action_event) return true;

                    const requiredFields = {
                        external_chatbot: {
                            get_chatbot_conversation: ['conversation_id'],
                            close_chatbot_ticket: ['conversation_id'],
                        },
                        marketing_bot: {
                            create_marketing_campaign: ['name', 'type', 'content'],
                            schedule_marketing_campaign: ['campaign_id'],
                        },
                        social_media_agent: {
                            create_social_post: ['agent_id', 'content'],
                            get_social_post_analytics: ['agent_id'],
                            approve_social_post: ['post_id'],
                            create_social_media_agent: ['name', 'tone', 'language'],
                        },
                        crm_agent: {
                            create_contact: ['first_name'],
                            create_company: ['name'],
                            create_deal: ['title'],
                            move_deal_stage: [],
                            create_task: ['title'],
                            add_note: ['notable', 'content'],
                        },
                        gmail: {
                            send_email: ['to', 'subject', 'body'],
                            reply_to_email: ['message_id', 'body'],
                            create_draft: ['to', 'subject', 'body'],
                            create_draft_reply: ['message_id', 'body'],
                            create_label: ['label_name'],
                            add_label_to_email: ['message_id', 'label_ids'],
                            remove_label_from_email: ['message_id', 'label_ids'],
                            remove_label_from_conversation: ['thread_id', 'label_ids'],
                            archive_email: ['message_id'],
                            delete_email: ['message_id'],
                            find_email: ['query'],
                            get_attachment_by_filename: ['message_id', 'filename'],
                        },
                        outlook: {
                            send_email: ['to', 'subject', 'body'],
                            create_draft_email: ['to', 'subject', 'body'],
                            send_draft_email: ['message_id'],
                            create_draft_reply: ['message_id'],
                            reply_to_email: ['message_id', 'body'],
                            forward_email: ['message_id', 'to'],
                            copy_email: ['message_id', 'destination_id'],
                            move_email_to_folder: ['message_id', 'destination_id'],
                            delete_email: ['message_id'],
                            mark_email_as_read_unread: ['message_id'],
                            flag_unflag_email: ['message_id'],
                            set_email_importance: ['message_id'],
                            set_categories_on_email: ['message_id', 'categories'],
                            remove_categories_from_email: ['message_id'],
                            find_emails: ['query'],
                            create_folder: ['display_name'],
                            create_event: ['subject', 'start', 'end'],
                            update_calendar_event: ['event_id'],
                            add_attendees_to_calendar_event: ['event_id', 'attendees'],
                            create_contact: ['given_name'],
                            update_contact: ['contact_id'],
                            delete_contact: ['contact_id'],
                        },
                    };

                    const fields = requiredFields[step.type]?.[c.action_event];
                    if (!fields) return false;

                    if (step.type === 'social_media_agent' && c.action_event === 'create_social_media_agent') {
                        if (!c.platform_ids || c.platform_ids.length === 0) return true;
                    }

                    return fields.some(f => {
                        const v = c[f];
                        return v === undefined || v === null || v === '' || v === 0;
                    });
                },

                // Step settings
                openStepSettings(stepId) {
                    if (this.settingsPanelOpen && this._panelSnapshot && this.selectedStepId !== stepId) {
                        if (JSON.stringify(this._snap()) !== JSON.stringify(this._panelSnapshot)) {
                            this._pushHistory();
                        }
                    }
                    this._panelSnapshot = this._snap();
                    this.selectedStepId = stepId;
                    this.settingsPanelOpen = true;
                    this.settingsPanelTab = 'configure';
                    this.testPanelOpen = false;
                    if (stepId !== 'trigger' && this.availableModelGroups.length === 0) {
                        this.fetchAvailableModels();
                    }
                    if (stepId !== 'trigger' && !this.knowledgeSourcesLoaded) {
                        this.fetchKnowledgeSources();
                    }
                    if (stepId !== 'trigger' && !this.socialMediaAgentsLoaded) {
                        this.fetchSocialMediaAgents();
                    }
                    if (stepId !== 'trigger' && !this.socialMediaPlatformsLoaded) {
                        this.fetchSocialMediaPlatforms();
                    }
                    const step = this.steps.find(s => s.id === stepId);
                    if (step?.type === 'gmail' && !this.gmailMessagesLoaded) {
                        this.fetchGmailMessages();
                        this.fetchGmailThreads();
                    }
                },

                closeStepSettings() {
                    if (this._panelSnapshot) {
                        if (JSON.stringify(this._snap()) !== JSON.stringify(this._panelSnapshot)) {
                            this._pushHistory();
                        }
                        this._panelSnapshot = null;
                    }
                    this.settingsPanelOpen = false;
                    this.selectedStepId = null;
                },

                openTestPanel() {
                    if (this.settingsPanelOpen) {
                        this.closeStepSettings();
                    }
                    this.testPanelOpen = true;
                    this.loadExistingTestConversation();
                    this.checkChannelStatus();
                },

                closeTestPanel() {
                    this.testPanelOpen = false;
                    this.testChannelStatus = null;
                },

                toggleChannelsModal(status) {
                    Alpine.$data(document.querySelector('#ai-agent-channels-modal')).modalOpen = status;
                },

                async checkChannelStatus() {
                    const channelId = this.triggerConfig.channel_id;
                    if (!channelId || this.triggerType !== 'channel_message') {
                        this.testChannelStatus = null;
                        return;
                    }
                    try {
                        const url = this.channelStatusBaseUrl.replace(/\/+$/, '') + '/' + channelId + '/check-status';
                        const res = await fetch(url, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                        });
                        this.testChannelStatus = await res.json();
                    } catch (e) {
                        this.testChannelStatus = null;
                    }
                },

                async loadExistingTestConversation() {
                    if (!this.workflowId || this.testMessages.length > 0) return;
                    this.testLoading = true;
                    try {
                        const res = await fetch(this.conversationsUrl, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                        });
                        const data = await res.json();
                        const match = (data.data ?? []).find(c => c.sender_id === 'wf_' + this.workflowId);
                        if (match) {
                            this.testConversationId = match.id;
                            const threadRes = await fetch(`${this.threadBaseUrl}/${match.id}/thread`, {
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                            });
                            this.testMessages = await threadRes.json();
                            this.$nextTick(() => {
                                const el = this.$refs.testMessagesWrap;
                                if (el) el.scrollTop = el.scrollHeight;
                            });
                        }
                    } catch (e) {
                        // silently fail
                    } finally {
                        this.testLoading = false;
                    }
                },

                async sendTestMessage(text) {
                    if (!text?.trim() || this.testSending || !this.workflowId) return;
                    this.testSending = true;
                    this.testMessages.push({
                        id: Date.now(),
                        direction: 'inbound',
                        content: text,
                        created_at: new Date().toISOString()
                    });
                    this.$nextTick(() => {
                        const el = this.$refs.testMessagesWrap;
                        if (el) el.scrollTop = el.scrollHeight;
                    });
                    try {
                        const res = await fetch(this.sendUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify({
                                workflow_id: this.workflowId,
                                message: text
                            }),
                        });
                        const data = await res.json();
                        if (data.conversation_id) {
                            this.testConversationId = data.conversation_id;
                            const threadRes = await fetch(`${this.threadBaseUrl}/${data.conversation_id}/thread`, {
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                            });
                            this.testMessages = await threadRes.json();
                        }
                    } catch (e) {
                        toastr.error('{{ trans('Failed to send test message.') }}');
                    } finally {
                        this.testSending = false;
                        this.$nextTick(() => {
                            const el = this.$refs.testMessagesWrap;
                            if (el) el.scrollTop = el.scrollHeight;
                        });
                    }
                },

                saveTriggerConfig() {
                    this.triggerConfigured = true;
                    this.closeStepSettings();
                    this.saveWorkflow();
                },

                disconnectTrigger() {
                    this._panelSnapshot = null;
                    this.triggerConfigured = false;
                    this.triggerConfig.cron = '';
                    this.closeStepSettings();
                    this._pushHistory();
                },

                toggleStepModal(status) {
                    Alpine.$data(document.querySelector('#ai-agent-steps-modal')).modalOpen = status;
                },

                // Steps management
                _normalizeStepConfig(step) {
                    if (step.type === 'generate_report' && !step.config?.system_prompt) {
                        step.config = Object.assign({}, step.config, {
                            system_prompt: "You are a concise business analyst. You will receive platform activity data as JSON.\nWrite a short, friendly summary for the owner:\n- Start with one sentence summarising the period overall.\n- Highlight only metrics with non-zero values.\n- Skip any metric that is 0 or empty.\n- Use plain text. No markdown headings. Bullet points are fine for key metrics.\n- Keep it under 300 words."
                        });
                    }
                    return step;
                },

                // Templates ship send_message steps without a channel — adopt the trigger channel
                // so the workflow is runnable without opening every node.
                _fillSendMessageChannels() {
                    const channelId = this.triggerConfig.channel_id;
                    if (!channelId) return;
                    const fill = (steps) => (steps ?? []).forEach(s => {
                        if (s.type === 'send_message' && !s.config?.channel_id) {
                            s.config = Object.assign({}, s.config, { channel_id: channelId });
                        }
                        if (s.type === 'path') {
                            (s.config?.paths ?? []).forEach(p => fill(p.steps));
                        }
                    });
                    fill(this.steps);
                },

                addStep(type, order, app, action) {
                    const varSlug = () => 'step_' + crypto.randomUUID().replace(/-/g, '').substring(0, 6);
                    const defaults = {
                        ai_call: {
                            system_prompt: '',
                            user_message: '{message_text}',
                            store_output_as: varSlug(),
                            inject_memory: false,
                            history_turns: 10
                        },
                        send_message: {
                            channel_id: null,
                            recipient_id: '{sender_id}',
                            message: ''
                        },
                        generate_report: {
                            date_range: 'today',
                            sections: ['messages', 'workflows'],
                            store_output_as: varSlug(),
                            system_prompt: "You are a concise business analyst. You will receive platform activity data as JSON.\nWrite a short, friendly summary for the owner:\n- Start with one sentence summarising the period overall.\n- Highlight only metrics with non-zero values.\n- Skip any metric that is 0 or empty.\n- Use plain text. No markdown headings. Bullet points are fine for key metrics.\n- Keep it under 300 words."
                        },
                        external_chatbot: {
                            action_event: '',
                            store_output_as: varSlug()
                        },
                        marketing_bot: {
                            action_event: '',
                            store_output_as: varSlug()
                        },
                        social_media_agent: {
                            action_event: '',
                            store_output_as: varSlug()
                        },
                        crm_agent: {
                            action_event: '',
                            store_output_as: varSlug()
                        },
                        gmail: {
                            action_event: '',
                            store_output_as: varSlug(),
                            return_format: 'id'
                        },
                        outlook: {
                            action_event: '',
                            store_output_as: varSlug()
                        },
                        path: {
                            paths: [{
                                    id: crypto.randomUUID(),
                                    name: 'Path A',
                                    ruleGroups: [{
                                        rules: [{
                                            field: '',
                                            operator: 'contains',
                                            value: ''
                                        }]
                                    }],
                                    steps: []
                                },
                                {
                                    id: crypto.randomUUID(),
                                    name: 'Path B',
                                    ruleGroups: [],
                                    steps: []
                                },
                            ]
                        },
                    };
                    const newStep = {
                        id: crypto.randomUUID(),
                        type: type,
                        app: app || this.appFromType(type),
                        action: action || type,
                        config: Object.assign({}, defaults[type] ?? {}),
                        order: order ?? this.steps.length,
                    };
                    this.steps.splice(newStep.order, 0, newStep);
                    this.steps = this.steps.map((s, i) => ({
                        ...s,
                        order: i
                    }));
                    this.openStepSettings(newStep.id);
                    this._pushHistory();
                },

                removeStep(id) {
                    this.steps = this.steps.filter(s => s.id !== id).map((s, i) => ({
                        ...s,
                        order: i
                    }));
                    if (this.selectedStepId === id) {
                        this._panelSnapshot = null;
                        this.closeStepSettings();
                    }
                    this._pushHistory();
                },

                // Path branch management
                addPath(parentStepId) {
                    const parent = this.steps.find(s => s.id === parentStepId);
                    if (!parent) return;
                    const count = parent.config.paths.length;
                    parent.config.paths.push({
                        id: crypto.randomUUID(),
                        name: 'Path ' + String.fromCharCode(65 + count),
                        ruleGroups: [],
                        steps: [],
                    });
                    this._pushHistory();
                },

                removePath(parentStepId, pathIndex) {
                    const parent = this.steps.find(s => s.id === parentStepId);
                    if (!parent || parent.config.paths.length <= 1) return;
                    parent.config.paths.splice(pathIndex, 1);
                    this._pushHistory();
                },

                addRuleGroup(parentStepId, pathIndex) {
                    const parent = this.steps.find(s => s.id === parentStepId);
                    if (!parent) return;
                    parent.config.paths[pathIndex].ruleGroups.push({
                        rules: [{
                            field: '',
                            operator: 'contains',
                            value: ''
                        }]
                    });
                    this._pushHistory();
                },

                removeRuleGroup(parentStepId, pathIndex, groupIndex) {
                    const parent = this.steps.find(s => s.id === parentStepId);
                    if (!parent) return;
                    parent.config.paths[pathIndex].ruleGroups.splice(groupIndex, 1);
                    this._pushHistory();
                },

                addRule(parentStepId, pathIndex, groupIndex) {
                    const parent = this.steps.find(s => s.id === parentStepId);
                    if (!parent) return;
                    parent.config.paths[pathIndex].ruleGroups[groupIndex].rules.push({
                        field: '',
                        operator: 'contains',
                        value: ''
                    });
                    this._pushHistory();
                },

                removeRule(parentStepId, pathIndex, groupIndex, ruleIndex) {
                    const parent = this.steps.find(s => s.id === parentStepId);
                    if (!parent) return;
                    parent.config.paths[pathIndex].ruleGroups[groupIndex].rules.splice(ruleIndex, 1);
                    this._pushHistory();
                },

                // Sub-step management (within path branches)
                openAddSubStepModal(parentStepId, pathIndex, order) {
                    this.addStepModal.mode = 'substep';
                    this.addStepModal.parentStepId = parentStepId;
                    this.addStepModal.pathIndex = pathIndex;
                    this.addStepModal.order = order;
                    this.addStepModal.search = '';
                    this.addStepModal.category = 'all';
                    this.toggleStepModal(true);
                    if (!Object.keys(this.addStepModal.actions).length) {
                        this.fetchAvailableActions();
                    }
                },

                removeSubStep(parentStepId, pathIndex, subStepId) {
                    const parent = this.steps.find(s => s.id === parentStepId);
                    if (!parent) return;
                    parent.config.paths[pathIndex].steps = parent.config.paths[pathIndex].steps
                        .filter(s => s.id !== subStepId)
                        .map((s, i) => ({
                            ...s,
                            order: i
                        }));
                    if (this.selectedStepId === `substep::${parentStepId}::${pathIndex}::${subStepId}`) {
                        this._panelSnapshot = null;
                        this.closeStepSettings();
                    }
                    this._pushHistory();
                },

                openSubStepSettings(parentStepId, pathIndex, subStepId) {
                    this.selectedStepId = `substep::${parentStepId}::${pathIndex}::${subStepId}`;
                    this.settingsPanelOpen = true;
                    this.settingsPanelTab = 'configure';
                    if (this.availableModelGroups.length === 0) this.fetchAvailableModels();
                    if (!this.knowledgeSourcesLoaded) this.fetchKnowledgeSources();
                },

                // Add step modal
                openAddStepModal(order) {
                    this.addStepModal.mode = 'top-level';
                    this.addStepModal.parentStepId = null;
                    this.addStepModal.pathIndex = null;
                    this.addStepModal.order = order;
                    this.toggleStepModal(true);
                    this.addStepModal.search = '';
                    this.addStepModal.category = 'all';
                    if (!Object.keys(this.addStepModal.actions).length) {
                        this.fetchAvailableActions();
                    }
                },

                async fetchAvailableActions() {
                    this.addStepModal.loading = true;
                    try {
                        const res = await fetch('/dashboard/user/ai-agent/workflows/available-actions', {
                            headers: {
                                'Accept': 'application/json'
                            },
                        });
                        const data = await res.json();
                        this.addStepModal.actions = data.data ?? {};
                        this.addStepModal.failedActions = data.failed ?? [];
                    } catch (e) {
                        // fallback: show built-in types
                        this.addStepModal.actions = {
                            ai: [{
                                key: 'ai_call',
                                label: 'Ask Agent',
                                description: 'Send a message to an AI model.',
                                icon: 'tabler-brain',
                                category: 'ai'
                            }],
                            channels: [{
                                key: 'send_message',
                                label: 'Send Message',
                                description: 'Send a message to a channel.',
                                icon: 'tabler-send',
                                category: 'channels'
                            }],
                            utilities: [{
                                key: 'generate_report',
                                label: 'Generate Report',
                                description: 'Generate an activity report.',
                                icon: 'tabler-report-analytics',
                                category: 'utilities'
                            }, ],
                            flow_controls: [{
                                key: 'path',
                                label: 'Path',
                                description: 'Split execution into conditional branches.',
                                icon: 'tabler-git-fork',
                                category: 'flow_controls'
                            }, ],
                        };
                    } finally {
                        this.addStepModal.loading = false;
                    }
                },

                selectActionFromModal(action) {
                    if (this.addStepModal.mode === 'substep') {
                        const parent = this.steps.find(s => s.id === this.addStepModal.parentStepId);
                        if (!parent) return;
                        const pathIndex = this.addStepModal.pathIndex;
                        const order = this.addStepModal.order;
                        const subStep = {
                            id: crypto.randomUUID(),
                            type: action.key,
                            app: action.app || this.appFromType(action.key),
                            action: action.key,
                            config: {},
                            order,
                        };
                        parent.config.paths[pathIndex].steps.splice(order, 0, subStep);
                        parent.config.paths[pathIndex].steps = parent.config.paths[pathIndex].steps.map((s, i) => ({
                            ...s,
                            order: i
                        }));
                        this.toggleStepModal(false);
                        this.openSubStepSettings(this.addStepModal.parentStepId, pathIndex, subStep.id);
                        this._pushHistory();
                        return;
                    }
                    this.addStep(action.key, this.addStepModal.order, action.category !== 'all' ? (action.app || action.category) : null, action.key);
                    this.toggleStepModal(false);
                },

                async fetchAvailableModels() {
                    try {
                        const res = await fetch('/dashboard/user/ai-agent/workflows/available-models', {
                            headers: {
                                'Accept': 'application/json'
                            },
                        });
                        const data = await res.json();
                        this.availableModelGroups = data.data ?? [];
                    } catch (e) {
                        this.availableModelGroups = [];
                    }
                },

                async fetchKnowledgeSources() {
                    try {
                        const res = await fetch('/dashboard/user/ai-agent/knowledge-sources', {
                            headers: {
                                'Accept': 'application/json'
                            },
                        });
                        const data = await res.json();
                        this.knowledgeSources = data.data ?? [];
                        this.knowledgeSourcesLoaded = true;
                    } catch (e) {
                        this.knowledgeSources = [];
                    }
                },

                async fetchSocialMediaAgents() {
                    try {
                        const res = await fetch('/dashboard/user/ai-agent/workflows/available-social-media-agents', {
                            headers: {
                                'Accept': 'application/json'
                            },
                        });
                        const data = await res.json();
                        this.socialMediaAgents = data.data ?? [];
                        this.socialMediaAgentsLoaded = true;
                    } catch (e) {
                        this.socialMediaAgents = [];
                        this.socialMediaAgentsLoaded = true;
                    }
                },

                async fetchSocialMediaPlatforms() {
                    try {
                        const res = await fetch('/dashboard/user/ai-agent/workflows/available-social-media-platforms', {
                            headers: {
                                'Accept': 'application/json'
                            },
                        });
                        const data = await res.json();
                        this.socialMediaPlatforms = data.data ?? [];
                        this.socialMediaPlatformsLoaded = true;
                    } catch (e) {
                        this.socialMediaPlatforms = [];
                        this.socialMediaPlatformsLoaded = true;
                    }
                },

                async fetchCrmRecords() {
                    if (this.crmRecordsLoaded || this.crmRecordsLoading) return;
                    this.crmRecordsLoading = true;
                    try {
                        const res = await fetch('/dashboard/user/ai-agent/workflows/available-crm-records', {
                            headers: {
                                'Accept': 'application/json'
                            },
                        });
                        const data = await res.json();
                        this.crmContacts = data.data?.contacts ?? [];
                        this.crmDeals = data.data?.deals ?? [];
                        this.crmCompanies = data.data?.companies ?? [];
                        this.crmStages = data.data?.stages ?? [];
                        this.crmRecordsLoaded = true;
                    } catch (e) {
                        this.crmContacts = [];
                        this.crmDeals = [];
                        this.crmCompanies = [];
                        this.crmStages = [];
                        this.crmRecordsLoaded = true;
                    } finally {
                        this.crmRecordsLoading = false;
                    }
                },

                async fetchGmailMessages(query = '') {
                    this.gmailMessagesLoading = true;
                    try {
                        const url = new URL('/dashboard/user/ai-agent/connectors/gmail/api/messages', window.location.origin);
                        if (query) url.searchParams.set('q', query);
                        const res = await fetch(url, {
                            headers: {
                                'Accept': 'application/json'
                            }
                        });
                        const data = await res.json();
                        this.gmailMessages = data.data ?? [];
                        this.gmailMessagesLoaded = true;
                    } catch (e) {
                        this.gmailMessages = [];
                        this.gmailMessagesLoaded = true;
                    } finally {
                        this.gmailMessagesLoading = false;
                    }
                },

                async fetchGmailThreads(query = '') {
                    this.gmailThreadsLoading = true;
                    try {
                        const url = new URL('/dashboard/user/ai-agent/connectors/gmail/api/threads', window.location.origin);
                        if (query) url.searchParams.set('q', query);
                        const res = await fetch(url, {
                            headers: {
                                'Accept': 'application/json'
                            }
                        });
                        const data = await res.json();
                        this.gmailThreads = data.data ?? [];
                        this.gmailThreadsLoaded = true;
                    } catch (e) {
                        this.gmailThreads = [];
                        this.gmailThreadsLoaded = true;
                    } finally {
                        this.gmailThreadsLoading = false;
                    }
                },

                toggleSocialMediaPlatform(step, platformId) {
                    if (!step.config.platform_ids) step.config.platform_ids = [];
                    const idx = step.config.platform_ids.indexOf(platformId);
                    if (idx === -1) {
                        step.config.platform_ids = [...step.config.platform_ids, platformId];
                    } else {
                        step.config.platform_ids = step.config.platform_ids.filter(id => id !== platformId);
                    }
                    this._pushHistory();
                    this._panelSnapshot = this._snap();
                },

                handleKnowledgeDrop(event) {
                    this.knowledgeForm.dragging = false;

                    const files = event.dataTransfer?.files;
                    if (!files || files.length === 0) return;

                    if (!this.knowledgeForm.name.trim()) {
                        return toastr.error('{{ __('Name is required') }}');
                    }

                    const file = files[0];
                    const accepted = (this.$refs.knowledgeBaseFileInput?.accept ?? '')
                        .split(',')
                        .map(s => s.trim().toLowerCase())
                        .filter(Boolean);
                    const ext = '.' + (file.name.split('.').pop() || '').toLowerCase();
                    if (accepted.length && !accepted.includes(ext)) {
                        return toastr.error('{{ __('Unsupported file type.') }}');
                    }

                    this.knowledgeForm.file = file;
                    this.saveKnowledgeSource();
                },

                async saveKnowledgeSource() {
                    this.knowledgeForm.loading = true;

                    if (this.knowledgeForm.name.trim() === '') {
                        return toastr.error('{{ __('Name is required.') }}');
                    }

                    try {
                        const formData = new FormData();
                        formData.append('name', this.knowledgeForm.name);
                        formData.append('type', this.knowledgeForm.type);
                        if (this.knowledgeForm.type === 'text') {
                            formData.append('content', this.knowledgeForm.content);
                        } else if (this.knowledgeForm.file) {
                            formData.append('file', this.knowledgeForm.file);
                        }
                        const res = await fetch('/dashboard/user/ai-agent/knowledge-sources', {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: formData,
                        });
                        const data = await res.json();
                        if (!res.ok || data.status === 'error') {
                            this.knowledgeForm.loading = false;
                            throw new Error(data?.message || '{{ __('Failed to save knowledge source.') }}');
                        }

                        this.knowledgeSources.unshift(data.data);

                        // Auto-attach to current step
                        if (this.selectedStep) {
                            this.toggleKnowledgeSource(this.selectedStep, data.data.id);
                        }

                        this.knowledgeForm = {
                            ...this.knowledgeForm,
                            open: false,
                            name: '',
                            content: '',
                            file: null,
                            loading: false,
                            dragging: false
                        };
                    } catch (e) {
                        const message = e.message || '{{ __('Failed to save knowledge source.') }}';
                        toastr.error(message);
                    } finally {
                        this.knowledgeForm.loading = false;
                    }
                },

                async deleteKnowledgeSource(id) {
                    try {
                        const res = await fetch('/dashboard/user/ai-agent/knowledge-sources/' + id, {
                            method: 'DELETE',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                        });
                        const data = await res.json();
                        if (!res.ok || data.status === 'error') {
                            throw new Error(data?.message || '{{ trans('Failed to delete knowledge source.') }}');
                        }
                        this.knowledgeSources = this.knowledgeSources.filter(s => s.id !== id);
                        // Remove from all steps
                        this.steps = this.steps.map(step => {
                            if (step.config?.knowledge_source_ids) {
                                step.config.knowledge_source_ids = step.config.knowledge_source_ids.filter(sid => sid !== id);
                            }
                            return step;
                        });
                    } catch (e) {
                        toastr.error('{{ trans('Failed to delete knowledge source.') }}');
                    }
                },

                knowledgeSourceName(id) {
                    return (this.knowledgeSources.find(s => s.id === id) ?? {}).name ?? id;
                },

                toggleKnowledgeSource(step, id) {
                    if (!step.config.knowledge_source_ids) step.config.knowledge_source_ids = [];
                    const idx = step.config.knowledge_source_ids.indexOf(id);
                    if (idx === -1) {
                        step.config.knowledge_source_ids = [...step.config.knowledge_source_ids, id];
                    } else {
                        step.config.knowledge_source_ids = step.config.knowledge_source_ids.filter(sid => sid !== id);
                    }
                    this._pushHistory();
                    this._panelSnapshot = this._snap();
                },

                channelName(id) {
                    const ch = this.channels.find(c => c.id == id);
                    return ch ? ch.name : '{{ trans('Unknown') }}';
                },

                toggleSection(step, value) {
                    if (!step.config.sections) step.config.sections = [];
                    const idx = step.config.sections.indexOf(value);
                    if (idx === -1) {
                        step.config.sections = [...step.config.sections, value];
                    } else {
                        step.config.sections = step.config.sections.filter(s => s !== value);
                    }
                    this.steps = [...this.steps];
                    this._pushHistory();
                    this._panelSnapshot = this._snap();
                },

                async saveWorkflow(silent = false) {
                    if (!this.workflowName.trim()) {
                        this.workflowName = 'Agent ' + new Date().toLocaleDateString('en-US', {
                            month: 'short',
                            day: 'numeric'
                        });
                    }
                    this.saving = true;
                    try {
                        const payload = {
                            name: this.workflowName,
                            description: this.workflowDescription,
                            role: this.workflowRole,
                            trigger_type: this.triggerType,
                            trigger_config: this.triggerConfig,
                            steps: this.steps.map(s => ({
                                id: s.id,
                                type: s.type,
                                app: s.app,
                                action: s.action,
                                config: s.config,
                                order: s.order,
                            })),
                        };
                        const url = this.workflowId ?
                            `/dashboard/user/ai-agent/workflows/${this.workflowId}` :
                            '/dashboard/user/ai-agent/workflows';
                        const method = this.workflowId ? 'PUT' : 'POST';
                        const response = await fetch(url, {
                            method,
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify(payload),
                        });
                        const data = await response.json();
                        if (data.status === 'success') {
                            if (!silent) {
                                toastr.success(data.message);
                                const dangling = this.steps.flatMap(s => this.stepUnknownVariables(s));
                                if (dangling.length) {
                                    toastr.warning(
                                        '{{ __('These variables are not produced by any earlier step and will fail at run time') }}: ' +
                                        [...new Set(dangling)].join(', ')
                                    );
                                }
                            }
                            if (!this.workflowId && data.data?.id) {
                                this.workflowId = data.data.id;
                                history.replaceState(null, '', `/dashboard/user/ai-agent/workflows/${data.data.id}/edit`);
                                const avatarToSet = this.workflowAvatarUrl ?? this.avatarPresets[0]?.url;
                                if (avatarToSet) {
                                    this.selectAvatarPreset(avatarToSet);
                                }
                            }
                            return true;
                        } else {
                            const errors = data.errors ? Object.values(data.errors).flat().join('\n') : (data.message || '{{ trans('An error occurred.') }}');
                            toastr.error(errors);
                            return false;
                        }
                    } catch (e) {
                        toastr.error('{{ trans('An error occurred.') }}');
                        return false;
                    } finally {
                        this.saving = false;
                    }
                },

                async toggleStatus() {
                    const saved = await this.saveWorkflow(true);
                    if (!saved || !this.workflowId) return;
                    try {
                        const response = await fetch(`/dashboard/user/ai-agent/workflows/${this.workflowId}/toggle-status`, {
                            method: 'PATCH',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                            },
                        });
                        const data = await response.json();
                        if (data.status === 'success') {
                            this.status = data.data.status;
                            toastr[data.data.status === 'active' ? 'success' : 'warning'](
                                data.data.status === 'active' ?
                                '{{ trans('Agent is now active!') }}' :
                                '{{ trans('Agent is now paused.') }}'
                            );
                        } else {
                            toastr.error(data.message || '{{ trans('An error occurred.') }}');
                        }
                    } catch (e) {
                        toastr.error('{{ trans('An error occurred.') }}');
                    }
                },

                // Copilot
                async sendCopilotMessage() {
                    const msg = this.copilotInput.trim();

                    if (!msg) return;
                    if (this.copilotLoading) return;

                    this.copilotInput = '';
                    this.copilotMessages.push({
                        id: Date.now(),
                        role: 'user',
                        content: msg
                    });
                    this.copilotLoading = true;

                    // Save workflow first to get an ID
                    if (!this.workflowId) {
                        await this.saveWorkflow(true);
                    }

                    if (!this.workflowId) {
                        toastr.error('{{ trans('Please save the workflow first.') }}');
                        this.copilotLoading = false;
                        return;
                    }

                    // Push a placeholder; track its index for reactive updates
                    this.copilotMessages.push({
                        id: Date.now() + 1,
                        role: 'assistant',
                        content: '',
                        toolCalls: [],
                        steps: []
                    });
                    const assistantIdx = this.copilotMessages.length - 1;

                    const updateAssistant = (patch) => {
                        this.copilotMessages[assistantIdx] = Object.assign({}, this.copilotMessages[assistantIdx], patch);
                    };

                    const getSteps = () => this.copilotMessages[assistantIdx].steps;

                    try {
                        const response = await fetch(`/dashboard/user/ai-agent/workflows/${this.workflowId}/copilot/chat`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'text/event-stream',
                            },
                            body: JSON.stringify({
                                message: msg,
                                steps: this.steps,
                                channels: this.channels,
                                connectors: this.connectors,
                                trigger_type: this.triggerType,
                                trigger_config: this.triggerConfig,
                            }),
                        });

                        const reader = response.body.getReader();
                        const decoder = new TextDecoder();
                        let copilotMutated = false;

                        while (true) {
                            const {
                                done,
                                value
                            } = await reader.read();
                            if (done) break;

                            const text = decoder.decode(value);
                            const lines = text.split('\n').filter(l => l.startsWith('data: '));

                            for (const line of lines) {
                                try {
                                    const event = JSON.parse(line.slice(6));
                                    const steps = [...getSteps()];

                                    console.log(event)

                                    if (event.type === 'thinking_start') {
                                        steps.push({
                                            type: 'thinking',
                                            status: 'pending',
                                            content: '',
                                            startedAt: event.payload?.started_at ?? Date.now(),
                                            durationMs: null,
                                            expanded: false
                                        });
                                        updateAssistant({
                                            steps
                                        });
                                    } else if (event.type === 'thinking_delta') {
                                        const idx = steps.map(s => s.type).lastIndexOf('thinking');
                                        if (idx !== -1) {
                                            steps[idx] = {
                                                ...steps[idx],
                                                content: (steps[idx].content ?? '') + (event.payload?.text ?? '')
                                            };
                                            updateAssistant({
                                                steps
                                            });
                                        }
                                    } else if (event.type === 'thinking_end') {
                                        const idx = steps.map(s => s.type).lastIndexOf('thinking');
                                        if (idx !== -1) {
                                            const startedAt = steps[idx].startedAt;
                                            const serverDuration = event.payload?.duration_ms ?? null;
                                            const localDuration = startedAt ? (Date.now() - startedAt) : null;
                                            steps[idx] = {
                                                ...steps[idx],
                                                status: 'done',
                                                durationMs: serverDuration ?? localDuration
                                            };
                                        }
                                        updateAssistant({
                                            steps
                                        });
                                    } else if (event.type === 'text_delta') {
                                        const text = event.payload?.text ?? event.payload ?? '';
                                        const lastStep = steps[steps.length - 1];
                                        if (lastStep && lastStep.type === 'text') {
                                            steps[steps.length - 1] = {
                                                ...lastStep,
                                                content: lastStep.content + text
                                            };
                                        } else {
                                            steps.push({
                                                type: 'text',
                                                content: text
                                            });
                                        }
                                        updateAssistant({
                                            steps,
                                            content: this.copilotMessages[assistantIdx].content + text
                                        });
                                    } else if (event.type === 'tool_call_start') {
                                        steps.push({
                                            type: 'tool_call',
                                            name: event.payload.name,
                                            id: event.payload.id,
                                            args: {},
                                            status: 'pending',
                                            expanded: false
                                        });
                                        updateAssistant({
                                            steps
                                        });
                                    } else if (event.type === 'tool_call_end') {
                                        const tcIdx = steps.findIndex(s => s.type === 'tool_call' && s.id === event.payload.id);
                                        if (tcIdx !== -1) {
                                            steps[tcIdx] = {
                                                ...steps[tcIdx],
                                                args: event.payload.args ?? {},
                                                status: event.payload.status ?? 'success'
                                            };
                                        }
                                        updateAssistant({
                                            steps,
                                            toolCalls: [...this.copilotMessages[assistantIdx].toolCalls, {
                                                name: event.payload.name,
                                                args: event.payload.args ?? {}
                                            }]
                                        });
                                        this.applyCopilotMutation({
                                            name: event.payload.name,
                                            args: event.payload.args ?? {}
                                        });
                                        copilotMutated = true;
                                    } else if (event.type === 'action_buttons') {
                                        const abSteps = [...getSteps()];
                                        abSteps.push({
                                            type: 'action_buttons',
                                            buttons: event.payload.buttons ?? []
                                        });
                                        updateAssistant({
                                            steps: abSteps
                                        });
                                    } else if (event.type === 'error') {
                                        const errSteps = [...getSteps()];
                                        const thIdx = errSteps.map(s => s.type).lastIndexOf('thinking');
                                        if (thIdx !== -1) {
                                            errSteps[thIdx] = {
                                                ...errSteps[thIdx],
                                                status: 'done'
                                            };
                                        }
                                        const errMsg = event.payload?.message ?? '{{ trans('An error occurred. Please try again.') }}';
                                        errSteps.push({
                                            type: 'text',
                                            content: errMsg
                                        });
                                        updateAssistant({
                                            steps: errSteps,
                                            content: errMsg
                                        });
                                    } else if (event.type === 'tool_call') {
                                        // Legacy event type — backwards compat
                                        const toolCalls = [...this.copilotMessages[assistantIdx].toolCalls, event.payload];
                                        updateAssistant({
                                            toolCalls
                                        });
                                        this.applyCopilotMutation(event.payload);
                                        copilotMutated = true;
                                    }
                                } catch {}
                            }
                        }

                        if (copilotMutated) {
                            this._pushHistory();
                        }
                    } catch (e) {
                        updateAssistant({
                            content: '{{ trans('An error occurred. Please try again.') }}'
                        });
                    } finally {
                        this.copilotLoading = false;
                    }
                },

                toolCallLabel(step) {
                    const args = step.args ?? {};
                    const appTypeMap = {
                        ai_call: 'AI Call',
                        send_message: 'Send Message',
                        generate_report: 'Report',
                        path: 'Path',
                        external_chatbot: 'Chatbot',
                        marketing_bot: 'Marketing Bot',
                        social_media_agent: 'Social Media',
                        crm_agent: 'CRM',
                        gmail: 'Gmail',
                        outlook: 'Outlook',
                    };
                    const typeLabel = appTypeMap[args.type] ?? appTypeMap[args.app] ?? '';
                    switch (step.name) {
                        case 'add_step':
                            return typeLabel ? `${typeLabel} step added` : 'Step added';
                        case 'update_step': {
                            const cfg = args.config ?? {};
                            const event = (cfg.action_event ?? '').toLowerCase();
                            const cfgType = event.includes('gmail') ? 'Gmail' :
                                event.includes('outlook') ? 'Outlook' :
                                'system_prompt' in cfg ? 'AI Call' :
                                'message' in cfg && 'channel_id' in cfg ? 'Send Message' :
                                '';
                            return cfgType ? `${cfgType} step updated` : 'Step updated';
                        }
                        case 'delete_step':
                            return 'Step removed';
                        case 'reorder_steps':
                            return 'Steps reordered';
                        case 'set_trigger': {
                            const tt = (args.trigger_type ?? '').replace(/_/g, ' ');
                            return tt ? `Trigger set: ${tt}` : 'Trigger set';
                        }
                        default:
                            return step.name.replace(/_/g, ' ');
                    }
                },

                applyCopilotMutation(toolCall) {
                    if (toolCall.name === 'add_step') {
                        const args = toolCall.args ?? {};
                        const outputTypes = ['ai_call', 'generate_report', 'external_chatbot', 'marketing_bot', 'social_media_agent', 'crm_agent', 'gmail', 'outlook'];
                        const config = {
                            ...(args.config ?? {})
                        };
                        if (outputTypes.includes(args.type) && !config.store_output_as) {
                            config.store_output_as = 'step_' + crypto.randomUUID().replace(/-/g, '').substring(0, 6);
                        }
                        if (args.type === 'ai_call') {
                            if (!config.system_prompt) config.system_prompt = 'You are a helpful assistant.';
                            if (!config.user_message) config.user_message = '{message_text}';
                        }
                        if (args.type === 'send_message') {
                            if (!config.channel_id && this.triggerConfig.channel_id) config.channel_id = this.triggerConfig.channel_id;
                            if (!config.recipient_id) config.recipient_id = '{sender_id}';
                            if (!config.message) {
                                const aiStep = this.steps.find(s => s.type === 'ai_call' && s.config?.store_output_as);
                                config.message = aiStep ? `{${aiStep.config.store_output_as}}` : '{ai_response}';
                            }
                        }
                        const step = {
                            id: args.id || crypto.randomUUID(),
                            type: args.type ?? '',
                            app: args.app ?? this.appFromType(args.type ?? ''),
                            action: args.action ?? args.type ?? '',
                            config,
                            order: args.order ?? this.steps.length,
                        };
                        this.steps.splice(step.order, 0, step);
                        this.steps = this.steps.map((s, i) => ({
                            ...s,
                            order: i
                        }));
                    } else if (toolCall.name === 'update_step') {
                        const args = toolCall.args ?? {};
                        this.steps = this.steps.map(s =>
                            s.id === args.id ? {
                                ...s,
                                config: {
                                    ...s.config,
                                    ...(args.config ?? {})
                                }
                            } : s
                        );
                    } else if (toolCall.name === 'delete_step') {
                        this.steps = this.steps.filter(s => s.id !== toolCall.args?.id).map((s, i) => ({
                            ...s,
                            order: i
                        }));
                    } else if (toolCall.name === 'reorder_steps') {
                        const ids = toolCall.args?.ids ?? [];
                        const ordered = ids.map(id => this.steps.find(s => s.id === id)).filter(Boolean);
                        this.steps = ordered.map((s, i) => ({
                            ...s,
                            order: i
                        }));
                    } else if (toolCall.name === 'set_trigger') {
                        const args = toolCall.args ?? {};
                        if (args.trigger_type) this.triggerType = args.trigger_type;
                        if (args.trigger_config) this.triggerConfig = {
                            ...this.triggerConfig,
                            ...args.trigger_config
                        };
                        this.triggerConfigured = true;
                    }
                    this.$nextTick(() => this.saveWorkflow(true));
                },

                handleCopilotAction(action, args) {
                    args = args ?? {};
                    switch (action) {
                        case 'open_trigger_panel':
                            this.openStepSettings('trigger');
                            break;
                        case 'open_channel_modal':
                            this.toggleChannelsModal(true);
                            this.channelModal.tab = 'create';
                            if (args.channel_type) {
                                this.channelModal.type = args.channel_type;
                            }
                            break;
                        case 'open_connectors_modal':
                            this.toggleConnectorsModal(true);
                            this.connectorsModal.tab = args.connector_type ? 'available' : 'list';
                            break;
                        case 'open_step_settings':
                            if (args.step_id) {
                                this.openStepSettings(args.step_id);
                            }
                            break;
                        case 'open_test_panel':
                            this.testPanelOpen = true;
                            break;
                        case 'open_workflow_settings':
                            this.openWorkflowSettings();
                            break;
                    }
                },

                renderMarkdown(content) {
                    if (!content) return '';
                    if (typeof window.markdownit === 'undefined') return content;
                    const md = window.markdownit({
                        breaks: true,
                        linkify: true
                    });
                    return md.render(content);
                },

                formatThinkingDuration(ms) {
                    if (ms === null || ms === undefined) return '';
                    if (ms < 1000) return `${ms}ms`;
                    const s = ms / 1000;
                    if (s < 60) return `${s.toFixed(s < 10 ? 1 : 0)}s`;
                    const m = Math.floor(s / 60);
                    const rem = Math.round(s % 60);
                    return rem ? `${m}m ${rem}s` : `${m}m`;
                },

                zoomIn() {
                    this.zoom = Math.min(150, this.zoom + 10);
                },
                zoomOut() {
                    this.zoom = Math.max(30, this.zoom - 10);
                },
                resetZoom() {
                    this.zoom = 83;
                },

                openWorkflowSettings() {
                    this.selectedStepId = 'workflow_settings';
                    Alpine.$data(document.querySelector('#ai-agent-workflow-details-modal')).toggleModal(true);
                    this.testPanelOpen = false;
                },

                // ── Channel modal ──────────────────────────────────────────────────────

                resetChannelModal() {
                    this.channelModal = {
                        tab: 'list',
                        name: '',
                        type: 'telegram',
                        credentials: {},
                        loading: false,
                        errors: {}
                    };

                    this.toggleChannelsModal(false);
                },

                async createChannel() {
                    this.channelModal.loading = true;
                    this.channelModal.errors = {};
                    try {
                        const response = await fetch('{{ route('dashboard.user.ai-agent.channels.store') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify({
                                name: this.channelModal.name,
                                type: this.channelModal.type,
                                credentials: this.channelModal.credentials,
                            }),
                        });
                        const data = await response.json();
                        if (!response.ok) {
                            if (data.errors) {
                                this.channelModal.errors = data.errors;
                            } else {
                                this.channelModal.errors._global = data.message || '{{ __('Something went wrong.') }}';
                            }
                            return;
                        }
                        this.channels.push(data.channel);
                        this.triggerConfig.channel_id = data.channel.id;
                        toastr.success(data.message);
                        this.channelModal = {
                            tab: 'list',
                            name: '',
                            type: 'telegram',
                            credentials: {},
                            loading: false,
                            errors: {}
                        };
                        this.toggleChannelsModal(true);
                    } catch {
                        this.channelModal.errors._global = '{{ __('Something went wrong.') }}';
                    } finally {
                        this.channelModal.loading = false;
                    }
                },

                async deleteChannel(id) {
                    if (!confirm('{{ __('Remove this channel?') }}')) {
                        return;
                    }
                    const ch = this.channels.find(c => c.id === id);
                    if (!ch) {
                        return;
                    }
                    try {
                        const res = await fetch(ch.delete_url, {
                            method: 'DELETE',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                        });
                        const data = await res.json();
                        if (res.ok) {
                            this.channels = this.channels.filter(c => c.id !== id);
                            if (this.triggerConfig.channel_id == id) {
                                this.triggerConfig.channel_id = null;
                            }
                            toastr.success(data.message || '{{ __('Channel removed.') }}');
                        } else {
                            toastr.error(data.message || '{{ __('Failed to remove channel.') }}');
                        }
                    } catch {
                        toastr.error('{{ __('Failed to remove channel.') }}');
                    }
                },

                async refreshChannelWebhook(url) {
                    try {
                        const res = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                        });
                        const data = await res.json();
                        if (res.ok) {
                            toastr.success(data.message || '{{ __('Webhook refreshed.') }}');
                        } else {
                            toastr.error(data.message || '{{ __('Failed to refresh webhook.') }}');
                        }
                    } catch {
                        toastr.error('{{ __('Failed to refresh webhook.') }}');
                    }
                },

                // ── Connector management ──────────────────────────────────────────────

                toggleConnectorsModal(state) {
                    if (!this.connectorsModal.modalEl) {
                        this.connectorsModal.modalEl = document.querySelector('#ai-agent-connectors-modal');
                    }

                    Alpine.$data(this.connectorsModal.modalEl).toggleModal(state);
                },

                openConnectorPopup(url) {
                    const w = 600,
                        h = 700;
                    const left = Math.round(screen.width / 2 - w / 2);
                    const top = Math.round(screen.height / 2 - h / 2);
                    const popup = window.open(url, 'connectorOAuth', `width=${w},height=${h},left=${left},top=${top},resizable=yes,scrollbars=yes`);

                    const handler = (event) => {
                        if (event.origin !== window.location.origin) {
                            return;
                        }
                        if (!event.data || event.data.type !== 'connector_connected') {
                            return;
                        }

                        window.removeEventListener('message', handler);

                        if (event.data.success && event.data.connector) {
                            const incoming = event.data.connector;
                            const idx = this.connectors.findIndex(c => c.id === incoming.id);
                            if (idx !== -1) {
                                this.connectors[idx] = incoming;
                            } else {
                                this.connectors.push(incoming);
                            }
                            toastr.success(event.data.message || '{{ __('Connector connected.') }}');
                        } else {
                            toastr.error(event.data.message || '{{ __('Connection failed.') }}');
                        }
                    };

                    window.addEventListener('message', handler);
                },

                async disconnectConnector(id, deleteUrl) {
                    if (!confirm('{{ __('Disconnect this account?') }}')) {
                        return;
                    }
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

                // ── Memory management ─────────────────────────────────────────────────

                async createBuilderMemory() {
                    if (!this.memoryForm.text.trim()) {
                        return;
                    }
                    this.memoryForm.loading = true;
                    try {
                        const response = await fetch('{{ route('dashboard.user.ai-agent.memory.store') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify({
                                memory: this.memoryForm.text
                            }),
                        });
                        const data = await response.json();
                        if (!response.ok) {
                            toastr.error(data.message || '{{ __('Something went wrong.') }}');
                            return;
                        }
                        this.memories.unshift(data.memory);
                        this.memoryForm = {
                            open: false,
                            text: '',
                            loading: false
                        };
                        toastr.success(data.message);
                    } catch {
                        toastr.error('{{ __('Something went wrong.') }}');
                    } finally {
                        this.memoryForm.loading = false;
                    }
                },

                async deleteBuilderMemory(id) {
                    if (!confirm('{{ __('Are you sure? This is permanent.') }}')) {
                        return;
                    }
                    try {
                        const url = '{{ route('dashboard.user.ai-agent.memory.destroy', ':id') }}'.replace(':id', id);
                        const response = await fetch(url, {
                            method: 'DELETE',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                        });
                        const data = await response.json();
                        if (!response.ok) {
                            toastr.error(data.message || '{{ __('Something went wrong.') }}');
                            return;
                        }
                        this.memories = this.memories.filter(m => m.id !== id);
                        toastr.success(data.message);
                    } catch {
                        toastr.error('{{ __('Something went wrong.') }}');
                    }
                },

                // ── Avatar upload ─────────────────────────────────────────────────────

                async selectAvatarPreset(url) {
                    if (!this.workflowId) {
                        const saved = await this.saveWorkflow(true);
                        if (!saved) {
                            return;
                        }
                    }
                    this.avatarUploading = true;
                    try {
                        const response = await fetch(`/dashboard/user/ai-agent/workflows/${this.workflowId}/avatar`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify({
                                avatar_url: url
                            }),
                        });
                        const data = await response.json();
                        if (data.status === 'success') {
                            this.workflowAvatarUrl = url;
                        } else {
                            toastr.error(data.message || '{{ __('Something went wrong.') }}');
                        }
                    } catch {
                        toastr.error('{{ __('Something went wrong.') }}');
                    } finally {
                        this.avatarUploading = false;
                    }
                },

                async uploadAvatar(event) {
                    const file = event.target.files?.[0];
                    if (!file || !this.workflowId) {
                        if (!this.workflowId) {
                            const saved = await this.saveWorkflow(true);
                            if (!saved) {
                                event.target.value = '';
                                return;
                            }
                        }
                    }
                    this.avatarUploading = true;
                    try {
                        const formData = new FormData();
                        formData.append('avatar', file);
                        formData.append('_method', 'POST');
                        const url = `/dashboard/user/ai-agent/workflows/${this.workflowId}/avatar`;
                        const response = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: formData,
                        });
                        const data = await response.json();
                        if (data.status === 'success') {
                            this.workflowAvatarUrl = data.url;
                            toastr.success('{{ __('Avatar updated.') }}');
                        } else {
                            toastr.error(data.message || '{{ __('Upload failed.') }}');
                        }
                    } catch {
                        toastr.error('{{ __('Upload failed.') }}');
                    } finally {
                        this.avatarUploading = false;
                        event.target.value = '';
                    }
                },

                _snap() {
                    return JSON.parse(JSON.stringify({
                        workflowName: this.workflowName,
                        workflowRole: this.workflowRole,
                        triggerConfigured: this.triggerConfigured,
                        triggerType: this.triggerType,
                        triggerConfig: this.triggerConfig,
                        steps: this.steps,
                    }));
                },

                _pushHistory() {
                    const snap = this._snap();
                    this._history = this._history.slice(0, this._historyIdx + 1);
                    this._history.push(snap);
                    if (this._history.length > this._historyMax) {
                        this._history.shift();
                    }
                    this._historyIdx = this._history.length - 1;
                },

                _restoreSnap(snap) {
                    this._inUndoRedo = true;
                    this.workflowName = snap.workflowName;
                    this.workflowRole = snap.workflowRole ?? '';
                    this.triggerConfigured = snap.triggerConfigured;
                    this.triggerType = snap.triggerType;
                    this.triggerConfig = JSON.parse(JSON.stringify(snap.triggerConfig));
                    this.steps = JSON.parse(JSON.stringify(snap.steps));
                    this._inUndoRedo = false;
                },

                undo() {
                    if (!this.canUndo) return;
                    this._panelSnapshot = null;
                    this._historyIdx--;
                    this._restoreSnap(this._history[this._historyIdx]);
                    this.settingsPanelOpen = false;
                    this.selectedStepId = null;
                },

                redo() {
                    if (!this.canRedo) return;
                    this._panelSnapshot = null;
                    this._historyIdx++;
                    this._restoreSnap(this._history[this._historyIdx]);
                    this.settingsPanelOpen = false;
                    this.selectedStepId = null;
                },
            }));

            Alpine.data('workflowAiGenerator', () => ({
                open: false,
                loading: false,
                prompt: '',
                error: null,
                generated: false,

                async generate() {
                    if (!this.prompt.trim()) return;
                    this.loading = true;
                    this.error = null;
                    try {
                        const response = await fetch('/dashboard/user/ai-agent/workflows/generate-from-prompt', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                prompt: this.prompt,
                                channels: window.__workflowData?.channels ?? []
                            }),
                        });
                        const data = await response.json();
                        if (data.status === 'success') {
                            window.dispatchEvent(new CustomEvent('workflow-generated', {
                                detail: data.data
                            }));
                            this.generated = true;
                            this.open = false;
                        } else {
                            this.error = data.message || '{{ trans('An error occurred. Please try again.') }}';
                        }
                    } catch {
                        this.error = '{{ trans('Network error. Please try again.') }}';
                    } finally {
                        this.loading = false;
                    }
                },

                resetGenerator() {
                    this.generated = false;
                    this.open = true;
                    this.error = null;
                },
            }));

            Alpine.data('variableTagInput', ({
                configKey,
                stepRef,
                multiline = false
            }) => ({
                menuOpen: false,
                multiline,

                get modelValue() {
                    return stepRef?.config?.[configKey] ?? '';
                },
                set modelValue(v) {
                    if (stepRef?.config) {
                        stepRef.config[configKey] = v;
                    }
                },

                init() {
                    this.$nextTick(() => this.render());
                    this.$watch(() => stepRef?.config?.[configKey], () => {
                        if (this.htmlToString() !== this.modelValue) {
                            this.render();
                        }
                    });
                },

                render() {
                    if (!this.$refs.editor) {
                        return;
                    }
                    this.$refs.editor.innerHTML = this.stringToHtml(this.modelValue);
                    this._updateEmpty();
                },

                _updateEmpty() {
                    if (!this.$refs.editor) {
                        return;
                    }
                    this.$refs.editor.classList.toggle('vti-editor-empty', !this.modelValue);
                },

                stringToHtml(text) {
                    return (text ?? '')
                        .replace(/[&<>]/g, c => ({
                            '&': '&amp;',
                            '<': '&lt;',
                            '>': '&gt;'
                        } [c]))
                        .replace(/\{([^}]+)\}/g, (_, varName) => {
                            const label = this.getVarLabel(varName);
                            return '<span contenteditable="false" data-var="' + varName + '" ' +
                                'class="inline-flex items-center gap-0.5 rounded bg-primary/10 px-1.5 py-px font-mono text-[10px] font-medium text-primary mx-0.5 select-none cursor-default">' +
                                label +
                                '<button type="button" data-remove-chip class="ms-0.5 leading-none text-primary/50 hover:text-primary">&times;</button></span>';
                        });
                },

                htmlToString() {
                    if (!this.$refs.editor) {
                        return '';
                    }
                    const clone = this.$refs.editor.cloneNode(true);
                    clone.querySelectorAll('[data-var]').forEach(el => el.replaceWith('{' + el.dataset.var+'}'));
                    return clone.textContent;
                },

                _parentVars() {
                    let el = this.$el?.parentElement;
                    while (el) {
                        try {
                            const d = Alpine.$data(el);
                            if (d && Array.isArray(d.availableVariables)) {
                                return d.availableVariables;
                            }
                        } catch (e) {}
                        el = el.parentElement;
                    }
                    return [];
                },

                getVarLabel(varName) {
                    return this._parentVars()
                        .find(v => v.name === '{' + varName + '}')?.description ?? varName;
                },

                syncToModel() {
                    this.modelValue = this.htmlToString();
                    this._updateEmpty();
                    if (this.$root?._pushHistory) {
                        this.$root._pushHistory();
                    }
                    if (this.$root?._snap) {
                        this.$root._panelSnapshot = this.$root._snap();
                    }
                },

                insertVariable(v) {
                    this.$refs.editor.focus();
                    const varName = v.name.replace(/[{}]/g, '');
                    const chipHtml = this.stringToHtml('{' + varName + '}') + '​';
                    const sel = window.getSelection();
                    if (sel?.rangeCount) {
                        const range = sel.getRangeAt(0);
                        if (this.$refs.editor.contains(range.commonAncestorContainer)) {
                            range.deleteContents();
                            const temp = document.createElement('span');
                            temp.innerHTML = chipHtml;
                            const frag = document.createDocumentFragment();
                            while (temp.firstChild) {
                                frag.appendChild(temp.firstChild);
                            }
                            range.insertNode(frag);
                            sel.collapseToEnd();
                        } else {
                            this.$refs.editor.innerHTML += chipHtml;
                        }
                    } else {
                        this.$refs.editor.innerHTML += chipHtml;
                    }
                    this.syncToModel();
                    this.menuOpen = false;
                },

                removeChip(el) {
                    el?.remove();
                    this.syncToModel();
                },

                handlePaste(e) {
                    e.preventDefault();
                    const text = e.clipboardData.getData('text/plain');
                    document.execCommand('insertText', false, text);
                },

                handleKeydown(e) {
                    if (e.key === 'Backspace') {
                        const sel = window.getSelection();
                        if (sel?.rangeCount) {
                            const range = sel.getRangeAt(0);
                            if (range.collapsed && range.startOffset === 0) {
                                const prev = range.startContainer.previousSibling;
                                if (prev?.dataset?.var) {
                                    e.preventDefault();
                                    prev.remove();
                                    this.syncToModel();
                                }
                            }
                        }
                    }
                },

                handleClick(e) {
                    if (e.target.closest('[data-remove-chip]')) {
                        this.removeChip(e.target.closest('[data-var]'));
                    }
                },
            }));
        });
    </script>
@endpush
