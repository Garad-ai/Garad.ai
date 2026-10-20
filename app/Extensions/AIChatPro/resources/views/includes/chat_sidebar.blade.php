@php
    $disable_actions = $app_is_demo && (isset($category) && in_array($category?->slug, ['ai_vision', 'ai_pdf', 'ai_chat_image'], true));
@endphp

<x-card
    class="chats-list-container garad-unified-sidebar-body flex h-[inherit] w-full shrink-0 grow-0 flex-col overflow-hidden rounded-none border-0 bg-transparent shadow-none max-md:absolute max-md:start-0 max-md:top-0 max-md:z-50 max-md:h-full max-md:bg-background/95 max-md:backdrop-blur-lg md:!flex"
    class:body="flex flex-col h-full min-h-0 p-0"
    id="chats-list-container"
    size="none"
    ::class="{ 'active': mobileSidebarShow }"
>
    @auth
        <div class="chats-list garad-sidebar-sections min-h-0 flex-1 overflow-hidden" id="chat_sidebar_container">
            @include('ai-chat-pro::includes.chat_sidebar_list', [
                'website_url' => 'chatpro',
                'category' => $category,
                'app_is_demo' => $app_is_demo,
            ])
        </div>
    @else
        <div class="flex h-full w-full flex-col items-center justify-center p-5 text-center">
            <x-tabler-message-circle class="mb-3 size-8 opacity-50" />
            <p class="m-0 text-xs opacity-70">{{ __('Login to save your chat history.') }}</p>
        </div>
    @endauth
</x-card>
