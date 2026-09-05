<div
    class="col-start-1 col-end-1 row-start-1 row-end-1 w-full px-4 py-6 text-center"
    x-show="contactInfo.activeTab === 'details'"
    x-transition.opacity
>
    <div class="mx-auto mb-3 size-[90px]">
        <figure
            class="relative grid size-full place-items-center rounded-full text-3xl/none font-semibold text-white"
            :style="{ 'backgroundColor': activeChat?.color ?? '#e633ec' }"
        >
            <img
                class="col-start-1 col-end-1 row-start-1 row-end-1 size-full object-cover object-center"
                :src="activeChat?.avatar"
                x-cloak
                x-show="activeChat?.avatar"
            >

            <span
                class="col-start-1 col-end-1 row-start-1 row-end-1"
                x-show="activeChat != null && !activeChat?.avatar"
                x-text="(activeChat?.conversation_name ?? '{{ __('Anonymous User') }}').split('')?.at(0)"
            >-</span>

            <x-button
                class="absolute -end-1 bottom-0 inline-grid size-7 place-items-center bg-background p-0 text-foreground"
                size="none"
                hover-variant="primary"
                x-cloak
                x-show="activeChat != null"
                @click.prevent="contactInfo.editMode = !contactInfo.editMode;
					$nextTick( () => {
						if ( contactInfo.editMode ) {
							$refs.contactInfoName.focus()
						} else {
							updateConversationDetails({name: $refs.contactInfoName.textContent})
						}
					})
				"
            >
                <span class="sr-only">
                    {{ __('Edit User Details') }}
                </span>
                <x-tabler-pencil
                    class="col-start-1 col-end-1 row-start-1 row-end-1 size-4"
                    x-show="!contactInfo.editMode"
                />
                <x-tabler-check
                    class="col-start-1 col-end-1 row-start-1 row-end-1 size-4"
                    x-cloak
                    x-show="contactInfo.editMode"
                />
            </x-button>
        </figure>
    </div>

    <h3
        class="mb-2.5"
        x-text="activeChat == null ? '---' : (activeChat?.conversation_name ?? '{{ __('Anonymous User') }}')"
        :contenteditable="activeChat != null && contactInfo.editMode"
        x-ref="contactInfoName"
        @keydown.enter="contactInfo.editMode = false; $el.blur();"
        @keydown.esc="contactInfo.editMode = false; $el.blur()"
        @dblclick.prevent="contactInfo.editMode = true; $nextTick( () => $refs.contactInfoName.focus())"
        @blur="contactInfo.editMode = false; $refs.contactInfoName.textContent.trim() !== activeChat.conversation_name && updateConversationDetails({name: $refs.contactInfoName.textContent})"
    >
        ---
    </h3>

    <div class="mb-2.5 flex items-center justify-center gap-3 font-medium opacity-70">
        {{ __('Channel') }}
        <span class="inline-block size-0.5 rounded-full bg-current"></span>
        <span
            x-text="activeChat?.chatbot_channel && activeChat.chatbot_channel === 'frame' ? '{{ __('Live Chat') }}' : activeChat?.chatbot_channel ? activeChat.chatbot_channel : '---'"
        ></span>
    </div>

    <p class="mb-7 flex items-center justify-center gap-1 font-medium text-blue-500">
        <x-tabler-at class="size-4" />
        ---
        </span>
    </p>

    <p class="-mx-4 mb-5 flex items-center justify-center gap-10">
        <span class="inline-block h-px grow bg-current opacity-5"></span>
        {{ __('Details') }}
        <span class="inline-block h-px grow bg-current opacity-5"></span>
    </p>

    <div class="rounded-lg border px-4 xl:mx-4">
        <p class="mb-0 flex items-center justify-between gap-1 border-b py-4 text-foreground/60">
            {{ __('ID') }}
            <span
                class="max-w-[65%] overflow-hidden text-ellipsis whitespace-nowrap text-end capitalize text-foreground"
                x-text="activeChat?.id ?? '---'"
            ></span>
        </p>
        <p class="mb-0 flex items-center justify-between gap-1 border-b py-4 text-foreground/60">
            {{ __('Status') }}
            <span
                class="max-w-[65%] overflow-hidden text-ellipsis whitespace-nowrap text-end capitalize text-foreground"
                x-text="activeChat?.ticket_status ?? '---'"
            ></span>
        </p>
        <p class="mb-0 flex items-center justify-between gap-1 border-b py-4 text-foreground/60">
            {{ __('Created') }}
			@if(\App\Helpers\Classes\Helper::appIsDemo())
				<span
					class="max-w-[65%] overflow-hidden text-ellipsis whitespace-nowrap text-end capitalize text-foreground"
				>{{ trans('1 hour ago') }}</span>
			@else
				<span
					class="max-w-[65%] overflow-hidden text-ellipsis whitespace-nowrap text-end capitalize text-foreground"
					x-text="activeChat?.created_at ? getDiffHumanTime(activeChat?.created_at) : '---'"
				></span>
			@endif

        </p>
        <p class="mb-0 flex items-center justify-between gap-1 border-b py-4 text-foreground/60">
            {{ __('Updated') }}
			@if(\App\Helpers\Classes\Helper::appIsDemo())
				<span
					class="max-w-[65%] overflow-hidden text-ellipsis whitespace-nowrap text-end capitalize text-foreground"
				>{{ trans('1 hour ago') }}</span>
			@else
				<span
					class="max-w-[65%] overflow-hidden text-ellipsis whitespace-nowrap text-end capitalize text-foreground"
					x-text="activeChat?.lastMessage?.updated_at ? getDiffHumanTime(activeChat?.lastMessage.updated_at) : '---'"
				></span>
			@endif
        </p>
        <p class="mb-0 flex items-center justify-between gap-1 py-4 text-foreground/60">
            {{ __('IP Address') }}
            <span
                class="max-w-[65%] overflow-hidden text-ellipsis whitespace-nowrap text-end capitalize text-foreground"
                x-text="activeChat?.ip_address ?? '---'"
            ></span>
        </p>
    </div>
</div>
