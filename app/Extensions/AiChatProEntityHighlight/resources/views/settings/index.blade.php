@extends('panel.layout.settings')
@section('title', __('Entity Highlights Settings'))
@section('titlebar_actions', '')

@section('settings')
    <form
        method="post"
        action="{{ route('dashboard.admin.ai-chat-pro-entity-highlight.settings.update') }}"
    >
        @csrf
        @method('PUT')

        <x-form-step
            step="1"
            label="{{ __('General') }}"
        >
        </x-form-step>

        <x-card
            class="mb-2 max-md:text-center"
            szie="lg"
        >
            <x-form.group class="flex flex-col gap-4">
                <x-form.checkbox
                    class="border-input rounded-input border !px-2.5 !py-3"
                    name="ai_chat_pro_entity_highlight_enabled"
                    label="{{ __('Enable Entity Highlights') }}"
                    checked="{{ (bool) setting('ai_chat_pro_entity_highlight_enabled', '1') }}"
                    tooltip="{{ __('When enabled, AI responses will highlight notable entities (movies, people, companies, etc.) that users can click for more details.') }}"
                />

                <x-forms.input
                    class="form-control"
                    label="{{ __('Max Highlights Per Response') }}"
                    type="number"
                    name="ai_chat_pro_entity_highlight_max"
                    min="1"
                    max="10"
                    tooltip="{{ __('Maximum number of entities to highlight in a single AI response. Lower values keep responses cleaner.') }}"
                    value="{{ setting('ai_chat_pro_entity_highlight_max', '3') }}"
                />

                <x-forms.input
                    class="form-control"
                    label="{{ __('Confidence Threshold') }}"
                    type="number"
                    name="ai_chat_pro_entity_highlight_threshold"
                    min="0.5"
                    max="1.0"
                    step="0.05"
                    tooltip="{{ __('Minimum confidence score (0.5-1.0) for entity detection. Higher values mean fewer but more accurate highlights.') }}"
                    value="{{ setting('ai_chat_pro_entity_highlight_threshold', '0.8') }}"
                />
            </x-form.group>
        </x-card>

        <button class="btn btn-primary mt-5 w-full">
            {{ __('Save') }}
        </button>
    </form>
@endsection
