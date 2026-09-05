@extends('panel.layout.app', [
    'disable_tblr' => true,
    'disable_header' => true,
    'disable_footer' => true,
    'disable_titlebar' => true,
    'layout_wide' => true,
    'disable_mobile_bottom_menu' => true,
])
@section('title', __('Channel Messages'))
@section('titlebar_actions', '')

@push('css')
    <style>
        @media (min-width: 992px) {
            .lqd-header {
                display: none !important;
            }

            .focus-mode .lqd-page-content-container {
                max-width: 100% !important;
            }
        }
    </style>
@endpush

@push('after-body-open')
    <script>
        (() => {
            document.body.classList.add('navbar-shrinked');
        })();
    </script>
@endpush

@section('content')
    <div
        class="lqd-ext-chatbot-history flex h-screen flex-col max-lg:[--header-height:65px] lg:flex-row"
        x-data="aiAgentMessages"
    >
        {{-- Left sidebar --}}
        <div
            class="lqd-ext-chatbot-history-sidebar group/history-sidebar relative flex shrink-0 flex-col bg-foreground/[3%] lg:w-[clamp(250px,27%,400px)]"
            :class="{ 'mobile-dropdown-open': mobileDropdownOpen }"
        >
            @include('ai-agent::messages.particles.conversations-filter')

            <div
                class="transition-all max-lg:fixed max-lg:bottom-0 max-lg:top-[calc(var(--header-height)+3.5rem)] max-lg:z-10 max-lg:flex max-lg:h-0 max-lg:w-full max-lg:flex-col max-lg:overflow-hidden max-lg:bg-background/90 max-lg:backdrop-blur-lg lg:contents max-lg:[&.active]:h-full"
                :class="{ 'active': mobile.filtersVisible }"
            >
                @include('ai-agent::messages.particles.conversations-channel-filter')

                @include('ai-agent::messages.particles.conversations-list')
            </div>
        </div>

        {{-- New Chat modal --}}
        <div
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 backdrop-blur-sm sm:items-center"
            x-show="newChat.modalOpen"
            x-cloak
            @keydown.escape.window="newChat.modalOpen = false"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
        >
            <div
                class="w-full max-w-lg rounded-t-2xl bg-background p-6 shadow-2xl sm:rounded-2xl"
                @click.stop
            >
                <div class="mb-5 flex items-center justify-between">
                    <h3 class="font-heading text-base font-semibold text-heading-foreground">{{ __('New Chat') }}</h3>
                    <x-button
                        class="size-7 bg-foreground/10"
                        size="none"
                        variant="none"
                        @click.prevent="newChat.modalOpen = false"
                    >
                        <x-tabler-x class="size-4" />
                    </x-button>
                </div>

                <div class="space-y-4">
                    <x-forms.input
                        label="{{ __('Agent') }}"
                        name="new_chat_workflow"
                        type="select"
                        x-model="newChat.workflowId"
                    >
                        <option value="">{{ __('Select an agent...') }}</option>
                        @foreach ($workflows as $workflow)
                            <option value="{{ $workflow->id }}">{{ $workflow->name }}</option>
                        @endforeach
                    </x-forms.input>

                    <x-forms.input
                        label="{{ __('Message') }}"
                        name="new_chat_message"
                        type="textarea"
                        rows="3"
                        x-model="newChat.message"
                        placeholder="{{ __('Type your message...') }}"
                        @keydown.enter.prevent="if(!$event.shiftKey) submitNewChat(); else newChat.message += '\n'"
                    />
                </div>

                <div class="mt-5 flex justify-end gap-3">
                    <x-button
                        variant="outline"
                        @click.prevent="newChat.modalOpen = false"
                    >
                        {{ __('Cancel') }}
                    </x-button>
                    <x-button
                        hover-variant="primary"
                        disabled
                        ::disabled="!newChat.workflowId || !newChat.message.trim() || newChat.sending"
                        @click.prevent="submitNewChat"
                    >
                        <x-tabler-send class="size-4" />
                        <span x-show="!newChat.sending">{{ __('Send') }}</span>
                        <span
                            x-show="newChat.sending"
                            x-cloak
                        >{{ __('Sending...') }}</span>
                    </x-button>
                </div>
            </div>
        </div>

        {{-- Middle: message thread --}}
        <div class="flex h-full grow flex-col lg:w-1/2">
            <div
                class="lqd-ext-chatbot-history-content-wrap flex grow flex-col overflow-y-auto"
                x-ref="historyContentWrap"
            >
                @include('ai-agent::messages.particles.messages-header')

                <div
                    class="lqd-ext-chatbot-history-messages relative flex grow flex-col gap-2 pt-10"
                    x-ref="historyMessages"
                >
                    <div class="mt-auto space-y-2 px-4 max-lg:pb-4 xl:px-10">

                        <template x-if="!activeConv && !threadFetching">
                            <div class="flex flex-col items-center justify-center gap-3 py-20 text-center text-foreground/40">
                                <x-tabler-messages class="size-12 opacity-30" />
                                <p class="text-sm">{{ __('Choose a conversation to view messages.') }}</p>
                            </div>
                        </template>

                        <template x-if="threadFetching">
                            <div class="flex items-center justify-center py-10">
                                <x-tabler-refresh class="size-5 animate-spin text-foreground/40" />
                            </div>
                        </template>

                        @include('ai-agent::messages.particles.messages-list')
                    </div>
                </div>
            </div>

            @include('ai-agent::messages.particles.messages-form')
        </div>

        <div
            class="lqd-ext-chatbot-contact-info flex flex-col border-s transition-all max-lg:fixed max-lg:bottom-0 max-lg:top-[calc(var(--header-height)+3.5rem)] max-lg:z-10 max-lg:h-0 max-lg:max-h-[calc(100%-var(--header-height)-3.5rem)] max-lg:w-full max-lg:overflow-hidden max-lg:bg-background/90 max-lg:backdrop-blur-lg lg:w-[clamp(250px,27%,400px)] max-lg:[&.active]:h-full"
            :class="{ 'active': mobile.contactInfoVisible }"
        >
            @include('ai-agent::messages.particles.contact-info-head')

            <div class="grid grow grid-cols-1 place-items-start overflow-y-auto">
                @include('ai-agent::messages.particles.contact-info-tab-details')
                @include('ai-agent::messages.particles.contact-info-tab-history')
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script src="{{ custom_theme_url('/assets/libs/markdownit/markdown-it.min.js') }}"></script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('aiAgentMessages', () => ({
                conversations: [],
                fetching: false,
                selectedChannelId: '',
                activeConv: null,
                thread: [],
                threadFetching: false,
                mobileDropdownOpen: false,
                conversationsSearchFormVisible: false,
                messagesSearchFormVisible: false,
                conversationSearch: '',
                messageSearch: '',
                mobile: {
                    filtersVisible: false,
                    contactInfoVisible: false,
                },
                contactInfo: {
                    activeTab: 'details',
                },
                showExportOptions: false,
                logsLoading: false,
                workflowRuns: [],

                conversationsUrl: @json(route('dashboard.user.ai-agent.messages.conversations')),
                threadBaseUrl: @json(route('dashboard.user.ai-agent.messages.index')),
                workflowsBaseUrl: @json(route('dashboard.user.ai-agent.workflows.index')),
                sendUrl: @json(route('dashboard.user.ai-agent.messages.send')),
                replyBaseUrl: @json(route('dashboard.user.ai-agent.messages.index')),
                sending: false,

                newChat: {
                    modalOpen: false,
                    workflowId: '',
                    message: '',
                    sending: false,
                },

                get filteredConversations() {
                    if (!this.conversationSearch) return this.conversations;
                    const q = this.conversationSearch.toLowerCase();
                    return this.conversations.filter(c =>
                        (c.sender_id ?? '').toLowerCase().includes(q) ||
                        (c.last_message_preview ?? '').toLowerCase().includes(q)
                    );
                },

                get filteredThread() {
                    if (!this.messageSearch) return this.thread;
                    const q = this.messageSearch.toLowerCase();
                    return this.thread.filter(m => (m.content ?? '').toLowerCase().includes(q));
                },

                async init() {
                    await this.loadConversations();

                    const params = new URLSearchParams(window.location.search);
                    const requestedWfId = params.get('workflow_id');

                    if (requestedWfId) {
                        const existing = this.conversations.find(
                            c => c.sender_id === 'wf_' + requestedWfId
                        );
                        if (existing) {
                            await this.selectConversation(existing);
                        } else {
                            this.newChat.workflowId = requestedWfId;
                            this.newChat.modalOpen = true;
                        }
                        const cleanUrl = window.location.pathname + window.location.hash;
                        window.history.replaceState({}, '', cleanUrl);
                    } else {
                        this.activeConv = this.conversations[0] ?? null;
                        if (this.activeConv) {
                            await this.selectConversation(this.activeConv);
                        }
                    }

                    this.$watch('contactInfo.activeTab', (tab) => {
                        if (tab === 'logs' && this.activeConv) {
                            this.loadLogs();
                        }
                    });
                },

                async loadConversations() {
                    this.fetching = true;
                    this.activeConv = null;
                    this.thread = [];
                    try {
                        const params = new URLSearchParams();
                        if (this.selectedChannelId) {
                            params.set('channel_id', this.selectedChannelId);
                        }
                        const res = await fetch(`${this.conversationsUrl}?${params.toString()}`, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                        });
                        const data = await res.json();
                        this.conversations = data.data ?? [];
                    } finally {
                        this.fetching = false;
                    }
                },

                async selectConversation(conv) {
                    this.activeConv = conv;
                    this.thread = [];
                    this.messageSearch = '';
                    this.mobile.filtersVisible = false;
                    this.threadFetching = true;
                    this.workflowRuns = [];
                    try {
                        const url = `${this.threadBaseUrl}/${conv.id}/thread`;
                        const res = await fetch(url, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                        });
                        this.thread = await res.json();
                        this.$nextTick(() => {
                            const el = this.$refs.historyContentWrap;
                            if (el) el.scrollTop = el.scrollHeight;
                        });

                        if (this.contactInfo.activeTab === 'logs') {
                            this.loadLogs();
                        }
                    } finally {
                        this.threadFetching = false;
                    }
                },

                async sendMessage() {
                    const text = this.$refs.messageInput?.value?.trim();
                    if (!text || this.sending || !this.activeConv) return;

                    this.sending = true;
                    this.$refs.messageInput.value = '';

                    this.thread.push({
                        id: Date.now(),
                        direction: 'inbound',
                        content: text,
                        created_at: new Date().toISOString(),
                    });
                    this.$nextTick(() => {
                        const el = this.$refs.historyContentWrap;
                        if (el) el.scrollTop = el.scrollHeight;
                    });

                    try {
                        const isWebConv = this.activeConv.sender_id?.startsWith('wf_');

                        if (isWebConv) {
                            const wfId = parseInt(this.activeConv.sender_id.replace('wf_', ''));
                            const res = await fetch(this.sendUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body: JSON.stringify({
                                    workflow_id: wfId,
                                    message: text
                                }),
                            });
                            const data = await res.json();
                            if (data.status === 'error') {
                                toastr.error(data.message);
                                return;
                            }
                        } else {
                            const res = await fetch(`${this.replyBaseUrl}/${this.activeConv.id}/reply`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body: JSON.stringify({
                                    message: text
                                }),
                            });
                            const data = await res.json();
                            if (data.status === 'error') {
                                toastr.error(data.message);
                                return;
                            }
                        }

                        await this.selectConversation(this.activeConv);
                    } finally {
                        this.sending = false;
                    }
                },

                async submitNewChat() {
                    if (!this.newChat.workflowId || !this.newChat.message.trim() || this.newChat.sending) return;

                    this.newChat.sending = true;
                    const wfId = parseInt(this.newChat.workflowId);
                    const msg = this.newChat.message.trim();

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
                                workflow_id: wfId,
                                message: msg
                            }),
                        });
                        const data = await res.json();
                        if (data.status === 'error') {
                            toastr.error(data.message);
                            return;
                        }

                        this.newChat.modalOpen = false;
                        this.newChat.workflowId = '';
                        this.newChat.message = '';

                        await this.loadConversations();

                        const match = this.conversations.find(
                            c => c.sender_id === 'wf_' + wfId
                        );
                        if (match) {
                            await this.selectConversation(match);
                        }
                    } finally {
                        this.newChat.sending = false;
                    }
                },

                async pinConversation(id) {
                    if (!id) return;
                    const res = await fetch(`${this.threadBaseUrl}/${id}/pin`, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                    const data = await res.json();
                    const conv = this.conversations.find(c => c.id === id);
                    if (conv) conv.pinned = data.pinned;
                    if (this.activeConv?.id === id) this.activeConv.pinned = data.pinned;
                    this.sortConversations();
                },

                async closeConversation(id) {
                    if (!id) return;
                    const res = await fetch(`${this.threadBaseUrl}/${id}/close`, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                    const data = await res.json();
                    const conv = this.conversations.find(c => c.id === id);
                    if (conv) conv.closed_at = data.closed_at;
                    if (this.activeConv?.id === id) this.activeConv.closed_at = data.closed_at;
                },

                sortConversations() {
                    this.conversations.sort((a, b) => {
                        const pinA = a.pinned ?? 0;
                        const pinB = b.pinned ?? 0;
                        if (pinA !== pinB) return pinB - pinA;
                        const tA = a.last_message_at ? new Date(a.last_message_at).getTime() : 0;
                        const tB = b.last_message_at ? new Date(b.last_message_at).getTime() : 0;
                        return tB - tA;
                    });
                },

                async deleteConversation(id) {
                    if (!id) return;
                    if (!confirm(@json(__('Are you sure you want to delete this conversation?')))) return;
                    await fetch(`${this.threadBaseUrl}/${id}`, {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                    this.activeConv = null;
                    this.thread = [];
                    await this.loadConversations();
                },

                async loadLogs() {
                    if (!this.activeConv) return;
                    this.logsLoading = true;
                    try {
                        const workflowId = this.activeConv.agent?.id;
                        if (workflowId) {
                            const res = await fetch(`${this.workflowsBaseUrl}/${workflowId}/runs`, {
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                            });
                            const data = await res.json();
                            this.workflowRuns = data.data ?? [];
                        } else {
                            this.workflowRuns = [];
                        }
                    } finally {
                        this.logsLoading = false;
                    }
                },

                getShortDiffHumanTime(dateStr) {
                    return this.formatDate(dateStr);
                },

                onMessageFieldHitEnter(event) {
                    if (!event.shiftKey) {
                        this.sendMessage();
                    } else {
                        event.target.value += '\n';
                    }
                },

                onMessageFieldInput() {
                    const text = this.$refs.messageInput?.value?.trim();
                    if (text) {
                        this.$refs.submitBtn?.removeAttribute('disabled');
                    } else {
                        this.$refs.submitBtn?.setAttribute('disabled', 'disabled');
                    }
                },

                searchConversations(value) {
                    this.conversationSearch = value;
                },

                searchMessages(value) {
                    this.messageSearch = value;
                },

                getFormattedString(string) {
                    if (!('markdownit' in window) || !string) return '';

                    const renderer = window.markdownit({ html: true, breaks: true });

                    return renderer.render(string);
                },

                formatDate(dateStr) {
                    if (!dateStr) return '';
                    const d = new Date(dateStr);
                    const now = new Date();
                    const diffDays = Math.floor((now - d) / 86400000);
                    if (diffDays === 0) return d.toLocaleTimeString([], {
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    if (diffDays === 1) return @json(__('Yesterday'));
                    return d.toLocaleDateString([], {
                        month: 'short',
                        day: 'numeric'
                    });
                },

                formatTime(dateStr) {
                    if (!dateStr) return '';
                    return new Date(dateStr).toLocaleTimeString([], {
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                },
            }));
        });
    </script>
@endpush
