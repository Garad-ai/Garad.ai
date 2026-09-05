<div class="flex flex-col gap-5">
    {{-- Schedule Config --}}
    <div
        class="flex flex-col gap-5"
        x-show="triggerType === 'schedule'"
    >

        {{-- Hidden input — keeps form submission unchanged --}}
        <input
            type="hidden"
            name="trigger_config[cron]"
            x-bind:value="triggerConfig.cron"
        >

        {{-- Preset grid --}}
        <div>
            <p class="text-2xs font-medium">
                {{ __('Schedule Time') }}
            </p>
            <div class="grid grid-cols-2 gap-2">
                <template
                    x-for="preset in cronPresets"
                    :key="preset.key"
                >
                    <button
                        class="flex flex-col justify-center rounded-lg border p-3.5 text-start transition active:scale-95"
                        :class="{ 'border-primary ring-1 ring-primary': cronPresetKey === preset.key }"
                        @click="selectCronPreset(preset)"
                        type="button"
                    >
                        <span
                            class="text-2xs font-medium"
                            x-text="preset.title"
                        ></span>
                        <span
                            class="text-[12px] opacity-85"
                            x-text="preset.sub"
                        ></span>
                    </button>
                </template>
            </div>
        </div>

        {{-- Custom schedule toggle --}}
        <button
            class="flex w-full items-center gap-5 text-2xs font-medium"
            @click.prevent="openCronCustom()"
            type="button"
        >
            <span class="inline-block h-px grow bg-foreground/5"></span>
            <span class="inline-flex items-center gap-1.5">
                {{ __('Custom schedule') }}
                <x-tabler-chevron-down
                    class="size-3.5 transition"
                    ::class="{ 'rotate-180': cronCustomOpen }"
                />
            </span>
            <span class="inline-block h-px grow bg-foreground/5"></span>
        </button>

        {{-- Custom dropdowns --}}
        <div
            class="grid grid-cols-2 gap-3"
            x-show="cronCustomOpen"
            x-collapse
        >
            {{-- Minute --}}
            <x-forms.input
                type="select"
                label="{{ __('Minute') }}"
                x-model="cronCustomMinute"
                @change="updateCronFromCustom()"
            >
                <option value="*">{{ __('Every minute') }}</option>
                <option value="*/5">{{ __('Every 5 min') }}</option>
                <option value="*/10">{{ __('Every 10 min') }}</option>
                <option value="*/15">{{ __('Every 15 min') }}</option>
                <option value="*/30">{{ __('Every 30 min') }}</option>
                <option value="0">{{ __('At :00') }}</option>
                <option value="30">{{ __('At :30') }}</option>
            </x-forms.input>

            {{-- Hour --}}
            <x-forms.input
                type="select"
                label="{{ __('Hour') }}"
                x-model="cronCustomHour"
                @change="updateCronFromCustom()"
            >
                <option value="*">{{ __('Every hour') }}</option>
                @foreach (range(0, 23) as $h)
                    <option value="{{ $h }}">{{ \Carbon\Carbon::createFromTime($h, 0)->format('g A') }}</option>
                @endforeach
            </x-forms.input>

            {{-- Day of Week --}}
            <x-forms.input
                type="select"
                label="{{ __('Day of Week') }}"
                x-model="cronCustomDayOfWeek"
                @change="updateCronFromCustom()"
            >
                <option value="*">{{ __('Every day') }}</option>
                <option value="1-5">{{ __('Weekdays') }}</option>
                <option value="0,6">{{ __('Weekends') }}</option>
                <option value="0">{{ __('Sunday') }}</option>
                <option value="1">{{ __('Monday') }}</option>
                <option value="2">{{ __('Tuesday') }}</option>
                <option value="3">{{ __('Wednesday') }}</option>
                <option value="4">{{ __('Thursday') }}</option>
                <option value="5">{{ __('Friday') }}</option>
                <option value="6">{{ __('Saturday') }}</option>
            </x-forms.input>

            {{-- Day of Month --}}
            <x-forms.input
                type="select"
                label="{{ __('Day of Month') }}"
                x-model="cronCustomDayOfMonth"
                @change="updateCronFromCustom()"
            >
                <option value="*">{{ __('Every day') }}</option>
                @foreach (range(1, 28) as $d)
                    <option value="{{ $d }}">{{ $d }}</option>
                @endforeach
            </x-forms.input>
        </div>

        <p
            class="m-0 text-2xs font-medium"
            x-show="!triggerConfig.cron"
        >
            {{ __('Select a preset or configure a custom schedule above.') }}
        </p>
    </div>

    {{-- Channel Message Config --}}
    <div
        class="flex flex-col gap-5"
        x-show="triggerType === 'channel_message'"
    >
        <x-forms.input
            class:label="mb-2"
            type="select"
            label="{{ __('Channel') }}"
            @change="triggerConfig.channel_id = $event.target.value"
        >
            <x-slot:label-extra>
                <x-button
                    class="py-1.5 text-2xs"
                    variant="outline"
                    hover-variant="primary"
                    size="sm"
                    type="button"
                    @click.prevent="toggleChannelsModal(true)"
                >
                    <x-tabler-plus class="size-4" />
                    {{ __('Connect new channel') }}
                </x-button>
            </x-slot:label-extra>
            <option value="">
                {{ __('Any channel') }}
            </option>
            <template
                x-for="ch in channels"
                :key="ch.id"
            >
                <option
                    :value="ch.id"
                    :selected="String(triggerConfig.channel_id) === String(ch.id)"
                    x-text="ch.name"
                ></option>
            </template>
        </x-forms.input>

        {{-- Keyword Filter --}}
        <div class="flex flex-col gap-5">
            <x-forms.input
                class="order-3 ms-auto"
                type="checkbox"
                label="{{ __('Keyword Filter') }}"
                size="sm"
                switcher
                tooltip="{{ __('Only trigger when message matches rules') }}"
                x-model="triggerConfig.keyword_filter.enabled"
            />

            <div
                class="flex flex-col gap-3"
                x-show="triggerConfig.keyword_filter.enabled"
                x-cloak
            >
                <p
                    class="m-0 text-3xs font-medium text-label"
                    x-show="triggerConfig.keyword_filter.enabled"
                    x-cloak
                >
                    {{ __('Only continue if') }}
                </p>

                <template
                    x-for="(group, groupIndex) in triggerConfig.keyword_filter.ruleGroups"
                    :key="groupIndex"
                >
                    <div>
                        {{-- OR separator --}}
                        <template x-if="groupIndex > 0">
                            <div class="mb-3 flex items-center gap-2 text-[12px] font-medium text-foreground/50">
                                <div class="h-px flex-1 bg-border"></div>
                                {{ __('or') }}
                                <div class="h-px flex-1 bg-border"></div>
                            </div>
                        </template>

                        <x-card
                            class:body="space-y-2"
                            size="xs"
                        >
                            <template
                                x-for="(rule, ruleIndex) in group.rules"
                                :key="ruleIndex"
                            >
                                <div class="space-y-1.5">
                                    {{-- AND separator --}}
                                    <template x-if="ruleIndex > 0">
                                        <div class="flex items-center gap-2 text-[12px] font-medium text-foreground/50">
                                            <div class="h-px flex-1 bg-border/50"></div>
                                            {{ __('and') }}
                                            <div class="h-px flex-1 bg-border/50"></div>
                                        </div>
                                    </template>

                                    {{-- Field --}}
                                    <x-forms.input
                                        class="w-full"
                                        type="text"
                                        x-model="rule.field"
                                        placeholder="{{ __('e.g. {message_text}') }}"
                                    />

                                    {{-- Operator + Remove --}}
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
                                        </x-forms.input>
                                        <button
                                            class="shrink-0 rounded-lg p-1 text-foreground/30 hover:bg-red-50 hover:text-red-500"
                                            type="button"
                                            @click="removeTriggerRule(groupIndex, ruleIndex)"
                                            x-show="group.rules.length > 1"
                                        >
                                            <x-tabler-x class="size-3" />
                                        </button>
                                    </div>

                                    {{-- Value --}}
                                    <template x-if="!['is_empty','is_not_empty'].includes(rule.operator)">
                                        <x-forms.input
                                            class="w-full"
                                            type="text"
                                            x-model="rule.value"
                                            placeholder="{{ __('Enter value...') }}"
                                        />
                                    </template>
                                </div>
                            </template>

                            <x-button
                                class="text-3xs text-green-500"
                                variant="link"
                                type="button"
                                @click="addTriggerRule(groupIndex)"
                            >
                                <x-tabler-plus class="size-3" />
                                {{ __('Add rule') }}
                            </x-button>
                        </x-card>

                        <template x-if="triggerConfig.keyword_filter.ruleGroups.length > 1">
                            <x-button
                                class="mt-1 text-3xs text-red-500"
                                variant="link"
                                type="button"
                                @click="removeTriggerRuleGroup(groupIndex)"
                            >
                                <x-tabler-trash class="size-3" />
                                {{ __('Remove group') }}
                            </x-button>
                        </template>
                    </div>
                </template>

                <x-button
                    variant="outline"
                    type="button"
                    @click="addTriggerRuleGroup()"
                >
                    <x-tabler-plus class="size-3" />
                    {{ __('Add "Or" rule group') }}
                </x-button>
            </div>
        </div>

    </div>

    {{-- Webhook Config --}}
    <div
        class="flex flex-col gap-5"
        x-show="triggerType === 'webhook'"
    >
        <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700">
            <div class="mb-1 flex items-center gap-1.5 font-medium">
                <x-tabler-info-circle class="size-4" />
                {{ __('Webhook URL') }}
            </div>
            <p class="text-xs text-blue-600">{{ __('Save this workflow first. The unique webhook URL will appear here after saving.') }}</p>
            <template x-if="workflowId">
                <code class="mt-2 block break-all rounded bg-blue-100 px-2 py-1 text-[11px] text-blue-800">
                    {{ config('app.url') }}/api/ai-agent/webhook/<span x-text="workflowId"></span>
                </code>
            </template>
        </div>
    </div>

</div>
