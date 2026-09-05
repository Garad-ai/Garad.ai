<div class="relative flex h-[--header-height] shrink-0 items-center gap-2.5 border-b px-4 xl:px-6">
    <x-button
        class="size-8 aspect-square bg-foreground/10 text-foreground lg:hidden lg:group-[&.focus-mode]/body:flex"
        hover-variant="primary"
        size="none"
        aria-label="{{ __('Back to AI Agent') }}"
        href="{{ route('dashboard.user.ai-agent.dashboard') }}"
    >
        <x-tabler-chevron-left class="size-5" />
    </x-button>

    <span class="flex items-center gap-2 font-heading text-base font-bold text-heading-foreground">
        <x-tabler-messages class="size-[18px]" />
        {{ __('Messages') }}
    </span>

    <div class="ms-auto flex gap-2">
        <x-button
            class="size-7 shrink-0 bg-foreground/5"
            size="none"
            variant="none"
            title="{{ __('New Chat') }}"
            @click.prevent="newChat.modalOpen = true"
        >
            <x-tabler-edit class="size-4" />
        </x-button>

        <x-button
            class="size-7 shrink-0 bg-foreground/5"
            size="none"
            variant="none"
            title="{{ __('Search') }}"
            @click.prevent="conversationsSearchFormVisible = !conversationsSearchFormVisible"
        >
            <x-tabler-search class="size-4" />
        </x-button>
    </div>

    <form
        class="absolute start-0 top-0 h-full w-full bg-background"
        action="#"
        x-ref="convSearchForm"
        @submit.prevent
        x-cloak
        x-show="conversationsSearchFormVisible"
        @keyup.escape.window="conversationsSearchFormVisible = false"
        x-transition
    >
        <input
            class="lqd-input h-full w-full rounded-none border-none bg-transparent pe-6 ps-10 font-medium text-input-foreground outline-none focus:ring-0 sm:ps-12 lg:pe-12"
            type="search"
            name="conv_search"
            placeholder="{{ __('Search conversations...') }}"
            @input.debounce.300ms="searchConversations($el.value)"
        />
        <x-tabler-search class="pointer-events-none absolute start-4 top-1/2 size-[18px] -translate-y-1/2 sm:start-5" />

        <x-button
            class="absolute end-4 top-1/2 size-6 -translate-y-1/2 bg-foreground/15 text-foreground hover:-translate-y-1/2 hover:scale-105"
            hover-variant="danger"
            @click.prevent="conversationsSearchFormVisible = false; searchConversations('')"
            size="none"
        >
            <x-tabler-x class="size-4" />
        </x-button>
    </form>
</div>
