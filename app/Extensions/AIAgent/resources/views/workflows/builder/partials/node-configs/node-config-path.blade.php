<template x-if="selectedStep.type === 'path'">
    <div class="space-y-4">
        <p class="text-2xs opacity-85">
            {{ __('Configure conditions for each branch. A branch with no conditions is a fallback that runs if no other branch matched.') }}
        </p>

        <template
            x-for="(path, pathIndex) in selectedStep.config.paths"
            :key="path.id"
        >
            <x-card
                class="overflow-hidden bg-foreground/[2%]"
                size="none"
            >
                {{-- Path header --}}
                <div class="flex items-center gap-2 border-b border-border px-3 py-1.5">
                    <x-tabler-git-fork class="size-4 shrink-0" />
                    <input
                        class="min-w-0 flex-1 border-0 bg-transparent text-xs font-semibold text-heading-foreground outline-none focus:ring-0"
                        type="text"
                        x-model="path.name"
                        placeholder="{{ __('Path name') }}"
                    >
                    <button
                        class="ms-auto rounded-lg p-1.5 text-red-400 hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-30"
                        type="button"
                        :disabled="selectedStep.config.paths.length <= 1"
                        @click="removePath(selectedStep.id, pathIndex)"
                    >
                        <x-tabler-trash class="size-4" />
                    </button>
                </div>

                <div class="flex flex-col gap-3 px-3 py-3">
                    {{-- Fallback badge --}}
                    <template x-if="path.ruleGroups.length === 0">
                        <div class="flex items-center gap-1.5 rounded-lg border border-dashed border-indigo-200 bg-indigo-50 px-2.5 py-1.5 text-3xs text-indigo-600">
                            <x-tabler-info-circle class="size-4 shrink-0" />
                            {{ __('Fallback — runs if no other path matched.') }}
                        </div>
                    </template>

                    {{-- Rule groups --}}
                    <template x-if="path.ruleGroups.length > 0">
                        <div class="space-y-2">
                            <p class="m-0 text-3xs font-medium text-label">
                                {{ __('Only continue if') }}
                            </p>

                            <template
                                x-for="(group, groupIndex) in path.ruleGroups"
                                :key="groupIndex"
                            >
                                <div>
                                    {{-- OR separator between groups --}}
                                    <template x-if="groupIndex > 0">
                                        <div class="mb-3 flex items-center gap-2 text-[12px] font-medium text-foreground/50">
                                            <div class="h-px flex-1 bg-border"></div>
                                            {{ __('or') }}
                                            <div class="h-px flex-1 bg-border"></div>
                                        </div>
                                    </template>

                                    {{-- Rule group box --}}
                                    <x-card
                                        class:body="space-y-2"
                                        size="xs"
                                    >
                                        <template
                                            x-for="(rule, ruleIndex) in group.rules"
                                            :key="ruleIndex"
                                        >
                                            <div class="space-y-1.5">
                                                {{-- AND label between rules --}}
                                                <template x-if="ruleIndex > 0">
                                                    <div class="flex items-center gap-2 text-[12px] font-medium text-foreground/50">
                                                        <div class="h-px flex-1 bg-border/50"></div>
                                                        {{ __('and') }}
                                                        <div class="h-px flex-1 bg-border/50"></div>
                                                    </div>
                                                </template>

                                                {{-- Field --}}
                                                <x-forms.input
                                                    type="text"
                                                    placeholder="{{ __('e.g. {message_text}') }}"
                                                    x-model="rule.field"
                                                >
                                                    <button
                                                        class="absolute end-1.5 top-1/2 -translate-y-1/2 rounded p-0.5 text-foreground/40 hover:bg-foreground/5 hover:text-foreground/70"
                                                        type="button"
                                                        @click.stop="activeVariableMenu = activeVariableMenu === ('path_field_' + pathIndex + '_' + groupIndex + '_' + ruleIndex) ? null : ('path_field_' + pathIndex + '_' + groupIndex + '_' + ruleIndex)"
                                                    ><x-tabler-braces class="size-3.5" /></button>
                                                    <div
                                                        class="absolute end-0 top-full z-50 mt-1 min-w-[160px] rounded-lg border border-border bg-background p-1.5 shadow-lg"
                                                        x-show="activeVariableMenu === ('path_field_' + pathIndex + '_' + groupIndex + '_' + ruleIndex)"
                                                        x-cloak
                                                        @click.outside="activeVariableMenu = null"
                                                    >
                                                        <template
                                                            x-for="v in availableVariables"
                                                            :key="v.name"
                                                        >
                                                            <button
                                                                class="block w-full rounded px-2 py-1 text-start hover:bg-foreground/5"
                                                                type="button"
                                                                @click="rule.field = (rule.field || '') + v.name; activeVariableMenu = null"
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
                                                </x-forms.input>

                                                {{-- Operator + Value --}}
                                                <div class="flex items-center gap-1.5">
                                                    <x-forms.input
                                                        class:container="w-full"
                                                        class="w-full"
                                                        type="select"
                                                        x-model="rule.operator"
                                                    >
                                                        <option value="contains">{{ __('contains') }}</option>
                                                        <option value="not_contains">{{ __('does not contain') }}</option>
                                                        <option value="equals">{{ __('equals') }}</option>
                                                        <option value="not_equals">{{ __('does not equal') }}</option>
                                                        <option value="is_empty">{{ __('is empty') }}</option>
                                                        <option value="is_not_empty">{{ __('is not empty') }}</option>
                                                        <option value="starts_with">{{ __('starts with') }}</option>
                                                        <option value="ends_with">{{ __('ends with') }}</option>
                                                        <option value="greater_than">{{ __('greater than') }}</option>
                                                        <option value="less_than">{{ __('less than') }}</option>
                                                    </x-forms.input>
                                                    <button
                                                        class="shrink-0 rounded-lg p-1 text-foreground/30 hover:bg-red-50 hover:text-red-500"
                                                        type="button"
                                                        @click="removeRule(selectedStep.id, pathIndex, groupIndex, ruleIndex)"
                                                    >
                                                        <x-tabler-x class="size-3" />
                                                    </button>
                                                </div>

                                                {{-- Value input (hidden for is_empty / is_not_empty) --}}
                                                <template x-if="!['is_empty','is_not_empty'].includes(rule.operator)">
                                                    <x-forms.input
                                                        type="text"
                                                        placeholder="{{ __('Enter value...') }}"
                                                        x-model="rule.value"
                                                    >
                                                        <button
                                                            class="absolute end-1.5 top-1/2 -translate-y-1/2 rounded p-0.5 text-foreground/40 hover:bg-foreground/5 hover:text-foreground/70"
                                                            type="button"
                                                            @click.stop="activeVariableMenu = activeVariableMenu === ('path_value_' + pathIndex + '_' + groupIndex + '_' + ruleIndex) ? null : ('path_value_' + pathIndex + '_' + groupIndex + '_' + ruleIndex)"
                                                        ><x-tabler-braces class="size-3.5" /></button>
                                                        <div
                                                            class="absolute end-0 top-full z-50 mt-1 min-w-[160px] rounded-lg border border-border bg-background p-1.5 shadow-lg"
                                                            x-show="activeVariableMenu === ('path_value_' + pathIndex + '_' + groupIndex + '_' + ruleIndex)"
                                                            x-cloak
                                                            @click.outside="activeVariableMenu = null"
                                                        >
                                                            <template
                                                                x-for="v in availableVariables"
                                                                :key="v.name"
                                                            >
                                                                <button
                                                                    class="block w-full rounded px-2 py-1 text-start hover:bg-foreground/5"
                                                                    type="button"
                                                                    @click="rule.value = (rule.value || '') + v.name; activeVariableMenu = null"
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
                                                    </x-forms.input>
                                                </template>
                                            </div>
                                        </template>

                                        {{-- Add rule --}}
                                        <x-button
                                            class="text-3xs text-green-500"
                                            variant="link"
                                            type="button"
                                            @click.prevent="addRule(selectedStep.id, pathIndex, groupIndex)"
                                        >
                                            <x-tabler-plus class="size-3" />
                                            {{ __('Add rule') }}
                                        </x-button>
                                    </x-card>

                                    {{-- Remove rule group --}}
                                    <template x-if="path.ruleGroups.length > 1">
                                        <x-button
                                            class="mt-1 text-3xs text-red-500"
                                            variant="link"
                                            type="button"
                                            @click.prevent="removeRuleGroup(selectedStep.id, pathIndex, groupIndex)"
                                        >
                                            <x-tabler-trash class="size-3" />
                                            {{ __('Remove group') }}
                                        </x-button>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- Add rule group (OR) --}}
                    <x-button
                        variant="outline"
                        type="button"
                        @click="addRuleGroup(selectedStep.id, pathIndex)"
                    >
                        <x-tabler-plus class="size-3" />
                        {{ __('Add "Or" rule group') }}
                    </x-button>
                </div>
            </x-card>
        </template>

        {{-- Add Path --}}
        <x-button
            class="w-full"
            type="button"
            @click="addPath(selectedStep.id)"
        >
            <x-tabler-plus class="size-4" />
            {{ __('Add Path') }}
        </x-button>
    </div>
</template>
