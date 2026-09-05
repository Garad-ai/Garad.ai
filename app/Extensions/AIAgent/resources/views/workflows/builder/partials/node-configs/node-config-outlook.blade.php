<template x-if="selectedStep.type === 'outlook'">
    <div class="space-y-4">
        {{-- Action Event --}}
        <div>
            <x-forms.input
                type="select"
                label="{{ __('Action Event') }}"
                x-model="selectedStep.config.action_event"
                tooltip="{{ __('Choose which Outlook operation to perform.') }}"
            >
                <option value="">{{ __('Select an action event...') }}</option>
                <optgroup label="{{ __('Email') }}">
                    <option value="send_email">{{ __('Send Email') }}</option>
                    <option value="create_draft_email">{{ __('Create Draft Email') }}</option>
                    <option value="send_draft_email">{{ __('Send Draft Email') }}</option>
                    <option value="create_draft_reply">{{ __('Create Draft Reply') }}</option>
                    <option value="reply_to_email">{{ __('Reply to Email') }}</option>
                    <option value="forward_email">{{ __('Forward Email') }}</option>
                    <option value="copy_email">{{ __('Copy Email') }}</option>
                    <option value="move_email_to_folder">{{ __('Move Email to Folder') }}</option>
                    <option value="delete_email">{{ __('Delete Email') }}</option>
                    <option value="mark_email_as_read_unread">{{ __('Mark Email as Read/Unread') }}</option>
                    <option value="flag_unflag_email">{{ __('Flag / Unflag Email') }}</option>
                    <option value="set_email_importance">{{ __('Set Email Importance') }}</option>
                    <option value="set_categories_on_email">{{ __('Set Categories on Email') }}</option>
                    <option value="remove_categories_from_email">{{ __('Remove Categories from Email') }}</option>
                    <option value="find_emails">{{ __('Find Emails') }}</option>
                    <option value="create_folder">{{ __('Create Folder') }}</option>
                </optgroup>
                <optgroup label="{{ __('Calendar') }}">
                    <option value="create_event">{{ __('Create Event') }}</option>
                    <option value="update_calendar_event">{{ __('Update Calendar Event') }}</option>
                    <option value="add_attendees_to_calendar_event">{{ __('Add Attendees to Calendar Event') }}</option>
                    <option value="find_calendar_events">{{ __('Find Calendar Events') }}</option>
                </optgroup>
                <optgroup label="{{ __('Contacts') }}">
                    <option value="create_contact">{{ __('Create Contact') }}</option>
                    <option value="update_contact">{{ __('Update Contact') }}</option>
                    <option value="delete_contact">{{ __('Delete Contact') }}</option>
                    <option value="find_contacts">{{ __('Find Contacts') }}</option>
                </optgroup>
            </x-forms.input>
        </div>

        {{-- send_email --}}
        <template x-if="selectedStep.config.action_event === 'send_email'">
            <div class="space-y-3">
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                        {{ __('To') }}
                        <span class="ms-1 text-[10px] font-normal text-red-500">
                            {{ __('required') }}
                        </span>
                        <x-info-tooltip text="{{ __('Comma-separated for multiple recipients. Use {context_key} for dynamic values.') }}" />
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
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">{{ __('Subject') }} <span
                            class="ms-1 text-[10px] font-normal text-red-500"
                        >{{ __('required') }}</span></label>
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
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">{{ __('Body') }} <span
                            class="ms-1 text-[10px] font-normal text-red-500"
                        >{{ __('required') }}</span></label>
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
                    label="{{ __('CC (optional)') }}"
                    placeholder="{{ __('cc@example.com, another@example.com') }}"
                    x-model="selectedStep.config.cc"
                />
            </div>
        </template>

        {{-- create_draft_email --}}
        <template x-if="selectedStep.config.action_event === 'create_draft_email'">
            <div class="space-y-3">
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">{{ __('To') }} <span
                            class="ms-1 text-[10px] font-normal text-red-500"
                        >{{ __('required') }}</span></label>
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
                <x-forms.input
                    type="text"
                    label="{{ __('Subject') }} *"
                    placeholder="{{ __('Draft subject') }}"
                    x-model="selectedStep.config.subject"
                    required
                />
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">{{ __('Body') }} <span
                            class="ms-1 text-[10px] font-normal text-red-500"
                        >{{ __('required') }}</span></label>
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
                    label="{{ __('CC (optional)') }}"
                    placeholder="{{ __('cc@example.com') }}"
                    x-model="selectedStep.config.cc"
                />
            </div>
        </template>

        {{-- send_draft_email --}}
        <template x-if="selectedStep.config.action_event === 'send_draft_email'">
            <div class="space-y-3">
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                        {{ __('Message ID') }}
                        <span class="ms-1 text-[10px] font-normal text-red-500">
                            {{ __('required') }}
                        </span>
                        <x-info-tooltip text="{{ __('Use {context_key} from a previous Create Draft Email step.') }}" />
                    </label>
                    <div
                        class="relative rounded-input border border-input-border bg-input-background focus-within:ring-1 focus-within:ring-primary/30"
                        x-data="variableTagInput({ configKey: 'message_id', stepRef: selectedStep, multiline: false })"
                    >
                        <div
                            class="px-3 py-2 pe-8 text-sm outline-none"
                            data-placeholder="{{ __('{draft_email.message_id}') }}"
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

        {{-- create_draft_reply --}}
        <template x-if="selectedStep.config.action_event === 'create_draft_reply'">
            <div class="space-y-3">
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">{{ __('Message ID') }} <span
                            class="ms-1 text-[10px] font-normal text-red-500"
                        >{{ __('required') }}</span></label>
                    <div
                        class="relative rounded-input border border-input-border bg-input-background focus-within:ring-1 focus-within:ring-primary/30"
                        x-data="variableTagInput({ configKey: 'message_id', stepRef: selectedStep, multiline: false })"
                    >
                        <div
                            class="px-3 py-2 pe-8 text-sm outline-none"
                            data-placeholder="{{ __('{found_emails}') }}"
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
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">{{ __('Body (optional)') }}</label>
                    <div
                        class="relative rounded-input border border-input-border bg-input-background focus-within:ring-1 focus-within:ring-primary/30"
                        x-data="variableTagInput({ configKey: 'body', stepRef: selectedStep, multiline: true })"
                    >
                        <div
                            class="px-3 py-2 pe-8 text-sm outline-none"
                            data-placeholder="{{ __('Pre-fill the reply body') }}"
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

        {{-- reply_to_email --}}
        <template x-if="selectedStep.config.action_event === 'reply_to_email'">
            <div class="space-y-3">
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">{{ __('Message ID') }} <span
                            class="ms-1 text-[10px] font-normal text-red-500"
                        >{{ __('required') }}</span></label>
                    <div
                        class="relative rounded-input border border-input-border bg-input-background focus-within:ring-1 focus-within:ring-primary/30"
                        x-data="variableTagInput({ configKey: 'message_id', stepRef: selectedStep, multiline: false })"
                    >
                        <div
                            class="px-3 py-2 pe-8 text-sm outline-none"
                            data-placeholder="{{ __('{found_emails}') }}"
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
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">{{ __('Body') }} <span
                            class="ms-1 text-[10px] font-normal text-red-500"
                        >{{ __('required') }}</span></label>
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

        {{-- forward_email --}}
        <template x-if="selectedStep.config.action_event === 'forward_email'">
            <div class="space-y-3">
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">{{ __('Message ID') }} <span
                            class="ms-1 text-[10px] font-normal text-red-500"
                        >{{ __('required') }}</span></label>
                    <div
                        class="relative rounded-input border border-input-border bg-input-background focus-within:ring-1 focus-within:ring-primary/30"
                        x-data="variableTagInput({ configKey: 'message_id', stepRef: selectedStep, multiline: false })"
                    >
                        <div
                            class="px-3 py-2 pe-8 text-sm outline-none"
                            data-placeholder="{{ __('{found_emails}') }}"
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
                    label="{{ __('To') }} *"
                    placeholder="recipient@example.com"
                    x-model="selectedStep.config.to"
                    required
                />
                <x-forms.input
                    type="textarea"
                    rows="2"
                    label="{{ __('Comment (optional)') }}"
                    placeholder="{{ __('Forwarding note') }}"
                    x-model="selectedStep.config.comment"
                />
            </div>
        </template>

        {{-- copy_email --}}
        <template x-if="selectedStep.config.action_event === 'copy_email'">
            <div class="space-y-3">
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">{{ __('Message ID') }} <span
                            class="ms-1 text-[10px] font-normal text-red-500"
                        >{{ __('required') }}</span></label>
                    <div
                        class="relative rounded-input border border-input-border bg-input-background focus-within:ring-1 focus-within:ring-primary/30"
                        x-data="variableTagInput({ configKey: 'message_id', stepRef: selectedStep, multiline: false })"
                    >
                        <div
                            class="px-3 py-2 pe-8 text-sm outline-none"
                            data-placeholder="{{ __('{found_emails}') }}"
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
                    label="{{ __('Destination Folder ID') }} *"
                    placeholder="{{ __('e.g. inbox, deleteditems, or folder ID') }}"
                    x-model="selectedStep.config.destination_id"
                    required
                    tooltip="{{ __('Well-known names: inbox, drafts, sentitems, deleteditems, archive, junkemail') }}"
                />
            </div>
        </template>

        {{-- move_email_to_folder --}}
        <template x-if="selectedStep.config.action_event === 'move_email_to_folder'">
            <div class="space-y-3">
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">{{ __('Message ID') }} <span
                            class="ms-1 text-[10px] font-normal text-red-500"
                        >{{ __('required') }}</span></label>
                    <div
                        class="relative rounded-input border border-input-border bg-input-background focus-within:ring-1 focus-within:ring-primary/30"
                        x-data="variableTagInput({ configKey: 'message_id', stepRef: selectedStep, multiline: false })"
                    >
                        <div
                            class="px-3 py-2 pe-8 text-sm outline-none"
                            data-placeholder="{{ __('{found_emails}') }}"
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
                    label="{{ __('Destination Folder ID') }} *"
                    placeholder="{{ __('e.g. archive, deleteditems, or folder ID') }}"
                    x-model="selectedStep.config.destination_id"
                    required
                    tooltip="{{ __('Well-known names: inbox, drafts, sentitems, deleteditems, archive, junkemail') }}"
                />
            </div>
        </template>

        {{-- delete_email --}}
        <template x-if="selectedStep.config.action_event === 'delete_email'">
            <div class="space-y-3">
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                        {{ __('Message ID') }}
                        <span class="ms-1 text-[10px] font-normal text-red-500">
                            {{ __('required') }}
                        </span>
                        <x-info-tooltip text="{{ __('Permanently deletes the message.') }}" />
                    </label>
                    <div
                        class="relative rounded-input border border-input-border bg-input-background focus-within:ring-1 focus-within:ring-primary/30"
                        x-data="variableTagInput({ configKey: 'message_id', stepRef: selectedStep, multiline: false })"
                    >
                        <div
                            class="px-3 py-2 pe-8 text-sm outline-none"
                            data-placeholder="{{ __('{found_emails}') }}"
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

        {{-- mark_email_as_read_unread --}}
        <template x-if="selectedStep.config.action_event === 'mark_email_as_read_unread'">
            <div class="space-y-3">
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">{{ __('Message ID') }} <span
                            class="ms-1 text-[10px] font-normal text-red-500"
                        >{{ __('required') }}</span></label>
                    <div
                        class="relative rounded-input border border-input-border bg-input-background focus-within:ring-1 focus-within:ring-primary/30"
                        x-data="variableTagInput({ configKey: 'message_id', stepRef: selectedStep, multiline: false })"
                    >
                        <div
                            class="px-3 py-2 pe-8 text-sm outline-none"
                            data-placeholder="{{ __('{found_emails}') }}"
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
                    type="select"
                    label="{{ __('Status') }} *"
                    x-model="selectedStep.config.is_read"
                    required
                >
                    <option value="true">{{ __('Mark as Read') }}</option>
                    <option value="false">{{ __('Mark as Unread') }}</option>
                </x-forms.input>
            </div>
        </template>

        {{-- flag_unflag_email --}}
        <template x-if="selectedStep.config.action_event === 'flag_unflag_email'">
            <div class="space-y-3">
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">{{ __('Message ID') }} <span
                            class="ms-1 text-[10px] font-normal text-red-500"
                        >{{ __('required') }}</span></label>
                    <div
                        class="relative rounded-input border border-input-border bg-input-background focus-within:ring-1 focus-within:ring-primary/30"
                        x-data="variableTagInput({ configKey: 'message_id', stepRef: selectedStep, multiline: false })"
                    >
                        <div
                            class="px-3 py-2 pe-8 text-sm outline-none"
                            data-placeholder="{{ __('{found_emails}') }}"
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
                    type="select"
                    label="{{ __('Flag Status') }} *"
                    x-model="selectedStep.config.flag_status"
                    required
                >
                    <option value="flagged">{{ __('Flagged') }}</option>
                    <option value="notFlagged">{{ __('Not Flagged') }}</option>
                    <option value="complete">{{ __('Complete') }}</option>
                </x-forms.input>
            </div>
        </template>

        {{-- set_email_importance --}}
        <template x-if="selectedStep.config.action_event === 'set_email_importance'">
            <div class="space-y-3">
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">{{ __('Message ID') }} <span
                            class="ms-1 text-[10px] font-normal text-red-500"
                        >{{ __('required') }}</span></label>
                    <div
                        class="relative rounded-input border border-input-border bg-input-background focus-within:ring-1 focus-within:ring-primary/30"
                        x-data="variableTagInput({ configKey: 'message_id', stepRef: selectedStep, multiline: false })"
                    >
                        <div
                            class="px-3 py-2 pe-8 text-sm outline-none"
                            data-placeholder="{{ __('{found_emails}') }}"
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
                    type="select"
                    label="{{ __('Importance') }} *"
                    x-model="selectedStep.config.importance"
                    required
                >
                    <option value="normal">{{ __('Normal') }}</option>
                    <option value="high">{{ __('High') }}</option>
                    <option value="low">{{ __('Low') }}</option>
                </x-forms.input>
            </div>
        </template>

        {{-- set_categories_on_email --}}
        <template x-if="selectedStep.config.action_event === 'set_categories_on_email'">
            <div class="space-y-3">
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">{{ __('Message ID') }} <span
                            class="ms-1 text-[10px] font-normal text-red-500"
                        >{{ __('required') }}</span></label>
                    <div
                        class="relative rounded-input border border-input-border bg-input-background focus-within:ring-1 focus-within:ring-primary/30"
                        x-data="variableTagInput({ configKey: 'message_id', stepRef: selectedStep, multiline: false })"
                    >
                        <div
                            class="px-3 py-2 pe-8 text-sm outline-none"
                            data-placeholder="{{ __('{found_emails}') }}"
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
                    label="{{ __('Categories') }} *"
                    placeholder="{{ __('Red Category, Blue Category') }}"
                    x-model="selectedStep.config.categories"
                    required
                    tooltip="{{ __('Comma-separated. Replaces existing categories.') }}"
                />
            </div>
        </template>

        {{-- remove_categories_from_email --}}
        <template x-if="selectedStep.config.action_event === 'remove_categories_from_email'">
            <div class="space-y-3">
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                        {{ __('Message ID') }}
                        <span class="ms-1 text-[10px] font-normal text-red-500">
                            {{ __('required') }}
                        </span>
                        <x-info-tooltip text="{{ __('Clears all categories from the message.') }}" />
                    </label>
                    <div
                        class="relative rounded-input border border-input-border bg-input-background focus-within:ring-1 focus-within:ring-primary/30"
                        x-data="variableTagInput({ configKey: 'message_id', stepRef: selectedStep, multiline: false })"
                    >
                        <div
                            class="px-3 py-2 pe-8 text-sm outline-none"
                            data-placeholder="{{ __('{found_emails}') }}"
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

        {{-- find_emails --}}
        <template x-if="selectedStep.config.action_event === 'find_emails'">
            <div class="space-y-3">
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                        {{ __('Search Query') }}
                        <span class="ms-1 text-[10px] font-normal text-red-500">
                            {{ __('required') }}
                        </span>
                        <x-info-tooltip text="{{ __('OData $search syntax. Use {context_key} for dynamic values.') }}" />
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
                />
                <x-forms.input
                    type="select"
                    label="{{ __('Return Format') }}"
                    x-model="selectedStep.config.return_format"
                >
                    <option value="id">{{ __('ID') }}</option>
                    <option value="subject">{{ __('Subject') }}</option>
                    <option value="sender">{{ __('Sender') }}</option>
                    <option value="date">{{ __('Date') }}</option>
                    <option value="body">{{ __('Body') }}</option>
                    <option value="full_as_string">{{ __('Full as string') }}</option>
                </x-forms.input>
            </div>
        </template>

        {{-- create_folder --}}
        <template x-if="selectedStep.config.action_event === 'create_folder'">
            <div class="space-y-3">
                <x-forms.input
                    type="text"
                    label="{{ __('Display Name') }} *"
                    placeholder="{{ __('e.g. Processed, Archive-2024') }}"
                    x-model="selectedStep.config.display_name"
                    required
                />
            </div>
        </template>

        {{-- create_event --}}
        <template x-if="selectedStep.config.action_event === 'create_event'">
            <div class="space-y-3">
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                        {{ __('Subject') }}
                        <span class="ms-1 text-[10px] font-normal text-red-500">
                            {{ __('required') }}
                        </span>
                    </label>
                    <div
                        class="relative rounded-input border border-input-border bg-input-background focus-within:ring-1 focus-within:ring-primary/30"
                        x-data="variableTagInput({ configKey: 'subject', stepRef: selectedStep, multiline: false })"
                    >
                        <div
                            class="px-3 py-2 pe-8 text-sm outline-none"
                            data-placeholder="{{ __('Meeting subject') }}"
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
                <div class="grid grid-cols-2 gap-3">
                    <x-forms.input
                        type="text"
                        label="{{ __('Start') }} *"
                        placeholder="{{ __('2025-01-15T10:00:00') }}"
                        x-model="selectedStep.config.start"
                        required
                    />
                    <x-forms.input
                        type="text"
                        label="{{ __('End') }} *"
                        placeholder="{{ __('2025-01-15T11:00:00') }}"
                        x-model="selectedStep.config.end"
                        required
                    />
                </div>
                <x-forms.input
                    type="text"
                    label="{{ __('Timezone') }}"
                    placeholder="UTC"
                    x-model="selectedStep.config.timezone"
                />
                <x-forms.input
                    type="textarea"
                    rows="2"
                    label="{{ __('Description (optional)') }}"
                    placeholder="{{ __('Event description') }}"
                    x-model="selectedStep.config.body"
                />
                <x-forms.input
                    type="text"
                    label="{{ __('Location (optional)') }}"
                    placeholder="{{ __('Conference Room A') }}"
                    x-model="selectedStep.config.location"
                />
                <x-forms.input
                    type="text"
                    label="{{ __('Attendees (optional)') }}"
                    placeholder="{{ __('user@example.com, other@example.com') }}"
                    x-model="selectedStep.config.attendees"
                    tooltip="{{ __('Comma-separated email addresses.') }}"
                />
                <x-forms.input
                    id="outlook_is_online"
                    type="checkbox"
                    label="{{ __('Create as Teams meeting') }}"
                    x-model="selectedStep.config.is_online"
                />
            </div>
        </template>

        {{-- update_calendar_event --}}
        <template x-if="selectedStep.config.action_event === 'update_calendar_event'">
            <div class="space-y-3">
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                        {{ __('Event ID') }}
                        <span class="ms-1 text-[10px] font-normal text-red-500">
                            {{ __('required') }}
                        </span>
                    </label>
                    <div
                        class="relative rounded-input border border-input-border bg-input-background focus-within:ring-1 focus-within:ring-primary/30"
                        x-data="variableTagInput({ configKey: 'event_id', stepRef: selectedStep, multiline: false })"
                    >
                        <div
                            class="px-3 py-2 pe-8 text-sm outline-none"
                            data-placeholder="{{ __('{found_events}') }}"
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
                    label="{{ __('Subject (optional)') }}"
                    placeholder="{{ __('New subject') }}"
                    x-model="selectedStep.config.subject"
                />
                <div class="grid grid-cols-2 gap-3">
                    <x-forms.input
                        type="text"
                        label="{{ __('Start (optional)') }}"
                        placeholder="{{ __('2025-01-15T10:00:00') }}"
                        x-model="selectedStep.config.start"
                    />
                    <x-forms.input
                        type="text"
                        label="{{ __('End (optional)') }}"
                        placeholder="{{ __('2025-01-15T11:00:00') }}"
                        x-model="selectedStep.config.end"
                    />
                </div>
                <x-forms.input
                    type="text"
                    label="{{ __('Timezone') }}"
                    placeholder="UTC"
                    x-model="selectedStep.config.timezone"
                />
                <x-forms.input
                    type="text"
                    label="{{ __('Location (optional)') }}"
                    placeholder="{{ __('New location') }}"
                    x-model="selectedStep.config.location"
                />
            </div>
        </template>

        {{-- add_attendees_to_calendar_event --}}
        <template x-if="selectedStep.config.action_event === 'add_attendees_to_calendar_event'">
            <div class="space-y-3">
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                        {{ __('Event ID') }}
                        <span class="ms-1 text-[10px] font-normal text-red-500">
                            {{ __('required') }}
                        </span>
                    </label>
                    <div
                        class="relative rounded-input border border-input-border bg-input-background focus-within:ring-1 focus-within:ring-primary/30"
                        x-data="variableTagInput({ configKey: 'event_id', stepRef: selectedStep, multiline: false })"
                    >
                        <div
                            class="px-3 py-2 pe-8 text-sm outline-none"
                            data-placeholder="{{ __('{found_events}') }}"
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
                    label="{{ __('Attendees') }} *"
                    placeholder="{{ __('user@example.com, other@example.com') }}"
                    x-model="selectedStep.config.attendees"
                    required
                    tooltip="{{ __('Comma-separated. Added to existing attendees.') }}"
                />
                <x-forms.input
                    type="select"
                    label="{{ __('Attendee Type') }}"
                    x-model="selectedStep.config.attendee_type"
                >
                    <option value="required">{{ __('Required') }}</option>
                    <option value="optional">{{ __('Optional') }}</option>
                    <option value="resource">{{ __('Resource') }}</option>
                </x-forms.input>
            </div>
        </template>

        {{-- find_calendar_events --}}
        <template x-if="selectedStep.config.action_event === 'find_calendar_events'">
            <div class="space-y-3">
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                        {{ __('Search Query (optional)') }}
                    </label>
                    <div
                        class="relative rounded-input border border-input-border bg-input-background focus-within:ring-1 focus-within:ring-primary/30"
                        x-data="variableTagInput({ configKey: 'query', stepRef: selectedStep, multiline: false })"
                    >
                        <div
                            class="px-3 py-2 pe-8 text-sm outline-none"
                            data-placeholder="{{ __('e.g. subject:Standup') }}"
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
                <div class="grid grid-cols-2 gap-3">
                    <x-forms.input
                        type="text"
                        label="{{ __('Start (optional)') }}"
                        placeholder="{{ __('2025-01-01T00:00:00') }}"
                        x-model="selectedStep.config.start_datetime"
                    />
                    <x-forms.input
                        type="text"
                        label="{{ __('End (optional)') }}"
                        placeholder="{{ __('2025-01-31T23:59:59') }}"
                        x-model="selectedStep.config.end_datetime"
                    />
                </div>
                <x-forms.input
                    type="number"
                    min="1"
                    max="50"
                    label="{{ __('Max Results') }}"
                    placeholder="10"
                    x-model.number="selectedStep.config.max_results"
                />
                <x-forms.input
                    type="select"
                    label="{{ __('Return Format') }}"
                    x-model="selectedStep.config.return_format"
                >
                    <option value="id">{{ __('ID') }}</option>
                    <option value="subject">{{ __('Subject') }}</option>
                    <option value="full_as_string">{{ __('Full as string') }}</option>
                </x-forms.input>
            </div>
        </template>

        {{-- create_contact --}}
        <template x-if="selectedStep.config.action_event === 'create_contact'">
            <div class="space-y-3">
                <x-forms.input
                    type="text"
                    label="{{ __('First Name') }} *"
                    placeholder="{{ __('John') }}"
                    x-model="selectedStep.config.given_name"
                    required
                />
                <div class="grid grid-cols-2 gap-3">
                    <x-forms.input
                        type="text"
                        label="{{ __('Last Name (optional)') }}"
                        placeholder="{{ __('Doe') }}"
                        x-model="selectedStep.config.surname"
                    />
                    <x-forms.input
                        type="text"
                        label="{{ __('Email (optional)') }}"
                        placeholder="{{ __('john@example.com') }}"
                        x-model="selectedStep.config.email"
                    />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <x-forms.input
                        type="text"
                        label="{{ __('Phone (optional)') }}"
                        placeholder="+1 555 0100"
                        x-model="selectedStep.config.phone"
                    />
                    <x-forms.input
                        type="text"
                        label="{{ __('Company (optional)') }}"
                        placeholder="{{ __('Acme Inc.') }}"
                        x-model="selectedStep.config.company"
                    />
                </div>
                <x-forms.input
                    type="text"
                    label="{{ __('Job Title (optional)') }}"
                    placeholder="{{ __('Sales Manager') }}"
                    x-model="selectedStep.config.job_title"
                />
            </div>
        </template>

        {{-- update_contact --}}
        <template x-if="selectedStep.config.action_event === 'update_contact'">
            <div class="space-y-3">
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                        {{ __('Contact ID') }}
                        <span class="ms-1 text-[10px] font-normal text-red-500">
                            {{ __('required') }}
                        </span>
                    </label>
                    <div
                        class="relative rounded-input border border-input-border bg-input-background focus-within:ring-1 focus-within:ring-primary/30"
                        x-data="variableTagInput({ configKey: 'contact_id', stepRef: selectedStep, multiline: false })"
                    >
                        <div
                            class="px-3 py-2 pe-8 text-sm outline-none"
                            data-placeholder="{{ __('{found_contacts}') }}"
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
                <div class="grid grid-cols-2 gap-3">
                    <x-forms.input
                        type="text"
                        label="{{ __('First Name (optional)') }}"
                        placeholder="{{ __('John') }}"
                        x-model="selectedStep.config.given_name"
                    />
                    <x-forms.input
                        type="text"
                        label="{{ __('Last Name (optional)') }}"
                        placeholder="{{ __('Doe') }}"
                        x-model="selectedStep.config.surname"
                    />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <x-forms.input
                        type="text"
                        label="{{ __('Email (optional)') }}"
                        placeholder="{{ __('john@example.com') }}"
                        x-model="selectedStep.config.email"
                    />
                    <x-forms.input
                        type="text"
                        label="{{ __('Phone (optional)') }}"
                        placeholder="+1 555 0100"
                        x-model="selectedStep.config.phone"
                    />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <x-forms.input
                        type="text"
                        label="{{ __('Company (optional)') }}"
                        placeholder="{{ __('Acme Inc.') }}"
                        x-model="selectedStep.config.company"
                    />
                    <x-forms.input
                        type="text"
                        label="{{ __('Job Title (optional)') }}"
                        placeholder="{{ __('Manager') }}"
                        x-model="selectedStep.config.job_title"
                    />
                </div>
            </div>
        </template>

        {{-- delete_contact --}}
        <template x-if="selectedStep.config.action_event === 'delete_contact'">
            <div class="space-y-3">
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                        {{ __('Contact ID') }}
                        <span class="ms-1 text-[10px] font-normal text-red-500">
                            {{ __('required') }}
                        </span>
                    </label>
                    <div
                        class="relative rounded-input border border-input-border bg-input-background focus-within:ring-1 focus-within:ring-primary/30"
                        x-data="variableTagInput({ configKey: 'contact_id', stepRef: selectedStep, multiline: false })"
                    >
                        <div
                            class="px-3 py-2 pe-8 text-sm outline-none"
                            data-placeholder="{{ __('{found_contacts}') }}"
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

        {{-- find_contacts --}}
        <template x-if="selectedStep.config.action_event === 'find_contacts'">
            <div class="space-y-3">
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                        {{ __('Search Query (optional)') }}
                    </label>
                    <div
                        class="relative rounded-input border border-input-border bg-input-background focus-within:ring-1 focus-within:ring-primary/30"
                        x-data="variableTagInput({ configKey: 'query', stepRef: selectedStep, multiline: false })"
                    >
                        <div
                            class="px-3 py-2 pe-8 text-sm outline-none"
                            data-placeholder="{{ __('e.g. John Doe') }}"
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
                />
                <x-forms.input
                    type="select"
                    label="{{ __('Return Format') }}"
                    x-model="selectedStep.config.return_format"
                >
                    <option value="id">{{ __('ID') }}</option>
                    <option value="name">{{ __('Name') }}</option>
                    <option value="email">{{ __('Email') }}</option>
                    <option value="full_as_string">{{ __('Full as string') }}</option>
                </x-forms.input>
            </div>
        </template>
    </div>
</template>
