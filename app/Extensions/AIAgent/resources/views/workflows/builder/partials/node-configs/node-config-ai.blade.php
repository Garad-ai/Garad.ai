<template x-if="selectedStep.type === 'ai_call'">
    <div class="space-y-4">
        {{-- Model grouped by provider --}}
        <x-forms.input
            type="select"
            label="{{ __('Model') }}"
            x-model="selectedStep.config.model"
            ::disabled="availableModelGroups.length === 0"
        >
            <option
                value=""
                x-text="availableModelGroups.length === 0 ? '{{ __('Loading models...') }}' : '{{ __('Default model') }}'"
            ></option>
            <template
                x-for="group in availableModelGroups"
                :key="group.engine"
            >
                <optgroup :label="group.label">
                    <template
                        x-for="m in group.models"
                        :key="m.value"
                    >
                        <option
                            :value="m.value"
                            :selected="m.value === selectedStep.config.model"
                            x-text="m.label"
                        ></option>
                    </template>
                </optgroup>
            </template>
        </x-forms.input>

        {{-- System Prompt --}}
        <div>
            <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                {{ __('Agent Role') }}
            </label>
            <div
                class="relative rounded-input border border-input-border bg-input-background focus-within:ring-1 focus-within:ring-primary/30"
                x-data="variableTagInput({ configKey: 'system_prompt', stepRef: selectedStep, multiline: true })"
            >
                <div
                    class="px-3 py-2 pe-8 text-sm outline-none"
                    data-placeholder="{{ __('You are a helpful assistant...') }}"
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

        {{-- User Message --}}
        <div>
            <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                {{ __('User Message') }}
            </label>
            <div
                class="relative rounded-input border border-input-border bg-input-background focus-within:ring-1 focus-within:ring-primary/30"
                x-data="variableTagInput({ configKey: 'user_message', stepRef: selectedStep, multiline: true })"
            >
                <div
                    class="px-3 py-2 pe-8 text-sm outline-none"
                    data-placeholder="{{ __('Use {message_text} for incoming message') }}"
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

        {{-- Structured output: one call fills many fields instead of one string --}}
        <div
            class="flex flex-col gap-3"
            x-data="{
                get isJson() {
                    return selectedStep.config.response_format === 'json';
                },
                set isJson(value) {
                    selectedStep.config.response_format = value ? 'json' : '';
                    if (value && !(selectedStep.config.output_keys ?? []).length) {
                        selectedStep.config.output_keys = [];
                    }
                },
                get keysText() {
                    return (selectedStep.config.output_keys ?? []).join(', ');
                },
                set keysText(value) {
                    selectedStep.config.output_keys = String(value)
                        .split(',')
                        .map(key => key.trim().replace(/[^\w]/g, '_'))
                        .filter(key => key.length > 0);
                },
            }"
        >
            <x-forms.input
                class="ms-auto"
                type="checkbox"
                switcher
                size="sm"
                label="{{ __('Structured Output') }}"
                tooltip="{{ __('Ask the model for several fields at once instead of one block of text.') }}"
                x-model.boolean="isJson"
            />

            <template x-if="isJson">
                <div class="flex flex-col gap-2">
                    <x-forms.input
                        type="text"
                        label="{{ __('Output Fields') }}"
                        placeholder="{{ __('first_name, email, phone') }}"
                        x-model.lazy="keysText"
                        tooltip="{{ __('Comma separated. Each field becomes its own variable for later steps.') }}"
                    />
                    <template x-if="(selectedStep.config.output_keys ?? []).length > 0">
                        <div class="flex flex-wrap gap-1.5">
                            <template
                                x-for="key in (selectedStep.config.output_keys ?? [])"
                                :key="key"
                            >
                                <span
                                    class="rounded bg-primary/10 px-2 py-1 font-mono text-[10px] text-heading-foreground"
                                    x-text="'{' + key + '}'"
                                ></span>
                            </template>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        @include('ai-agent::includes.knowledge-base-modal')

        {{-- Attached sources --}}
        <template x-if="(selectedStep.config.knowledge_source_ids ?? []).length > 0">
            <div class="space-y-1.5">
                <template
                    x-for="sid in (selectedStep.config.knowledge_source_ids ?? [])"
                    :key="sid"
                >
                    <div class="flex items-center gap-3 rounded-full border px-6 py-2.5">
                        <x-tabler-file-text class="size-5 shrink-0 opacity-65" />
                        <span
                            class="truncate text-sm"
                            x-text="knowledgeSourceName(sid)"
                        ></span>

                        <x-button
                            class="ms-auto size-7 shrink-0"
                            size="none"
                            variant="ghost"
                            type="button"
                            @click.prevent="deleteKnowledgeSource(sid)"
                            title="{{ __('Delete') }}"
                        >
                            <x-tabler-x class="size-4" />
                        </x-button>
                    </div>
                </template>
            </div>
        </template>

        {{-- Advanced options --}}
        <div
            class="group"
            x-data="{ open: false }"
        >
            <button
                class="flex w-full items-center gap-5 text-2xs font-medium"
                type="button"
                @click.prevent="open = !open"
            >
                <span class="inline-block h-px grow bg-border"></span>
                <span class="inline-flex items-center gap-2">
                    {{ __('Advanced Options') }}
                    <x-tabler-chevron-down
                        class="size-3.5 transition"
                        ::class="{ 'rotate-180': open }"
                    />
                </span>
                <span class="inline-block h-px grow bg-border"></span>
            </button>

            <div
                x-show="open"
                x-cloak
                x-collapse
            >
                <div class="flex flex-col gap-5 py-3">
                    <x-forms.input
                        type="number"
                        min="0"
                        max="50"
                        label="{{ __('History Turns') }}"
                        placeholder="10"
                        x-model.number="selectedStep.config.history_turns"
                        tooltip="{{ __('Previous message pairs to include as context. Set 0 to disable.') }}"
                    />

                    <x-forms.input
                        class="order-3 ms-auto"
                        type="checkbox"
                        switcher
                        size="sm"
                        label="{{ __('Memories') }}"
                        tooltip="{{ __('Prepend user memories into system prompt.') }}"
                        x-model.boolean="selectedStep.config.inject_memory"
                    />

                    {{-- Per-step memory picker (only when inject_memory is enabled) --}}
                    <div
                        class="-mt-1.5 flex flex-col gap-2"
                        x-show="selectedStep.config.inject_memory !== false"
                    >
                        <div class="grid grid-cols-1 gap-1">
                            <template
                                x-for="mem in memories"
                                :key="'step-mem-' + mem.id"
                            >
                                <div
                                    class="group/mem flex items-center gap-2 rounded-lg border border-border px-3 py-2 text-xs transition-colors hover:bg-foreground/5"
                                    :class="{ 'border-primary bg-primary/5': (selectedStep.config.selected_memory_ids || []).includes(mem.id) }"
                                >
                                    <label class="flex min-w-0 flex-1 cursor-pointer items-center gap-2">
                                        <input
                                            class="size-3 shrink-0 rounded"
                                            type="checkbox"
                                            :checked="(selectedStep.config.selected_memory_ids || []).includes(mem.id)"
                                            @change="
												const ids = selectedStep.config.selected_memory_ids || [];
												selectedStep.config.selected_memory_ids = $event.target.checked
													? [...ids, mem.id]
													: ids.filter(id => id !== mem.id)
											"
                                        >
                                        <span
                                            class="truncate text-[11px] text-foreground/70"
                                            x-text="mem.memory"
                                        ></span>
                                    </label>
                                    <button
                                        class="inline-grid size-6 shrink-0 place-items-center rounded hover:bg-red-50 hover:text-red-500"
                                        type="button"
                                        @click.prevent="deleteBuilderMemory(mem.id)"
                                        title="{{ __('Delete') }}"
                                    >
                                        <x-tabler-trash class="size-3" />
                                    </button>
                                </div>
                            </template>
                        </div>

                        {{-- Add memory inline form --}}
                        <template x-if="memoryForm.open">
                            <div class="mt-1 flex flex-col gap-1.5">
                                <x-forms.input
                                    type="textarea"
                                    rows="2"
                                    placeholder="{{ __('e.g. User prefers English responses') }}"
                                    x-model="memoryForm.text"
                                    @keydown.enter.ctrl="createBuilderMemory()"
                                />
                                <div class="flex items-center gap-1.5">
                                    <x-button
                                        variant="outline"
                                        size="sm"
                                        type="button"
                                        @click.prevent="memoryForm = { open: false, text: '', loading: false }"
                                    >
                                        {{ __('Cancel') }}
                                    </x-button>
                                    <x-button
                                        size="sm"
                                        type="button"
                                        @click="createBuilderMemory()"
                                        ::disabled="memoryForm.loading || !memoryForm.text.trim()"
                                    >
                                        <span x-show="!memoryForm.loading">
                                            {{ __('Save') }}
                                        </span>
                                        <span
                                            x-show="memoryForm.loading"
                                            x-cloak
                                        >
                                            {{ __('Saving...') }}
                                        </span>
                                    </x-button>
                                </div>
                            </div>
                        </template>

                        <x-button
                            class="w-full"
                            variant="outline"
                            type="button"
                            @click="memoryForm.open = !memoryForm.open; memoryForm.text = ''"
                            x-show="!memoryForm.open"
                        >
                            <x-tabler-plus class="size-3" />
                            {{ __('Add new memory') }}
                        </x-button>
                    </div>

                    <x-forms.input
                        class="order-3 ms-auto"
                        type="checkbox"
                        switcher
                        size="sm"
                        label="{{ __('Web Search') }}"
                        tooltip="{{ __('Use the model\'s built-in web search (OpenAI & Gemini).') }}"
                        x-model.boolean="selectedStep.config.web_search"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
