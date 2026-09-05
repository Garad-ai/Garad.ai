<div class="h-full overflow-y-auto">
    <ul class="lqd-ext-chatbot-history-list">
        <template
            x-for="(conv, index) in filteredConversations"
            :key="conv.id"
        >
            <li
                class="lqd-ext-chatbot-history-list-item group/chat-item relative border-b px-6 py-4 before:absolute before:inset-x-2.5 before:inset-y-1.5 before:z-0 before:scale-95 before:rounded-xl before:bg-accent/10 before:opacity-0 before:transition [&.active]:before:scale-100 [&.active]:before:opacity-100"
                :class="{ 'active': activeConv && activeConv.id === conv.id }"
            >
                <div class="relative z-1 flex gap-2.5">
                    <figure
                        class="inline-grid size-8 shrink-0 place-items-center rounded-full font-heading text-xs font-semibold uppercase"
                        :class="conv.agent?.avatar ? '' : 'bg-primary/20 text-primary'"
                    >
                        <template x-if="conv.agent?.avatar">
                            <img
                                class="size-8 rounded-full object-contain"
                                :src="conv.agent.avatar"
                                :alt="conv.agent.name"
                            >
                        </template>
                        <template x-if="!conv.agent?.avatar">
                            <span x-text="(conv.workflow_name || conv.channel?.name || conv.sender_id || '?').slice(0, 1).toUpperCase()"></span>
                        </template>
                    </figure>

                    <div class="flex w-10/12 grow gap-1">
                        <div class="max-w-full grow overflow-hidden text-start">
                            <h4 class="mb-0 flex max-w-full items-center gap-2 text-xs font-medium">
                                <span
                                    class="inline-block grow truncate"
                                    x-text="conv.workflow_name || conv.channel?.name || conv.sender_id"
                                ></span>
                                <x-tabler-pinned
                                    class="size-4 shrink-0 fill-current"
                                    x-cloak
                                    x-show="conv.pinned != null && conv.pinned > 0"
                                />
                            </h4>
                            <p
                                class="mb-0 text-xs opacity-50"
                                x-text="conv.channel?.type === 'magicai' ? '{{ __('Web') }}' : (conv.channel?.type ? '@' + conv.channel.type : '')"
                            ></p>
                            <p
                                class="mb-0 truncate text-xs"
                                x-text="conv.last_message_preview || '{{ __('No messages') }}'"
                            ></p>
                        </div>

                        <div class="shrink-0">
                            <p
                                class="mb-0.5 text-[12px] opacity-40"
                                x-text="formatDate(conv.last_message_at)"
                            ></p>
                        </div>
                    </div>
                </div>

                <a
                    class="lqd-ext-chatbot-history-list-item-trigger absolute start-0 top-0 z-2 inline-block h-full w-full"
                    href="#"
                    title="{{ __('Open Conversation') }}"
                    @click.prevent="selectConversation(conv)"
                ></a>
            </li>
        </template>

        <template x-if="!fetching && !filteredConversations.length">
            <p class="mb-0 px-4 py-5 font-medium">
                {{ __('No conversations found.') }}
            </p>
        </template>
    </ul>

    <div class="grid place-items-center p-6 font-medium text-heading-foreground">
        <span
            class="inline-flex gap-2"
            x-show="fetching"
        >
            {{ __('Loading') }}
            <x-tabler-refresh class="size-4 animate-spin" />
        </span>
    </div>
</div>
