<template x-if="selectedStep.type === 'marketing_bot'">
    <div class="space-y-4">
        {{-- Action Event --}}
        <div>
            <x-forms.input
                type="select"
                label="{{ __('Action Event') }}"
                x-model="selectedStep.config.action_event"
                tooltip="{{ __('Choose which marketing operation to perform.') }}"
            >
                <option value="">{{ __('Select an action event...') }}</option>
                <option value="list_marketing_campaigns">{{ __('List Marketing Campaigns') }}</option>
                <option value="get_marketing_stats">{{ __('Get Marketing Stats') }}</option>
                <option value="list_marketing_conversations">{{ __('List Marketing Conversations') }}</option>
                <option value="create_marketing_campaign">{{ __('Create Marketing Campaign') }}</option>
                <option value="schedule_marketing_campaign">{{ __('Schedule Marketing Campaign') }}</option>
            </x-forms.input>
        </div>

        {{-- list_marketing_campaigns fields --}}
        <template x-if="selectedStep.config.action_event === 'list_marketing_campaigns'">
            <div class="space-y-3">
                <x-forms.input
                    type="select"
                    label="{{ __('Channel Type') }}"
                    x-model="selectedStep.config.type"
                >
                    <option value="">{{ __('All channels') }}</option>
                    <option value="telegram">{{ __('Telegram') }}</option>
                    <option value="whatsapp">{{ __('WhatsApp') }}</option>
                </x-forms.input>
                <x-forms.input
                    type="select"
                    label="{{ __('Status') }}"
                    x-model="selectedStep.config.status"
                >
                    <option value="">{{ __('Any status') }}</option>
                    <option value="pending">{{ __('Pending') }}</option>
                    <option value="scheduled">{{ __('Scheduled') }}</option>
                    <option value="in_progress">{{ __('In Progress') }}</option>
                    <option value="published">{{ __('Published') }}</option>
                    <option value="failed">{{ __('Failed') }}</option>
                    <option value="running">{{ __('Running') }}</option>
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

        {{-- get_marketing_stats fields --}}
        <template x-if="selectedStep.config.action_event === 'get_marketing_stats'">
            <div class="space-y-3">
                <x-forms.input
                    type="select"
                    label="{{ __('Period') }}"
                    x-model="selectedStep.config.period"
                >
                    <option value="today">{{ __('Today') }}</option>
                    <option value="last_7_days">{{ __('Last 7 Days') }}</option>
                    <option value="last_30_days">{{ __('Last 30 Days') }}</option>
                </x-forms.input>
            </div>
        </template>

        {{-- list_marketing_conversations fields --}}
        <template x-if="selectedStep.config.action_event === 'list_marketing_conversations'">
            <div class="space-y-3">
                <x-forms.input
                    type="select"
                    label="{{ __('Channel') }}"
                    x-model="selectedStep.config.channel"
                >
                    <option value="">{{ __('All channels') }}</option>
                    <option value="telegram">{{ __('Telegram') }}</option>
                    <option value="whatsapp">{{ __('WhatsApp') }}</option>
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

        {{-- create_marketing_campaign fields --}}
        <template x-if="selectedStep.config.action_event === 'create_marketing_campaign'">
            <div class="space-y-3">
                <x-forms.input
                    type="text"
                    label="{{ __('Campaign Name') }} *"
                    placeholder="{{ __('e.g. Summer Promo') }}"
                    x-model="selectedStep.config.name"
                    required
                />
                <x-forms.input
                    type="select"
                    label="{{ __('Channel Type') }} *"
                    x-model="selectedStep.config.type"
                    required
                >
                    <option value="">{{ __('Select channel...') }}</option>
                    <option value="telegram">{{ __('Telegram') }}</option>
                    <option value="whatsapp">{{ __('WhatsApp') }}</option>
                </x-forms.input>
                <div>
                    <label class="mb-1.5 block text-[11px] font-medium text-label">
                        {{ __('Content') }}
                        <span class="ms-1 text-[10px] font-normal text-red-500">{{ __('required') }}</span>
                    </label>
                    <div
                        class="relative rounded-input border border-input-border bg-input-background focus-within:ring-1 focus-within:ring-primary/30"
                        x-data="variableTagInput({ configKey: 'content', stepRef: selectedStep, multiline: true })"
                    >
                        <div
                            class="px-3 py-2 pe-8 text-sm outline-none"
                            data-placeholder="{{ __('Use {ai_response} for dynamic content') }}"
                            x-ref="editor"
                            contenteditable="true"
                            @input.debounce.50ms="syncToModel()"
                            @keydown="handleKeydown($event)"
                            @paste="handlePaste($event)"
                            @click="handleClick($event)"
                            :class="multiline ? 'min-h-[4.5rem] whitespace-pre-wrap break-words' : 'min-h-[2.25rem] whitespace-nowrap overflow-x-auto'"
                        ></div>
                        <button
                            class="absolute end-2 top-2 rounded p-0.5 text-foreground/40 hover:bg-foreground/5 hover:text-foreground/70"
                            type="button"
                            @click.stop="menuOpen = !menuOpen"
                        ><x-tabler-braces class="size-3.5" /></button>
                        <div
                            class="absolute end-0 top-full z-50 mt-1 min-w-[180px] rounded-lg border border-border bg-background p-1.5 shadow-lg"
                            x-show="menuOpen"
                            x-cloak
                            @click.outside="menuOpen = false"
                        >
                            <template
                                x-for="v in availableVariables"
                                :key="v.name"
                            >
                                <button
                                    class="block w-full rounded px-2 py-1 text-start hover:bg-foreground/5"
                                    type="button"
                                    @click="insertVariable(v)"
                                >
                                    <span
                                        class="block font-mono text-[10px] text-heading-foreground"
                                        x-text="v.name"
                                    ></span>
                                    <span
                                        class="block text-[9px] text-foreground/40"
                                        x-text="v.description"
                                    ></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
                <x-forms.input
                    type="datetime-local"
                    label="{{ __('Schedule At') }}"
                    x-model="selectedStep.config.scheduled_at"
                    tooltip="{{ __('Leave blank to save as pending.') }}"
                />
            </div>
        </template>

        {{-- schedule_marketing_campaign fields --}}
        <template x-if="selectedStep.config.action_event === 'schedule_marketing_campaign'">
            <div class="space-y-3">
                <x-forms.input
                    type="number"
                    min="1"
                    label="{{ __('Campaign ID') }} *"
                    placeholder="{{ __('Campaign ID') }}"
                    x-model.number="selectedStep.config.campaign_id"
                    required
                />
                <x-forms.input
                    type="datetime-local"
                    label="{{ __('Schedule At') }}"
                    x-model="selectedStep.config.scheduled_at"
                    tooltip="{{ __('Leave blank to schedule immediately.') }}"
                />
            </div>
        </template>
    </div>
</template>
