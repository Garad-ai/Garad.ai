{{-- chat_area_container.blade --}}

@php
    $disable_actions = $app_is_demo;
    $example_prompts = collect([
        ['name' => 'Change Pose', 'prompt' => 'Change the pose of the person in the image to a more dynamic and engaging stance.'],
        ['name' => 'Replace Background', 'prompt' => 'Replace the background of the image with a scenic outdoor setting.'],
        ['name' => 'Place Product Inside a Model', 'prompt' => 'Place the product seamlessly into the hands of the model in the image.'],
        ['name' => 'Make a Movie Poster', 'prompt' => 'Transform the image into a dramatic movie poster with bold text and effects.'],
        ['name' => 'Change Hairstyle of a Model', 'prompt' => 'Change the hairstyle of the model in the image to a trendy bob cut.'],
    ])
        ->map(fn($item) => (object) $item)
        ->toArray();
    $example_prompts_json = json_encode($example_prompts, JSON_THROW_ON_ERROR);
    $example_prompts = json_decode(setting('ai_chat_pro_image_chat_suggestions', $example_prompts_json), false, 512, JSON_THROW_ON_ERROR);
@endphp
<div
    class="conversation-area flex h-[inherit] grow flex-col justify-between overflow-y-auto rounded-b-[inherit] rounded-t-[inherit] max-md:max-h-full"
    id="chat_area_to_hide"
    {{-- x-data="chatsV2()" --}}
    @cleanup.window="cleanup()"
>

    @if (view()->hasSection('chat_head'))
        @yield('chat_head')
    @else
        @include('ai-chat-pro-image-chat::includes.chat_head')
    @endif

    <div class="relative flex grow flex-col">

        <div @class(['grid place-items-center w-full overflow-x-hidden h-full'])>
            <div
                class="pointer-events-none invisible col-start-1 col-end-1 row-start-1 row-end-1 flex w-full scale-[1.1] flex-col items-center overflow-hidden py-10 opacity-0 transition-all group-[&.conversation-not-started]/chats-wrap:pointer-events-auto group-[&.conversation-not-started]/chats-wrap:visible group-[&.conversation-not-started]/chats-wrap:scale-100 group-[&.conversation-not-started]/chats-wrap:opacity-100">
                <h2 class="mb-8 px-5 text-center text-[26px] font-medium leading-[1.1em] md:text-[30px]">
                    <span class="block text-[0.7em]">
                        <span class="opacity-50">
                            {{ __("Hey :user_name!, I'm your AI assistant.", ['user_name' => auth()->user()?->name ?: 'there']) }}
                        </span>
                        👋
                    </span>
                    {{ __('How can I help you?') }}
                </h2>

                <div
                    class="flex w-full gap-4 [--mask-from:7rem] [--mask-to:calc(100%-7rem)]"
                    style="mask-image: linear-gradient(to right, transparent, black var(--mask-from), black var(--mask-to), transparent);"
                    x-data="marquee({ pauseOnHover: true })"
                >
                    <div class="lqd-marquee-viewport relative flex w-full overflow-hidden">
                        <div class="lqd-marquee-slider flex w-full gap-4 py-2 lg:px-14">
                            @for ($i = 0; $i < 3; $i++)
                                @foreach ($example_prompts ?? [] as $prompt)
                                    <button
                                        class="lqd-marquee-cell inline-flex shrink-0 items-center justify-center whitespace-nowrap rounded-[14px] bg-foreground/5 px-7 py-4 text-2xs font-medium text-heading-foreground transition hover:bg-primary hover:text-primary-foreground"
                                        data-prompt="{{ __($prompt?->prompt) }}"
                                        type="button"
                                        @click.prevent="
										const promptText = $event.currentTarget.getAttribute('data-prompt');
										const promptInput = document.querySelector('#prompt');
										if (promptInput) {
											promptInput.value = promptText;
											promptInput.dispatchEvent(new Event('input'));
											promptInput.focus();
										}
									"
                                    >
                                        {{ __($prompt?->name) }}
                                    </button>
                                @endforeach
                            @endfor
                        </div>
                    </div>
                </div>
            </div>
            <div @class([
                'h-full chats-container text-xs p-8 max-md:p-4 overflow-x-hidden col-start-1 col-end-1 row-start-1 row-end-1 w-full transition-all group-[&.conversation-not-started]/chats-wrap:scale-95 group-[&.conversation-not-started]/chats-wrap:opacity-0 group-[&.conversation-not-started]/chats-wrap:invisible group-[&.conversation-not-started]/chats-wrap:pointer-events-none',
            ])>
                @if (view()->hasSection('chat_area'))
                    @yield('chat_area')
                @else
                    @include('panel.user.openai_chat.components.chat_area', [
                        'website_url' => 'chatpro-image',
                    ])
                @endif
            </div>
        </div>
    </div>

    @include('ai-chat-pro-image-chat::includes.generator-form')
</div>
