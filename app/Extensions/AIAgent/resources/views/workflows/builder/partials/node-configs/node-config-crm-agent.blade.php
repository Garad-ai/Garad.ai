<template x-if="selectedStep.type === 'crm_agent'">
    <div class="space-y-4">
        {{-- Action Event --}}
        <div>
            <x-forms.input
                type="select"
                label="{{ __('Action Event') }}"
                x-model="selectedStep.config.action_event"
                tooltip="{{ __('Choose which CRM operation to perform.') }}"
            >
                <option value="">{{ __('Select an action event...') }}</option>
                <option value="list_contacts">{{ __('List Contacts') }}</option>
                <option value="create_contact">{{ __('Create Contact') }}</option>
                <option value="list_companies">{{ __('List Companies') }}</option>
                <option value="create_company">{{ __('Create Company') }}</option>
                <option value="list_deals">{{ __('List Deals') }}</option>
                <option value="create_deal">{{ __('Create Deal') }}</option>
                <option value="move_deal_stage">{{ __('Move Deal Stage') }}</option>
                <option value="create_task">{{ __('Create Task') }}</option>
                <option value="add_note">{{ __('Add Note') }}</option>
                <option value="get_crm_analytics">{{ __('Get CRM Analytics') }}</option>
            </x-forms.input>
        </div>

        {{-- list_contacts / list_companies fields --}}
        <template x-if="selectedStep.config.action_event === 'list_contacts' || selectedStep.config.action_event === 'list_companies'">
            <div class="space-y-3">
                <x-forms.input
                    type="text"
                    label="{{ __('Search') }}"
                    placeholder="{{ __('Name or email...') }}"
                    x-model="selectedStep.config.search"
                />
                <x-forms.input
                    type="number"
                    min="1"
                    max="50"
                    label="{{ __('Limit') }}"
                    placeholder="10"
                    x-model.number="selectedStep.config.limit"
                />
                <x-forms.input
                    type="select"
                    label="{{ __('Output Format') }}"
                    x-model="selectedStep.config.output_format"
                    tooltip="{{ __('Markdown renders nicely in chat replies and AI prompts.') }}"
                >
                    <option value="json">{{ __('JSON — full structured data') }}</option>
                    <option value="plain">{{ __('Markdown — table') }}</option>
                </x-forms.input>
            </div>
        </template>

        {{-- create_contact fields --}}
        <template x-if="selectedStep.config.action_event === 'create_contact'">
            <div class="space-y-3" x-init="fetchCrmRecords()">
                @include('ai-agent::workflows.builder.partials.variable-tag-input', ['configKey' => 'first_name', 'label' => __('First Name'), 'placeholder' => __('e.g. {message_text}'), 'required' => true])
                @include('ai-agent::workflows.builder.partials.variable-tag-input', ['configKey' => 'last_name', 'label' => __('Last Name'), 'placeholder' => __('e.g. {lead_last_name}')])
                @include('ai-agent::workflows.builder.partials.variable-tag-input', ['configKey' => 'email', 'label' => __('Email'), 'placeholder' => __('e.g. {lead_email}')])
                @include('ai-agent::workflows.builder.partials.variable-tag-input', ['configKey' => 'phone', 'label' => __('Phone'), 'placeholder' => __('e.g. {lead_phone}')])
                @include('ai-agent::workflows.builder.partials.variable-tag-input', ['configKey' => 'job_title', 'label' => __('Job Title'), 'placeholder' => __('e.g. {lead_job_title}')])
                @include('ai-agent::workflows.builder.partials.variable-tag-input', ['configKey' => 'company_name', 'label' => __('Company Name'), 'placeholder' => __('e.g. {lead_company_name}')])
                <x-forms.input
                    type="select"
                    label="{{ __('Company') }}"
                    x-model.number="selectedStep.config.company_id"
                    ::disabled="crmRecordsLoading"
                    tooltip="{{ __('Optional related company. Overrides Company Name when set.') }}"
                >
                    <option value="" x-text="crmRecordsLoading ? '{{ __('Loading records...') }}' : '{{ __('None') }}'"></option>
                    <template x-for="rec in crmCompanies" :key="rec.id">
                        <option :value="rec.id" :selected="rec.id === selectedStep.config.company_id" x-text="'#' + rec.id + ' — ' + rec.label"></option>
                    </template>
                </x-forms.input>
                @include('ai-agent::workflows.builder.partials.variable-tag-input', ['configKey' => 'notes', 'label' => __('Notes'), 'placeholder' => __('Use {message_text} for dynamic content'), 'multiline' => true])
            </div>
        </template>

        {{-- create_company fields --}}
        <template x-if="selectedStep.config.action_event === 'create_company'">
            <div class="space-y-3">
                @include('ai-agent::workflows.builder.partials.variable-tag-input', ['configKey' => 'name', 'label' => __('Name'), 'placeholder' => __('e.g. {message_text}'), 'required' => true])
                <x-forms.input type="text" label="{{ __('Industry') }}" x-model="selectedStep.config.industry" />
                <x-forms.input type="url" label="{{ __('Website') }}" placeholder="https://example.com" x-model="selectedStep.config.website" />
                <x-forms.input type="text" label="{{ __('Email') }}" x-model="selectedStep.config.email" />
                <x-forms.input type="text" label="{{ __('Phone') }}" x-model="selectedStep.config.phone" />
                <x-forms.input type="text" label="{{ __('City') }}" x-model="selectedStep.config.city" />
                <x-forms.input type="text" label="{{ __('Country') }}" x-model="selectedStep.config.country" />
            </div>
        </template>

        {{-- list_deals fields --}}
        <template x-if="selectedStep.config.action_event === 'list_deals'">
            <div class="space-y-3" x-init="fetchCrmRecords()">
                <x-forms.input
                    type="select"
                    label="{{ __('Stage') }}"
                    x-model.number="selectedStep.config.stage_id"
                    ::disabled="crmRecordsLoading"
                    tooltip="{{ __('Optional pipeline stage filter.') }}"
                >
                    <option value="" x-text="crmRecordsLoading ? '{{ __('Loading records...') }}' : '{{ __('All stages') }}'"></option>
                    <template x-for="rec in crmStages" :key="rec.id">
                        <option :value="rec.id" :selected="rec.id === selectedStep.config.stage_id" x-text="rec.label"></option>
                    </template>
                </x-forms.input>
                <x-forms.input type="number" min="1" max="50" label="{{ __('Limit') }}" placeholder="10" x-model.number="selectedStep.config.limit" />
                <x-forms.input
                    type="select"
                    label="{{ __('Output Format') }}"
                    x-model="selectedStep.config.output_format"
                    tooltip="{{ __('Markdown renders nicely in chat replies and AI prompts.') }}"
                >
                    <option value="json">{{ __('JSON — full structured data') }}</option>
                    <option value="plain">{{ __('Markdown — table') }}</option>
                </x-forms.input>
            </div>
        </template>

        {{-- create_deal fields --}}
        <template x-if="selectedStep.config.action_event === 'create_deal'">
            <div class="space-y-3" x-init="fetchCrmRecords()">
                @include('ai-agent::workflows.builder.partials.variable-tag-input', ['configKey' => 'title', 'label' => __('Title'), 'placeholder' => __('e.g. {deal_title}'), 'required' => true])
                @include('ai-agent::workflows.builder.partials.variable-tag-input', ['configKey' => 'value', 'label' => __('Value'), 'placeholder' => __('e.g. 5000 or {deal_value}')])
                @include('ai-agent::workflows.builder.partials.variable-tag-input', ['configKey' => 'currency', 'label' => __('Currency'), 'placeholder' => __('USD')])
                <x-forms.input
                    type="select"
                    label="{{ __('Stage') }}"
                    x-model.number="selectedStep.config.stage_id"
                    ::disabled="crmRecordsLoading"
                    tooltip="{{ __('Defaults to the first pipeline stage.') }}"
                >
                    <option value="" x-text="crmRecordsLoading ? '{{ __('Loading records...') }}' : '{{ __('First stage (default)') }}'"></option>
                    <template x-for="rec in crmStages" :key="rec.id">
                        <option :value="rec.id" :selected="rec.id === selectedStep.config.stage_id" x-text="rec.label"></option>
                    </template>
                </x-forms.input>
                @include('ai-agent::workflows.builder.partials.variable-tag-input', ['configKey' => 'contact_name', 'label' => __('Contact Name'), 'placeholder' => __('e.g. {contact_name}')])
                @include('ai-agent::workflows.builder.partials.variable-tag-input', ['configKey' => 'contact_email', 'label' => __('Contact Email'), 'placeholder' => __('e.g. {contact_email}')])
                <x-forms.input
                    type="select"
                    label="{{ __('Contact') }}"
                    x-model.number="selectedStep.config.contact_id"
                    ::disabled="crmRecordsLoading"
                    tooltip="{{ __('Optional related contact. Overrides Contact Name when set.') }}"
                >
                    <option value="" x-text="crmRecordsLoading ? '{{ __('Loading records...') }}' : '{{ __('None') }}'"></option>
                    <template x-for="rec in crmContacts" :key="rec.id">
                        <option :value="rec.id" :selected="rec.id === selectedStep.config.contact_id" x-text="'#' + rec.id + ' — ' + rec.label"></option>
                    </template>
                </x-forms.input>
                @include('ai-agent::workflows.builder.partials.variable-tag-input', ['configKey' => 'company_name', 'label' => __('Company Name'), 'placeholder' => __('e.g. {company_name}')])
                <x-forms.input
                    type="select"
                    label="{{ __('Company') }}"
                    x-model.number="selectedStep.config.company_id"
                    ::disabled="crmRecordsLoading"
                    tooltip="{{ __('Optional related company. Overrides Company Name when set.') }}"
                >
                    <option value="" x-text="crmRecordsLoading ? '{{ __('Loading records...') }}' : '{{ __('None') }}'"></option>
                    <template x-for="rec in crmCompanies" :key="rec.id">
                        <option :value="rec.id" :selected="rec.id === selectedStep.config.company_id" x-text="'#' + rec.id + ' — ' + rec.label"></option>
                    </template>
                </x-forms.input>
                @include('ai-agent::workflows.builder.partials.variable-tag-input', ['configKey' => 'expected_close_date', 'label' => __('Expected Close Date'), 'placeholder' => __('YYYY-MM-DD or {expected_close_date}')])
            </div>
        </template>

        {{-- move_deal_stage fields --}}
        <template x-if="selectedStep.config.action_event === 'move_deal_stage'">
            <div class="space-y-3" x-init="fetchCrmRecords()">
                @include('ai-agent::workflows.builder.partials.variable-tag-input', ['configKey' => 'deal_title', 'label' => __('Deal Title'), 'placeholder' => __('e.g. {deal_title} — must match an existing deal')])
                <x-forms.input
                    type="select"
                    label="{{ __('Deal') }}"
                    x-model.number="selectedStep.config.deal_id"
                    ::disabled="crmRecordsLoading"
                    tooltip="{{ __('Optional fixed deal. Overrides Deal Title when set.') }}"
                >
                    <option value="" x-text="crmRecordsLoading ? '{{ __('Loading records...') }}' : '{{ __('None — resolve by Deal Title') }}'"></option>
                    <template x-for="rec in crmDeals" :key="rec.id">
                        <option :value="rec.id" :selected="rec.id === selectedStep.config.deal_id" x-text="'#' + rec.id + ' — ' + rec.label"></option>
                    </template>
                </x-forms.input>
                @include('ai-agent::workflows.builder.partials.variable-tag-input', ['configKey' => 'stage_name', 'label' => __('Target Stage Name'), 'placeholder' => __('e.g. {stage_name} or Negotiation')])
                <x-forms.input
                    type="select"
                    label="{{ __('Target Stage') }}"
                    x-model.number="selectedStep.config.stage_id"
                    ::disabled="crmRecordsLoading"
                    tooltip="{{ __('Optional fixed stage. Overrides Target Stage Name when set.') }}"
                >
                    <option value="" x-text="crmRecordsLoading ? '{{ __('Loading records...') }}' : '{{ __('None — resolve by Target Stage Name') }}'"></option>
                    <template x-for="rec in crmStages" :key="rec.id">
                        <option :value="rec.id" :selected="rec.id === selectedStep.config.stage_id" x-text="rec.label"></option>
                    </template>
                </x-forms.input>
            </div>
        </template>

        {{-- create_task fields --}}
        <template x-if="selectedStep.config.action_event === 'create_task'">
            <div class="space-y-3" x-init="fetchCrmRecords()">
                @include('ai-agent::workflows.builder.partials.variable-tag-input', ['configKey' => 'title', 'label' => __('Title'), 'placeholder' => __('e.g. {ai_response}'), 'required' => true])
                @include('ai-agent::workflows.builder.partials.variable-tag-input', ['configKey' => 'description', 'label' => __('Description'), 'placeholder' => __('Use {message_text} for dynamic content'), 'multiline' => true])
                <x-forms.input type="select" label="{{ __('Priority') }}" x-model="selectedStep.config.priority">
                    {{-- A workflow can drive the priority from an earlier step, so keep that value selectable. --}}
                    <template x-if="String(selectedStep.config.priority ?? '').includes('{')">
                        <option :value="selectedStep.config.priority" selected x-text="selectedStep.config.priority"></option>
                    </template>
                    <option value="low">{{ __('Low') }}</option>
                    <option value="medium">{{ __('Medium') }}</option>
                    <option value="high">{{ __('High') }}</option>
                    <option value="urgent">{{ __('Urgent') }}</option>
                </x-forms.input>
                @include('ai-agent::workflows.builder.partials.variable-tag-input', ['configKey' => 'due_date', 'label' => __('Due Date'), 'placeholder' => __('YYYY-MM-DD or {task_due_date}')])
                <x-forms.input
                    type="select"
                    label="{{ __('Contact') }}"
                    x-model.number="selectedStep.config.contact_id"
                    ::disabled="crmRecordsLoading"
                >
                    <option value="" x-text="crmRecordsLoading ? '{{ __('Loading records...') }}' : '{{ __('None') }}'"></option>
                    <template x-for="rec in crmContacts" :key="rec.id">
                        <option :value="rec.id" :selected="rec.id === selectedStep.config.contact_id" x-text="'#' + rec.id + ' — ' + rec.label"></option>
                    </template>
                </x-forms.input>
                <x-forms.input
                    type="select"
                    label="{{ __('Deal') }}"
                    x-model.number="selectedStep.config.deal_id"
                    ::disabled="crmRecordsLoading"
                >
                    <option value="" x-text="crmRecordsLoading ? '{{ __('Loading records...') }}' : '{{ __('None') }}'"></option>
                    <template x-for="rec in crmDeals" :key="rec.id">
                        <option :value="rec.id" :selected="rec.id === selectedStep.config.deal_id" x-text="'#' + rec.id + ' — ' + rec.label"></option>
                    </template>
                </x-forms.input>
            </div>
        </template>

        {{-- add_note fields --}}
        <template x-if="selectedStep.config.action_event === 'add_note'">
            <div class="space-y-3" x-init="fetchCrmRecords()">
                <x-forms.input type="select" label="{{ __('Attach To') }}" x-model="selectedStep.config.notable" required @change="selectedStep.config.notable_id = null">
                    <option value="">{{ __('Select record type...') }}</option>
                    <option value="contact">{{ __('Contact') }}</option>
                    <option value="deal">{{ __('Deal') }}</option>
                </x-forms.input>
                <template x-if="selectedStep.config.notable">
                    <div class="space-y-3">
                        @include('ai-agent::workflows.builder.partials.variable-tag-input', ['configKey' => 'record_name', 'label' => __('Record Name'), 'placeholder' => __('e.g. {contact_name} or a deal title')])
                        <x-forms.input
                            type="select"
                            label="{{ __('Record') }}"
                            x-model.number="selectedStep.config.notable_id"
                            ::disabled="crmRecordsLoading"
                            tooltip="{{ __('Optional fixed record. Overrides Record Name when set.') }}"
                        >
                            <option
                                value=""
                                x-text="crmRecordsLoading ? '{{ __('Loading records...') }}' : '{{ __('None — resolve by Record Name') }}'"
                            ></option>
                            <template
                                x-for="rec in (selectedStep.config.notable === 'deal' ? crmDeals : crmContacts)"
                                :key="rec.id"
                            >
                                <option
                                    :value="rec.id"
                                    :selected="rec.id === selectedStep.config.notable_id"
                                    x-text="'#' + rec.id + ' — ' + rec.label"
                                ></option>
                            </template>
                        </x-forms.input>
                    </div>
                </template>
                <x-forms.input type="select" label="{{ __('Type') }}" x-model="selectedStep.config.type">
                    {{-- A workflow can drive the type from an earlier step, so keep that value selectable. --}}
                    <template x-if="String(selectedStep.config.type ?? '').includes('{')">
                        <option :value="selectedStep.config.type" selected x-text="selectedStep.config.type"></option>
                    </template>
                    <option value="note">{{ __('Note') }}</option>
                    <option value="call">{{ __('Call') }}</option>
                    <option value="meeting">{{ __('Meeting') }}</option>
                    <option value="email">{{ __('Email') }}</option>
                </x-forms.input>
                <template x-if="['call', 'meeting'].includes(selectedStep.config.type) || String(selectedStep.config.type ?? '').includes('{')">
                    <div>
                        @include('ai-agent::workflows.builder.partials.variable-tag-input', ['configKey' => 'scheduled_at', 'label' => __('Scheduled At'), 'placeholder' => __('YYYY-MM-DD HH:MM or {scheduled_at}')])
                    </div>
                </template>
                @include('ai-agent::workflows.builder.partials.variable-tag-input', ['configKey' => 'content', 'label' => __('Content'), 'placeholder' => __('Use {message_text} for dynamic content'), 'required' => true, 'multiline' => true])
            </div>
        </template>

        {{-- get_crm_analytics fields --}}
        <template x-if="selectedStep.config.action_event === 'get_crm_analytics'">
            <div class="space-y-3">
                <div class="rounded-lg border border-dashed border-border px-3 py-2.5 text-[11px] text-foreground/50">
                    {{ __('Returns a pipeline snapshot.') }}
                </div>
                <x-forms.input
                    type="select"
                    label="{{ __('Output Format') }}"
                    x-model="selectedStep.config.output_format"
                    tooltip="{{ __('Markdown renders nicely in chat replies and AI prompts.') }}"
                >
                    <option value="json">{{ __('JSON — full structured data') }}</option>
                    <option value="plain">{{ __('Markdown — summary') }}</option>
                </x-forms.input>
            </div>
        </template>
    </div>
</template>
