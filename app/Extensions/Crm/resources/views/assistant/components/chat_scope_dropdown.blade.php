@php
    $scopeIcons = [
        'all' => 'tabler-layout-grid',
        'contacts' => 'tabler-users',
        'companies' => 'tabler-building',
        'deals' => 'tabler-target-arrow',
        'tasks' => 'tabler-checklist',
        'projects' => 'tabler-folders',
        'invoices' => 'tabler-file-invoice',
        'presentations' => 'tabler-presentation',
    ];
@endphp

{{--
    Mirrors the Social Media Agent's agent picker. CRM has no agents, so the same
    dropdown selects the context scope instead: it writes #crm_assistant_scope,
    which is posted with every stream request and narrows the CRM snapshot sent
    to the model (see CrmAssistantChatService::buildCrmContext).
--}}
<div
    x-data="crmAssistantScopePicker({{ json_encode($defaultScope) }}, {{ json_encode($scopes) }})"
    x-id="['crm-scope']"
>
    <x-dropdown.dropdown
        class="lqd-chat-category-dropdown static"
        class:dropdown-dropdown="end-2 start-2 max-h-[calc(100vh-270px)] overflow-y-auto overscroll-contain rounded-b-xl rounded-t-none shadow-[0_4px_20px_rgba(0,0,0,0.07)] sm:max-h-[calc(var(--chats-container-height,500px)-30px)] lg:end-4 lg:start-4"
        class:dropdown="w-full"
        triggerType="click"
        :teleport="false"
    >
        <x-slot:trigger
            class="gap-0.5 before:content-none hover:no-underline lg:gap-4"
        >
            <span
                class="lqd-chat-category-avatar inline-flex size-11 items-center justify-center overflow-hidden rounded-full bg-primary/10 text-primary transition-all group-hover:-translate-y-0.5"
            >
                <x-tabler-message-chatbot
                    class="size-6"
                    stroke-width="1.5"
                />
            </span>

            <span class="lqd-chat-category-info m-0 flex flex-col gap-1 text-xs">
                <span class="lqd-chat-category-name flex items-center justify-center gap-1 rounded-full bg-foreground/5 px-2 py-1 font-semibold leading-tight max-sm:size-6 max-sm:p-0">
                    <span class="max-sm:hidden">
                        {{ __('CRM Assistant') }}
                    </span>

                    <x-tabler-chevron-down class="size-4 transition-transform group-[&.lqd-is-active]/dropdown:rotate-180" />
                </span>

                <span class="lqd-chat-category-role m-0 block text-2xs text-heading-foreground/60 max-sm:hidden">
                    {{ __('Context:') }} <span x-text="scopeLabel"></span>
                </span>
            </span>
        </x-slot:trigger>

        <x-slot:dropdown>
            <div
                class="flex flex-col gap-3 px-4 py-4 sm:px-7"
                x-data="{ searchString: '' }"
                x-trap="open"
            >
                <p class="m-0 text-2xs text-heading-foreground/60">
                    {{ __('Choose which part of your CRM the assistant should look at. Narrower scopes keep answers focused and faster.') }}
                </p>

                <x-forms.input
                    class="lqd-dropdown-dropdown-search rounded-full border-clay bg-clay ps-10"
                    container-class="mb-2"
                    type="search"
                    placeholder="{{ __('Search context') }}"
                    x-model="searchString"
                >
                    <x-tabler-search class="absolute start-3 top-1/2 size-5 -translate-y-1/2" />
                </x-forms.input>

                @foreach ($scopes as $scopeKey => $scopeLabel)
                    <button
                        class="lqd-crm-scope-option relative flex items-center gap-3 rounded-xl border px-5 py-3 text-start transition-all hover:scale-[1.02] hover:shadow-lg hover:shadow-black/5 [&.active]:border-primary [&.active]:bg-primary/5"
                        type="button"
                        data-search-text="{{ e($scopeLabel) }}"
                        x-show="searchString === '' || $el.getAttribute('data-search-text').toLowerCase().includes(searchString.toLowerCase())"
                        :class="{ 'active': scope === '{{ $scopeKey }}' }"
                        @click.prevent="setScope('{{ $scopeKey }}')"
                    >
                        <span
                            class="inline-flex size-11 shrink-0 items-center justify-center rounded-full bg-foreground/5 text-foreground/70"
                        >
                            <x-dynamic-component
                                :component="$scopeIcons[$scopeKey] ?? 'tabler-layout-grid'"
                                class="size-5"
                                stroke-width="1.5"
                            />
                        </span>

                        <span class="min-w-0">
                            <span class="m-0 block text-xs font-semibold">{{ __($scopeLabel) }}</span>
                            <span class="m-0 block text-2xs text-heading-foreground/60">
                                {{ $scopeKey === 'all' ? __('Send the full CRM snapshot') : __('Only :module data', ['module' => __($scopeLabel)]) }}
                            </span>
                        </span>

                        <x-tabler-check
                            class="ms-auto size-4 shrink-0 text-primary"
                            x-show="scope === '{{ $scopeKey }}'"
                            x-cloak
                        />
                    </button>
                @endforeach
            </div>
        </x-slot:dropdown>
    </x-dropdown.dropdown>
</div>

@once
    @push('script')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('crmAssistantScopePicker', (initialScope, scopes) => ({
                    scope: initialScope,
                    scopes: scopes,

                    get scopeLabel() {
                        return this.scopes[this.scope] ?? this.scopes.all;
                    },

                    setScope(scope) {
                        if (!(scope in this.scopes)) return;

                        this.scope = scope;

                        const input = document.getElementById('crm_assistant_scope');
                        if (input) input.value = scope;
                    },
                }));
            });
        </script>
    @endpush
@endonce
