<div class="conversation-area relative flex grow flex-col overflow-hidden">
    @if ($chat->messages->count() > 1)
        @include('default.panel.user.openai_chat.components.chat_messages')
    @else
        @php
            $chatGreetingName = auth()->user()?->first_name
                ?? auth()->user()?->name
                ?? __('there');
            $chatGreetingHour = now('Africa/Mogadishu')->hour;
            $chatGreeting = $chatGreetingHour < 12
                ? __('Good morning')
                : ($chatGreetingHour < 17 ? __('Good afternoon') : __('Good evening'));
            $chatRotatingPrompts = [
                __('What would you like to build today?'),
                __('How can I help you move an idea forward?'),
                __('What are you working on right now?'),
                __('Need a clear answer or a fresh perspective?'),
                __('What can GaradAI take off your plate?'),
            ];
        @endphp
        <div class="garad-empty-state absolute inset-0 flex flex-col items-center justify-start overflow-y-auto px-6 pt-24 text-center max-md:px-4 max-md:pt-20 max-sm:px-3" data-empty-chat-state>
            <div class="garad-empty-copy">
                <div class="garad-empty-kicker">
                    {{ $chatGreeting }}, {{ $chatGreetingName }}
                </div>
                <h2 data-garad-rotating-prompt>{{ $chatRotatingPrompts[0] }}</h2>
                <p>{{ __('Research, write, analyze files, work with documents, and run your AI agents without leaving Chat Pro.') }}</p>
            </div>
        </div>

        <script>
            (() => {
                const promptElement = document.querySelector('[data-garad-rotating-prompt]');
                const prompts = {!! json_encode($chatRotatingPrompts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};

                if (!promptElement || prompts.length < 2) return;

                let promptIndex = 0;
                window.setInterval(() => {
                    promptElement.classList.add('is-changing');
                    window.setTimeout(() => {
                        promptIndex = (promptIndex + 1) % prompts.length;
                        promptElement.textContent = prompts[promptIndex];
                        promptElement.classList.remove('is-changing');
                    }, 180);
                }, 45000);
            })();
        </script>
    @endif
</div>
