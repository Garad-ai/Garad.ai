<div
    class="col-start-1 col-end-1 row-start-1 row-end-1 w-full px-4 py-6 text-center"
    x-show="contactInfo.activeTab === 'details'"
    x-transition.opacity
>
    <template x-if="activeConv?.agent && activeConv.channel?.type === 'magicai'">
        <div>
            <template x-if="activeConv.agent?.avatar">
                <img
                    class="mx-auto mb-3 h-auto w-20 rounded-full"
                    :src="activeConv.agent.avatar"
                    x-cloak
                >
            </template>

            <h3
                class="mb-2.5"
                x-text="activeConv.agent?.name || '---'"
                x-ref="contactInfoName"
            >
                ---
            </h3>

            <p
                class="text-2xs opacity-85"
                x-text="activeConv.agent?.description || ''"
            ></p>

            <p class="mb-2.5 text-3xs font-medium text-foreground/50">
                {{ __('Created') }}
                <span x-text="activeConv.agent?.created_at ?? '---'"></span>
            </p>

            <p class="mb-2.5 text-3xs font-medium text-foreground/50">
                <span
                    class="me-1.5 inline-flex size-1.5 rounded-full align-middle"
                    :class="activeConv.agent?.is_active ? 'bg-green-500' : 'bg-yellow-500'"
                ></span>
                <span x-text="activeConv.agent?.is_active ? '{{ __('Active') }}' : '{{ __('Paused') }}'"></span>
                <u class="decoration-dashed">
                    <span x-text="activeConv.agent?.last_run_at ? `{{ __('Last Run') }}: ${activeConv.agent.last_run_at}` : '{{ __('Never Ran') }}'"></span>
                </u>
            </p>
        </div>
    </template>

    <template x-if="activeConv && (!activeConv.agent || activeConv.channel?.type !== 'magicai')">
        <div>
            <template x-if="activeConv.agent?.avatar">
                <img
                    class="mx-auto mb-3 h-auto w-20 rounded-full"
                    :src="activeConv.agent.avatar"
                    x-cloak
                >
            </template>
            <template x-if="!activeConv.agent?.avatar">
                <figure class="mx-auto mb-3 inline-grid size-16 place-items-center rounded-full bg-primary/20 font-heading text-2xl font-semibold uppercase text-primary">
                    <span x-text="(activeConv.agent?.name || activeConv.workflow_name || activeConv.channel?.name || activeConv.sender_id || '?').slice(0, 1).toUpperCase()"></span>
                </figure>
            </template>

            <h3
                class="mb-2.5"
                x-text="activeConv.agent?.name || activeConv.sender_id || '---'"
            >
                ---
            </h3>

            <p
                class="text-2xs opacity-85"
                x-show="activeConv.agent?.description"
                x-text="activeConv.agent?.description || ''"
            ></p>

            <p
                class="mb-2.5 text-3xs font-medium text-foreground/50"
                x-show="activeConv.agent?.created_at"
            >
                {{ __('Created') }}
                <span x-text="activeConv.agent?.created_at ?? '---'"></span>
            </p>

            <p
                class="mb-4 text-3xs font-medium text-foreground/50"
                x-show="activeConv.agent"
            >
                <span
                    class="me-1.5 inline-flex size-1.5 rounded-full align-middle"
                    :class="activeConv.agent?.is_active ? 'bg-green-500' : 'bg-yellow-500'"
                ></span>
                <span x-text="activeConv.agent?.is_active ? '{{ __('Active') }}' : '{{ __('Paused') }}'"></span>
                <u class="decoration-dashed">
                    <span x-text="activeConv.agent?.last_run_at ? `{{ __('Last Run') }}: ${activeConv.agent.last_run_at}` : '{{ __('Never Ran') }}'"></span>
                </u>
            </p>

            <p
                class="text-3xs font-medium text-foreground/50"
                x-show="activeConv.last_message_at"
            >
                {{ __('Last message') }}:
                <span x-text="formatDate(activeConv.last_message_at)"></span>
            </p>

            <template x-if="activeConv.channel">
                <div class="mb-4 inline-flex items-center gap-2">
                    <img
                        class="size-4 object-contain"
                        :src="`{{ custom_theme_url('/vendor/ai-agent/images/platforms/') }}${activeConv.channel.type}.png`"
                        :alt="activeConv.channel.type"
                        aria-hidden="true"
                    >
                    <span
                        class="text-2xs"
                        x-text="activeConv.channel.name || activeConv.channel.type"
                    ></span>
                </div>
            </template>
        </div>
    </template>
</div>
