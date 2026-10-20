<div class="garad-chat-top-controls" aria-label="{{ __('Chat controls') }}">
    <div class="garad-chat-model-control">
        @auth
            @include('components.select-ai-model-list', ['forceChatProPicker' => true])
        @else
            @include('components.select-ai-model-list-un-auth')
        @endauth
    </div>
</div>
