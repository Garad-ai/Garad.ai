<template x-if="selectedStep.type === 'external_chatbot'">
    <div class="space-y-4">
        {{-- Action Event --}}
        <x-forms.input
            type="select"
            label="{{ __('Action Event') }}"
            x-model="selectedStep.config.action_event"
            tooltip="{{ __('Choose which chatbot operation to perform.') }}"
        >
            <option value="">{{ __('Select an action event...') }}</option>
            <option value="list_chatbots">{{ __('List Chatbots') }}</option>
            <option value="get_chatbot_analytics">{{ __('Get Chatbot Analytics') }}</option>
            <option value="list_chatbot_conversations">{{ __('List Chatbot Conversations') }}</option>
            <option value="get_chatbot_conversation">{{ __('Get Chatbot Conversation') }}</option>
            <option value="get_chatbot_conversations">{{ __('Get Chatbot Conversations') }}</option>
            <option value="close_chatbot_ticket">{{ __('Close Chatbot Ticket') }}</option>
        </x-forms.input>

        {{-- list_chatbots fields --}}
        <template x-if="selectedStep.config.action_event === 'list_chatbots'">
            <div class="space-y-3">
                <div class="flex items-center justify-between rounded-lg border border-border px-3 py-2.5">
                    <div>
                        <p class="text-[11px] font-medium text-heading-foreground">{{ __('Active only') }}</p>
                        <p class="text-[10px] text-foreground/40">{{ __('Return only active chatbots.') }}</p>
                    </div>
                    <button
                        class="relative h-5 w-9 rounded-full transition-colors focus:outline-none"
                        :class="selectedStep.config.active_only ? 'bg-primary' : 'bg-foreground/20'"
                        @click="selectedStep.config.active_only = !selectedStep.config.active_only"
                        type="button"
                    >
                        <span
                            class="absolute top-0.5 h-4 w-4 rounded-full bg-white shadow transition-transform"
                            :class="selectedStep.config.active_only ? 'translate-x-4' : 'translate-x-0.5'"
                        ></span>
                    </button>
                </div>
                <x-forms.input
                    type="number"
                    min="1"
                    max="50"
                    label="{{ __('Limit') }}"
                    placeholder="10"
                    x-model.number="selectedStep.config.limit"
                    tooltip="{{ __('Max results (1–50).') }}"
                />
            </div>
        </template>

        {{-- get_chatbot_analytics fields --}}
        <template x-if="selectedStep.config.action_event === 'get_chatbot_analytics'">
            <x-forms.input
                type="number"
                min="1"
                label="{{ __('Chatbot ID') }}"
                placeholder="{{ __('Leave blank for all chatbots') }}"
                x-model.number="selectedStep.config.chatbot_id"
                tooltip="{{ __('Optional. Scope analytics to a single chatbot.') }}"
            />
        </template>

        {{-- list_chatbot_conversations fields --}}
        <template x-if="selectedStep.config.action_event === 'list_chatbot_conversations'">
            <div class="space-y-3">
                <x-forms.input
                    type="number"
                    min="1"
                    label="{{ __('Chatbot ID') }}"
                    placeholder="{{ __('Leave blank for all chatbots') }}"
                    x-model.number="selectedStep.config.chatbot_id"
                />
                <x-forms.input
                    type="text"
                    label="{{ __('Channel') }}"
                    placeholder="{{ __('e.g. whatsapp, telegram, livechat') }}"
                    x-model="selectedStep.config.channel"
                />
                <x-forms.input
                    type="select"
                    label="{{ __('Ticket Status') }}"
                    x-model="selectedStep.config.ticket_status"
                >
                    <option value="">{{ __('Any status') }}</option>
                    <option value="new">{{ __('New') }}</option>
                    <option value="closed">{{ __('Closed') }}</option>
                </x-forms.input>
                <x-forms.input
                    type="number"
                    min="1"
                    max="50"
                    label="{{ __('Limit') }}"
                    placeholder="10"
                    x-model.number="selectedStep.config.limit"
                />
            </div>
        </template>

        {{-- get_chatbot_conversation fields --}}
        <template x-if="selectedStep.config.action_event === 'get_chatbot_conversation'">
            <div class="space-y-3">
                <x-forms.input
                    type="number"
                    min="1"
                    label="{{ __('Conversation ID') }} *"
                    placeholder="{{ __('Conversation ID') }}"
                    x-model.number="selectedStep.config.conversation_id"
                    required
                    tooltip="{{ __('Use {context_key} syntax to pass a dynamic value.') }}"
                />
                <x-forms.input
                    type="select"
                    label="{{ __('Output Format') }}"
                    x-model="selectedStep.config.output_format"
                    tooltip="{{ __('Plain text is easier to use directly in AI prompts.') }}"
                >
                    <option value="json">{{ __('JSON — full structured data') }}</option>
                    <option value="plain">{{ __('Plain text — messages only') }}</option>
                </x-forms.input>
            </div>
        </template>

        {{-- get_chatbot_conversations fields --}}
        <template x-if="selectedStep.config.action_event === 'get_chatbot_conversations'">
            <div class="space-y-3">
                <x-forms.input
                    type="number"
                    min="1"
                    max="50"
                    label="{{ __('Limit') }}"
                    placeholder="5"
                    x-model.number="selectedStep.config.limit"
                    tooltip="{{ __('Number of most recent conversations to fetch (default 5, max 50).') }}"
                />
                <x-forms.input
                    type="select"
                    label="{{ __('Message Sender') }}"
                    x-model="selectedStep.config.message_sender"
                >
                    <option value="all">{{ __('All') }}</option>
                    <option value="user">{{ __('User') }}</option>
                    <option value="admin">{{ __('Admin') }}</option>
                </x-forms.input>
                <x-forms.input
                    type="select"
                    label="{{ __('Output Format') }}"
                    x-model="selectedStep.config.output_format"
                    tooltip="{{ __('Plain text is easier to use directly in AI prompts.') }}"
                >
                    <option value="json">{{ __('JSON — full structured data') }}</option>
                    <option value="plain">{{ __('Plain text — messages only') }}</option>
                </x-forms.input>
            </div>
        </template>

        {{-- close_chatbot_ticket fields --}}
        <template x-if="selectedStep.config.action_event === 'close_chatbot_ticket'">
            <x-forms.input
                type="number"
                min="1"
                label="{{ __('Conversation ID') }} *"
                placeholder="{{ __('Conversation ID') }}"
                x-model.number="selectedStep.config.conversation_id"
                required
                tooltip="{{ __('The conversation ticket will be marked as closed.') }}"
            />
        </template>
    </div>
</template>
