@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', __('Phone Call Agents'))
@section('titlebar_subtitle')
    {{ __('Let AI answer your phone calls and communicate naturally with your customers.') }}
@endsection
@section('titlebar_actions')
    <x-button
        href="#"
        variant="ghost-shadow"
        @click.prevent="$store.phoneCallAgentHistory.setOpen(true)"
        x-data="{}"
    >
        @lang('Call History')
    </x-button>

    <x-button
        href="#"
        @click.prevent="$store.phoneCallAgentEditor.setActiveAgent('new_agent', 1, true);"
        x-data="{}"
    >
        <x-tabler-plus class="size-4" />
        @lang('Add New Agent')
    </x-button>
@endsection

@section('content')
    <div class="py-10">
        <div
            class="lqd-phone-agent-edit"
            x-data="phoneCallAgentEditor"
            @keydown.escape.window="setActiveAgent(null)"
        >
            @include('phone-call-agent::home.agents-list', ['agents' => $agents])

            @include('phone-call-agent::home.edit-window.edit-window')
        </div>

        @include('phone-call-agent::home.call-history.call-history')
    </div>
@endsection

@push('script')
    <link
        rel="stylesheet"
        href="{{ custom_theme_url('/assets/libs/prism/prism.css') }}"
    />
    <script src="{{ custom_theme_url('/assets/libs/prism/prism.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/markdownit/markdown-it.min.js') }}"></script>

    <script>
        (() => {
            document.addEventListener('alpine:init', () => {
                Alpine.data('phoneCallAgentEditor', () => ({
                    agents: @json($agents),
                    elevenLabsVoices: @json($voices),
                    activeAgent: {},
                    prevActiveAgentId: null,
                    editingStep: 1,
                    submittingData: false,
                    agentTraining: null,
                    _previewAudio: null,
                    previewingVoice: null,
                    voiceQuery: '',
                    defaultFormInputs: {
                        id: '',
                        title: '{{ $setting->site_name . __(' Agent') }}',
                        welcome_message: '{{ __('Hello, how can I help you today?') }}',
                        instructions: '',
                        language: '',
                        voice_id: '',
                        phone_number: '',
                        phone_number_id: '',
                        ai_model: 'gpt-4o-mini',
                        provider: '{{ setting('phone_call_agent_provider', 'elevenlabs') }}',
                        max_call_duration: 300,
                        silence_timeout: 30,
                        active: true,
                        booking_enabled: false,
                        booking_provider: null,
                        booking_event_type_id: null,
                        booking_api_key: null,
                    },
                    formErrors: {},
                    phoneNumbersList: [],
                    phoneBusy: false,
                    phoneForm: {
                        import_type: 'twilio',
                        phone_number: '',
                        label: '',
                        twilio_account_sid: '',
                        twilio_auth_token: '',
                        sip_address: '',
                        sip_transport: 'auto',
                        sip_username: '',
                        sip_password: '',
                        exotel_account_sid: '',
                        exotel_api_key: '',
                        exotel_api_token: '',
                        exotel_subdomain: 'api.exotel.com',
                        exotel_app_id: '',
                        exotel_applet_url: '',
                    },
                    defaultPhoneForm() {
                        return {
                            import_type: 'twilio',
                            phone_number: '',
                            label: '',
                            twilio_account_sid: '',
                            twilio_auth_token: '',
                            sip_address: '',
                            sip_transport: 'auto',
                            sip_username: '',
                            sip_password: '',
                            exotel_account_sid: '',
                            exotel_api_key: '',
                            exotel_api_token: '',
                            exotel_subdomain: 'api.exotel.com',
                            exotel_app_id: '',
                            exotel_applet_url: '',
                        };
                    },
                    simulation: {
                        status: 'idle',
                        error: null,
                        conversation: null,
                    },
                    init() {
                        this.createNewAgentObj();
                        this.initFormErrors();
                        Alpine.store('phoneCallAgentEditor', this);
                        if (@json($autoOpen ?? null) === 'new') {
                            this.setActiveAgent('new_agent', 1, true);
                        }
                    },
                    createNewAgentObj() {
                        this.agents.data.unshift({
                            ...this.defaultFormInputs,
                            id: 'new_agent',
                        });
                    },
                    initFormErrors() {
                        Object.keys(this.defaultFormInputs).forEach(key => {
                            this.formErrors[key] = [];
                        });
                    },
                    get filteredElevenLabsVoices() {
                        const query = this.voiceQuery.toLowerCase();
                        if (!query) {
                            return this.elevenLabsVoices;
                        }
                        return this.elevenLabsVoices.filter(v =>
                            v.name.toLowerCase().includes(query) ||
                            (v.category && v.category.toLowerCase().includes(query)) ||
                            (v.labels?.language && v.labels.language.toLowerCase().includes(query))
                        );
                    },
                    previewUrlFor(voiceId) {
                        const voice = this.elevenLabsVoices.find(v => v.voice_id === voiceId);
                        return voice?.preview_url || null;
                    },
                    previewVoice(voiceId) {
                        const url = this.previewUrlFor(voiceId);
                        if (!url) {
                            return;
                        }

                        if (this.previewingVoice === voiceId && this._previewAudio) {
                            this._previewAudio.pause();
                            this._previewAudio = null;
                            this.previewingVoice = null;
                            return;
                        }

                        if (this._previewAudio) {
                            this._previewAudio.pause();
                        }

                        this._previewAudio = new Audio(url);
                        this.previewingVoice = voiceId;
                        this._previewAudio.play();
                        this._previewAudio.onended = () => {
                            this.previewingVoice = null;
                            this._previewAudio = null;
                        };
                    },
                    setActiveAgent(agentId, step, skipCRUD = false) {
                        const topNoticeBar = document.querySelector('.top-notice-bar');
                        const navbar = document.querySelector('.lqd-navbar');
                        const pageContentWrap = document.querySelector('.lqd-page-content-wrap');
                        const navbarExpander = document.querySelector('.lqd-navbar-expander');

                        const activeAgentId = this.activeAgent.id;

                        this.activeAgent = this.agents.data.find(a => a.id === agentId) || {
                            id: agentId
                        };

                        if (activeAgentId) {
                            this.prevActiveAgentId = activeAgentId;
                        }

                        if (step) {
                            this.setEditingStep(step, skipCRUD);
                        }

                        this.formErrors = {};

                        document.documentElement.style.overflow = this.activeAgent.id ? 'hidden' : '';

                        if (window.innerWidth >= 992) {
                            if (navbar) {
                                navbar.style.position = this.activeAgent.id ? 'fixed' : '';
                            }
                            if (pageContentWrap && navbar?.offsetWidth > 0) {
                                pageContentWrap.style.paddingInlineStart = this.activeAgent.id ? 'var(--navbar-width)' : '';
                            }
                            if (topNoticeBar) {
                                topNoticeBar.style.visibility = this.activeAgent.id ? 'hidden' : '';
                            }
                            if (navbarExpander) {
                                navbarExpander.style.visibility = this.activeAgent.id ? 'hidden' : '';
                                navbarExpander.style.opacity = this.activeAgent.id ? 0 : 1;
                            }
                        }
                    },
                    async setEditingStep(step, skipCRUD = false) {
                        const prevStep = this.editingStep;
                        let editingStep = step;

                        if (step === '>') {
                            editingStep = Math.min(3, this.editingStep + 1);
                        } else if (step === '<') {
                            editingStep = Math.max(1, this.editingStep - 1);
                        }

                        if (
                            !skipCRUD &&
                            prevStep !== editingStep &&
                            prevStep === 1 &&
                            this.activeAgent.id === 'new_agent'
                        ) {
                            await this.createNewAgent();
                            return;
                        }

                        if (
                            !skipCRUD &&
                            prevStep !== editingStep &&
                            (prevStep === 2 || (prevStep === 1 && editingStep === 2)) &&
                            this.activeAgent.id !== 'new_agent'
                        ) {
                            await this.updateAgent();
                        }

                        if (
                            !skipCRUD &&
                            this.agentTraining != null &&
                            editingStep === 3 &&
                            this.activeAgent.id !== 'new_agent'
                        ) {
                            this.agentTraining.fetchEmbeddings();
                        }

                        if (
                            editingStep === 3 &&
                            this.activeAgent.id !== 'new_agent' &&
                            this.activeAgent.provider !== 'twilio' &&
                            this.activeAgent.agent_id
                        ) {
                            this.loadPhoneNumbers();
                        }

                        this.prevEditingStep = this.editingStep;
                        this.editingStep = editingStep;
                    },
                    async toggleAgentActivation(agentId) {
                        const agent = this.agents.data.find(a => a.id === agentId);
                        if (!agent) { return; }
                        await this.updateAgent(agent);
                    },
                    async deleteAgent(event) {
                        @if ($app_is_demo)
                            toastr.error('{{ trans('This feature is disabled in Demo version.') }}');
                            return;
                        @endif

                        if (!confirm('{{ __('Are you sure you want to delete this agent?') }}')) {
                            return;
                        }

                        const form = event.target;
                        const id = form.elements['id'].value;
                        const agentIndex = this.agents.data.findIndex(a => a.id == id);

                        this.submittingData = true;

                        const res = await fetch(form.action, {
                            method: 'DELETE',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: this.getFormData(this.agents.data.at(agentIndex))
                        });

                        if (!res.ok) {
                            toastr.error('{{ __('Failed to delete agent') }}');
                            return;
                        }

                        const data = await res.json();

                        if (data.type !== 'success') {
                            toastr.error(data.message);
                            return;
                        }

                        if (agentIndex !== -1) {
                            this.agents.data.splice(agentIndex, 1);
                        }

                        this.submittingData = false;

                        toastr.clear();
                        toastr.success(data.message || '{{ __('Agent deleted successfully') }}');
                    },
                    training: {
                        activeTab: 'url',
                        setActiveTab(tab) {
                            if (this.activeTab === tab) { return; }
                            this.activeTab = tab;
                        }
                    },
                    async createNewAgent() {
                        @if ($app_is_demo)
                            toastr.error('{{ trans('This feature is disabled in Demo version.') }}');
                            return;
                        @endif

                        this.submittingData = true;
                        this.formErrors = {};

                        const res = await fetch('{{ route('dashboard.phone-call-agent.store') }}', {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: this.getFormData()
                        });
                        const data = await res.json();
                        const { data: agentData } = data;

                        this.submittingData = false;

                        if (!res.ok || !agentData) {
                            if (data.errors) {
                                this.formErrors = data.errors;
                            } else if (data.message) {
                                toastr.error(data.message);
                            }
                            this.setEditingStep(1, true);
                            return;
                        }

                        this.agents.data.shift();
                        this.agents.data.unshift({ ...this.defaultFormInputs, ...agentData });

                        this.setActiveAgent(agentData.id);
                        this.setEditingStep(2, true);
                        this.createNewAgentObj();

                        toastr.clear();
                        toastr.success('{{ __('Agent created successfully') }}');
                    },
                    async updateAgent(agent) {
                        @if ($app_is_demo)
                            toastr.error('{{ trans('This feature is disabled in Demo version.') }}');
                            return;
                        @endif

                        this.submittingData = true;
                        this.formErrors = {};

                        const res = await fetch('{{ route('dashboard.phone-call-agent.update') }}', {
                            method: 'PUT',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: this.getFormData(agent)
                        });
                        const data = await res.json();
                        const { data: agentData } = data;

                        this.submittingData = false;

                        if (!res.ok || !agentData) {
                            if (data.errors) {
                                this.formErrors = data.errors;
                            } else if (data.message) {
                                toastr.error(data.message);
                            }
                            return;
                        }

                        const agentIndex = this.agents.data.findIndex(a => a.id === agentData.id);
                        if (agentIndex !== -1) {
                            this.agents.data[agentIndex] = {
                                ...this.agents.data[agentIndex],
                                ...agentData
                            };
                        }

                        toastr.clear();
                        toastr.success('{{ __('Agent updated successfully') }}');
                    },
                    syncActiveAgent(agentData) {
                        const agentIndex = this.agents.data.findIndex(a => a.id === agentData.id);
                        if (agentIndex !== -1) {
                            this.agents.data[agentIndex] = {
                                ...this.agents.data[agentIndex],
                                ...agentData
                            };
                        }
                        this.activeAgent = { ...this.activeAgent, ...agentData };
                    },
                    async loadPhoneNumbers() {
                        if (!this.activeAgent.uuid) {
                            return;
                        }
                        try {
                            const res = await fetch(`/dashboard/phone-call-agent/phone-numbers/${this.activeAgent.uuid}`, {
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                },
                            });
                            const data = await res.json();
                            this.phoneNumbersList = res.ok ? (data.phone_numbers || []) : [];
                        } catch (e) {
                            this.phoneNumbersList = [];
                        }
                    },
                    async importPhoneNumber() {
                        @if ($app_is_demo)
                            toastr.error('{{ __('phone-call-agent::phone-call-agent.demo_mode_disabled') }}');
                            return;
                        @endif

                        if (this.phoneBusy) { return; }
                        this.phoneBusy = true;

                        const res = await fetch('{{ route('dashboard.phone-call-agent.phone-numbers.import') }}', {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify({ agent_id: this.activeAgent.uuid, ...this.phoneForm }),
                        });
                        const data = await res.json();
                        this.phoneBusy = false;

                        if (!res.ok || !data.data) {
                            toastr.error(data.message || Object.values(data.errors || {}).flat()[0] || 'Error');
                            return;
                        }

                        this.syncActiveAgent(data.data);
                        Object.assign(this.phoneForm, this.defaultPhoneForm());
                        this.loadPhoneNumbers();

                        const modal = document.querySelector('#phone-import-modal');
                        if (modal) {
                            Alpine.$data(modal).modalOpen = false;
                        }

                        toastr.success(this.activeAgent.provider === 'twilio'
                            ? '{{ __('phone-call-agent::phone-call-agent.twilio_number_connected') }}'
                            : '{{ __('phone-call-agent::phone-call-agent.phone_number_imported') }}');
                    },
                    async assignPhoneNumber(number) {
                        if (this.phoneBusy) { return; }
                        this.phoneBusy = true;

                        const res = await fetch('{{ route('dashboard.phone-call-agent.phone-numbers.assign') }}', {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify({
                                agent_id: this.activeAgent.uuid,
                                phone_number_id: number.phone_number_id,
                                phone_number: number.phone_number,
                            }),
                        });
                        const data = await res.json();
                        this.phoneBusy = false;

                        if (!res.ok || !data.data) {
                            toastr.error(data.message || 'Error');
                            return;
                        }

                        this.syncActiveAgent(data.data);
                        toastr.success('{{ __('phone-call-agent::phone-call-agent.phone_number_attached') }}');
                    },
                    async releasePhoneNumber() {
                        @if ($app_is_demo)
                            toastr.error('{{ __('phone-call-agent::phone-call-agent.demo_mode_disabled') }}');
                            return;
                        @endif

                        if (!confirm('{{ __('Are you sure you want to remove this phone number? This deletes it from ElevenLabs.') }}')) {
                            return;
                        }

                        if (this.phoneBusy) { return; }
                        this.phoneBusy = true;

                        const res = await fetch(`/dashboard/phone-call-agent/phone-numbers/${this.activeAgent.uuid}`, {
                            method: 'DELETE',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                        });
                        const data = await res.json();
                        this.phoneBusy = false;

                        if (!res.ok || !data.data) {
                            toastr.error(data.message || 'Error');
                            return;
                        }

                        this.syncActiveAgent(data.data);
                        this.loadPhoneNumbers();
                        toastr.success('{{ __('phone-call-agent::phone-call-agent.phone_number_released') }}');
                    },
                    getFormData(agent) {
                        const agentData = agent || this.activeAgent;
                        const formData = {};
                        Object.keys(agentData).forEach(key => {
                            formData[key] = agentData[key];
                        });
                        return JSON.stringify(formData);
                    },
                    async startSimulation() {
                        @if ($app_is_demo)
                            toastr.error('{{ __('phone-call-agent::phone-call-agent.demo_mode_disabled') }}');
                            return;
                        @endif

                        if (this.simulation.status === 'active' || this.simulation.status === 'loading') {
                            return;
                        }

                        if (!this.activeAgent.id || this.activeAgent.id === 'new_agent') {
                            toastr.warning('{{ __('Save the agent first before testing.') }}');
                            return;
                        }

                        if (this.activeAgent.provider === 'twilio') {
                            toastr.info('{{ __('Web simulation is available for ElevenLabs agents only.') }}');
                            return;
                        }

                        this.simulation.status = 'loading';
                        this.simulation.error = null;

                        try {
                            const res = await fetch(`/dashboard/phone-call-agent/${this.activeAgent.uuid}/simulate`, {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                },
                            });

                            const data = await res.json();

                            if (!res.ok || !data.signed_url) {
                                throw new Error(data.message || '{{ __('Failed to get simulation token.') }}');
                            }

                            if (!window.ElevenLabsConversation) {
                                await new Promise((resolve, reject) => {
                                    const s = document.createElement('script');
                                    s.src = 'https://unpkg.com/@11labs/client/dist/lib.umd.js';
                                    s.onload = () => {
                                        window.ElevenLabsConversation = window.client?.Conversation;
                                        resolve();
                                    };
                                    s.onerror = () => reject(new Error('{{ __('Failed to load ElevenLabs SDK.') }}'));
                                    document.head.appendChild(s);
                                });
                            }

                            const Conversation = window.ElevenLabsConversation;

                            if (!Conversation) {
                                throw new Error('{{ __('ElevenLabs SDK not available.') }}');
                            }

                            this.simulation.conversation = await Conversation.startSession({
                                signedUrl: data.signed_url,
                                onConnect: () => {
                                    this.simulation.status = 'active';
                                },
                                onDisconnect: () => {
                                    this.simulation.status = 'ended';
                                    this.simulation.conversation = null;
                                },
                                onError: (err) => {
                                    this.simulation.status = 'error';
                                    this.simulation.error = err?.message || '{{ __('Simulation error') }}';
                                    toastr.error(this.simulation.error);
                                },
                            });
                        } catch (e) {
                            this.simulation.status = 'error';
                            this.simulation.error = e.message;
                            toastr.error(e.message);
                        }
                    },
                    async endSimulation() {
                        if (this.simulation.conversation) {
                            try {
                                await this.simulation.conversation.endSession();
                            } catch (_) {}
                        }
                        this.simulation.status = 'idle';
                        this.simulation.conversation = null;
                        this.simulation.error = null;
                    },
                }));
            });
        })();
    </script>
@endpush
