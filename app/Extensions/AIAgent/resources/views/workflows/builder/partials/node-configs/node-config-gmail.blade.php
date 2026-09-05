<template x-if="selectedStep.type === 'gmail'">
    <div class="space-y-4">
        {{-- Action Event --}}
        <div>
            <x-forms.input
                type="select"
                label="{{ __('Action Event') }}"
                x-model="selectedStep.config.action_event"
                tooltip="{{ __('Choose which Gmail operation to perform.') }}"
            >
                <option value="">{{ __('Select an action event...') }}</option>
                <option value="send_email">{{ __('Send Email') }}</option>
                <option value="reply_to_email">{{ __('Reply to Email') }}</option>
                <option value="create_draft">{{ __('Create Draft') }}</option>
                <option value="create_draft_reply">{{ __('Create Draft Reply') }}</option>
                <option value="create_label">{{ __('Create Label') }}</option>
                <option value="add_label_to_email">{{ __('Add Label to Email') }}</option>
                <option value="remove_label_from_email">{{ __('Remove Label from Email') }}</option>
                <option value="remove_label_from_conversation">{{ __('Remove Label from Conversation') }}</option>
                <option value="archive_email">{{ __('Archive Email') }}</option>
                <option value="delete_email">{{ __('Delete Email') }}</option>
                <option value="find_email">{{ __('Find Email') }}</option>
                <option value="get_attachment_by_filename">{{ __('Get Attachment by Filename') }}</option>
            </x-forms.input>
        </div>

        {{-- send_email fields --}}
        <template x-if="selectedStep.config.action_event === 'send_email'">
            <div class="space-y-3">
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                        {{ __('To') }}
                        <span class="ms-1 text-[10px] font-normal text-red-500">{{ __('required') }}</span>
                        <x-info-tooltip text="{{ __('Use {context_key} for dynamic values.') }}" />
                    </label>
                    <div
                        class="relative rounded-input border border-input-border bg-input-background focus-within:ring-1 focus-within:ring-primary/30"
                        x-data="variableTagInput({ configKey: 'to', stepRef: selectedStep, multiline: false })"
                    >
                        <div
                            class="px-3 py-2 pe-8 text-sm outline-none"
                            data-placeholder="recipient@example.com"
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
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                        {{ __('Subject') }}
                        <span class="ms-1 text-[10px] font-normal text-red-500">{{ __('required') }}</span>
                    </label>
                    <div
                        class="relative rounded-input border border-input-border bg-input-background focus-within:ring-1 focus-within:ring-primary/30"
                        x-data="variableTagInput({ configKey: 'subject', stepRef: selectedStep, multiline: false })"
                    >
                        <div
                            class="px-3 py-2 pe-8 text-sm outline-none"
                            data-placeholder="{{ __('Email subject') }}"
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
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                        {{ __('Body') }}
                        <span class="ms-1 text-[10px] font-normal text-red-500">{{ __('required') }}</span>
                    </label>
                    <div
                        class="relative rounded-input border border-input-border bg-input-background focus-within:ring-1 focus-within:ring-primary/30"
                        x-data="variableTagInput({ configKey: 'body', stepRef: selectedStep, multiline: true })"
                    >
                        <div
                            class="px-3 py-2 pe-8 text-sm outline-none"
                            data-placeholder="{{ __('Email body content') }}"
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
                    type="text"
                    label="{{ __('From (optional)') }}"
                    size="lg"
                    placeholder="{{ __('Defaults to connected Gmail address') }}"
                    x-model="selectedStep.config.from"
                />
            </div>
        </template>

        {{-- reply_to_email fields --}}
        <template x-if="selectedStep.config.action_event === 'reply_to_email'">
            <div class="space-y-3">
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                        {{ __('Message') }}
                        <span class="ms-1 text-[10px] font-normal text-red-500">{{ __('required') }}</span>
                    </label>
                    <x-forms.input
                        type="select"
                        x-model="selectedStep.config.message_id"
                        ::disabled="gmailMessagesLoading"
                    >
                        <option
                            value=""
                            x-text="gmailMessagesLoading ? '{{ __('Loading...') }}' : '{{ __('Select a message...') }}'"
                        ></option>
                        <template
                            x-for="msg in gmailMessages"
                            :key="msg.id"
                        >
                            <option
                                :value="msg.id"
                                x-text="msg.subject + ' — ' + msg.from"
                            ></option>
                        </template>
                    </x-forms.input>
                    <div class="mt-1 flex justify-end">
                        <button
                            class="text-[10px] text-primary hover:underline"
                            type="button"
                            @click="fetchGmailMessages()"
                            :disabled="gmailMessagesLoading"
                        >{{ __('↻ Refresh') }}</button>
                    </div>
                </div>
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                        {{ __('Body') }}
                        <span class="ms-1 text-[10px] font-normal text-red-500">{{ __('required') }}</span>
                    </label>
                    <div
                        class="relative rounded-input border border-input-border bg-input-background focus-within:ring-1 focus-within:ring-primary/30"
                        x-data="variableTagInput({ configKey: 'body', stepRef: selectedStep, multiline: true })"
                    >
                        <div
                            class="px-3 py-2 pe-8 text-sm outline-none"
                            data-placeholder="{{ __('Reply body content') }}"
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
            </div>
        </template>

        {{-- create_draft fields --}}
        <template x-if="selectedStep.config.action_event === 'create_draft'">
            <div class="space-y-3">
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                        {{ __('To') }}
                        <span class="ms-1 text-[10px] font-normal text-red-500">{{ __('required') }}</span>
                    </label>
                    <div
                        class="relative rounded-input border border-input-border bg-input-background focus-within:ring-1 focus-within:ring-primary/30"
                        x-data="variableTagInput({ configKey: 'to', stepRef: selectedStep, multiline: false })"
                    >
                        <div
                            class="px-3 py-2 pe-8 text-sm outline-none"
                            data-placeholder="recipient@example.com"
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
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                        {{ __('Subject') }}
                        <span class="ms-1 text-[10px] font-normal text-red-500">{{ __('required') }}</span>
                    </label>
                    <div
                        class="relative rounded-input border border-input-border bg-input-background focus-within:ring-1 focus-within:ring-primary/30"
                        x-data="variableTagInput({ configKey: 'subject', stepRef: selectedStep, multiline: false })"
                    >
                        <div
                            class="px-3 py-2 pe-8 text-sm outline-none"
                            data-placeholder="{{ __('Draft subject') }}"
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
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                        {{ __('Body') }}
                        <span class="ms-1 text-[10px] font-normal text-red-500">{{ __('required') }}</span>
                    </label>
                    <div
                        class="relative rounded-input border border-input-border bg-input-background focus-within:ring-1 focus-within:ring-primary/30"
                        x-data="variableTagInput({ configKey: 'body', stepRef: selectedStep, multiline: true })"
                    >
                        <div
                            class="px-3 py-2 pe-8 text-sm outline-none"
                            data-placeholder="{{ __('Draft body content') }}"
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
                    type="text"
                    label="{{ __('From (optional)') }}"
                    size="lg"
                    placeholder="{{ __('Defaults to connected Gmail address') }}"
                    x-model="selectedStep.config.from"
                />
            </div>
        </template>

        {{-- create_draft_reply fields --}}
        <template x-if="selectedStep.config.action_event === 'create_draft_reply'">
            <div class="space-y-3">
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                        {{ __('Message') }}
                        <span class="ms-1 text-[10px] font-normal text-red-500">{{ __('required') }}</span>
                    </label>
                    <x-forms.input
                        type="select"
                        x-model="selectedStep.config.message_id"
                        ::disabled="gmailMessagesLoading"
                    >
                        <option
                            value=""
                            x-text="gmailMessagesLoading ? '{{ __('Loading...') }}' : '{{ __('Select a message...') }}'"
                        ></option>
                        <template
                            x-for="msg in gmailMessages"
                            :key="msg.id"
                        >
                            <option
                                :value="msg.id"
                                x-text="msg.subject + ' — ' + msg.from"
                            ></option>
                        </template>
                    </x-forms.input>
                    <div class="mt-1 flex justify-end">
                        <button
                            class="text-[10px] text-primary hover:underline"
                            type="button"
                            @click="fetchGmailMessages()"
                            :disabled="gmailMessagesLoading"
                        >{{ __('↻ Refresh') }}</button>
                    </div>
                </div>
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                        {{ __('Body') }}
                        <span class="ms-1 text-[10px] font-normal text-red-500">{{ __('required') }}</span>
                    </label>
                    <div
                        class="relative rounded-input border border-input-border bg-input-background focus-within:ring-1 focus-within:ring-primary/30"
                        x-data="variableTagInput({ configKey: 'body', stepRef: selectedStep, multiline: true })"
                    >
                        <div
                            class="px-3 py-2 pe-8 text-sm outline-none"
                            data-placeholder="{{ __('Draft reply body content') }}"
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
            </div>
        </template>

        {{-- create_label fields --}}
        <template x-if="selectedStep.config.action_event === 'create_label'">
            <div class="space-y-3">
                <x-forms.input
                    type="text"
                    label="{{ __('Label Name') }} *"
                    placeholder="{{ __('e.g. Urgent, Follow-up') }}"
                    x-model="selectedStep.config.label_name"
                    required
                />
            </div>
        </template>

        {{-- add_label_to_email fields --}}
        <template x-if="selectedStep.config.action_event === 'add_label_to_email'">
            <div class="space-y-3">
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                        {{ __('Message') }}
                        <span class="ms-1 text-[10px] font-normal text-red-500">{{ __('required') }}</span>
                    </label>
                    <x-forms.input
                        type="select"
                        x-model="selectedStep.config.message_id"
                        ::disabled="gmailMessagesLoading"
                    >
                        <option
                            value=""
                            x-text="gmailMessagesLoading ? '{{ __('Loading...') }}' : '{{ __('Select a message...') }}'"
                        ></option>
                        <template
                            x-for="msg in gmailMessages"
                            :key="msg.id"
                        >
                            <option
                                :value="msg.id"
                                x-text="msg.subject + ' — ' + msg.from"
                            ></option>
                        </template>
                    </x-forms.input>
                    <div class="mt-1 flex justify-end">
                        <button
                            class="text-[10px] text-primary hover:underline"
                            type="button"
                            @click="fetchGmailMessages()"
                            :disabled="gmailMessagesLoading"
                        >{{ __('↻ Refresh') }}</button>
                    </div>
                </div>
                <x-forms.input
                    type="text"
                    label="{{ __('Label IDs') }} *"
                    placeholder="{{ __('INBOX,Label_123 (comma-separated)') }}"
                    x-model="selectedStep.config.label_ids"
                    required
                    tooltip="{{ __('Comma-separated Gmail label IDs.') }}"
                />
            </div>
        </template>

        {{-- remove_label_from_email fields --}}
        <template x-if="selectedStep.config.action_event === 'remove_label_from_email'">
            <div class="space-y-3">
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                        {{ __('Message') }}
                        <span class="ms-1 text-[10px] font-normal text-red-500">{{ __('required') }}</span>
                    </label>
                    <x-forms.input
                        type="select"
                        x-model="selectedStep.config.message_id"
                        ::disabled="gmailMessagesLoading"
                    >
                        <option
                            value=""
                            x-text="gmailMessagesLoading ? '{{ __('Loading...') }}' : '{{ __('Select a message...') }}'"
                        ></option>
                        <template
                            x-for="msg in gmailMessages"
                            :key="msg.id"
                        >
                            <option
                                :value="msg.id"
                                x-text="msg.subject + ' — ' + msg.from"
                            ></option>
                        </template>
                    </x-forms.input>
                    <div class="mt-1 flex justify-end">
                        <button
                            class="text-[10px] text-primary hover:underline"
                            type="button"
                            @click="fetchGmailMessages()"
                            :disabled="gmailMessagesLoading"
                        >{{ __('↻ Refresh') }}</button>
                    </div>
                </div>
                <x-forms.input
                    type="text"
                    label="{{ __('Label IDs') }} *"
                    placeholder="{{ __('INBOX,Label_123 (comma-separated)') }}"
                    x-model="selectedStep.config.label_ids"
                    required
                    tooltip="{{ __('Comma-separated Gmail label IDs to remove.') }}"
                />
            </div>
        </template>

        {{-- remove_label_from_conversation fields --}}
        <template x-if="selectedStep.config.action_event === 'remove_label_from_conversation'">
            <div class="space-y-3">
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                        {{ __('Conversation') }}
                        <span class="ms-1 text-[10px] font-normal text-red-500">{{ __('required') }}</span>
                    </label>
                    <x-forms.input
                        type="select"
                        x-model="selectedStep.config.thread_id"
                        ::disabled="gmailThreadsLoading"
                    >
                        <option
                            value=""
                            x-text="gmailThreadsLoading ? '{{ __('Loading...') }}' : '{{ __('Select a conversation...') }}'"
                        ></option>
                        <template
                            x-for="thread in gmailThreads"
                            :key="thread.id"
                        >
                            <option
                                :value="thread.id"
                                x-text="thread.subject + ' — ' + thread.from"
                            ></option>
                        </template>
                    </x-forms.input>
                    <div class="mt-1 flex justify-end">
                        <button
                            class="text-[10px] text-primary hover:underline"
                            type="button"
                            @click="fetchGmailThreads()"
                            :disabled="gmailThreadsLoading"
                        >{{ __('↻ Refresh') }}</button>
                    </div>
                </div>
                <x-forms.input
                    type="text"
                    label="{{ __('Label IDs') }} *"
                    placeholder="{{ __('INBOX,Label_123 (comma-separated)') }}"
                    x-model="selectedStep.config.label_ids"
                    required
                    tooltip="{{ __('Removes label from all messages in thread.') }}"
                />
            </div>
        </template>

        {{-- archive_email fields --}}
        <template x-if="selectedStep.config.action_event === 'archive_email'">
            <div class="space-y-3">
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                        {{ __('Message') }}
                        <span class="ms-1 text-[10px] font-normal text-red-500">{{ __('required') }}</span>
                    </label>
                    <x-forms.input
                        type="select"
                        x-model="selectedStep.config.message_id"
                        ::disabled="gmailMessagesLoading"
                        tooltip="{{ __('Removes INBOX label — email moves to All Mail.') }}"
                    >
                        <option
                            value=""
                            x-text="gmailMessagesLoading ? '{{ __('Loading...') }}' : '{{ __('Select a message...') }}'"
                        ></option>
                        <template
                            x-for="msg in gmailMessages"
                            :key="msg.id"
                        >
                            <option
                                :value="msg.id"
                                x-text="msg.subject + ' — ' + msg.from"
                            ></option>
                        </template>
                    </x-forms.input>
                    <div class="mt-1 flex items-center justify-between">
                        <button
                            class="text-[10px] text-primary hover:underline"
                            type="button"
                            @click="fetchGmailMessages()"
                            :disabled="gmailMessagesLoading"
                        >{{ __('↻ Refresh') }}</button>
                    </div>
                </div>
            </div>
        </template>

        {{-- delete_email fields --}}
        <template x-if="selectedStep.config.action_event === 'delete_email'">
            <div class="space-y-3">
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                        {{ __('Message') }}
                        <span class="ms-1 text-[10px] font-normal text-red-500">{{ __('required') }}</span>
                    </label>
                    <x-forms.input
                        type="select"
                        x-model="selectedStep.config.message_id"
                        ::disabled="gmailMessagesLoading"
                    >
                        <option
                            value=""
                            x-text="gmailMessagesLoading ? '{{ __('Loading...') }}' : '{{ __('Select a message...') }}'"
                        ></option>
                        <template
                            x-for="msg in gmailMessages"
                            :key="msg.id"
                        >
                            <option
                                :value="msg.id"
                                x-text="msg.subject + ' — ' + msg.from"
                            ></option>
                        </template>
                    </x-forms.input>
                    <div class="mt-1 flex items-center justify-between">
                        <p class="text-[10px] text-foreground/40">{{ __('Moves the message to Trash.') }}</p>
                        <button
                            class="text-[10px] text-primary hover:underline"
                            type="button"
                            @click="fetchGmailMessages()"
                            :disabled="gmailMessagesLoading"
                        >{{ __('↻ Refresh') }}</button>
                    </div>
                </div>
            </div>
        </template>

        {{-- find_email fields --}}
        <template x-if="selectedStep.config.action_event === 'find_email'">
            <div class="space-y-3">
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                        {{ __('Search Query') }}
                        <span class="ms-1 text-[10px] font-normal text-red-500">{{ __('required') }}</span>
                        <x-info-tooltip text="{{ __('Gmail search syntax supported. Use {context_key} for dynamic values.') }}" />
                    </label>
                    <div
                        class="relative rounded-input border border-input-border bg-input-background focus-within:ring-1 focus-within:ring-primary/30"
                        x-data="variableTagInput({ configKey: 'query', stepRef: selectedStep, multiline: false })"
                    >
                        <div
                            class="px-3 py-2 pe-8 text-sm outline-none"
                            data-placeholder="{{ __('e.g. from:user@example.com subject:Invoice') }}"
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
                    type="number"
                    min="1"
                    max="50"
                    label="{{ __('Max Results') }}"
                    placeholder="10"
                    x-model.number="selectedStep.config.max_results"
                    tooltip="{{ __('Options other than ID fetch extra data from the Gmail API.') }}"
                />
                <div>
                    <x-forms.input
                        type="select"
                        label="{{ __('Return Format') }}"
                        x-model="selectedStep.config.return_format"
                    >
                        <option value="id">{{ __('ID') }}</option>
                        <option value="subject">{{ __('Subject') }}</option>
                        <option value="body">{{ __('Body') }}</option>
                        <option value="sender">{{ __('Sender') }}</option>
                        <option value="date">{{ __('Date') }}</option>
                        <option value="full_as_string">{{ __('Full as string') }}</option>
                    </x-forms.input>
                </div>
            </div>
        </template>

        {{-- get_attachment_by_filename fields --}}
        <template x-if="selectedStep.config.action_event === 'get_attachment_by_filename'">
            <div class="space-y-3">
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                        {{ __('Message') }}
                        <span class="ms-1 text-[10px] font-normal text-red-500">{{ __('required') }}</span>
                    </label>
                    <x-forms.input
                        type="select"
                        x-model="selectedStep.config.message_id"
                        ::disabled="gmailMessagesLoading"
                    >
                        <option
                            value=""
                            x-text="gmailMessagesLoading ? '{{ __('Loading...') }}' : '{{ __('Select a message...') }}'"
                        ></option>
                        <template
                            x-for="msg in gmailMessages"
                            :key="msg.id"
                        >
                            <option
                                :value="msg.id"
                                x-text="msg.subject + ' — ' + msg.from"
                            ></option>
                        </template>
                    </x-forms.input>
                    <div class="mt-1 flex justify-end">
                        <button
                            class="text-[10px] text-primary hover:underline"
                            type="button"
                            @click="fetchGmailMessages()"
                            :disabled="gmailMessagesLoading"
                        >{{ __('↻ Refresh') }}</button>
                    </div>
                </div>
                <x-forms.input
                    type="text"
                    label="{{ __('Filename') }} *"
                    placeholder="{{ __('e.g. invoice.pdf') }}"
                    x-model="selectedStep.config.filename"
                    required
                />
            </div>
        </template>
    </div>
</template>
