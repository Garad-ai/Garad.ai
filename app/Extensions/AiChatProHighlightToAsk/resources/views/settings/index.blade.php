@extends('panel.layout.settings')
@section('title', __('Highlight to Ask Settings'))
@section('titlebar_actions', '')

@section('settings')
    <form
        method="post"
        action="{{ route('dashboard.admin.ai-chat-pro-highlight-to-ask.settings.update') }}"
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
                    name="ai_chat_pro_highlight_to_ask_enabled"
                    label="{{ __('Enable Highlight to Ask') }}"
                    checked="{{ (bool) setting('ai_chat_pro_highlight_to_ask_enabled', '1') }}"
                    tooltip="{{ __('When enabled, users can highlight text in AI Chat Pro responses and quickly ask follow-up questions about the selected text.') }}"
                />
            </x-form.group>
        </x-card>

        <button class="btn btn-primary mt-5 w-full">
            {{ __('Save') }}
        </button>
    </form>
@endsection
