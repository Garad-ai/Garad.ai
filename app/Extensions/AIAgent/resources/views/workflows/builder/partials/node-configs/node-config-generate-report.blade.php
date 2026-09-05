<template x-if="selectedStep.type === 'generate_report'">
    <div class="space-y-4">
        <x-forms.input
            type="select"
            label="{{ __('Date Range') }}"
            x-model="selectedStep.config.date_range"
        >
            <option value="today">{{ __('Today') }}</option>
            <option value="yesterday">{{ __('Yesterday') }}</option>
            <option value="this_week">{{ __('This Week') }}</option>
            <option value="this_month">{{ __('This Month') }}</option>
        </x-forms.input>

        <div>
            <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                {{ __('Sections') }}
            </label>
            @php
                use App\Helpers\Classes\MarketplaceHelper;
                $reportSections = array_filter([
                    ['messages',           __('Inbox Messages'),       __('Inbox messages received and sent'),                    true],
                    ['workflows',          __('Agents'),               __('Automated agent executions and triggers'),              true],
                    ['ai_usage',           __('AI Usage'),             __('Token consumption and model usage stats'),              true],
                    ['chatbot',            __('Chatbot'),              __('AI chatbot conversations and sessions'),                MarketplaceHelper::isRegistered('chatbot')],
                    ['marketing',          __('Marketing'),            __('Email campaigns and marketing activity'),               MarketplaceHelper::isRegistered('marketing-bot')],
                    ['marketing_contacts', __('Marketing Contacts'),   __('Marketing contact list changes and growth'),            MarketplaceHelper::isRegistered('marketing-bot')],
                    ['blogpilot',          __('Blog Pilot'),           __('Blog post generation and publishing'),                  MarketplaceHelper::isRegistered('blogpilot')],
                    ['social_media',       __('Social Media'),         __('Social media posts and scheduling'),                    MarketplaceHelper::isRegistered('ai-social-media')],
                    ['social_media_agent', __('Social Media Agent'),   __('Social media agent automation activity'),               MarketplaceHelper::isRegistered('social-media-agent')],
                    ['crm',                __('CRM'),                  __('Pipeline analytics: deals, contacts and tasks'),        MarketplaceHelper::isRegistered('crm')],
                    ['creative_suite',     __('Creative Suite'),       __('Creative content generation activity'),                 MarketplaceHelper::isRegistered('creative-suite')],
                    ['ai_images',          __('AI Images'),            __('Image generation requests and results'),                MarketplaceHelper::isRegistered('ai-image-pro')],
                ], fn ($s) => $s[3]);
            @endphp
            <div class="flex flex-col gap-3">
                @foreach ($reportSections as [$val, $lbl, $desc])
                    <label
                        class="flex cursor-pointer items-start gap-3 rounded-lg border px-3 py-3.5"
                        for="{{ $val }}-section"
                    >
                        <span
                            class="mt-0.5 inline-grid size-[23px] place-items-center rounded border transition has-[input:checked]:border-primary has-[input:checked]:bg-primary has-[input:checked]:text-primary-foreground"
                        >
                            <input
                                class="peer hidden"
                                id="{{ $val }}-section"
                                type="checkbox"
                                :checked="(selectedStep.config.sections || []).includes('{{ $val }}')"
                                @change="toggleSection(selectedStep, '{{ $val }}')"
                            >
                            <x-tabler-check class="size-4 scale-90 opacity-0 transition peer-checked:scale-100 peer-checked:opacity-100" />
                        </span>
                        <div class="grow">
                            <p class="m-0 text-xs font-medium">
                                {{ $lbl }}
                            </p>
                            <p class="m-0 text-2xs opacity-80">
                                {{ $desc }}
                            </p>
                        </div>
                    </label>
                @endforeach
            </div>
        </div>
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
                    data-placeholder="{{ __('System prompt for AI summarisation...') }}"
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
