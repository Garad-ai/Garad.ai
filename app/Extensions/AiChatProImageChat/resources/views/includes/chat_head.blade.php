<div
    class="lqd-chat-head sticky -top-px z-30 flex min-h-20 items-center justify-between gap-2 rounded-se-[inherit] border-b bg-background/80 px-5 py-3 backdrop-blur-lg backdrop-saturate-150 max-md:bg-background/95 max-md:px-4">
    <div class="flex grow items-center justify-between gap-4">
        @include('ai-image-pro::includes.select-model-dropdown')

        <div class="flex gap-2">
            @if (view()->hasSection('chat_head_actions'))
                @yield('chat_head_actions')
            @endif

            @if (view()->hasSection('chat_sidebar_actions'))
                @yield('chat_sidebar_actions')
            @else
                <x-button
                    class="lqd-new-chat-trigger group size-8 shrink-0 grid-flow-row place-items-center rounded-full shadow-md max-md:grid md:hidden"
                    variant="none"
                    size="none"
                    href="javascript:void(0);"
                    onclick="{!! $disable_actions
                        ? 'return toastr.info(\'{{ __('This feature is disabled in Demo version.') }}\')'
                        : (auth()->check()
                            ? 'return startNewChat(\'{{ $category?->id }}\', \'{{ LaravelLocalization::getCurrentLocale() }}\', \'chatpro-image\')'
                            : 'return window.location.reload();') !!}"
                >
                    <x-tabler-plus class="size-5" />
                    <span class="sr-only">
                        {{ __('New Conversation') }}
                    </span>
                </x-button>
                <div class="lqd-chat-mobile-sidebar-trigger self-center">
                    <button
                        class="group size-8 shrink-0 grid-flow-row place-items-center rounded-full shadow-md max-md:grid md:hidden"
                        :class="{ 'active': mobileSidebarShow }"
                        @click.prevent="toggleMobileSidebar()"
                        type="button"
                    >
                        <x-tabler-history class="col-start-1 row-start-1 size-5 transition-all group-[&.active]:rotate-45 group-[&.active]:scale-75 group-[&.active]:opacity-0" />
                        <x-tabler-x class="col-start-1 row-start-1 size-4 -rotate-45 opacity-0 transition-all group-[&.active]:rotate-0 group-[&.active]:!opacity-100" />
                    </button>
                </div>
            @endif
        </div>
    </div>
</div>
