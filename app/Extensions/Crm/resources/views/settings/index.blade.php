@extends('panel.layout.settings')
@section('title', __('CRM Settings'))
@section('titlebar_pretitle', '')
@section('titlebar_actions', '')

@section('settings')
    <form
        method="post"
        action="{{ route('dashboard.admin.crm.update') }}"
    >
        @csrf

        <x-form-step
            auto-increment
            label="{{ __('Modules') }}"
        >
        </x-form-step>
        <x-card class="mb-2">
            <x-form.group class="grid grid-cols-1 gap-2">
                <x-form.checkbox
                    class="border-input rounded-input border !px-2.5 !py-3"
                    name="crm_enabled"
                    label="{{ __('Enable CRM') }}"
                    checked="{{ (bool) setting('crm_enabled', '1') }}"
                    tooltip="{{ __('Master switch for the whole CRM extension. When disabled, the CRM and Sales menus and pages are hidden for all users.') }}"
                />
                <x-form.checkbox
                    class="border-input rounded-input border !px-2.5 !py-3"
                    name="crm_assistant_enabled"
                    label="{{ __('Enable CRM Assistant') }}"
                    checked="{{ (bool) setting('crm_assistant_enabled', '1') }}"
                    tooltip="{{ __('Show the AI Assistant inside the CRM. When disabled, the AI Assistant menu and pages are hidden.') }}"
                />
                <x-form.checkbox
                    class="border-input rounded-input border !px-2.5 !py-3"
                    name="crm_presentations_enabled"
                    label="{{ __('Enable Presentations') }}"
                    checked="{{ (bool) setting('crm_presentations_enabled', '1') }}"
                    tooltip="{{ __('Show the Presentations module inside the CRM. When disabled, the Presentations menu and pages are hidden.') }}"
                />
            </x-form.group>
        </x-card>

        <x-form-step
            auto-increment
            label="{{ __('Contact Sync') }}"
        >
        </x-form-step>
        <x-card class="mb-2">
            <x-form.group class="grid grid-cols-1 gap-2">
                <x-form.checkbox
                    class="border-input rounded-input border !px-2.5 !py-3"
                    name="crm_contact_sync_enabled"
                    label="{{ __('Enable Contact Sync') }}"
                    checked="{{ (bool) setting('crm_contact_sync_enabled', '0') }}"
                    tooltip="{{ __('Master switch. When enabled, new contacts captured by the selected extensions are mirrored into the CRM.') }}"
                />
                <x-form.checkbox
                    class="border-input rounded-input border !px-2.5 !py-3"
                    name="crm_sync_marketing_whatsapp"
                    label="{{ __('Sync MarketingBot (WhatsApp)') }}"
                    checked="{{ (bool) setting('crm_sync_marketing_whatsapp', '0') }}"
                    tooltip="{{ __('Mirror WhatsApp contacts into the CRM as they are added or imported.') }}"
                />
                <x-form.checkbox
                    class="border-input rounded-input border !px-2.5 !py-3"
                    name="crm_sync_marketing_telegram"
                    label="{{ __('Sync MarketingBot (Telegram)') }}"
                    checked="{{ (bool) setting('crm_sync_marketing_telegram', '0') }}"
                    tooltip="{{ __('Mirror Telegram group subscribers into the CRM as they join.') }}"
                />
                <x-form.checkbox
                    class="border-input rounded-input border !px-2.5 !py-3"
                    name="crm_sync_chatbot"
                    label="{{ __('Sync Chatbot Customers') }}"
                    checked="{{ (bool) setting('crm_sync_chatbot', '0') }}"
                    tooltip="{{ __('Mirror chatbot customers into the CRM once they share an email.') }}"
                />
            </x-form.group>
        </x-card>

        <x-form-step
            auto-increment
            label="{{ __('Presentations') }}"
        >
        </x-form-step>
        <x-card
            class="mb-2"
            x-data="{ engine: '{{ setting('crm_presentation_engine', 'fal') }}' }"
        >
            <x-form.group class="flex flex-col gap-4">
                <x-forms.input
                    size="lg"
                    type="select"
                    label="{{ __('Presentation Engine') }}"
                    name="crm_presentation_engine"
                    x-model="engine"
                    tooltip="{{ __('Choose how CRM pitch decks are generated. Fal AI renders individual slide images (Nano Banana Pro). Gamma produces a full editable deck with PPTX/PDF export.') }}"
                >
                    <option
                        value="fal"
                        {{ setting('crm_presentation_engine', 'fal') === 'fal' ? 'selected' : '' }}
                    >
                        {{ __('Fal AI — Slide images (Nano Banana Pro)') }}
                    </option>
                    <option
                        value="gamma"
                        {{ setting('crm_presentation_engine', 'fal') === 'gamma' ? 'selected' : '' }}
                    >
                        {{ __('Gamma — Full deck (PPTX/PDF)') }}
                    </option>
                </x-forms.input>

                <x-forms.input
                    size="lg"
                    type="password"
                    label="{{ __('Gamma API Key') }}"
                    name="gamma_api_secret"
                    autocomplete="new-password"
                    value="{{ setting('gamma_api_secret') }}"
                    x-show="engine === 'gamma'"
                    x-cloak
                    placeholder="{{ __('Enter your Gamma API key') }}"
                    tooltip="{{ __('Required when the Gamma engine is selected. Get your key from gamma.app.') }}"
                />
            </x-form.group>
        </x-card>

        <x-form-step
            auto-increment
            label="{{ __('Sales') }}"
        >
        </x-form-step>
        <x-card class="mb-2">
            <x-form.group class="flex flex-col gap-4">
                <x-forms.input
                    size="lg"
                    type="select"
                    label="{{ __('Default Currency') }}"
                    name="crm_default_currency"
                    tooltip="{{ __('The currency pre-selected when creating new invoices, proposals, and estimates.') }}"
                >
                    @foreach ($currencies as $currency)
                        <option
                            value="{{ $currency }}"
                            {{ setting('crm_default_currency', 'USD') === $currency ? 'selected' : '' }}
                        >
                            {{ $currency }}
                        </option>
                    @endforeach
                </x-forms.input>
            </x-form.group>
        </x-card>

        <x-button
            class="mt-5 w-full"
            type="submit"
            size="lg"
        >
            {{ __('Save') }}
        </x-button>
    </form>
@endsection
