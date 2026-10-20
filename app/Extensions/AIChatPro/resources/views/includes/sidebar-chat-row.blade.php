<li
    class="chat-list-item garad-sidebar-chat-row group relative flex shrink-0 items-center gap-1 [word-break:break-word]"
    :id="'chat_' + chat.id"
    :class="{ 'pin-mode': chat.is_pinned, 'active': openChat == chat.id }"
>
    <button
        class="chat-list-item-trigger flex min-w-0 grow cursor-pointer items-center gap-2 text-start text-heading-foreground"
        type="button"
        @click="await openChatAreaContainer(chat.id, 'chatpro'); mobileSidebarShow = false; $nextTick(() => { openChat = chat.id })"
    >
        <x-tabler-message-circle class="garad-chat-row-icon size-4 shrink-0 opacity-70" stroke-width="1.6" />
        <span class="lqd-chat-item-trigger-info min-w-0 grow">
            <span class="chat-item-title block truncate text-xs" x-text="chat.title || '{{ __('Untitled chat') }}'"></span>
        </span>
    </button>

    <x-dropdown.dropdown class="order-last shrink-0" offsetY="8px">
        <x-slot:trigger
            class="garad-chat-row-menu inline-grid size-7 place-items-center rounded-md p-0 opacity-0 group-hover:opacity-100 group-[&.lqd-is-active]/dropdown:opacity-100"
            variant="none"
        >
            <x-tabler-dots class="size-4 opacity-70" />
        </x-slot:trigger>
        <x-slot:dropdown class="min-w-[190px] px-2 py-2">
            <x-button
                class="w-full justify-start rounded-md px-3 py-2 text-start text-2xs font-medium text-foreground/70 hover:transform-none hover:bg-heading-foreground/[3%] hover:text-heading-foreground"
                variant="none"
                @click.prevent.stop="toggle('collapse'); setSelectedItem({type: 'chatItem', id: chat.id, title: chat.title}); toggleRenameModal(true);"
            >
                <x-tabler-pencil class="size-4 shrink-0" /> {{ __('Rename') }}
            </x-button>
            <x-button
                class="w-full justify-start rounded-md px-3 py-2 text-start text-2xs font-medium text-foreground/70 hover:transform-none hover:bg-heading-foreground/[3%] hover:text-heading-foreground"
                variant="none"
                @click.prevent.stop="toggle('collapse'); pinChat(chat.id, !chat.is_pinned)"
            >
                <x-tabler-pin class="size-4 shrink-0" x-show="!chat.is_pinned" />
                <x-tabler-pinned class="size-4 shrink-0" x-cloak x-show="chat.is_pinned" />
                <span x-text="chat.is_pinned ? '{{ __('Unpin') }}' : '{{ __('Pin') }}'"></span>
            </x-button>
            @if ($chatProFoldersEnabled)
                <div class="my-1 border-t border-foreground/10"></div>
                <div class="px-3 pb-1 pt-1 text-[10px] font-semibold uppercase tracking-[.08em] text-foreground/45">{{ __('Project') }}</div>
                <x-button
                    class="w-full justify-start rounded-md px-3 py-2 text-start text-2xs font-medium text-foreground/70 hover:transform-none hover:bg-heading-foreground/[3%] hover:text-heading-foreground"
                    variant="none"
                    x-show="chat.folder_id"
                    @click.prevent.stop="moveToFolder(chat.id, null); toggle('collapse');"
                >
                    <x-tabler-folder-x class="size-4 shrink-0" /> {{ __('Remove from project') }}
                </x-button>
                <template x-for="folder in folders.filter(folder => folder.id !== chat.folder_id)" :key="'move-' + chat.id + '-' + folder.id">
                    <button
                        type="button"
                        class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-start text-2xs font-medium text-foreground/70 hover:bg-heading-foreground/[3%] hover:text-heading-foreground"
                        @click.prevent.stop="moveToFolder(chat.id, folder.id); toggle('collapse');"
                    >
                        <x-tabler-folder class="size-4 shrink-0" />
                        <span class="truncate" x-text="folder.name"></span>
                    </button>
                </template>
            @endif
            <div class="my-1 border-t border-foreground/10"></div>
            <x-button
                class="w-full justify-start rounded-md px-3 py-2 text-start text-2xs font-medium text-red-500 hover:transform-none hover:bg-red-500/10"
                variant="none"
                @click.prevent.stop="toggle('collapse'); setSelectedItem({type: 'chatItem', id: chat.id, title: chat.title}); toggleDeleteModal(true);"
            >
                <x-tabler-trash class="size-4 shrink-0" /> {{ __('Delete') }}
            </x-button>
        </x-slot:dropdown>
    </x-dropdown.dropdown>
</li>
